<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The recorder bundle is served at a fixed URL that is baked into the
 * tracking script on customer sites, so it cannot move when the build
 * re-hashes the file.
 */
class RecorderScriptTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_recorder_bundle_is_served_at_a_stable_url(): void
    {
        if (! file_exists(public_path('build/manifest.json'))) {
            $this->markTestSkipped('Front-end assets have not been built.');
        }

        $response = $this->get('/t/recorder.js');

        $response->assertOk();
        $this->assertStringContainsString(
            'javascript',
            (string) $response->headers->get('Content-Type')
        );

        // The hook the tracking script calls once the bundle loads. Without
        // it the file is served but nothing ever starts recording.
        $this->assertStringContainsString('__abStartRecorder', $response->getContent());
    }

    /**
     * The regression that prompted this: /t/{siteId}.js also matches the
     * literal string "recorder", so the two routes must stay in an order
     * where the fixed path wins.
     */
    public function test_the_recorder_route_is_not_swallowed_by_the_site_script_route(): void
    {
        $response = $this->get('/t/recorder.js');

        $this->assertNotSame(
            404,
            $response->getStatusCode(),
            'The recorder URL resolved to the per-site script route instead.'
        );
    }
}
