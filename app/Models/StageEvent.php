<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageEvent extends Model
{
    use HasUlids;

    protected $fillable = [
        'experiment_trial_id',
        'stage',
        'status',
        'duration_ms',
        'reason_code',
        'sanitized_metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'sanitized_metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<ExperimentTrial, $this> */
    public function trial(): BelongsTo
    {
        return $this->belongsTo(ExperimentTrial::class, 'experiment_trial_id');
    }
}
