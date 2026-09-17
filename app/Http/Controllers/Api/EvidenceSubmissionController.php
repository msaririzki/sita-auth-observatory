<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GitHubOidcTokenVerifier;
use App\Services\TrialEvidenceImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvidenceSubmissionController extends Controller
{
    public function __invoke(
        Request $request,
        GitHubOidcTokenVerifier $verifier,
        TrialEvidenceImporter $importer,
    ): JsonResponse {
        if (strlen($request->getContent()) > 65536) {
            return response()->json(['message' => 'Bukti melebihi batas 64 KiB.'], 413);
        }

        $claims = $verifier->verify((string) $request->bearerToken());
        $evidence = $request->json()->all();
        if ($evidence === []) {
            return response()->json(['message' => 'Bukti JSON wajib diisi.'], 422);
        }

        $this->assertIdentityBinding($claims, $evidence);
        $trial = $importer->import(
            $evidence,
            'oidc_authenticated_workflow_push',
            true,
            $this->sanitizedClaims($claims),
        );

        return response()->json([
            'data' => [
                'experiment_id' => $trial->experiment_id,
                'trial_id' => $trial->id,
                'status' => $trial->status->value,
                'classification' => $trial->classification?->value,
                'signature_verified' => true,
            ],
        ], 201);
    }

    /**
     * @param  array<string, mixed>  $claims
     * @param  array<string, mixed>  $evidence
     */
    private function assertIdentityBinding(array $claims, array $evidence): void
    {
        $github = $evidence['github'] ?? null;
        if (! is_array($github)) {
            abort(422, 'Provenance GitHub wajib tersedia.');
        }

        $bindings = [
            'repository' => $github['repository'] ?? null,
            'repository_id' => $github['repository_id'] ?? null,
            'repository_owner_id' => $github['repository_owner_id'] ?? null,
            'ref' => $github['ref'] ?? null,
            'sha' => $github['sha'] ?? null,
            'workflow_ref' => $github['workflow_ref'] ?? null,
            'event_name' => $github['event_name'] ?? null,
            'run_id' => $github['run_id'] ?? null,
            'run_attempt' => $github['run_attempt'] ?? null,
        ];

        foreach ($bindings as $claim => $expected) {
            if ($expected === null || (string) $claims[$claim] !== (string) $expected) {
                abort(403, "Klaim OIDC {$claim} tidak cocok dengan provenance bukti.");
            }
        }
    }

    /** @param array<string, mixed> $claims
     * @return array<string, mixed>
     */
    private function sanitizedClaims(array $claims): array
    {
        $sanitized = [];
        foreach (['iss', 'aud', 'sub', 'repository', 'repository_id', 'repository_owner_id', 'ref', 'sha', 'workflow_ref', 'job_workflow_ref', 'event_name', 'environment', 'run_id', 'run_attempt', 'actor_id', 'iat', 'nbf', 'exp'] as $key) {
            if (array_key_exists($key, $claims)) {
                $sanitized[$key] = $claims[$key];
            }
        }
        if (isset($claims['jti']) && is_string($claims['jti'])) {
            $sanitized['jti_sha256'] = hash('sha256', $claims['jti']);
        }

        return $sanitized;
    }
}
