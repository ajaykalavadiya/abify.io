<?php

namespace App\Jobs\Screenshots;

use App\Models\PageScreenshot;
use App\Support\Screenshots\PageScreenshotter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Drives a headless browser to capture the heatmap backdrop for one
 * (site, url, device) and records the result on the PageScreenshot row.
 *
 * Central database only — nothing here touches tenant data, so the worker
 * needs no tenant context.
 */
class CapturePageScreenshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public PageScreenshot $screenshot) {}

    /**
     * A capture can legitimately take most of a minute on a heavy page, so
     * the job's own budget sits above the browser's.
     */
    public function timeout(): int
    {
        return (int) config('screenshots.timeout') + 30;
    }

    public function handle(PageScreenshotter $screenshotter): void
    {
        $screenshot = $this->screenshot->fresh();

        if (! $screenshot) {
            return;
        }

        $device = config("screenshots.devices.{$screenshot->device}");

        if (! $device) {
            $this->markFailed($screenshot, "Unknown device [{$screenshot->device}].");

            return;
        }

        try {
            $captured = $screenshotter->capture($screenshot->url, $device);
        } catch (Throwable $exception) {
            $this->markFailed($screenshot, $exception->getMessage());

            throw $exception;
        }

        $disk = (string) config('screenshots.disk');
        $path = sprintf(
            'screenshots/%d/%s-%s-%s.jpg',
            $screenshot->site_id,
            substr($screenshot->url_hash, 0, 16),
            $screenshot->device,
            Str::lower(Str::random(8)),
        );

        Storage::disk($disk)->put($path, $captured->contents);

        // Drop the previous image only once the new one is safely stored, so
        // a failed write never leaves the heatmap with no backdrop at all.
        $screenshot->deleteImage();

        $screenshot->forceFill([
            'status' => PageScreenshot::STATUS_READY,
            'disk' => $disk,
            'path' => $path,
            'width' => $captured->width,
            'height' => $captured->height,
            'full_height' => $captured->fullHeight,
            'error' => null,
            'captured_at' => now(),
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        $screenshot = $this->screenshot->fresh();

        if ($screenshot && $screenshot->status !== PageScreenshot::STATUS_READY) {
            $this->markFailed($screenshot, $exception?->getMessage() ?? 'The capture failed.');
        }
    }

    protected function markFailed(PageScreenshot $screenshot, string $message): void
    {
        $screenshot->forceFill([
            'status' => PageScreenshot::STATUS_FAILED,
            // Chrome errors run long and the column is a text field, but the
            // UI shows this inline — keep it to something readable.
            'error' => Str::limit($message, 500),
        ])->save();
    }
}
