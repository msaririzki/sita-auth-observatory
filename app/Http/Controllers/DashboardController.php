<?php

namespace App\Http\Controllers;

use App\Enums\ExperimentStatus;
use App\Enums\TrialStatus;
use App\Models\Experiment;
use App\Models\ExperimentTrial;
use App\Services\GitHubWorkflowDispatcher;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(GitHubWorkflowDispatcher $dispatcher): Response
    {
        $completedTrials = ExperimentTrial::query()
            ->where('status', TrialStatus::Completed)
            ->count();

        $correctTrials = ExperimentTrial::query()
            ->where('status', TrialStatus::Completed)
            ->whereIn('classification', ['TP', 'TN'])
            ->count();

        return Inertia::render('dashboard', [
            'summary' => [
                'experiments' => Experiment::query()->count(),
                'active' => Experiment::query()
                    ->whereIn('status', [ExperimentStatus::Queued, ExperimentStatus::Running])
                    ->count(),
                'completed_trials' => $completedTrials,
                'decision_accuracy' => $completedTrials > 0
                    ? round(($correctTrials / $completedTrials) * 100, 1)
                    : null,
            ],
            'recentExperiments' => Experiment::query()
                ->withCount([
                    'trials',
                    'trials as completed_trials_count' => fn ($query) => $query->where('status', TrialStatus::Completed),
                ])
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (Experiment $experiment) => [
                    'id' => $experiment->id,
                    'name' => $experiment->name,
                    'profile' => $experiment->profile->value,
                    'profile_label' => $experiment->profile->label(),
                    'scenario_label' => $experiment->scenario->label(),
                    'status' => $experiment->status->value,
                    'trials_count' => $experiment->trials_count,
                    'completed_trials_count' => $experiment->completed_trials_count,
                    'created_at' => $experiment->created_at?->toIso8601String(),
                ]),
            'dispatchReady' => $dispatcher->isConfigured(),
        ]);
    }
}
