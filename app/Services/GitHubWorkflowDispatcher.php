<?php

namespace App\Services;

use App\Models\Experiment;
use App\Models\ExperimentTrial;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use LogicException;
use RuntimeException;

class GitHubWorkflowDispatcher
{
    public function isConfigured(): bool
    {
        return $this->hasDispatchIdentity()
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
        return Http::withToken($this->accessToken())
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

    private function hasDispatchIdentity(): bool
    {
        return filled(config('observatory.github.token')) || $this->hasGitHubAppCredentials();
    }

    private function hasGitHubAppCredentials(): bool
    {
        return filled(config('observatory.github.app_id'))
            && filled(config('observatory.github.installation_id'))
            && filled(config('observatory.github.private_key_base64'));
    }

    private function accessToken(): string
    {
        $staticToken = config('observatory.github.token');

        if (filled($staticToken)) {
            return (string) $staticToken;
        }

        if (! $this->hasGitHubAppCredentials()) {
            throw new LogicException('Identitas GitHub untuk menjalankan workflow belum dikonfigurasi.');
        }

        return Cache::remember(
            'github-app-installation-token',
            now()->addMinutes(50),
            fn (): string => $this->issueInstallationToken(),
        );
    }

    private function issueInstallationToken(): string
    {
        $installationId = rawurlencode((string) config('observatory.github.installation_id'));
        $response = Http::withToken($this->appJwt())
            ->accept('application/vnd.github+json')
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->connectTimeout(10)
            ->timeout(30)
            ->post("https://api.github.com/app/installations/{$installationId}/access_tokens")
            ->throw();

        $token = $response->json('token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('GitHub App tidak mengembalikan token instalasi.');
        }

        return $token;
    }

    private function appJwt(): string
    {
        $encodedKey = (string) config('observatory.github.private_key_base64');
        $privateKeyPem = base64_decode($encodedKey, true);

        if ($privateKeyPem === false || $privateKeyPem === '') {
            throw new RuntimeException('Private key GitHub App tidak dapat dibaca.');
        }

        $header = $this->base64Url(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));
        $issuedAt = now()->timestamp;
        $payload = $this->base64Url(json_encode([
            'iat' => $issuedAt - 60,
            'exp' => $issuedAt + 540,
            'iss' => (string) config('observatory.github.app_id'),
        ], JSON_THROW_ON_ERROR));
        $signingInput = "{$header}.{$payload}";
        $signature = '';

        if (! openssl_sign($signingInput, $signature, $privateKeyPem, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('JWT GitHub App tidak dapat ditandatangani.');
        }

        return "{$signingInput}.{$this->base64Url($signature)}";
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
