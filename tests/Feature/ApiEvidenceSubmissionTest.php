<?php

namespace Tests\Feature;

use App\Models\Experiment;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiEvidenceSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKey;

    /** @var array<string, mixed> */
    private array $jwks;

    protected function setUp(): void
    {
        parent::setUp();

        [$key, $this->privateKey] = $this->newPrivateKey();
        $details = openssl_pkey_get_details($key);
        $this->jwks = ['keys' => [[
            'kty' => 'RSA',
            'kid' => 'research-test-key',
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => $this->base64Url($details['rsa']['n']),
            'e' => $this->base64Url($details['rsa']['e']),
        ]]];

        config([
            'observatory.evidence_oidc.issuer' => 'https://token.actions.githubusercontent.com',
            'observatory.evidence_oidc.audience' => 'https://authlab.example.test/api/v1/evidence',
            'observatory.evidence_oidc.discovery_url' => 'https://token.actions.githubusercontent.com/.well-known/openid-configuration',
        ]);
        Cache::flush();
        Http::fake([
            'https://token.actions.githubusercontent.com/.well-known/openid-configuration' => Http::response([
                'issuer' => 'https://token.actions.githubusercontent.com',
                'jwks_uri' => 'https://token.actions.githubusercontent.com/.well-known/jwks',
            ]),
            'https://token.actions.githubusercontent.com/.well-known/jwks' => Http::response($this->jwks),
        ]);
    }

    public function test_signed_oidc_submission_is_bound_to_provenance_and_imported(): void
    {
        $evidence = $this->evidence();

        $this->withToken($this->token($evidence))->postJson('/api/v1/evidence', $evidence)
            ->assertCreated()
            ->assertJsonPath('data.trial_id', $evidence['trial_id'])
            ->assertJsonPath('data.signature_verified', true);

        $trial = Experiment::query()->findOrFail($evidence['experiment_id'])->trials()->sole();
        $this->assertSame('oidc_authenticated_workflow_push', $trial->sanitized_metadata['source']);
        $this->assertTrue($trial->sanitized_metadata['signature_verified_by_observatory']);
        $this->assertSame($evidence['github']['run_id'], $trial->sanitized_metadata['verified_submission_claims']['run_id']);
        $this->assertArrayNotHasKey('jti', $trial->sanitized_metadata['verified_submission_claims']);
        $this->assertArrayHasKey('jti_sha256', $trial->sanitized_metadata['verified_submission_claims']);
    }

    public function test_submission_is_rejected_when_oidc_identity_does_not_match_evidence(): void
    {
        $evidence = $this->evidence();
        $claims = $this->claims($evidence);
        $claims['ref'] = 'refs/heads/untrusted';
        $token = JWT::encode($claims, $this->privateKey, 'RS256', 'research-test-key');

        $this->withToken($token)->postJson('/api/v1/evidence', $evidence)->assertForbidden();
        $this->assertDatabaseEmpty('stage_events');
    }

    public function test_submission_without_a_valid_signature_is_rejected(): void
    {
        $evidence = $this->evidence();
        [, $privateKey] = $this->newPrivateKey();
        $token = JWT::encode($this->claims($evidence), $privateKey, 'RS256', 'research-test-key');

        $this->withToken($token)->postJson('/api/v1/evidence', $evidence)->assertUnauthorized();
        $this->assertDatabaseEmpty('stage_events');
    }

    /** @return array<string, mixed> */
    private function evidence(): array
    {
        $data = json_decode(file_get_contents(base_path('fixtures/v1/valid-wif-basic-allow.json')), true, flags: JSON_THROW_ON_ERROR);
        $experiment = Experiment::query()->create([
            'name' => 'Automatic OIDC evidence', 'profile' => 'wif_basic', 'scenario' => 'valid',
            'expected_decision' => 'allow', 'target' => 'sita-docker', 'git_ref' => 'codex/wif-poc',
            'commit_sha' => $data['github']['sha'], 'repetitions' => 1, 'cooldown_seconds' => 0,
        ]);
        $trial = $experiment->trials()->create(['sequence_number' => 1]);
        $data['experiment_id'] = $experiment->id;
        $data['trial_id'] = $trial->id;
        $data['tailscale']['target'] = 'sita-docker';
        array_unshift($data['stages'],
            ['name' => 'preflight', 'status' => 'pass', 'duration_ms' => 0.0],
            ['name' => 'oidc_claim_capture', 'status' => 'pass', 'duration_ms' => 0.0]);

        return $data;
    }

    /** @param array<string, mixed> $evidence */
    private function token(array $evidence): string
    {
        return JWT::encode($this->claims($evidence), $this->privateKey, 'RS256', 'research-test-key');
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array<string, mixed>
     */
    private function claims(array $evidence): array
    {
        $now = now()->timestamp;

        return [
            'iss' => config('observatory.evidence_oidc.issuer'),
            'aud' => config('observatory.evidence_oidc.audience'),
            'sub' => 'repo:msaririzki/sita:ref:refs/heads/codex/wif-poc',
            'iat' => $now - 5,
            'nbf' => $now - 5,
            'exp' => $now + 300,
            'jti' => 'raw-jti-must-not-be-stored',
            'repository' => $evidence['github']['repository'],
            'repository_id' => $evidence['github']['repository_id'],
            'repository_owner_id' => $evidence['github']['repository_owner_id'],
            'ref' => $evidence['github']['ref'],
            'sha' => $evidence['github']['sha'],
            'workflow_ref' => $evidence['github']['workflow_ref'],
            'event_name' => $evidence['github']['event_name'],
            'run_id' => $evidence['github']['run_id'],
            'run_attempt' => (string) $evidence['github']['run_attempt'],
            'actor_id' => $evidence['github']['actor_id'],
        ];
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /** @return array{0: \OpenSSLAsymmetricKey, 1: string} */
    private function newPrivateKey(): array
    {
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $windowsConfig = 'C:/php/extras/ssl/openssl.cnf';
        if (PHP_OS_FAMILY === 'Windows' && file_exists($windowsConfig)) {
            $options['config'] = $windowsConfig;
        }
        $key = openssl_pkey_new($options);
        $this->assertNotFalse($key);
        $privateKey = '';
        $this->assertTrue(openssl_pkey_export($key, $privateKey, null, $options));

        return [$key, $privateKey];
    }
}
