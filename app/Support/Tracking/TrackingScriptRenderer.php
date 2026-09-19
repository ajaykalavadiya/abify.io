<?php

namespace App\Support\Tracking;

/**
 * Builds the JS payload served at GET /t/{siteId}.js. Plain PHP string
 * templating (no Blade) — this response isn't an app page, it's raw
 * JavaScript consumed by a <script> tag on third-party pages, so Inertia
 * doesn't apply here either.
 */
class TrackingScriptRenderer
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function render(
        array $settings,
        string $assignUrl,
        string $collectUrl,
        string $recordUrl = '',
        string $recorderUrl = '',
    ): string {
        $settingsJson = json_encode($settings);
        $assignUrlJson = json_encode($assignUrl);
        $collectUrlJson = json_encode($collectUrl);
        $recordUrlJson = json_encode($recordUrl);
        $recorderUrlJson = json_encode($recorderUrl);

        return <<<JS
        (function (w, d) {
            var settings = {$settingsJson};
            var assignUrl = {$assignUrlJson};
            var collectUrl = {$collectUrlJson};
            var recordUrl = {$recordUrlJson};
            var recorderUrl = {$recorderUrlJson};

            // --- visitor identity -------------------------------------------------
            function uuid() {
                return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                    var r = (Math.random() * 16) | 0;
                    var v = c === 'x' ? r : (r & 0x3) | 0x8;
                    return v.toString(16);
                });
            }

            function readCookie(name) {
                var match = d.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
                return match ? decodeURIComponent(match[1]) : null;
            }

            function writeCookie(name, value) {
                var expires = new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toUTCString();
                d.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + expires + '; path=/';
            }

            var visitorUuid = readCookie('_ab_uuid');
            if (!visitorUuid) {
                visitorUuid = uuid();
                writeCookie('_ab_uuid', visitorUuid);
            }

            // --- session identity --------------------------------------------------
            // A session is a visit, not a page load. Kept in its own cookie as
            // "<uuid>.<lastSeenMs>" and rolled forward on activity, expiring
            // after 30 minutes idle — the same window GA4 uses, so the two
            // report comparable session counts.
            var SESSION_IDLE_MS = 30 * 60 * 1000;

            function writeSessionCookie(id, lastSeen) {
                var expires = new Date(lastSeen + SESSION_IDLE_MS).toUTCString();
                d.cookie = '_ab_sn=' + encodeURIComponent(id + '.' + lastSeen) +
                    '; expires=' + expires + '; path=/';
            }

            var sessionId;

            (function () {
                var raw = readCookie('_ab_sn');
                var parts = raw ? raw.split('.') : [];
                var now = Date.now();

                if (parts.length === 2 && parts[0] && (now - parseInt(parts[1], 10)) < SESSION_IDLE_MS) {
                    sessionId = parts[0];
                } else {
                    sessionId = uuid();
                }

                writeSessionCookie(sessionId, now);
            })();

            // --- GA4 identity ------------------------------------------------------
            // GA4's own client id, read out of the _ga cookie. Google writes
            // it as "GA1.1.<client_id>" where the id itself is the last two
            // dot-separated segments, and that is the key every GA4 export and
            // the Measurement Protocol are addressed by.
            //
            // Captured so a conversion abify learns about server-side — a
            // Shopify order webhook, hours after the visit — can still be
            // reported into the merchant's GA4 against the right user.
            function gaClientId() {
                var raw = readCookie('_ga');
                if (!raw) return null;

                var parts = raw.split('.');
                return parts.length >= 2 ? parts.slice(-2).join('.') : null;
            }

            function touchSession() {
                writeSessionCookie(sessionId, Date.now());
            }

            // --- deterministic bucketing (mirrors App\\Support\\Tracking\\VariationBucketer) --
            function crc32(str) {
                var table = crc32.table || (crc32.table = (function () {
                    var c, t = [];
                    for (var n = 0; n < 256; n++) {
                        c = n;
                        for (var k = 0; k < 8; k++) {
                            c = c & 1 ? (0xedb88320 ^ (c >>> 1)) : c >>> 1;
                        }
                        t[n] = c;
                    }
                    return t;
                })());

                var crc = 0 ^ -1;
                for (var i = 0; i < str.length; i++) {
                    crc = (crc >>> 8) ^ table[(crc ^ str.charCodeAt(i)) & 0xff];
                }
                return (crc ^ -1) >>> 0;
            }

            // Mirrors VariationBucketer::isEligible — separate salt from the
            // variation bucket below so a test running under 100% traffic
            // still splits its variations evenly.
            function isEligible(experiment) {
                var allocation = experiment.traffic_allocation;
                if (allocation === null || allocation === undefined || allocation >= 100) return true;
                if (allocation <= 0) return false;
                return crc32(visitorUuid + '-' + experiment.id + '-alloc') % 100 < allocation;
            }

            function bucketVariation(experiment) {
                if (!isEligible(experiment)) return null;

                var bucket = crc32(visitorUuid + '-' + experiment.id) % 100;
                var cumulative = 0;
                var variations = experiment.variations.slice().sort(function (a, b) { return a.id - b.id; });

                for (var i = 0; i < variations.length; i++) {
                    cumulative += variations[i].traffic_weight;
                    if (bucket < cumulative) {
                        return variations[i];
                    }
                }
                return null;
            }

            // --- preview mode: ?_ab_preview=1 forces variations via
            // ?_ab_variation_<experimentKey>=<variationKey> and shows a debug
            // panel instead of sending real assignment/analytics traffic --
            var previewParams = new URLSearchParams(w.location.search);
            var isPreview = previewParams.get('_ab_preview') === '1';
            var previewLog = isPreview ? ['Test page: ' + d.location.href] : [];
            var previewApplied = [];

            // Does the current page match the experiment's target URL?
            // Untargeted/legacy experiments (no url_pattern) match every
            // page. A broken regex pattern is treated as no match rather
            // than throwing.
            function matchesPage(experiment) {
                var pattern = experiment.url_pattern;
                if (!pattern) return true;

                var href = d.location.href;

                if (experiment.url_match_type === 'exact') {
                    return href.replace(/\/$/, '') === pattern.replace(/\/$/, '');
                }

                if (experiment.url_match_type === 'regex') {
                    try {
                        return new RegExp(pattern).test(href);
                    } catch (e) {
                        return false;
                    }
                }

                return href.indexOf(pattern) !== -1;
            }

            // Applies a single declarative element-targeted change (text/html
            // swap, or hide) by CSS selector. Mirrors the "Element not
            // loaded: <selector>" language a VWO-style debugger shows when
            // the target isn't found on the page.
            function applyChange(change) {
                var els = d.querySelectorAll(change.selector);

                if (!els.length) {
                    return { selector: change.selector, found: false };
                }

                els.forEach(function (el) {
                    if (change.action === 'text') el.textContent = change.value;
                    else if (change.action === 'html') el.innerHTML = change.value;
                    else if (change.action === 'hide') el.style.display = 'none';
                });

                return { selector: change.selector, found: true, count: els.length };
            }

            // --- analytics integrations --------------------------------------------
            // Announced at the moment a variation is applied, not on a timer:
            // a GA4 hit that arrives before the visitor is known to be in a
            // test cannot be segmented by it afterwards.
            function publishAssignment(experiment, variation) {
                var integrations = settings.integrations || {};

                if (integrations.gtm) {
                    w.dataLayer = w.dataLayer || [];
                    w.dataLayer.push({
                        event: 'abify.experimentViewed',
                        abifyExperiment: experiment.key,
                        abifyExperimentId: experiment.id,
                        abifyVariation: variation.key,
                        abifyVariationId: variation.id
                    });
                }

                // Only ever uses a gtag the page already has. Loading GA4
                // ourselves would give the merchant a second, competing
                // tracker and double every pageview they report.
                if (integrations.ga4 && typeof w.gtag === 'function') {
                    w.gtag('event', 'experiment_viewed', {
                        experiment_id: String(experiment.id),
                        experiment_key: experiment.key,
                        variation_id: String(variation.id),
                        variation_key: variation.key
                    });

                    // User-scoped too, so GA4 can segment a visitor's whole
                    // session by the variation rather than only the one hit.
                    w.gtag('set', 'user_properties', {
                        abify_variation: experiment.key + ':' + variation.key
                    });
                }
            }

            // --- apply assigned variations, then reveal ----------------------------
            var assignments = {};
            var redirecting = false;

            (settings.experiments || []).forEach(function (experiment) {
                var pageMatches = matchesPage(experiment);

                if (isPreview) {
                    previewLog.push('"' + experiment.key + '" — Matching Test Page Pattern: ' + (pageMatches ? 'Success' : 'Failed'));
                }

                if (!pageMatches) return;

                var forcedKey = isPreview ? previewParams.get('_ab_variation_' + experiment.key) : null;
                var forced = forcedKey
                    ? experiment.variations.filter(function (v) { return v.key === forcedKey; })[0]
                    : null;
                var variation = forced || bucketVariation(experiment);

                if (!variation) {
                    // Most often this visitor simply fell outside the
                    // experiment's traffic allocation — worth saying out
                    // loud in preview, otherwise QA reads "nothing applied"
                    // as a broken test.
                    if (isPreview) {
                        previewLog.push(
                            !isEligible(experiment)
                                ? '"' + experiment.key + '": outside traffic allocation (' + experiment.traffic_allocation + '%) for this visitor'
                                : '"' + experiment.key + '": no variation matched this visitor'
                        );
                    }
                    return;
                }

                assignments[experiment.key] = { experimentId: experiment.id, variationId: variation.id };

                var payload = variation.payload || {};
                var applied = [];

                if (!isPreview) {
                    sendBeacon(assignUrl, {
                        site_id: settings.siteId,
                        visitor_uuid: visitorUuid,
                        experiment_id: experiment.id,
                    });

                    publishAssignment(experiment, variation);
                }

                // Split URL: redirect non-control variations to a different
                // page entirely instead of mutating this one. Skip if
                // already on the target (avoids a redirect loop).
                if (experiment.type === 'split_url' && payload.redirect_url) {
                    if (w.location.href.replace(/\/$/, '') !== payload.redirect_url.replace(/\/$/, '')) {
                        redirecting = true;
                        if (isPreview) {
                            previewLog.push('"' + experiment.key + '": split URL — redirecting to ' + payload.redirect_url);
                            previewApplied.push({ experiment: experiment, variation: variation, forced: !!forced });
                        } else {
                            w.location.replace(payload.redirect_url);
                        }
                    }
                    return;
                }

                if (payload.css) {
                    var style = d.createElement('style');
                    style.textContent = payload.css;
                    d.head.appendChild(style);
                    applied.push('css');
                }

                if (payload.js) {
                    try {
                        // eslint-disable-next-line no-new-func
                        new Function(payload.js)();
                        applied.push('js');
                    } catch (e) {
                        if (isPreview) previewLog.push('"' + experiment.key + '": JS error — ' + e.message);
                    }
                }

                (payload.changes || []).forEach(function (change) {
                    var result = applyChange(change);
                    applied.push(change.action + ':' + change.selector);
                    if (isPreview) {
                        previewLog.push(
                            result.found
                                ? 'Element found: ' + result.selector + ' — applied "' + change.action + '"'
                                : 'Element not loaded: ' + result.selector
                        );
                    }
                });

                if (isPreview) {
                    previewLog.push(
                        '"' + experiment.key + '": matched — applying "' + variation.key + '"' +
                        (forced ? ' (forced)' : ' (auto-bucketed)')
                    );
                    previewLog.push('Operation applied: ' + (applied.length ? applied.join(', ') : 'none'));
                    previewApplied.push({ experiment: experiment, variation: variation, forced: !!forced });
                }
            });

            // --- Shopify identity bridge -------------------------------------------
            // Shopify's checkout is sandboxed: none of this script runs at the
            // moment of purchase, and the order webhook arrives hours later on
            // a connection that knows nothing about this browser. Writing the
            // visitor id into cart attributes is the only thing that survives
            // the crossing — Shopify carries attributes through checkout and
            // onto the order as note_attributes.
            function bridgeShopifyIdentity() {
                if (!w.Shopify || isPreview) return;

                // Once per visit. This is a write against the merchant's cart
                // and must not run on every pageview.
                if (readCookie('_ab_shopify') === sessionId) return;

                fetch('/cart/update.js', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        attributes: { '_abify_vid': visitorUuid }
                    })
                }).then(function () {
                    writeCookie('_ab_shopify', sessionId);
                }).catch(function () {
                    // A storefront with no cart route, or a request the theme
                    // blocked. Attribution is lost for this visit; nothing
                    // else about the test is affected.
                });
            }

            bridgeShopifyIdentity();

            if (!redirecting && w.__abReveal) w.__abReveal();

            if (isPreview) {
                var mountPreviewPanel = function () {
                    renderPreviewPanel(previewApplied, previewLog, previewParams);
                };
                if (d.body) {
                    mountPreviewPanel();
                } else {
                    d.addEventListener('DOMContentLoaded', mountPreviewPanel);
                }
            }

            // --- page context ------------------------------------------------------
            // Heatmap coordinates are relative to the whole document, so the
            // dashboard needs the document's own size to lay a screenshot
            // underneath them, and the viewport width to know which device
            // layout the visitor was looking at.
            function pageContext() {
                var doc = d.documentElement;
                var body = d.body;

                return {
                    viewport_width: w.innerWidth || doc.clientWidth || 0,
                    viewport_height: w.innerHeight || doc.clientHeight || 0,
                    doc_width: Math.max(doc.scrollWidth, body ? body.scrollWidth : 0, doc.clientWidth),
                    doc_height: Math.max(doc.scrollHeight, body ? body.scrollHeight : 0, doc.clientHeight)
                };
            }

            // --- event batching / collection ---------------------------------------
            var queue = [];

            function sendBeacon(url, body) {
                var json = JSON.stringify(body);
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(url, new Blob([json], { type: 'application/json' }));
                } else {
                    fetch(url, { method: 'POST', body: json, headers: { 'Content-Type': 'application/json' }, keepalive: true });
                }
            }

            function flush() {
                if (!queue.length) return;

                // Refresh the idle window on real activity only — doing it on
                // a timer would keep an abandoned tab's session alive forever.
                touchSession();

                sendBeacon(collectUrl, {
                    site_id: settings.siteId,
                    visitor_uuid: visitorUuid,
                    session_id: sessionId,
                    ga_client_id: gaClientId(),
                    events: queue.splice(0, queue.length),
                });
            }

            function push(event) {
                // Preview mode is for QA'ing variations — it must never count
                // as real traffic.
                if (isPreview) return;

                queue.push(Object.assign({ occurred_at: new Date().toISOString() }, event));
                if (queue.length >= 20) flush();
            }

            function renderPreviewPanel(applied, log, params) {
                var panel = d.createElement('div');
                panel.style.cssText = 'position:fixed;bottom:0;right:0;width:320px;max-height:70vh;overflow:auto;' +
                    'background:#1f2937;color:#f9fafb;font:12px/1.4 -apple-system,BlinkMacSystemFont,sans-serif;' +
                    'z-index:2147483647;border-top-left-radius:8px;box-shadow:0 0 20px rgba(0,0,0,.35)';

                var header = d.createElement('div');
                header.style.cssText = 'padding:8px 12px;font-weight:600;background:#111827;' +
                    'border-top-left-radius:8px;display:flex;justify-content:space-between;align-items:center';
                header.textContent = 'Preview Mode';

                var close = d.createElement('button');
                close.textContent = '×';
                close.setAttribute('aria-label', 'Close preview panel');
                close.style.cssText = 'background:none;border:none;color:#f9fafb;cursor:pointer;font-size:16px;line-height:1';
                close.onclick = function () { panel.remove(); };
                header.appendChild(close);
                panel.appendChild(header);

                applied.forEach(function (item) {
                    var block = d.createElement('div');
                    block.style.cssText = 'padding:8px 12px;border-top:1px solid #374151';

                    var title = d.createElement('div');
                    title.style.cssText = 'font-weight:600;margin-bottom:4px';
                    title.textContent = item.experiment.name || item.experiment.key;
                    block.appendChild(title);

                    var select = d.createElement('select');
                    select.style.cssText = 'width:100%;margin-bottom:4px;background:#111827;color:#f9fafb;' +
                        'border:1px solid #374151;border-radius:4px;padding:2px 4px';

                    item.experiment.variations.forEach(function (v) {
                        var opt = d.createElement('option');
                        opt.value = v.key;
                        opt.textContent = v.name + (v.key === item.variation.key ? ' (current)' : '');
                        opt.selected = v.key === item.variation.key;
                        select.appendChild(opt);
                    });

                    select.onchange = function () {
                        params.set('_ab_preview', '1');
                        params.set('_ab_variation_' + item.experiment.key, select.value);
                        w.location.search = params.toString();
                    };
                    block.appendChild(select);

                    var status = d.createElement('div');
                    status.style.cssText = 'color:#9ca3af';
                    status.textContent = item.forced ? 'Forced via preview link' : 'Auto-bucketed for this visitor';
                    block.appendChild(status);

                    panel.appendChild(block);
                });

                var logBlock = d.createElement('div');
                logBlock.style.cssText = 'padding:8px 12px;border-top:1px solid #374151;color:#9ca3af;white-space:pre-wrap';
                logBlock.textContent = log.join('\\n');
                panel.appendChild(logBlock);

                (d.body || d.documentElement).appendChild(panel);
            }

            setInterval(flush, 5000);
            d.addEventListener('visibilitychange', function () {
                if (d.visibilityState === 'hidden') flush();
            });
            w.addEventListener('pagehide', flush);

            // pageview
            var context = pageContext();
            push({
                type: 'pageview',
                url: d.location.href,
                referrer: d.referrer || null,
                viewport_width: context.viewport_width,
                viewport_height: context.viewport_height
            });

            // --- public API ---------------------------------------------------------
            // abify('track', 'name', props)         — a custom event
            // abify('goal', goalId, props)          — a conversion, against every
            //                                         experiment this visitor is in.
            //                                         props.revenue / props.currency /
            //                                         props.order_id carry an order.
            //
            // This is the function a GTM tag calls, so its name is part of the
            // product's public contract.
            w.abify = function (action, name, props) {
                if (action === 'track') {
                    push({ type: 'custom', url: d.location.href, name: name, props: props || {} });
                }

                if (action === 'goal') {
                    Object.keys(assignments).forEach(function (key) {
                        push({
                            type: 'goal_conversion',
                            experiment_id: assignments[key].experimentId,
                            goal_id: name,
                            props: props || {},
                        });
                    });

                    // Conversions are worth a round trip immediately. A
                    // purchase is usually the last thing that happens before
                    // the visitor leaves, and waiting for the 5s timer loses
                    // exactly the events that matter most.
                    flush();
                }
            };

            // Deprecated alias kept so snippets written against the old name
            // keep working. Remove once no customer is calling it.
            w.vwo = w.abify;

            // heatmap: clicks
            d.addEventListener('click', function (e) {
                var ctx = pageContext();
                if (!ctx.doc_width || !ctx.doc_height) return;

                push({
                    type: 'heatmap_click',
                    url: d.location.href,
                    x_pct: Math.round((e.pageX / ctx.doc_width) * 10000),
                    y_pct: Math.round((e.pageY / ctx.doc_height) * 10000),
                    viewport_width: ctx.viewport_width,
                    viewport_height: ctx.viewport_height,
                    doc_width: ctx.doc_width,
                    doc_height: ctx.doc_height,
                });
            });

            // heatmap: throttled mouse moves
            var lastMove = 0;
            d.addEventListener('mousemove', function (e) {
                var now = Date.now();
                if (now - lastMove < 500) return;
                lastMove = now;

                var ctx = pageContext();
                if (!ctx.doc_width || !ctx.doc_height) return;

                push({
                    type: 'heatmap_move',
                    url: d.location.href,
                    x_pct: Math.round((e.pageX / ctx.doc_width) * 10000),
                    y_pct: Math.round((e.pageY / ctx.doc_height) * 10000),
                    viewport_width: ctx.viewport_width,
                    viewport_height: ctx.viewport_height,
                    doc_width: ctx.doc_width,
                    doc_height: ctx.doc_height,
                });
            });

            // heatmap: scroll depth
            var maxDepth = 0;
            w.addEventListener('scroll', function () {
                var ctx = pageContext();
                // Depth is measured against the bottom of the visible window,
                // not the top: someone who scrolls a 2000px page by 1000px on
                // an 1000px screen has seen all of it, not half.
                var scrollable = ctx.doc_height - ctx.viewport_height;
                if (scrollable <= 0) return;

                var depth = Math.min(10000, Math.round((w.scrollY / scrollable) * 10000));
                if (depth > maxDepth) {
                    maxDepth = depth;
                    push({
                        type: 'heatmap_scroll',
                        url: d.location.href,
                        scroll_depth_pct: depth,
                        viewport_width: ctx.viewport_width,
                        viewport_height: ctx.viewport_height,
                        doc_width: ctx.doc_width,
                        doc_height: ctx.doc_height,
                    });
                }
            });
            // --- session recording -------------------------------------------------
            // Deliberately the last thing in the file and gated behind load:
            // the recorder is by far the heaviest thing the script can pull
            // in, and the page is already hidden waiting on the variation
            // work above. Fetching it any earlier would trade a conversion
            // test for a slower page, which is the wrong way round.
            function shouldRecord() {
                var config = settings.recording || {};
                if (!config.enabled || isPreview) return false;

                var rate = typeof config.sampleRate === 'number' ? config.sampleRate : 100;
                if (rate <= 0) return false;
                if (rate >= 100) return true;

                // Sampled per visit rather than per page load, so a visitor
                // who is being recorded stays recorded as they navigate.
                return crc32(sessionId + '-rec') % 100 < rate;
            }

            if (shouldRecord() && recorderUrl) {
                var startRecorder = function () {
                    var s = d.createElement('script');
                    s.src = recorderUrl;
                    s.async = true;
                    s.fetchPriority = 'low';
                    s.onload = function () {
                        if (w.__abStartRecorder) {
                            w.__abStartRecorder({
                                url: recordUrl,
                                siteId: settings.siteId,
                                sessionId: sessionId,
                                visitorUuid: visitorUuid
                            });
                        }
                    };
                    (d.head || d.documentElement).appendChild(s);
                };

                if (d.readyState === 'complete') {
                    startRecorder();
                } else {
                    w.addEventListener('load', startRecorder);
                }
            }
        })(window, document);
        JS;
    }
}
