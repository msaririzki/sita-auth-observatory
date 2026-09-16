<?php

namespace App\Services;

use App\Models\Experiment;
use App\Models\ExperimentTrial;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use LogicException;

class GitHubWorkflowDispatcher
{
    public function isConfigured(): bool
    {
        return filled(config('observatory.github.token'))
            && filled(config('observatory.github.owner'))
            && filled(config('observatory.github.repository'))
            && filled(config('observatory.github.workflow'));
    }

    public function dispatch(Experiment $experiment, ExperimentTrial $trial): void
    {
        if (! $this->isConfigured()) {
            throw new LogicException('Kontrol GitHub belum dikonfigurasi.');
        }

        $this->client()
            ->post($this->workflowDispatchUrl(), [
                'ref' => $experiment->git_ref,
                'inputs' => [
                    'experiment_id' => $experiment->id,
                    'trial_id' => $trial->id,
                    'profile' => $experiment->profile->value,
                    'scenario' => $experiment->scenario->value,
                    'expected_decision' => $experiment->expected_decision->value,
                    'repetition' => (string) $trial->sequence_number,
                    'target' => $experiment->target,
                    'commit_sha' => $experiment->commit_sha ?? '',
                ],
            ])
            ->throw();
    }

    private function client(): PendingRequest
    {
        return Http::withToken((string) config('observatory.github.token'))
            ->accept('application/vnd.github+json')
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->connectTimeout(10)
            ->timeout(30);
    }

    private function workflowDispatchUrl(): string
    {
        $owner = rawurlencode((string) config('observatory.github.owner'));
        $repository = rawurlencode((string) config('observatory.github.repository'));
        $workflow = rawurlencode((string) config('observatory.github.workflow'));

        return "https://api.github.com/repos/{$owner}/{$repository}/actions/workflows/{$workflow}/dispatches";
    }
}
