<?php

namespace Tests\Feature;

use App\Enums\TrialStatus;
use App\Models\Experiment;
use App\Models\User;
use App\Services\TrialEvidenceImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EvidenceImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_preserves_traceability_and_duplicate_import_is_idempotent(): void
    {
        $data = $this->evidence();
        $importer = app(TrialEvidenceImporter::class);
        $trial = $importer->import($data);
        $importer->import($data);

        $this->assertSame(TrialStatus::Completed, $trial->status);
        $this->assertSame('TP', $trial->classification->value);
        $this->assertEquals(8243.15, $trial->authentication_duration_ms);
        $this->assertNull($trial->tailnet_join_duration_ms);
        $this->assertEquals(9383.4, $trial->total_duration_ms);
        $this->assertSame(5, $trial->stageEvents()->count());
        $this->assertNull($trial->stageEvents()->where('stage', 'preflight')->sole()->duration_ms);
        $this->assertFalse($trial->sanitized_metadata['signature_verified_by_observatory']);

        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
            ->get(route('experiments.show', $data['experiment_id']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('experiments/show')
                ->has('trials', 1)->where('trials.0.metadata.evidence.github.run_id', $data['github']['run_id']));
    }

    public function test_conflicting_evidence_cannot_overwrite_a_completed_trial(): void
    {
        $data = $this->evidence();
        app(TrialEvidenceImporter::class)->import($data);
        $data['stages'][2]['duration_ms'] = 100;
        $this->expectException(ValidationException::class);
        app(TrialEvidenceImporter::class)->import($data);
    }

    public function test_raw_token_fields_and_wrong_commit_are_rejected(): void
    {
        $data = $this->evidence();
        $data['oidc_claims']['raw_token'] = 'should-never-be-stored';
        try {
            app(TrialEvidenceImporter::class)->import($data);
            $this->fail('Unknown token field was accepted');
        } catch (ValidationException) {
            $this->assertDatabaseEmpty('stage_events');
        }
        unset($data['oidc_claims']['raw_token']);
        $data['github']['sha'] = str_repeat('a', 40);
        $this->expectException(ValidationException::class);
        app(TrialEvidenceImporter::class)->import($data);
    }

    public function test_collection_failure_is_excluded_from_decision_accuracy(): void
    {
        $data = $this->evidence();
        $data['oidc_claims'] = null;
        $data['stages'][1]['status'] = 'fail';
        $trial = app(TrialEvidenceImporter::class)->import($data);
        $this->assertSame(TrialStatus::Failed, $trial->status);
        $this->assertNull($trial->classification);
    }

    public function test_expected_audience_rejection_is_recorded_as_a_true_negative(): void
    {
        $data = $this->evidence();
        Experiment::query()->findOrFail($data['experiment_id'])->update([
            'scenario' => 'wrong_audience',
            'expected_decision' => 'deny',
        ]);
        $data['scenario'] = 'wrong_audience';
        $data['expected_decision'] = 'deny';
        $data['actual_decision'] = 'deny';
        $data['classification'] = 'TN';
        $data['reason_code'] = 'AUTHENTICATION_OR_ACCESS_DENIED';
        $data['stages'][2]['status'] = 'fail';
        $data['stages'][3]['status'] = 'skipped';
        $data['stages'][4]['status'] = 'skipped';

        $trial = app(TrialEvidenceImporter::class)->import($data);

        $this->assertSame(TrialStatus::Completed, $trial->status);
        $this->assertSame('TN', $trial->classification->value);
        $this->assertSame('wif_exchange_and_join', $trial->failure_stage);
        $this->assertSame('AUTHENTICATION_OR_ACCESS_DENIED', $trial->failure_reason);
    }

    public function test_upload_requires_login_and_accepts_a_bound_json_file(): void
    {
        $data = $this->evidence();
        $url = route('experiments.evidence', $data['experiment_id']);
        $this->post($url)->assertRedirect(route('login'));
        $file = UploadedFile::fake()->createWithContent('trial-evidence.json', json_encode($data, JSON_THROW_ON_ERROR));
        $this->actingAs(User::factory()->create())->post($url, ['evidence_file' => $file])
            ->assertRedirect(route('experiments.show', $data['experiment_id']));
    }

    /** @return array<string, mixed> */
    private function evidence(): array
    {
        $data = json_decode(file_get_contents(base_path('fixtures/v1/valid-wif-basic-allow.json')), true, flags: JSON_THROW_ON_ERROR);
        $experiment = Experiment::query()->create([
            'name' => 'Fixture used only in tests', 'profile' => 'wif_basic', 'scenario' => 'valid',
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
}
