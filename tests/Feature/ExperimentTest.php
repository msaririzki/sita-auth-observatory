<?php

namespace Tests\Feature;

use App\Enums\Decision;
use App\Enums\ExperimentStatus;
use App\Enums\TrialStatus;
use App\Models\Experiment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExperimentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_experiments(): void
    {
        $this->get(route('experiments.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_create_a_sequential_experiment(): void
    {
        $user = $this->createUser('sequential');

        $response = $this->actingAs($user)->post(route('experiments.store'), [
            'name' => 'Pilot WIF dasar',
            'profile' => 'wif_basic',
            'scenario' => 'valid',
            'target' => 'sita-docker',
            'git_ref' => 'main',
            'commit_sha' => null,
            'repetitions' => 5,
            'cooldown_seconds' => 45,
        ]);

        $experiment = Experiment::query()->sole();

        $response->assertRedirect(route('experiments.index'));

        $this->assertSame(Decision::Allow, $experiment->expected_decision);
        $this->assertSame(ExperimentStatus::Draft, $experiment->status);
        $this->assertSame($user->id, $experiment->initiated_by);
        $this->assertSame(range(1, 5), $experiment->trials()->pluck('sequence_number')->all());
        $this->assertTrue(
            $experiment->trials()->get()->every(
                fn ($trial) => $trial->status === TrialStatus::Pending,
            ),
        );
    }

    public function test_denial_scenario_records_deny_as_expected_decision(): void
    {
        $user = $this->createUser('denial');

        $this->actingAs($user)->post(route('experiments.store'), [
            'name' => 'Branch tidak sah',
            'profile' => 'wif_multi_claim',
            'scenario' => 'wrong_branch',
            'target' => 'sita-docker',
            'git_ref' => 'feature/untrusted',
            'repetitions' => 1,
            'cooldown_seconds' => 30,
        ])->assertRedirect(route('experiments.index'));

        $this->assertSame(Decision::Deny, Experiment::query()->sole()->expected_decision);
    }

    public function test_experiment_rejects_unknown_targets_and_excessive_repetitions(): void
    {
        $user = $this->createUser('invalid');

        $this->actingAs($user)->post(route('experiments.store'), [
            'name' => 'Konfigurasi tidak sah',
            'profile' => 'wif_basic',
            'scenario' => 'valid',
            'target' => 'server-lain',
            'git_ref' => 'main',
            'repetitions' => 31,
            'cooldown_seconds' => 45,
        ])->assertSessionHasErrors(['target', 'repetitions']);

        $this->assertDatabaseEmpty('experiments');
    }

    public function test_dispatch_sends_only_the_first_pending_trial_to_github(): void
    {
        config([
            'observatory.github.token' => 'test-token',
            'observatory.github.owner' => 'msaririzki',
            'observatory.github.repository' => 'sita',
            'observatory.github.workflow' => 'auth-experiment.yml',
        ]);

        Http::fake([
            'api.github.com/*' => Http::response(status: 204),
        ]);

        $user = $this->createUser('dispatch');

        $this->actingAs($user)->post(route('experiments.store'), [
            'name' => 'Pilot WIF dasar',
            'profile' => 'wif_basic',
            'scenario' => 'valid',
            'target' => 'sita-docker',
            'git_ref' => 'main',
            'repetitions' => 3,
            'cooldown_seconds' => 45,
        ]);

        $experiment = Experiment::query()->sole();

        $this->actingAs($user)
            ->post(route('experiments.dispatch', $experiment))
            ->assertRedirect(route('experiments.show', $experiment));

        $this->assertSame(ExperimentStatus::Queued, $experiment->refresh()->status);
        $this->assertSame(
            [TrialStatus::Dispatched, TrialStatus::Pending, TrialStatus::Pending],
            $experiment->trials()
                ->orderBy('sequence_number')
                ->get()
                ->map(fn ($trial) => $trial->status)
                ->all(),
        );

        Http::assertSent(function (Request $request) use ($experiment): bool {
            return $request->url() === 'https://api.github.com/repos/msaririzki/sita/actions/workflows/auth-experiment.yml/dispatches'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request['ref'] === 'main'
                && $request['inputs']['experiment_id'] === $experiment->id
                && $request['inputs']['profile'] === 'wif_basic'
                && $request['inputs']['repetition'] === '1';
        });

        $this->actingAs($user)
            ->post(route('experiments.dispatch', $experiment))
            ->assertSessionHasErrors('experiment');

        Http::assertSentCount(1);
    }

    public function test_dispatch_can_use_a_repository_scoped_github_app_identity(): void
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($privateKey === false) {
            $this->markTestSkipped('OpenSSL key generation is unavailable in this PHP runtime.');
        }

        openssl_pkey_export($privateKey, $privateKeyPem);

        config([
            'observatory.github.token' => null,
            'observatory.github.app_id' => '123456',
            'observatory.github.installation_id' => '789012',
            'observatory.github.private_key_base64' => base64_encode($privateKeyPem),
            'observatory.github.owner' => 'msaririzki',
            'observatory.github.repository' => 'sita',
            'observatory.github.workflow' => 'auth-experiment.yml',
        ]);

        Http::fake([
            'api.github.com/app/installations/789012/access_tokens' => Http::response([
                'token' => 'installation-token',
            ]),
            'api.github.com/repos/msaririzki/sita/actions/workflows/auth-experiment.yml/dispatches' => Http::response(status: 204),
        ]);

        $user = $this->createUser('github-app');
        $this->actingAs($user)->post(route('experiments.store'), [
            'name' => 'GitHub App pilot',
            'profile' => 'wif_basic',
            'scenario' => 'valid',
            'target' => 'sita-docker',
            'git_ref' => 'main',
            'repetitions' => 1,
            'cooldown_seconds' => 0,
        ]);

        $experiment = Experiment::query()->sole();

        $this->actingAs($user)
            ->post(route('experiments.dispatch', $experiment))
            ->assertRedirect(route('experiments.show', $experiment));

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.github.com/app/installations/789012/access_tokens'
                && str_starts_with((string) $request->header('Authorization')[0], 'Bearer ey');
        });
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.github.com/repos/msaririzki/sita/actions/workflows/auth-experiment.yml/dispatches'
                && $request->hasHeader('Authorization', 'Bearer installation-token');
        });
    }

    public function test_dispatch_failure_is_recorded_without_automatic_retry(): void
    {
        config([
            'observatory.github.token' => 'test-token',
            'observatory.github.owner' => 'msaririzki',
            'observatory.github.repository' => 'sita',
            'observatory.github.workflow' => 'auth-experiment.yml',
        ]);

        Http::fake([
            'api.github.com/*' => Http::response(['message' => 'Invalid ref'], 422),
        ]);

        $user = $this->createUser('dispatch-failure');

        $this->actingAs($user)->post(route('experiments.store'), [
            'name' => 'Invalid workflow dispatch',
            'profile' => 'wif_basic',
            'scenario' => 'valid',
            'target' => 'sita-docker',
            'git_ref' => 'missing-ref',
            'repetitions' => 1,
            'cooldown_seconds' => 45,
        ]);

        $experiment = Experiment::query()->sole();

        $this->actingAs($user)
            ->post(route('experiments.dispatch', $experiment))
            ->assertSessionHasErrors('experiment');

        $this->assertSame(ExperimentStatus::Failed, $experiment->refresh()->status);

        $trial = $experiment->trials()->sole();
        $this->assertSame(TrialStatus::Failed, $trial->status);
        $this->assertSame('workflow_dispatch', $trial->failure_stage);
        $this->assertSame('RequestException', $trial->failure_reason);
        Http::assertSentCount(1);
    }

    private function createUser(string $suffix): User
    {
        return User::query()->create([
            'name' => 'Research Operator',
            'email' => "operator-{$suffix}@example.test",
            'email_verified_at' => now(),
            'password' => Hash::make('research-password'),
        ]);
    }
}
