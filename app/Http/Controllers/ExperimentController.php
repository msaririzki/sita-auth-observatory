<?php

namespace App\Http\Controllers;

use App\Enums\AuthenticationProfile;
use App\Enums\ExperimentScenario;
use App\Enums\ExperimentStatus;
use App\Enums\TrialStatus;
use App\Http\Requests\StoreExperimentRequest;
use App\Jobs\DispatchExperimentTrial;
use App\Models\Experiment;
use App\Services\GitHubWorkflowDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ExperimentController extends Controller
{
    public function index(GitHubWorkflowDispatcher $dispatcher): Response
    {
        return Inertia::render('experiments/index', [
            'experiments' => Experiment::query()
                ->withCount([
                    'trials',
                    'trials as finalized_trials_count' => fn ($query) => $query->whereIn('status', [
                        TrialStatus::Completed,
                        TrialStatus::Failed,
                        TrialStatus::Cancelled,
                    ]),
                    'trials as failed_trials_count' => fn ($query) => $query->where('status', TrialStatus::Failed),
                ])
                ->latest()
                ->paginate(12)
                ->through(fn (Experiment $experiment) => [
                    'id' => $experiment->id,
                    'name' => $experiment->name,
                    'profile' => $experiment->profile->value,
                    'profile_label' => $experiment->profile->label(),
                    'scenario' => $experiment->scenario->value,
                    'scenario_label' => $experiment->scenario->label(),
                    'expected_decision' => $experiment->expected_decision->value,
                    'target' => $experiment->target,
                    'git_ref' => $experiment->git_ref,
                    'status' => $experiment->status->value,
                    'trials_count' => $experiment->trials_count,
                    'finalized_trials_count' => $experiment->finalized_trials_count,
                    'failed_trials_count' => $experiment->failed_trials_count,
                    'created_at' => $experiment->created_at?->toIso8601String(),
                ]),
            'options' => [
                'profiles' => collect(AuthenticationProfile::cases())
                    ->map(fn (AuthenticationProfile $profile) => [
                        'value' => $profile->value,
                        'label' => $profile->label(),
                    ]),
                'scenarios' => collect(ExperimentScenario::cases())
                    ->map(fn (ExperimentScenario $scenario) => [
                        'value' => $scenario->value,
                        'label' => $scenario->label(),
                        'expected_decision' => $scenario->expectedDecision()->value,
                    ]),
                'targets' => config('observatory.allowed_targets'),
            ],
            'dispatchReady' => $dispatcher->isConfigured(),
        ]);
    }

    public function store(StoreExperimentRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $scenario = ExperimentScenario::from($validated['scenario']);

        $experiment = DB::transaction(function () use ($validated, $scenario, $request): Experiment {
            $experiment = Experiment::query()->create([
                ...$validated,
                'expected_decision' => $scenario->expectedDecision(),
                'status' => ExperimentStatus::Draft,
                'initiated_by' => $request->user()?->id,
            ]);

            $trials = collect(range(1, (int) $validated['repetitions']))
                ->map(fn (int $sequence) => [
                    'sequence_number' => $sequence,
                    'status' => TrialStatus::Pending,
                ]);

            $experiment->trials()->createMany($trials->all());

            return $experiment;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Rencana {$experiment->name} tersimpan dan sudah muncul di riwayat.",
        ]);

        return to_route('experiments.index');
    }

    public function dispatch(
        Experiment $experiment,
        GitHubWorkflowDispatcher $dispatcher,
    ): RedirectResponse {
        if ($experiment->profile->value !== 'wif_basic' || ! in_array($experiment->scenario->value, ['valid', 'wrong_audience'], true)) {
            throw ValidationException::withMessages([
                'experiment' => 'Pilot WIF dasar mendukung skenario autentikasi valid dan audience tidak sesuai.',
            ]);
        }
        if (! $dispatcher->isConfigured()) {
            throw ValidationException::withMessages([
                'experiment' => 'GitHub App belum dikonfigurasi pada server Observatory.',
            ]);
        }

        $queuedExperiment = DB::transaction(function () use ($experiment): Experiment {
            $lockedExperiment = Experiment::query()
                ->lockForUpdate()
                ->findOrFail($experiment->id);

            if ($lockedExperiment->status !== ExperimentStatus::Draft) {
                throw ValidationException::withMessages([
                    'experiment' => 'Hanya eksperimen berstatus draft yang dapat dijalankan.',
                ]);
            }

            $hasPendingTrial = $lockedExperiment->trials()
                ->where('status', TrialStatus::Pending)
                ->exists();
            if (! $hasPendingTrial) {
                throw ValidationException::withMessages([
                    'experiment' => 'Eksperimen tidak memiliki trial yang dapat dijalankan.',
                ]);
            }

            $lockedExperiment->update([
                'status' => ExperimentStatus::Queued,
                'started_at' => now(),
            ]);

            return $lockedExperiment->fresh();
        });

        DispatchExperimentTrial::dispatch($queuedExperiment->id)
            ->onQueue('experiment-dispatch')
            ->afterCommit();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Eksperimen {$experiment->name} masuk antrean Observatory. Pemantauan proses dibuka.",
        ]);

        return to_route('experiments.show', $experiment);
    }
}
