<?php

namespace Tests\Feature\Api;

use App\Actions\Tracking\ReserveRecordingSlot;
use App\Models\Site;
use App\Models\Tenant\SessionRecording;
use App\Support\Tenancy\TenantManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithTenantDatabase;
use Tests\TestCase;

class RecordingQuotaTest extends TestCase
{
    use InteractsWithTenantDatabase, RefreshDatabase;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantDatabase();
        Storage::fake('local');

        $company = $this->tenantCompany();
        $this->site = Site::factory()->for($company)->create([
            'settings' => ['recording' => ['enabled' => true, 'daily_limit' => 3, 'sample_rate' => 100]],
        ]);

        app(TenantManager::class)->forCompany($company);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantDatabase();

        parent::tearDown();
    }

    private function sendChunk(?string $session = null, int $seq = 0): TestResponse
    {
        return $this->postJson('/api/t/rec', [
            'site_id' => $this->site->public_id,
            'session_id' => $session ?? (string) Str::uuid(),
            'visitor_uuid' => (string) Str::uuid(),
            'seq' => $seq,
            'events' => [['type' => 2, 'data' => ['source' => 0]]],
            'duration_ms' => 1500,
        ]);
    }

    public function test_it_records_up_to_the_daily_limit_then_refuses(): void
    {
        foreach (range(1, 3) as $ignored) {
            $this->sendChunk()->assertStatus(202)->assertJson(['recording' => true]);
        }

        // Fourth visit of the day is turned away.
        $this->sendChunk()->assertStatus(200)->assertJson(['recording' => false]);

        $this->assertSame(3, SessionRecording::count());
    }

    /**
     * The race the atomic reservation exists for: on a busy store several
     * visitors reach the check together, and a read-then-write would let them
     * all pass the same limit.
     */
    public function test_concurrent_reservations_cannot_exceed_the_limit(): void
    {
        $quota = app(ReserveRecordingSlot::class);

        $granted = 0;
        foreach (range(1, 25) as $ignored) {
            $granted += $quota->reserve($this->site) ? 1 : 0;
        }

        $this->assertSame(3, $granted, 'The quota admitted more visits than the daily limit.');
        $this->assertSame(3, (int) DB::table('recording_usage')
            ->where('site_id', $this->site->id)
            ->value('sessions_recorded'));
    }

    public function test_an_admitted_visit_keeps_recording_after_the_quota_fills(): void
    {
        $session = (string) Str::uuid();

        // This visit takes the last slot...
        $this->site->forceFill(['settings' => ['recording' => ['enabled' => true, 'daily_limit' => 1]]])->save();
        $this->sendChunk($session, 0)->assertJson(['recording' => true]);

        // ...and a different visitor is now refused.
        $this->sendChunk()->assertJson(['recording' => false]);

        // But the admitted visit must not be truncated mid-way.
        $this->sendChunk($session, 1)->assertJson(['recording' => true]);

        $this->assertSame(2, SessionRecording::where('session_id', $session)->value('chunk_count'));
    }

    public function test_recording_is_off_unless_the_site_enables_it(): void
    {
        $this->site->forceFill(['settings' => ['recording' => ['enabled' => false]]])->save();

        $this->sendChunk()->assertJson(['recording' => false]);

        $this->assertSame(0, SessionRecording::count());
    }

    public function test_a_zero_limit_disables_recording(): void
    {
        $this->site->forceFill(['settings' => ['recording' => ['enabled' => true, 'daily_limit' => 0]]])->save();

        $this->sendChunk()->assertJson(['recording' => false]);
    }

    public function test_chunks_are_written_to_disk_compressed(): void
    {
        $session = (string) Str::uuid();

        $this->sendChunk($session, 0);

        $recording = SessionRecording::first();
        $paths = $recording->chunkPaths();

        $this->assertCount(1, $paths);

        $stored = Storage::disk('local')->get($paths[0]);
        $this->assertNotFalse(gzdecode($stored), 'Chunk should be gzip encoded on disk.');
        $this->assertGreaterThan(0, $recording->bytes);
    }

    public function test_usage_is_counted_for_billing(): void
    {
        $this->sendChunk();
        $this->sendChunk();

        $usage = DB::table('recording_usage')->where('site_id', $this->site->id)->first();

        $this->assertSame(2, (int) $usage->sessions_recorded);
        $this->assertGreaterThan(0, (int) $usage->bytes_stored);
        $this->assertSame($this->site->company_id, (int) $usage->company_id);
    }

    public function test_frustration_signals_are_sticky_across_chunks(): void
    {
        $session = (string) Str::uuid();

        $this->postJson('/api/t/rec', [
            'site_id' => $this->site->public_id,
            'session_id' => $session,
            'visitor_uuid' => (string) Str::uuid(),
            'seq' => 0,
            'events' => [['type' => 3]],
            'rage_click' => true,
        ]);

        $this->sendChunk($session, 1);

        $this->assertTrue(SessionRecording::where('session_id', $session)->value('has_rage_click'));
    }

    public function test_remaining_today_reflects_usage(): void
    {
        $quota = app(ReserveRecordingSlot::class);

        $this->assertSame(3, $quota->remainingToday($this->site));

        $quota->reserve($this->site);

        $this->assertSame(2, $quota->remainingToday($this->site));
    }

    /**
     * The regression this guards, found in real captured data: a visit spans
     * page loads, and the recorder in each new page starts its own chunk
     * counter at zero. Naming stored chunks by that counter meant the second
     * page's first chunk overwrote the first page's, destroying the start of
     * every multi-page replay.
     */
    public function test_a_second_page_load_does_not_overwrite_earlier_chunks(): void
    {
        $session = (string) Str::uuid();

        // First page load: chunks 0 and 1.
        $this->sendChunk($session, 0);
        $this->sendChunk($session, 1);

        // Visitor navigates. Same visit, same session id, counter restarts.
        $this->sendChunk($session, 0);
        $this->sendChunk($session, 1);

        $recording = SessionRecording::where('session_id', $session)->first();

        $this->assertSame(4, $recording->chunk_count);
        $this->assertCount(
            4,
            $recording->chunkPaths(),
            'Chunks from the second page load overwrote the first page.'
        );
    }

    /**
     * The regression this guards, found in a real replay rendering the word
     * "null" over the page: TrimStrings and ConvertEmptyStringsToNull run
     * globally and rewrite every string in a request. A DOM snapshot is
     * captured data, not user input — the whitespace-only text nodes between
     * HTML elements trimmed to '' and were then stored as null, which rrweb
     * renders literally.
     */
    public function test_captured_dom_text_is_stored_exactly_as_sent(): void
    {
        $session = (string) Str::uuid();

        $this->postJson('/api/t/rec', [
            'site_id' => $this->site->public_id,
            'session_id' => $session,
            'visitor_uuid' => (string) Str::uuid(),
            'seq' => 0,
            'events' => [[
                'type' => 2,
                'timestamp' => 1,
                'data' => ['node' => ['type' => 0, 'childNodes' => [
                    ['type' => 3, 'textContent' => "\n    ", 'id' => 5],
                    ['type' => 3, 'textContent' => '  Ship experiments  ', 'id' => 6],
                    ['type' => 3, 'textContent' => '', 'id' => 7],
                ]]],
            ]],
        ])->assertStatus(202);

        $recording = SessionRecording::where('session_id', $session)->first();
        $stored = json_decode(
            gzdecode(Storage::disk('local')->get($recording->chunkPaths()[0])),
            true
        );

        $nodes = $stored[0]['data']['node']['childNodes'];

        $this->assertSame("\n    ", $nodes[0]['textContent'], 'Whitespace node was trimmed away.');
        $this->assertSame('  Ship experiments  ', $nodes[1]['textContent'], 'Text lost its padding.');
        $this->assertSame('', $nodes[2]['textContent'], 'Empty text node became null.');
    }
}
