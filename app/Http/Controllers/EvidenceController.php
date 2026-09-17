<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportEvidenceRequest;
use App\Models\Experiment;
use App\Models\ExperimentTrial;
use App\Models\StageEvent;
use App\Services\TrialEvidenceImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use JsonException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenceController extends Controller
{
    public function show(Experiment $experiment): Response
    {
        $trials = $experiment->trials()->with('stageEvents')->orderBy('sequence_number')->get();

        return Inertia::render('experiments/show', [
            'experiment' => [
                'id' => $experiment->id, 'name' => $experiment->name,
                'profile' => $experiment->profile->value, 'scenario' => $experiment->scenario->value,
                'status' => $experiment->status->value, 'git_ref' => $experiment->git_ref, 'target' => $experiment->target,
            ],
            'trials' => $trials->map(fn (ExperimentTrial $trial): array => $this->trialPayload($experiment, $trial)),
        ]);
    }

    public function events(Experiment $experiment): StreamedResponse
    {
        return response()->stream(function () use ($experiment): void {
            $deadline = microtime(true) + (int) config('observatory.progress_stream_seconds');
            $lastHash = null;

            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            ini_set('zlib.output_compression', '0');
            echo "retry: 1000\n\n";
            flush();

            while (! connection_aborted() && microtime(true) < $deadline) {
                $freshExperiment = Experiment::query()->findOrFail($experiment->id);
                $trials = $freshExperiment->trials()->with('stageEvents')->orderBy('sequence_number')->get();
                $payload = [
                    'experiment' => ['status' => $freshExperiment->status->value],
                    'trials' => $trials->map(fn (ExperimentTrial $trial): array => $this->trialPayload($freshExperiment, $trial)),
                ];
                $data = json_encode($payload, JSON_THROW_ON_ERROR);
                $hash = hash('sha256', $data);

                if ($hash !== $lastHash) {
                    echo "event: progress\n";
                    echo 'data: '.$data."\n\n";
                    flush();
                    $lastHash = $hash;
                }

                usleep(250000);
            }
        }, 200, [
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function store(ImportEvidenceRequest $request, Experiment $experiment, TrialEvidenceImporter $importer): RedirectResponse
    {
        try {
            $file = $request->file('evidence_file');
            if ($file === null) {
                throw new JsonException('Berkas tidak ditemukan.');
            }
            $contents = $file->get();
            if ($contents === false) {
                throw new JsonException('Berkas tidak dapat dibaca.');
            }
            $data = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['evidence_file' => 'Berkas bukan JSON yang valid.']);
        }
        if (! is_array($data) || ($data['experiment_id'] ?? null) !== $experiment->id) {
            throw ValidationException::withMessages(['evidence_file' => 'Berkas bukan bukti eksperimen ini.']);
        }
        $importer->import($data);

        return to_route('experiments.show', $experiment)->with('success', 'Bukti pilot divalidasi dan disimpan.');
    }

    /** @return array<string, mixed> */
    private function trialPayload(Experiment $experiment, ExperimentTrial $trial): array
    {
        $stages = $trial->stageEvents()->orderBy('occurred_at')->get()
            ->map(fn (StageEvent $event): array => [
                'name' => $event->stage, 'status' => $event->status,
                'duration_ms' => $event->duration_ms, 'reason_code' => $event->reason_code,
                'message' => $event->sanitized_metadata['message'] ?? null,
                'occurred_at' => $event->occurred_at,
            ]);

        return [
            'id' => $trial->id, 'sequence' => $trial->sequence_number, 'status' => $trial->status->value,
            'run_id' => $trial->github_run_id, 'run_attempt' => $trial->run_attempt,
            'expected_decision' => $experiment->expected_decision->value,
            'actual_decision' => $trial->actual_decision?->value, 'classification' => $trial->classification?->value,
            'network_path' => $trial->network_path, 'authentication_ms' => $trial->authentication_duration_ms,
            'reachability_ms' => $trial->reachability_duration_ms, 'ssh_ms' => $trial->ssh_duration_ms,
            'total_ms' => $trial->total_duration_ms, 'metadata' => $trial->sanitized_metadata, 'stages' => $stages,
            'failure_stage' => $trial->failure_stage, 'failure_reason' => $trial->failure_reason,
        ];
    }
}
