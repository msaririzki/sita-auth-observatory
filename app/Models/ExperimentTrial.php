<?php

namespace App\Models;

use App\Enums\Decision;
use App\Enums\DecisionClassification;
use App\Enums\TrialStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property TrialStatus $status
 * @property Decision|null $actual_decision
 * @property DecisionClassification|null $classification
 */
class ExperimentTrial extends Model
{
    use HasUlids;

    protected $fillable = [
        'experiment_id',
        'sequence_number',
        'github_run_id',
        'run_attempt',
        'status',
        'actual_decision',
        'classification',
        'authentication_duration_ms',
        'tailnet_join_duration_ms',
        'reachability_duration_ms',
        'ssh_duration_ms',
        'total_duration_ms',
        'network_path',
        'failure_stage',
        'failure_reason',
        'sanitized_metadata',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TrialStatus::class,
            'actual_decision' => Decision::class,
            'classification' => DecisionClassification::class,
            'sanitized_metadata' => 'array',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Experiment, $this> */
    public function experiment(): BelongsTo
    {
        return $this->belongsTo(Experiment::class);
    }

    /** @return HasMany<StageEvent, $this> */
    public function stageEvents(): HasMany
    {
        return $this->hasMany(StageEvent::class);
    }
}
