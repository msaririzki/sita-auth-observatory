<?php

namespace App\Models;

use App\Enums\AuthenticationProfile;
use App\Enums\Decision;
use App\Enums\ExperimentScenario;
use App\Enums\ExperimentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property AuthenticationProfile $profile
 * @property ExperimentScenario $scenario
 * @property Decision $expected_decision
 * @property ExperimentStatus $status
 * @property-read int $trials_count
 * @property-read int $completed_trials_count
 */
class Experiment extends Model
{
    use HasUlids;

    protected $fillable = [
        'name',
        'profile',
        'scenario',
        'expected_decision',
        'target',
        'git_ref',
        'commit_sha',
        'repetitions',
        'cooldown_seconds',
        'status',
        'initiated_by',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'profile' => AuthenticationProfile::class,
            'scenario' => ExperimentScenario::class,
            'expected_decision' => Decision::class,
            'status' => ExperimentStatus::class,
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /** @return HasMany<ExperimentTrial, $this> */
    public function trials(): HasMany
    {
        return $this->hasMany(ExperimentTrial::class);
    }
}
