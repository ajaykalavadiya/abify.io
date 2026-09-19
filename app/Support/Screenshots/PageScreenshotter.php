<?php

namespace App\Support\Screenshots;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Captures a full-page screenshot of a URL at an emulated device size, for
 * use as the backdrop of a heatmap.
 */
class PageScreenshotter
{
    /**
     * @param  array<string, mixed>  $device  One entry from config('screenshots.devices')
     */
    public function capture(string $url, array $device): CapturedPage
    {
        $timeout = (int) config('screenshots.timeout');

        $chrome = new ChromeProcess((string) config('screenshots.binary'), $timeout);
        $chrome->start();

        $connection = null;

        try {
            $connection = new DevToolsConnection($chrome->webSocketUrl(), $timeout);

            return $this->captureWith($connection, $url, $device);
        } finally {
            $connection?->close();
            $chrome->stop();
        }
    }

    /**
     * @param  array<string, mixed>  $device
     */
    protected function captureWith(DevToolsConnection $cdp, string $url, array $device): CapturedPage
    {
        $target = $cdp->call('Target.createTarget', ['url' => 'about:blank']);
        $session = $cdp->call('Target.attachToTarget', [
            'targetId' => $target['targetId'],
            'flatten' => true,
        ])['sessionId'];

        $width = (int) $device['width'];
        $viewportHeight = (int) $device['height'];

        $cdp->call('Page.enable', [], $session);
        $this->silenceTracking($cdp, $session);
        $cdp->call('Emulation.setDeviceMetricsOverride', [
            'width' => $width,
            'height' => $viewportHeight,
            // Deliberately 1, not the device's real DPR: a 2x capture of a
            // 12000px page is a ~40 megapixel image, and the heatmap is
            // displayed scaled down to a column a few hundred pixels wide.
            'deviceScaleFactor' => 1,
            'mobile' => (bool) $device['mobile'],
        ], $session);

        if ($device['mobile']) {
            $cdp->call('Emulation.setTouchEmulationEnabled', ['enabled' => true], $session);
        }

        $cdp->call('Page.navigate', ['url' => $url], $session);

        // A page that never fires 'load' (a hanging third-party beacon, say)
        // still gets captured — whatever painted is more useful than nothing.
        $cdp->waitForEvent('Page.loadEventFired', min((int) config('screenshots.timeout'), 30));

        $this->primeLazyContent($cdp, $session, $viewportHeight);

        usleep(((int) config('screenshots.settle_ms')) * 1000);

        $metrics = $cdp->call('Page.getLayoutMetrics', [], $session);
        $content = $metrics['cssContentSize'] ?? $metrics['contentSize'] ?? [];

        $fullHeight = max($viewportHeight, (int) round($content['height'] ?? $viewportHeight));
        $contentWidth = max($width, (int) round($content['width'] ?? $width));
        $height = min($fullHeight, (int) config('screenshots.max_height'));

        $shot = $cdp->call('Page.captureScreenshot', [
            'format' => 'jpeg',
            'quality' => (int) config('screenshots.quality'),
            'captureBeyondViewport' => true,
            'optimizeForSpeed' => true,
            'clip' => [
                'x' => 0,
                'y' => 0,
                'width' => $contentWidth,
                'height' => $height,
                'scale' => 1,
            ],
        ], $session);

        $contents = base64_decode($shot['data'] ?? '', true);

        if ($contents === false || $contents === '') {
            throw new RuntimeException('Chrome returned an empty screenshot.');
        }

        return new CapturedPage(
            contents: $contents,
            width: $contentWidth,
            height: $height,
            fullHeight: $fullHeight,
            viewportHeight: $viewportHeight,
        );
    }

    /**
     * Stop the capture from counting as a visit.
     *
     * The tracking script is deliberately left running — it applies the
     * running experiments, so the screenshot shows the page as visitors
     * actually see it. What must not happen is the browser reporting its own
     * pageview, clicks and scrolls back as real traffic, so the two
     * collection endpoints are blocked at the network layer instead.
     */
    protected function silenceTracking(DevToolsConnection $cdp, string $session): void
    {
        try {
            $cdp->call('Network.enable', [], $session);
            $cdp->call('Network.setBlockedURLs', [
                'urls' => [
                    '*'.parse_url(route('tracking.collect'), PHP_URL_PATH).'*',
                    '*'.parse_url(route('tracking.assign'), PHP_URL_PATH).'*',
                ],
            ], $session);
        } catch (RuntimeException $exception) {
            Log::warning('Screenshot could not block tracking beacons: '.$exception->getMessage());
        }
    }

    /**
     * Scroll the page end to end and back, so lazy-loaded images and
     * scroll-triggered reveals have fired before the capture. Without this a
     * heatmap sits on top of a page of empty placeholders — exactly the parts
     * of the page the deep clicks are on.
     */
    protected function primeLazyContent(DevToolsConnection $cdp, string $session, int $viewportHeight): void
    {
        try {
            $cdp->call('Runtime.evaluate', [
                'awaitPromise' => true,
                'returnByValue' => true,
                'expression' => <<<JS
                new Promise(function (resolve) {
                    var step = {$viewportHeight};
                    var y = 0;
                    var limit = Math.min(document.documentElement.scrollHeight, 40000);

                    (function next() {
                        if (y >= limit) {
                            window.scrollTo(0, 0);
                            // One frame at the top so anything that reacts to
                            // scrolling back up (sticky headers) settles too.
                            requestAnimationFrame(function () { resolve(true); });
                            return;
                        }
                        window.scrollTo(0, y);
                        y += step;
                        setTimeout(next, 60);
                    })();
                })
                JS,
            ], $session);
        } catch (RuntimeException $exception) {
            // A page that blocks Runtime.evaluate (strict CSP on some sites)
            // is still worth capturing unprimed.
            Log::debug('Screenshot lazy-content priming skipped: '.$exception->getMessage());
        }
    }
}
