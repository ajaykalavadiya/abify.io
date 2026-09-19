<?php

namespace App\Models;

use Database\Factories\ExperimentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $site_id
 * @property string $key
 * @property string $name
 * @property string $status
 * @property string $type
 * @property string $url_match_type
 * @property string|null $url_pattern
 * @property int $traffic_allocation
 * @property array<int, array<string, mixed>>|null $factors
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['company_id', 'key', 'name', 'type', 'status', 'url_match_type', 'url_pattern', 'traffic_allocation', 'factors', 'started_at', 'ended_at'])]
class Experiment extends Model
{
    /** @use HasFactory<ExperimentFactory> */
    use HasFactory;

    /**
     * Get the company that owns the experiment.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the site that owns the experiment.
     *
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Get the variations belonging to the experiment.
     *
     * @return HasMany<ExperimentVariation, $this>
     */
    public function variations(): HasMany
    {
        return $this->hasMany(ExperimentVariation::class);
    }

    /**
     * Get the goals belonging to the experiment.
     *
     * @return HasMany<ExperimentGoal, $this>
     */
    public function goals(): HasMany
    {
        return $this->hasMany(ExperimentGoal::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'traffic_allocation' => 'integer',
            'factors' => 'array',
        ];
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }
}
