<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $uid
 * @property string|null $stripe_id
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 * @property Carbon|null $trial_ends_at
 * @property string|null $database
 * @property Carbon|null $tenant_provisioned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use Billable, HasFactory;

    /**
     * Get the sites belonging to the company.
     *
     * @return HasMany<Site, $this>
     */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    /**
     * Get the users belonging to the company (with their per-company role
     * available via $user->pivot->role) — a user can belong to more than
     * one company, see User::companies().
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    /**
     * Get the pending (unaccepted) invitations for the company.
     *
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * Get the experiments belonging to the company.
     *
     * @return HasMany<Experiment, $this>
     */
    public function experiments(): HasMany
    {
        return $this->hasMany(Experiment::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_provisioned_at' => 'datetime',
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * 'basic' once subscribed (monthly or yearly — same tier, different
     * billing interval), 'trial' during the pre-subscription trial window,
     * else 'expired'. See config/billing.php for what each tier allows.
     */
    public function planTier(): string
    {
        if ($this->subscribed('default')) {
            // Which tier, not just "subscribed": Growth and Basic are the
            // same Stripe subscription with different prices on it, and
            // reading only `subscribed()` would hand a Growth customer
            // Basic's limits.
            $growthPrices = array_filter([
                config('billing.stripe_price_growth_monthly'),
                config('billing.stripe_price_growth_yearly'),
            ]);

            foreach ($growthPrices as $price) {
                if ($this->subscribedToPrice($price, 'default')) {
                    return 'growth';
                }
            }

            return 'basic';
        }

        if ($this->onGenericTrial()) {
            return 'trial';
        }

        return 'expired';
    }

    /**
     * @return array{label: string, sites: int, tests_per_site: int, heatmaps_per_month: int, recordings_per_day: int}
     */
    public function planLimits(): array
    {
        return config("billing.plans.{$this->planTier()}");
    }

    /**
     * The {account} route segment is the public uid, not the numeric id —
     * this makes Laravel's automatic SubstituteBindings middleware (which
     * runs before our own ResolveAccountFromRoute) resolve it correctly.
     */
    public function getRouteKeyName(): string
    {
        return 'uid';
    }

    /**
     * Boot the model, generating a unique public uid on creation.
     */
    protected static function booted(): void
    {
        static::creating(function (Company $company) {
            if (! $company->uid) {
                do {
                    $uid = Str::upper(Str::random(8));
                } while (static::where('uid', $uid)->exists());

                $company->uid = $uid;
            }
        });
    }
}
