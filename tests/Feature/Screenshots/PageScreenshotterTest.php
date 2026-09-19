<?php

namespace Tests\Feature\Screenshots;

use App\Support\Screenshots\PageScreenshotter;
use Tests\TestCase;

/**
 * Exercises the whole capture stack against a real browser: the launcher, the
 * hand-rolled WebSocket framing in DevToolsConnection, and the CDP calls.
 *
 * Skipped when no browser is installed, so a machine without Chrome still
 * gets a green suite — but where one exists this is the test that would catch
 * a framing or protocol regression, which is otherwise silent until a
 * customer's heatmap has no backdrop.
 */
class PageScreenshotterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! is_executable((string) config('screenshots.binary'))) {
            $this->markTestSkipped('No Chrome binary at '.config('screenshots.binary').'.');
        }
    }

    protected function fixtureUrl(): string
    {
        return 'file://'.realpath(base_path('tests/fixtures/screenshot-page.html'));
    }

    public function test_it_captures_the_whole_page_not_just_the_viewport()
    {
        $device = config('screenshots.devices.desktop');

        $captured = app(PageScreenshotter::class)->capture($this->fixtureUrl(), $device);

        // The fixture is three 1200px blocks; a viewport-only capture would
        // come back at the device height instead.
        $this->assertGreaterThan($device['height'] * 2, $captured->height);
        $this->assertSame($captured->height, $captured->fullHeight);
        $this->assertFalse($captured->wasTruncated());
        $this->assertNotEmpty($captured->contents);
        $this->assertStringStartsWith("\xff\xd8\xff", $captured->contents, 'Expected JPEG bytes.');
    }

    public function test_it_emulates_the_requested_device_width()
    {
        $captured = app(PageScreenshotter::class)->capture(
            $this->fixtureUrl(),
            config('screenshots.devices.mobile'),
        );

        $this->assertSame(config('screenshots.devices.mobile.width'), $captured->width);
    }

    public function test_it_stops_at_the_configured_height_ceiling_and_says_so()
    {
        // Chrome's compositor gives out on very tall pages, so the capture is
        // clipped — but a clipped image must never silently pass as complete,
        // or every point below the cut is drawn in the wrong place.
        config(['screenshots.max_height' => 1000]);

        $captured = app(PageScreenshotter::class)->capture(
            $this->fixtureUrl(),
            config('screenshots.devices.desktop'),
        );

        $this->assertSame(1000, $captured->height);
        $this->assertGreaterThan(1000, $captured->fullHeight);
        $this->assertTrue($captured->wasTruncated());
    }
}
