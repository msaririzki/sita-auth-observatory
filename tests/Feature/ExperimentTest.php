<?php

namespace Tests\Feature;

use App\Enums\Decision;
use App\Enums\ExperimentStatus;
use App\Enums\TrialStatus;
use App\Jobs\DispatchExperimentTrial;
use App\Models\Experiment;
use App\Models\User;
use App\Services\GitHubWorkflowDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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

    public function test_dispatch_queues_the_first_pending_trial_for_the_worker(): void
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
        Queue::fake();

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
            [TrialStatus::Pending, TrialStatus::Pending, TrialStatus::Pending],
            $experiment->trials()
                ->orderBy('sequence_number')
                ->get()
                ->map(fn ($trial) => $trial->status)
                ->all(),
        );

        Queue::assertPushed(
            DispatchExperimentTrial::class,
            fn (DispatchExperimentTrial $job): bool => $job->experimentId === $experiment->id,
        );
        Http::assertNothingSent();

        $this->actingAs($user)
            ->post(route('experiments.dispatch', $experiment))
            ->assertSessionHasErrors('experiment');

        Queue::assertPushed(DispatchExperimentTrial::class, 1);
    }

    public function test_dispatch_accepts_the_valid_static_oauth_baseline(): void
    {
        config([
            'observatory.github.token' => 'test-token',
            'observatory.github.owner' => 'msaririzki',
            'observatory.github.repository' => 'sita',
            'observatory.github.workflow' => 'wif-poc.yml',
        ]);
        Queue::fake();

        $user = $this->createUser('oauth-static');
        $this->actingAs($user)->post(route('experiments.store'), [
            'name' => 'Baseline OAuth statis',
            'profile' => 'oauth_static',
            'scenario' => 'valid',
            'target' => 'sita-docker',
            'git_ref' => 'codex/wif-deploy-basic',
            'repetitions' => 1,
            'cooldown_seconds' => 0,
        ]);

        $experiment = Experiment::query()->sole();

        $this->actingAs($user)
            ->post(route('experiments.dispatch', $experiment))
            ->assertRedirect(route('experiments.show', $experiment));

        $this->assertSame(ExperimentStatus::Queued, $experiment->fresh()->status);
        Queue::assertPushed(
            DispatchExperimentTrial::class,
            fn (DispatchExperimentTrial $job): bool => $job->experimentId === $experiment->id,
        );
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

        (new DispatchExperimentTrial($experiment->id))
            ->handle(app(GitHubWorkflowDispatcher::class));

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
            ->assertRedirect(route('experiments.show', $experiment));

        (new DispatchExperimentTrial($experiment->id))
            ->handle(app(GitHubWorkflowDispatcher::class));

        $this->assertSame(ExperimentStatus::Failed, $experiment->refresh()->status);

        $trial = $experiment->trials()->sole();
        $this->assertSame(TrialStatus::Failed, $trial->status);
        $this->assertSame('workflow_dispatch', $trial->failure_stage);
        $this->assertSame('RequestException', $trial->failure_reason);
        Http::assertSentCount(1);
    }

    public function test_worker_reserves_only_one_trial_until_final_evidence_arrives(): void
    {
        config([
            'observatory.github.token' => 'test-token',
            'observatory.github.owner' => 'msaririzki',
            'observatory.github.repository' => 'sita',
            'observatory.github.workflow' => 'auth-experiment.yml',
        ]);
        Http::fake(['api.github.com/*' => Http::response(status: 204)]);

        $experiment = Experiment::query()->create([
            'name' => 'Sequential batch',
            'profile' => 'wif_basic',
            'scenario' => 'valid',
            'expected_decision' => 'allow',
            'target' => 'sita-docker',
            'git_ref' => 'main',
            'repetitions' => 3,
            'cooldown_seconds' => 45,
            'status' => ExperimentStatus::Queued,
        ]);
        $experiment->trials()->createMany([
            ['sequence_number' => 1],
            ['sequence_number' => 2],
            ['sequence_number' => 3],
        ]);

        $job = new DispatchExperimentTrial($experiment->id);
        $job->handle(app(GitHubWorkflowDispatcher::class));
        $job->handle(app(GitHubWorkflowDispatcher::class));

        $this->assertSame(
            [TrialStatus::Dispatched, TrialStatus::Pending, TrialStatus::Pending],
            $experiment->trials()->orderBy('sequence_number')->get()->pluck('status')->all(),
        );
        Http::assertSentCount(1);
    }

    public function test_worker_waits_when_another_experiment_has_an_active_trial(): void
    {
        config([
            'observatory.github.token' => 'test-token',
            'observatory.github.owner' => 'msaririzki',
            'observatory.github.repository' => 'sita',
            'observatory.github.workflow' => 'auth-experiment.yml',
        ]);
        Http::fake(['api.github.com/*' => Http::response(status: 204)]);

        $activeExperiment = Experiment::query()->create([
            'name' => 'Active experiment',
            'profile' => 'wif_basic',
            'scenario' => 'valid',
            'expected_decision' => 'allow',
            'target' => 'sita-docker',
            'git_ref' => 'main',
            'repetitions' => 1,
            'cooldown_seconds' => 0,
            'status' => ExperimentStatus::Running,
        ]);
        $activeExperiment->trials()->create([
            'sequence_number' => 1,
            'status' => TrialStatus::Authenticating,
        ]);

        $waitingExperiment = Experiment::query()->create([
            'name' => 'Waiting experiment',
            'profile' => 'wif_basic',
            'scenario' => 'valid',
            'expected_decision' => 'allow',
            'target' => 'sita-docker',
            'git_ref' => 'main',
            'repetitions' => 1,
            'cooldown_seconds' => 0,
            'status' => ExperimentStatus::Queued,
        ]);
        $waitingTrial = $waitingExperiment->trials()->create([
            'sequence_number' => 1,
        ]);

        (new DispatchExperimentTrial($waitingExperiment->id))
            ->handle(app(GitHubWorkflowDispatcher::class));

        $this->assertSame(TrialStatus::Pending, $waitingTrial->fresh()->status);
        Http::assertNothingSent();
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
