<?php

namespace App\Models;

use Database\Factories\ExperimentGoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $experiment_id
 * @property string $key
 * @property string $name
 * @property string $type
 * @property string|null $match_type
 * @property string|null $target
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'name', 'type', 'match_type', 'target'])]
class ExperimentGoal extends Model
{
    /** @use HasFactory<ExperimentGoalFactory> */
    use HasFactory;

    /**
     * Get the experiment that owns the goal.
     *
     * @return BelongsTo<Experiment, $this>
     */
    public function experiment(): BelongsTo
    {
        return $this->belongsTo(Experiment::class);
    }
}
