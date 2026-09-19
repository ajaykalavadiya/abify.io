<?php

namespace Tests\Feature\Screenshots;

use App\Jobs\Screenshots\CapturePageScreenshotJob;
use App\Models\PageScreenshot;
use App\Models\Site;
use App\Support\Screenshots\CapturedPage;
use App\Support\Screenshots\PageScreenshotter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class CapturePageScreenshotJobTest extends TestCase
{
    use RefreshDatabase;

    protected function screenshotFor(Site $site, string $url = 'https://example.com/pricing'): PageScreenshot
    {
        return PageScreenshot::create([
            'site_id' => $site->id,
            'url' => $url,
            'url_hash' => hash('sha256', $url),
            'device' => 'desktop',
            'status' => PageScreenshot::STATUS_PENDING,
        ]);
    }

    protected function fakeCapture(CapturedPage $page): void
    {
        $this->mock(PageScreenshotter::class)
            ->shouldReceive('capture')
            ->once()
            ->andReturn($page);
    }

    public function test_it_stores_the_image_and_records_the_capture_geometry()
    {
        Storage::fake('local');

        $site = Site::factory()->create();
        $screenshot = $this->screenshotFor($site);

        $this->fakeCapture(new CapturedPage('jpeg-bytes', 1440, 3200, 3200, 900));

        CapturePageScreenshotJob::dispatchSync($screenshot);

        $screenshot->refresh();

        $this->assertSame(PageScreenshot::STATUS_READY, $screenshot->status);
        $this->assertSame(1440, $screenshot->width);
        $this->assertSame(3200, $screenshot->height);
        $this->assertNotNull($screenshot->captured_at);
        Storage::disk('local')->assertExists($screenshot->path);
        $this->assertSame('jpeg-bytes', Storage::disk('local')->get($screenshot->path));
    }

    public function test_a_recapture_replaces_the_previous_image_rather_than_leaking_it()
    {
        Storage::fake('local');

        $site = Site::factory()->create();
        $screenshot = $this->screenshotFor($site);

        $this->mock(PageScreenshotter::class)
            ->shouldReceive('capture')
            ->twice()
            ->andReturn(
                new CapturedPage('first', 1440, 900, 900, 900),
                new CapturedPage('second', 1440, 900, 900, 900),
            );

        CapturePageScreenshotJob::dispatchSync($screenshot);
        $firstPath = $screenshot->refresh()->path;

        CapturePageScreenshotJob::dispatchSync($screenshot);
        $secondPath = $screenshot->refresh()->path;

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($secondPath);
    }

    public function test_a_truncated_capture_is_flagged_so_the_map_can_say_so()
    {
        Storage::fake('local');

        $site = Site::factory()->create();
        $screenshot = $this->screenshotFor($site);

        $this->fakeCapture(new CapturedPage('jpeg', 1440, 12000, 18400, 900));

        CapturePageScreenshotJob::dispatchSync($screenshot);

        $this->assertTrue($screenshot->refresh()->isTruncated());
    }

    public function test_a_failed_capture_is_recorded_on_the_row_with_its_reason()
    {
        Storage::fake('local');

        $site = Site::factory()->create();
        $screenshot = $this->screenshotFor($site);

        $this->mock(PageScreenshotter::class)
            ->shouldReceive('capture')
            ->andThrow(new RuntimeException('Timed out waiting for Chrome.'));

        // The job rethrows so the queue can retry; the row still has to carry
        // the reason, because that string is what the heatmap shows the admin.
        $this->expectException(RuntimeException::class);

        try {
            CapturePageScreenshotJob::dispatchSync($screenshot);
        } finally {
            $screenshot->refresh();

            $this->assertSame(PageScreenshot::STATUS_FAILED, $screenshot->status);
            $this->assertStringContainsString('Timed out waiting for Chrome.', (string) $screenshot->error);
            $this->assertNull($screenshot->path);
        }
    }

    public function test_deleting_the_row_removes_the_stored_image()
    {
        Storage::fake('local');

        $site = Site::factory()->create();
        $screenshot = $this->screenshotFor($site);

        $this->fakeCapture(new CapturedPage('jpeg', 1440, 900, 900, 900));

        CapturePageScreenshotJob::dispatchSync($screenshot);
        $path = $screenshot->refresh()->path;

        $screenshot->delete();

        Storage::disk('local')->assertMissing($path);
    }

    public function test_deleting_a_site_takes_its_screenshots_with_it()
    {
        $site = Site::factory()->create();
        $screenshot = $this->screenshotFor($site);

        $site->delete();

        $this->assertDatabaseMissing('page_screenshots', ['id' => $screenshot->id]);
    }
}
