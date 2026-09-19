import { record } from 'rrweb';
import type { eventWithTime } from '@rrweb/types';

/**
 * Session replay recorder, served at /t/recorder.js and loaded by the
 * tracking script only after the page has finished loading.
 *
 * Kept as its own bundle on purpose: rrweb is by far the heaviest thing the
 * tracking script can pull in, and the snippet hides the page while it
 * applies variations. Putting this in the critical path would trade a
 * conversion test for a slower page, which is the wrong way round.
 */

type StartOptions = {
    url: string;
    siteId: string;
    sessionId: string;
    visitorUuid: string;
};

/** Flush cadence. Short enough that a visitor who closes the tab loses little. */
const FLUSH_INTERVAL_MS = 5000;

/** Force a flush before the buffer grows large enough to be worth losing. */
const MAX_BUFFERED_EVENTS = 100;

/** Clicks within this window, on this radius, read as one frustrated burst. */
const RAGE_WINDOW_MS = 1000;
const RAGE_RADIUS_PX = 30;
const RAGE_CLICK_COUNT = 3;

declare global {
    interface Window {
        __abStartRecorder?: (options: StartOptions) => void;
        __abRecorderStarted?: boolean;
    }
}

function startRecorder(options: StartOptions): void {
    // The tracking script can be evaluated more than once on sites that do
    // their own SPA routing; recording twice would double every visitor's
    // payload and their bill with it.
    if (window.__abRecorderStarted) return;
    window.__abRecorderStarted = true;

    let buffer: eventWithTime[] = [];
    let sequence = 0;
    let stopped = false;
    let sawRageClick = false;
    let sawError = false;
    const startedAt = Date.now();

    let stopRecording: (() => void) | undefined;

    const stop = () => {
        if (stopped) return;
        stopped = true;

        window.clearInterval(timer);
        stopRecording?.();
    };

    const flush = (useBeacon = false) => {
        if (stopped || buffer.length === 0) return;

        const payload = JSON.stringify({
            site_id: options.siteId,
            session_id: options.sessionId,
            visitor_uuid: options.visitorUuid,
            seq: sequence++,
            events: buffer,
            rage_click: sawRageClick,
            error: sawError,
            duration_ms: Date.now() - startedAt,
        });

        buffer = [];
        // Signals describe the chunk that carried them; the server keeps them
        // sticky for the recording as a whole.
        sawRageClick = false;
        sawError = false;

        // On unload only sendBeacon is guaranteed to survive the page going
        // away — but it cannot read a response, so the server's "stop
        // recording" reply is only honoured on normal flushes.
        if (useBeacon && navigator.sendBeacon) {
            navigator.sendBeacon(
                options.url,
                new Blob([payload], { type: 'application/json' }),
            );
            return;
        }

        void fetch(options.url, {
            method: 'POST',
            body: payload,
            headers: { 'Content-Type': 'application/json' },
            keepalive: true,
        })
            .then((response) => response.json())
            .then((result: { recording?: boolean }) => {
                // The server owns the decision. This is how the daily quota,
                // and the maximum-duration cut-off, actually reach the page.
                if (result && result.recording === false) stop();
            })
            .catch(() => {
                // A failed chunk is a gap in a replay, not something worth
                // surfacing on a customer's site.
            });
    };

    // --- frustration signals ------------------------------------------------
    // Computed here rather than derived later: they are what makes a pile of
    // recordings triageable, and recomputing them would mean replaying every
    // session server-side to find the few worth watching.
    let recentClicks: { x: number; y: number; at: number }[] = [];

    document.addEventListener(
        'click',
        (event) => {
            const now = Date.now();

            recentClicks = recentClicks.filter(
                (click) => now - click.at < RAGE_WINDOW_MS,
            );
            recentClicks.push({ x: event.clientX, y: event.clientY, at: now });

            const clustered = recentClicks.filter(
                (click) =>
                    Math.abs(click.x - event.clientX) < RAGE_RADIUS_PX &&
                    Math.abs(click.y - event.clientY) < RAGE_RADIUS_PX,
            );

            if (clustered.length >= RAGE_CLICK_COUNT) {
                sawRageClick = true;
                recentClicks = [];
            }
        },
        { capture: true, passive: true },
    );

    window.addEventListener('error', () => {
        sawError = true;
    });
    window.addEventListener('unhandledrejection', () => {
        sawError = true;
    });

    // --- capture ------------------------------------------------------------
    stopRecording = record({
        emit(event) {
            if (stopped) return;

            buffer.push(event);

            if (buffer.length >= MAX_BUFFERED_EVENTS) flush();
        },

        // Masking is the default, not an opt-in. Recordings routinely cross
        // checkout and account pages, and the cost of leaking a customer's
        // address or card details is not comparable to the cost of a slightly
        // less readable replay.
        maskAllInputs: true,
        maskInputOptions: {
            password: true,
            email: true,
            tel: true,
            text: true,
        },
        // Honours the conventional opt-out classes so a merchant can exclude
        // anything else by marking it up.
        blockClass: 'ab-block',
        ignoreClass: 'ab-ignore',
        maskTextClass: 'ab-mask',

        // Cross-origin stylesheets cannot be read back, so without this a
        // replay of a site using a CDN for its CSS renders unstyled.
        inlineStylesheet: true,

        // A visit that idles still produces a full snapshot periodically, so
        // a replay joined late is not a blank screen.
        checkoutEveryNms: 2 * 60 * 1000,
    });

    const timer = window.setInterval(() => flush(), FLUSH_INTERVAL_MS);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') flush(true);
    });
    window.addEventListener('pagehide', () => flush(true));
}

// The tracking script calls this from the script tag's own onload handler, so
// the bundle is always evaluated before the start signal arrives.
window.__abStartRecorder = startRecorder;
