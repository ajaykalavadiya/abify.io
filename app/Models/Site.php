<?php

namespace App\Models;

use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string|null $domain
 * @property string $public_id
 * @property string|null $write_key_hash
 * @property Carbon|null $write_key_created_at
 * @property string $status
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'domain'])]
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    /**
     * Get the company that owns the site.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the experiments belonging to the site.
     *
     * @return HasMany<Experiment, $this>
     */
    public function experiments(): HasMany
    {
        return $this->hasMany(Experiment::class);
    }

    /**
     * Prefix on every write key. Makes a leaked key identifiable at a glance
     * in a log or a support ticket, and lets secret scanners match on it.
     */
    public const WRITE_KEY_PREFIX = 'abk_';

    /**
     * Issue a new write key, invalidating any previous one.
     *
     * Returns the plaintext, which is the only time it exists — only its
     * hash is persisted, so a lost key is rotated rather than recovered.
     */
    public function generateWriteKey(): string
    {
        $key = self::WRITE_KEY_PREFIX.Str::random(40);

        $this->forceFill([
            'write_key_hash' => self::hashWriteKey($key),
            'write_key_created_at' => now(),
        ])->save();

        return $key;
    }

    public static function hashWriteKey(string $key): string
    {
        return hash('sha256', $key);
    }

    /**
     * Resolve the site a presented write key belongs to, or null.
     */
    public static function findByWriteKey(string $key): ?self
    {
        if (! str_starts_with($key, self::WRITE_KEY_PREFIX)) {
            return null;
        }

        return static::where('write_key_hash', self::hashWriteKey($key))->first();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'write_key_created_at' => 'datetime',
        ];
    }

    /**
     * Boot the model, generating a unique public tracking id on creation.
     */
    protected static function booted(): void
    {
        static::creating(function (Site $site) {
            if (! $site->public_id) {
                $site->public_id = (string) Str::ulid();
            }

            if (! $site->status) {
                $site->status = 'active';
            }
        });
    }
}
