<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A connected commerce platform for one site — see the create migration for
 * why this is one table rather than one per provider.
 *
 * @property int $id
 * @property int $company_id
 * @property int $site_id
 * @property string $provider
 * @property string $external_id
 * @property string $status
 * @property array<string, mixed>|null $credentials
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $connected_at
 */
#[Fillable(['company_id', 'site_id', 'provider', 'external_id', 'status', 'credentials', 'meta', 'connected_at'])]
class Integration extends Model
{
    public const SHOPIFY = 'shopify';

    public const WOOCOMMERCE = 'woocommerce';

    public const MAGENTO = 'magento';

    /**
     * Google Analytics 4. Not a commerce platform, but it connects on the
     * same terms — a remote identity (the measurement id) plus a credential
     * (the API secret) that must not sit in plaintext.
     */
    public const GA4 = 'ga4';

    public const STATUS_ACTIVE = 'active';

    /** Set when the merchant uninstalls, rather than deleting the row — the
     *  conversions it produced are still real and still referenced. */
    public const STATUS_REVOKED = 'revoked';

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function accessToken(): ?string
    {
        return $this->credentials['access_token'] ?? null;
    }

    /**
     * GA4 Measurement Protocol secret. Distinct from an OAuth access token:
     * it authorises writing events to one property and nothing else.
     */
    public function apiSecret(): ?string
    {
        return $this->credentials['api_secret'] ?? null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // 'encrypted:array' rather than 'array': these credentials read a
            // merchant's orders, so a database dump must not be enough to use
            // them.
            'credentials' => 'encrypted:array',
            'meta' => 'array',
            'connected_at' => 'datetime',
        ];
    }
}
