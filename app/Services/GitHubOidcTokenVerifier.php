<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class GitHubOidcTokenVerifier
{
    /** @return array<string, mixed> */
    public function verify(string $token): array
    {
        if ($token === '' || strlen($token) > 8192 || substr_count($token, '.') !== 2) {
            throw new AuthenticationException('Token OIDC tidak valid.');
        }

        try {
            $discoveryUrl = (string) config('observatory.evidence_oidc.discovery_url');
            $metadata = Cache::remember('evidence-oidc-discovery-v1', now()->addHour(), fn (): array => Http::acceptJson()
                ->timeout(5)
                ->get($discoveryUrl)
                ->throw()
                ->json());

            $issuer = (string) config('observatory.evidence_oidc.issuer');
            $jwksUrl = $metadata['jwks_uri'] ?? null;
            if (($metadata['issuer'] ?? null) !== $issuer || ! is_string($jwksUrl) || ! str_starts_with($jwksUrl, $issuer.'/')) {
                throw new AuthenticationException('Metadata penerbit OIDC tidak sesuai.');
            }

            $jwks = Cache::remember('evidence-oidc-jwks-v1', now()->addHour(), fn (): array => Http::acceptJson()
                ->timeout(5)
                ->get($jwksUrl)
                ->throw()
                ->json());
            $claims = json_decode(json_encode(JWT::decode($token, JWK::parseKeySet($jwks)), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        } catch (AuthenticationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw new AuthenticationException('Tanda tangan atau masa berlaku token OIDC tidak valid.');
        }

        $audience = $claims['aud'] ?? null;
        $audiences = is_array($audience) ? $audience : [$audience];
        if (($claims['iss'] ?? null) !== $issuer || ! in_array(config('observatory.evidence_oidc.audience'), $audiences, true)) {
            throw new AuthenticationException('Penerbit atau audience token OIDC tidak sesuai.');
        }

        foreach (['repository', 'repository_id', 'repository_owner_id', 'ref', 'sha', 'workflow_ref', 'event_name', 'run_id', 'run_attempt'] as $claim) {
            if (! isset($claims[$claim]) || (! is_string($claims[$claim]) && ! is_int($claims[$claim]))) {
                throw new AuthenticationException("Klaim OIDC wajib tidak tersedia: {$claim}.");
            }
        }

        return $claims;
    }
}
