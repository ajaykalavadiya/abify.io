<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Site;
use App\Models\Tenant\SessionRecording;
use App\Models\Tenant\VisitorSession;
use App\Models\User;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenantDatabase;
use Tests\TestCase;

class RecordingsListTest extends TestCase
{
    use InteractsWithTenantDatabase, RefreshDatabase;

    private Company $company;

    private Site $site;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantDatabase();

        $this->user = User::factory()->create();
        $this->company = $this->user->companies->first();
        $this->company->forceFill([
            'database' => $this->tenantDatabasePath,
            'tenant_provisioned_at' => now(),
        ])->save();

        $this->site = Site::factory()->for($this->company)->create();
        $this->user->forceFill(['current_site_id' => $this->site->id])->save();

        app(TenantManager::class)->forCompany($this->company);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantDatabase();

        parent::tearDown();
    }

    private function recording(array $overrides = []): SessionRecording
    {
        $sessionId = (string) Str::uuid();

        VisitorSession::create([
            'site_id' => $this->site->id,
            'session_id' => $sessionId,
            'visitor_uuid' => (string) Str::uuid(),
            'entry_url' => 'https://shop.test/products/hat',
            'exit_url' => 'https://shop.test/cart',
            'device_type' => 'desktop',
            'pageview_count' => 3,
            'event_count' => 9,
            'started_at' => now(),
            'last_seen_at' => now(),
        ]);

        return SessionRecording::create([
            'site_id' => $this->site->id,
            'session_id' => $sessionId,
            'visitor_uuid' => (string) Str::uuid(),
            'status' => SessionRecording::STATUS_RECORDING,
            'disk' => 'local',
            'path_prefix' => "recordings/x/{$sessionId}",
            'chunk_count' => 2,
            'event_count' => 40,
            'bytes' => 2048,
            'duration_ms' => 65000,
            'has_rage_click' => false,
            'has_error' => false,
            'started_at' => now(),
            'expires_at' => now()->addDays(30),
            ...$overrides,
        ]);
    }

    public function test_it_lists_recordings_for_the_active_site(): void
    {
        $this->recording();
        $this->recording();

        $this->actingAs($this->user)
            ->get("/a/{$this->company->uid}/recordings")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('recordings/index')
                ->where('recordings.total', 2));
    }

    /**
     * The list exists for triage, so the filters are the whole point.
     */
    public function test_it_filters_to_recordings_with_rage_clicks(): void
    {
        $this->recording();
        $this->recording(['has_rage_click' => true]);

        $this->actingAs($this->user)
            ->get("/a/{$this->company->uid}/recordings?filter=rage")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('recordings.total', 1));
    }

    public function test_it_filters_to_recordings_with_errors(): void
    {
        $this->recording();
        $this->recording(['has_error' => true]);

        $this->actingAs($this->user)
            ->get("/a/{$this->company->uid}/recordings?filter=errors")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('recordings.total', 1));
    }

    /**
     * A visit that was admitted but sent nothing has no payload to play, so
     * listing it would only ever produce a broken player.
     */
    public function test_it_hides_recordings_with_no_chunks(): void
    {
        $this->recording();
        $this->recording(['chunk_count' => 0]);

        $this->actingAs($this->user)
            ->get("/a/{$this->company->uid}/recordings")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('recordings.total', 1));
    }

    public function test_it_includes_visit_context_from_the_session(): void
    {
        $this->recording();

        $this->actingAs($this->user)
            ->get("/a/{$this->company->uid}/recordings")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('recordings.data.0.entry_url', 'https://shop.test/products/hat')
                ->where('recordings.data.0.pageview_count', 3)
                ->where('recordings.data.0.device_type', 'desktop'));
    }

    public function test_guests_cannot_see_recordings(): void
    {
        $this->get("/a/{$this->company->uid}/recordings")->assertRedirect();
    }

    public function test_it_shows_the_player_page_for_a_recording(): void
    {
        $recording = $this->recording();

        $this->actingAs($this->user)
            ->get("/a/{$this->company->uid}/recordings/{$recording->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('recordings/show')
                ->where('recording.id', $recording->id));
    }

    /**
     * rrweb needs the events back in one array, in timestamp order — chunks
     * are uploaded over sendBeacon and can be written out of sequence.
     */
    public function test_it_concatenates_chunks_in_timestamp_order(): void
    {
        Storage::fake('local');

        $recording = $this->recording(['chunk_count' => 3, 'disk' => 'local']);

        // Written deliberately out of order.
        Storage::disk('local')->put("{$recording->path_prefix}/a.json.gz", gzencode(json_encode([
            ['type' => 4, 'timestamp' => 30],
        ])));
        Storage::disk('local')->put("{$recording->path_prefix}/b.json.gz", gzencode(json_encode([
            ['type' => 2, 'timestamp' => 10],
        ])));
        Storage::disk('local')->put("{$recording->path_prefix}/c.json.gz", gzencode(json_encode([
            ['type' => 3, 'timestamp' => 20],
        ])));

        $response = $this->actingAs($this->user)
            ->getJson("/a/{$this->company->uid}/recordings/{$recording->id}/events");

        $response->assertOk();

        $timestamps = array_column($response->json('events'), 'timestamp');

        $this->assertSame([10, 20, 30], $timestamps);
    }

    /**
     * A pruned or half-written chunk is a gap in a replay, not a reason to
     * fail the whole thing — rrweb plays what it has.
     */
    public function test_a_missing_chunk_does_not_fail_the_replay(): void
    {
        Storage::fake('local');

        $recording = $this->recording(['chunk_count' => 2, 'disk' => 'local']);

        Storage::disk('local')->put("{$recording->path_prefix}/a.json.gz", gzencode(json_encode([
            ['type' => 2, 'timestamp' => 10],
        ])));
        // Chunk 1 deliberately absent.

        $this->actingAs($this->user)
            ->getJson("/a/{$this->company->uid}/recordings/{$recording->id}/events")
            ->assertOk()
            ->assertJsonCount(1, 'events');
    }

    public function test_a_recording_from_another_site_is_not_reachable(): void
    {
        $recording = $this->recording();

        $other = Site::factory()->for($this->company)->create();
        $this->user->forceFill(['current_site_id' => $other->id])->save();

        $this->actingAs($this->user)
            ->get("/a/{$this->company->uid}/recordings/{$recording->id}")
            ->assertNotFound();
    }

    public function test_guests_cannot_fetch_replay_events(): void
    {
        $recording = $this->recording();

        $this->get("/a/{$this->company->uid}/recordings/{$recording->id}/events")
            ->assertRedirect();
    }
}
