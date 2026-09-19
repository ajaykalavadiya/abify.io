<?php

namespace App\Models;

use Database\Factories\ExperimentVariationFactory;
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
 * @property bool $is_control
 * @property int $traffic_weight
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'name', 'is_control', 'traffic_weight', 'payload'])]
class ExperimentVariation extends Model
{
    /** @use HasFactory<ExperimentVariationFactory> */
    use HasFactory;

    /**
     * Get the experiment that owns the variation.
     *
     * @return BelongsTo<Experiment, $this>
     */
    public function experiment(): BelongsTo
    {
        return $this->belongsTo(Experiment::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_control' => 'boolean',
            'traffic_weight' => 'integer',
            'payload' => 'array',
        ];
    }
}
