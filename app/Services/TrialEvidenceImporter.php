<?php

namespace App\Services;

use App\Enums\Decision;
use App\Enums\ExperimentStatus;
use App\Enums\TrialStatus;
use App\Jobs\DispatchExperimentTrial;
use App\Models\Experiment;
use App\Models\ExperimentTrial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TrialEvidenceImporter
{
    /**
     * @param  array<string, mixed>  $evidence
     * @param  array<string, mixed>  $verifiedSubmissionClaims
     */
    public function import(
        array $evidence,
        string $source = 'operator_artifact_import',
        bool $signatureVerified = false,
        array $verifiedSubmissionClaims = [],
    ): ExperimentTrial {
        $data = Validator::make(['evidence' => $evidence], $this->rules())->validate()['evidence'];
        $digest = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        $nextTrialDelay = null;

        $trial = DB::transaction(function () use ($data, $digest, $signatureVerified, $source, $verifiedSubmissionClaims, &$nextTrialDelay): ExperimentTrial {
            $experiment = Experiment::query()->lockForUpdate()->whereKey($data['experiment_id'])->firstOrFail();
            $trial = $experiment->trials()->lockForUpdate()->whereKey($data['trial_id'])->firstOrFail();
            $matches = $experiment->profile->value === $data['profile']
                && $experiment->scenario->value === $data['scenario']
                && $experiment->expected_decision->value === $data['expected_decision']
                && $trial->sequence_number === $data['repetition']
                && $data['github']['repository'] === config('observatory.github.owner').'/'.config('observatory.github.repository')
                && $data['github']['ref'] === 'refs/heads/'.$experiment->git_ref
                && $data['github']['workflow_ref'] === $data['github']['repository'].'/.github/workflows/'.config('observatory.github.workflow').'@'.$data['github']['ref']
                && ($data['oidc_claims'] === null || (
                    ($data['oidc_claims']['repository'] ?? null) === $data['github']['repository']
                    && ($data['oidc_claims']['ref'] ?? null) === $data['github']['ref']
                    && ($data['oidc_claims']['workflow_ref'] ?? null) === $data['github']['workflow_ref']
                ))
                && ($experiment->commit_sha === null || $experiment->commit_sha === $data['github']['sha'])
                && (($data['tailscale']['target'] ?? $experiment->target) === $experiment->target);
            if (! $matches) {
                throw ValidationException::withMessages(['evidence' => 'Bukti tidak cocok dengan trial, repository, ref, SHA, atau target.']);
            }
            if ($trial->sanitized_metadata !== null) {
                if (($trial->sanitized_metadata['evidence_sha256'] ?? null) === $digest) {
                    return $trial;
                }
                throw ValidationException::withMessages(['evidence' => 'Trial sudah memiliki bukti berbeda. Bukti tidak ditimpa.']);
            }
            $stages = [];
            foreach ($data['stages'] as $stage) {
                $stages[$stage['name']] = $stage;
            }
            $baseStages = ['preflight', 'oidc_claim_capture', 'wif_exchange_and_join', 'target_reachability', 'tailscale_ssh'];
            $deploymentStages = ['docker_deployment', 'application_healthcheck'];
            $hasDeploymentEvidence = isset($stages['docker_deployment'], $stages['application_healthcheck']);
            $requiredStages = $hasDeploymentEvidence ? [...$baseStages, ...$deploymentStages] : $baseStages;
            if (count($stages) !== count($requiredStages) || array_diff($requiredStages, array_keys($stages)) !== []) {
                throw ValidationException::withMessages(['evidence' => 'Tahap bukti wajib unik dan lengkap.']);
            }
            $valid = $stages['preflight']['status'] === 'pass'
                && $stages['oidc_claim_capture']['status'] === 'pass'
                && ! empty($data['oidc_claims'])
                && $stages['wif_exchange_and_join']['status'] !== 'skipped';
            if ($hasDeploymentEvidence && $data['scenario'] === 'valid') {
                $valid = $valid
                    && $stages['docker_deployment']['status'] === 'pass'
                    && $stages['application_healthcheck']['status'] === 'pass';
            }
            $allowed = true;
            foreach (['wif_exchange_and_join', 'target_reachability', 'tailscale_ssh'] as $name) {
                $allowed = $allowed && $stages[$name]['status'] === 'pass';
            }
            $actual = $allowed ? Decision::Allow : Decision::Deny;
            $classification = app(TrialClassifier::class)->classify($experiment->expected_decision, $actual);
            if ($actual->value !== $data['actual_decision'] || $classification->value !== $data['classification']) {
                throw ValidationException::withMessages(['evidence' => 'Keputusan atau klasifikasi tidak konsisten dengan tahap.']);
            }
            $failure = null;
            $totalDuration = 0.0;
            foreach ($data['stages'] as $stage) {
                $totalDuration += $this->duration($stage) ?? 0.0;
                if ($failure === null && $stage['status'] === 'fail') {
                    $failure = $stage;
                }
            }
            $trial->update([
                'github_run_id' => $data['github']['run_id'],
                'run_attempt' => $data['github']['run_attempt'],
                'status' => $valid ? TrialStatus::Completed : TrialStatus::Failed,
                'actual_decision' => $actual,
                'classification' => $valid ? $classification : null,
                'authentication_duration_ms' => $this->duration($stages['wif_exchange_and_join']),
                'tailnet_join_duration_ms' => null,
                'reachability_duration_ms' => $this->duration($stages['target_reachability']),
                'ssh_duration_ms' => $this->duration($stages['tailscale_ssh']),
                'total_duration_ms' => $totalDuration,
                'network_path' => $data['tailscale']['network_path'] ?? 'unknown',
                'failure_stage' => $failure['name'] ?? null,
                'failure_reason' => $data['reason_code'] ?? null,
                'sanitized_metadata' => [
                    'source' => $source,
                    'evidence_sha256' => $digest,
                    'measurement_valid' => $valid,
                    'signature_verified_by_observatory' => $signatureVerified,
                    'verified_submission_claims' => $verifiedSubmissionClaims,
                    'evidence' => $data,
                ],
                'finished_at' => $data['integrity']['generated_at'],
            ]);
            foreach ($data['stages'] as $stage) {
                $event = $trial->stageEvents()->where('stage', $stage['name'])->first();
                $attributes = [
                    'status' => $stage['status'],
                    'duration_ms' => $this->duration($stage),
                    'reason_code' => $stage['reason_code'] ?? null,
                    'occurred_at' => $data['integrity']['generated_at'],
                ];
                if ($event === null) {
                    $trial->stageEvents()->create($attributes + ['stage' => $stage['name']]);
                } else {
                    $event->update($attributes);
                }
            }
            $finished = $experiment->trials()->whereNotIn('status', [TrialStatus::Completed, TrialStatus::Failed])->doesntExist();
            $anyFailed = $experiment->trials()->where('status', TrialStatus::Failed)->exists();
            $experiment->update([
                'status' => $finished ? ($anyFailed ? ExperimentStatus::Failed : ExperimentStatus::Completed) : ExperimentStatus::Running,
                'finished_at' => $finished ? $data['integrity']['generated_at'] : null,
            ]);

            if (! $finished) {
                $nextTrialDelay = $experiment->cooldown_seconds;
            }

            return $trial->refresh();
        });

        if ($nextTrialDelay !== null) {
            DispatchExperimentTrial::dispatch($trial->experiment_id)
                ->onQueue('experiment-dispatch')
                ->delay(now()->addSeconds($nextTrialDelay));
        }

        return $trial;
    }

    /** @param array<string, mixed> $stage */
    private function duration(array $stage): ?float
    {
        return $stage['status'] === 'skipped' ? null : (float) $stage['duration_ms'];
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        $rules = [
            'evidence' => ['required', 'array:schema_version,experiment_id,trial_id,correlation_id,profile,scenario,repetition,expected_decision,actual_decision,classification,reason_code,github,oidc_claims,tailscale,stages,integrity'],
            'evidence.schema_version' => ['required', Rule::in(['1.0.0'])],
            'evidence.experiment_id' => ['required', 'ulid'],
            'evidence.trial_id' => ['required', 'ulid'],
            'evidence.correlation_id' => ['required', 'uuid'],
            'evidence.profile' => ['required', Rule::in(['wif_basic'])],
            'evidence.scenario' => ['required', Rule::in(['valid', 'wrong_audience'])],
            'evidence.repetition' => ['required', 'integer', 'min:1', 'max:30'],
            'evidence.expected_decision' => ['required', Rule::in(['allow', 'deny'])],
            'evidence.actual_decision' => ['required', Rule::in(['allow', 'deny'])],
            'evidence.classification' => ['required', Rule::in(['TP', 'TN', 'FP', 'FN'])],
            'evidence.reason_code' => ['nullable', 'string', 'max:96', 'regex:/^[A-Z0-9_]+$/'],
            'evidence.github' => ['required', 'array:repository,repository_id,repository_owner_id,ref,sha,workflow_ref,job_workflow_ref,run_id,run_attempt,event_name,actor_id,runner_os,runner_arch'],
            'evidence.github.sha' => ['required', 'regex:/^[a-f0-9]{40}$/'],
            'evidence.github.run_id' => ['required', 'regex:/^[0-9]{1,18}$/'],
            'evidence.github.run_attempt' => ['required', 'integer', 'min:1', 'max:65535'],
            'evidence.github.event_name' => ['required', Rule::in(['workflow_dispatch'])],
            'evidence.oidc_claims' => ['nullable', 'array:iss,aud,sub,repository,repository_id,repository_owner_id,ref,workflow_ref,job_workflow_ref,event_name,environment,iat,nbf,exp,jti_sha256'],
            'evidence.tailscale' => ['nullable', 'array:backend_state,node_id,dns_name,tags,network_path,target'],
            'evidence.tailscale.tags' => ['sometimes', 'array', 'max:10'],
            'evidence.tailscale.tags.*' => ['string', 'max:128'],
            'evidence.tailscale.network_path' => ['sometimes', Rule::in(['direct', 'derp', 'unknown'])],
            'evidence.stages' => ['required', 'array', 'min:5', 'max:7'],
            'evidence.stages.*' => ['array:name,status,duration_ms,reason_code'],
            'evidence.stages.*.name' => ['required', Rule::in(['preflight', 'oidc_claim_capture', 'wif_exchange_and_join', 'target_reachability', 'tailscale_ssh', 'docker_deployment', 'application_healthcheck'])],
            'evidence.stages.*.status' => ['required', Rule::in(['pass', 'fail', 'skipped'])],
            'evidence.stages.*.duration_ms' => ['required', 'numeric', 'min:0', 'max:7200000'],
            'evidence.stages.*.reason_code' => ['nullable', 'string', 'max:96', 'regex:/^[A-Z0-9_]+$/'],
            'evidence.integrity' => ['required', 'array:generated_at,collector_version,policy_version'],
            'evidence.integrity.generated_at' => ['required', 'date'],
            'evidence.integrity.collector_version' => ['required', 'string', 'max:64'],
            'evidence.integrity.policy_version' => ['nullable', 'string', 'max:64'],
        ];
        foreach (['repository', 'repository_id', 'repository_owner_id', 'ref', 'workflow_ref', 'runner_os', 'runner_arch'] as $key) {
            $rules['evidence.github.'.$key] = ['required', 'string', 'max:512'];
        }
        foreach (['job_workflow_ref', 'actor_id'] as $key) {
            $rules['evidence.github.'.$key] = ['sometimes', 'nullable', 'string', 'max:512'];
        }
        foreach (['iss', 'aud', 'sub', 'repository', 'repository_id', 'repository_owner_id', 'ref', 'workflow_ref', 'job_workflow_ref', 'event_name', 'environment', 'jti_sha256'] as $key) {
            $rules['evidence.oidc_claims.'.$key] = ['sometimes', 'nullable', 'string', 'max:512'];
        }
        foreach (['iat', 'nbf', 'exp'] as $key) {
            $rules['evidence.oidc_claims.'.$key] = ['sometimes', 'integer', 'min:0'];
        }
        foreach (['backend_state', 'node_id', 'dns_name', 'target'] as $key) {
            $rules['evidence.tailscale.'.$key] = ['sometimes', 'nullable', 'string', 'max:255'];
        }

        return $rules;
    }
}
