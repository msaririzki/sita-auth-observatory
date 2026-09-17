<?php

namespace App\Http\Controllers\Api;

use App\Enums\ExperimentStatus;
use App\Enums\TrialStatus;
use App\Http\Controllers\Controller;
use App\Models\Experiment;
use App\Models\ExperimentTrial;
use App\Services\GitHubOidcTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TrialProgressController extends Controller
{
    /** @var list<string> */
    private const STAGES = [
        'preflight',
        'oidc_claim_capture',
        'wif_exchange_and_join',
        'target_reachability',
        'tailscale_ssh',
        'docker_deployment',
        'application_healthcheck',
    ];

    public function __invoke(Request $request, GitHubOidcTokenVerifier $verifier): JsonResponse
    {
        if (strlen($request->getContent()) > 4096) {
            return response()->json(['message' => 'Status proses melebihi batas 4 KiB.'], 413);
        }

        $claims = $verifier->verify(
            (string) $request->bearerToken(),
            (string) config('observatory.progress_oidc_audience'),
        );
        $data = Validator::make($request->json()->all(), $this->rules())->validate();

        $trial = DB::transaction(function () use ($claims, $data): ExperimentTrial {
            $experiment = Experiment::query()->lockForUpdate()->findOrFail($data['experiment_id']);
            $trial = $experiment->trials()->lockForUpdate()->findOrFail($data['trial_id']);
            $this->assertTrialBinding($claims, $experiment, $trial);

            if ($trial->sanitized_metadata !== null) {
                throw ValidationException::withMessages(['trial' => 'Bukti final sudah tersimpan dan progres tidak dapat diubah.']);
            }

            $stage = $data['stage'];
            $event = $trial->stageEvents()->where('stage', $stage['name'])->first();
            if ($event !== null && $event->status !== 'running' && $event->status !== $stage['status']) {
                throw ValidationException::withMessages(['stage' => 'Tahap yang telah selesai tidak dapat ditulis ulang.']);
            }

            $eventData = [
                'status' => $stage['status'],
                'duration_ms' => null,
                'reason_code' => $stage['reason_code'] ?? null,
                'sanitized_metadata' => isset($stage['message']) ? ['message' => $stage['message']] : null,
                'occurred_at' => now(),
            ];
            if ($event === null) {
                $trial->stageEvents()->create($eventData + ['stage' => $stage['name']]);
            } else {
                $event->update($eventData);
            }

            $failed = $stage['status'] === 'fail';
            $trial->update([
                'github_run_id' => (int) $claims['run_id'],
                'run_attempt' => (int) $claims['run_attempt'],
                'status' => $failed ? TrialStatus::Failed : $this->trialStatusFor($stage['name']),
                'failure_stage' => $failed ? $stage['name'] : null,
                'failure_reason' => $failed ? ($stage['reason_code'] ?? 'STAGE_FAILED') : null,
                'started_at' => $trial->started_at ?? now(),
                'finished_at' => $failed ? now() : null,
            ]);
            $experiment->update([
                'status' => ExperimentStatus::Running,
                'started_at' => $experiment->started_at ?? now(),
            ]);

            return $trial->refresh();
        });

        return response()->json([
            'data' => [
                'trial_id' => $trial->id,
                'status' => $trial->status->value,
                'stage' => $data['stage']['name'],
            ],
        ], 202);
    }

    /** @param array<string, mixed> $claims */
    private function assertTrialBinding(array $claims, Experiment $experiment, ExperimentTrial $trial): void
    {
        $repository = config('observatory.github.owner').'/'.config('observatory.github.repository');
        $ref = 'refs/heads/'.$experiment->git_ref;
        $workflowRef = $repository.'/.github/workflows/'.config('observatory.github.workflow').'@'.$ref;
        $matches = (string) $claims['repository'] === $repository
            && (string) $claims['ref'] === $ref
            && (string) $claims['workflow_ref'] === $workflowRef
            && (string) $claims['event_name'] === 'workflow_dispatch'
            && ($experiment->commit_sha === null || (string) $claims['sha'] === $experiment->commit_sha)
            && $trial->status !== TrialStatus::Completed;
        if (! $matches) {
            abort(403, 'Identitas GitHub tidak sesuai dengan eksperimen yang didaftarkan.');
        }
    }

    private function trialStatusFor(string $stage): TrialStatus
    {
        return match ($stage) {
            'target_reachability' => TrialStatus::TargetReachable,
            'tailscale_ssh', 'docker_deployment', 'application_healthcheck' => TrialStatus::SshVerified,
            default => TrialStatus::Authenticating,
        };
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'experiment_id' => ['required', 'ulid'],
            'trial_id' => ['required', 'ulid'],
            'stage' => ['required', 'array:name,status,reason_code,message'],
            'stage.name' => ['required', Rule::in(self::STAGES)],
            'stage.status' => ['required', Rule::in(['running', 'pass', 'fail', 'skipped'])],
            'stage.reason_code' => ['nullable', 'string', 'max:96', 'regex:/^[A-Z0-9_]+$/'],
            'stage.message' => ['nullable', 'string', 'max:280'],
        ];
    }
}
