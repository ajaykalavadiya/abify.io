<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A captured backdrop for one (site, url, device) heatmap.
 *
 * @property int $id
 * @property int $site_id
 * @property string $url
 * @property string $url_hash
 * @property string $device
 * @property string $status
 * @property string|null $disk
 * @property string|null $path
 * @property int|null $width
 * @property int|null $height
 * @property int|null $full_height
 * @property string|null $error
 * @property Carbon|null $captured_at
 */
#[Fillable(['site_id', 'url', 'url_hash', 'device', 'status', 'disk', 'path', 'width', 'height', 'full_height', 'error', 'captured_at'])]
class PageScreenshot extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY && $this->path !== null;
    }

    /**
     * True when the page was taller than config('screenshots.max_height') and
     * the capture stops short of the bottom. The UI says so rather than
     * quietly stretching points that fall past the cut.
     */
    public function isTruncated(): bool
    {
        return $this->full_height !== null
            && $this->height !== null
            && $this->full_height > $this->height;
    }

    public function isStale(): bool
    {
        return $this->captured_at !== null
            && $this->captured_at->lt(now()->subDays((int) config('screenshots.stale_after_days')));
    }

    /**
     * Remove the stored image, if any. Called before a re-capture so an
     * abandoned file never outlives the row that pointed at it.
     */
    public function deleteImage(): void
    {
        if ($this->path && $this->disk) {
            Storage::disk($this->disk)->delete($this->path);
        }
    }

    protected static function booted(): void
    {
        static::deleting(fn (PageScreenshot $screenshot) => $screenshot->deleteImage());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
        ];
    }
}
