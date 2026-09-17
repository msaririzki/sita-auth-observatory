<?php

namespace App\Jobs;

use App\Enums\ExperimentStatus;
use App\Enums\TrialStatus;
use App\Models\Experiment;
use App\Models\ExperimentTrial;
use App\Services\GitHubWorkflowDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class DispatchExperimentTrial implements ShouldQueue
{
    use Queueable;

    public int $tries = 240;

    public int $timeout = 60;

    /**
     * @param  string  $experimentId  ULID of the experiment whose next trial is due.
     */
    public function __construct(public readonly string $experimentId) {}

    public function handle(GitHubWorkflowDispatcher $dispatcher): void
    {
        if ($this->hasOtherActiveTrial()) {
            $this->release(15);

            return;
        }

        $selection = $this->reserveNextTrial();

        if ($selection === null) {
            return;
        }

        [$experiment, $trial] = $selection;

        try {
            $dispatcher->dispatch($experiment, $trial);
        } catch (Throwable $exception) {
            $this->recordDispatchFailure($trial, $exception);
        }
    }

    private function hasOtherActiveTrial(): bool
    {
        return ExperimentTrial::query()
            ->where('experiment_id', '!=', $this->experimentId)
            ->whereNotIn('status', [
                TrialStatus::Pending,
                TrialStatus::Completed,
                TrialStatus::Failed,
                TrialStatus::Cancelled,
            ])
            ->exists();
    }

    /** @return array{0: Experiment, 1: ExperimentTrial}|null */
    private function reserveNextTrial(): ?array
    {
        return DB::transaction(function (): ?array {
            $experiment = Experiment::query()
                ->lockForUpdate()
                ->find($this->experimentId);

            if ($experiment === null || in_array($experiment->status, [
                ExperimentStatus::Completed,
                ExperimentStatus::Failed,
                ExperimentStatus::Cancelled,
            ], true)) {
                return null;
            }

            $hasActiveTrial = $experiment->trials()
                ->whereNotIn('status', [
                    TrialStatus::Pending,
                    TrialStatus::Completed,
                    TrialStatus::Failed,
                    TrialStatus::Cancelled,
                ])
                ->exists();

            if ($hasActiveTrial) {
                return null;
            }

            $trial = $experiment->trials()
                ->where('status', TrialStatus::Pending)
                ->orderBy('sequence_number')
                ->lockForUpdate()
                ->first();

            if ($trial === null) {
                return null;
            }

            $trial->update([
                'status' => TrialStatus::Dispatched,
                'started_at' => now(),
            ]);
            $experiment->update([
                'status' => ExperimentStatus::Queued,
                'started_at' => $experiment->started_at ?? now(),
            ]);

            return [$experiment->fresh(), $trial->fresh()];
        });
    }

    private function recordDispatchFailure(ExperimentTrial $trial, Throwable $exception): void
    {
        DB::transaction(function () use ($trial, $exception): void {
            $lockedTrial = ExperimentTrial::query()->lockForUpdate()->findOrFail($trial->id);
            $experiment = Experiment::query()->lockForUpdate()->findOrFail($lockedTrial->experiment_id);

            $lockedTrial->update([
                'status' => TrialStatus::Failed,
                'failure_stage' => 'workflow_dispatch',
                'failure_reason' => class_basename($exception),
                'finished_at' => now(),
            ]);
            $experiment->trials()
                ->where('status', TrialStatus::Pending)
                ->update([
                    'status' => TrialStatus::Cancelled,
                    'finished_at' => now(),
                ]);
            $experiment->update([
                'status' => ExperimentStatus::Failed,
                'finished_at' => now(),
            ]);
        });
    }
}
