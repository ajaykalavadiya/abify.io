<?php

namespace App\Support\Tracking;

use App\Models\Site;

/**
 * Builds the snippet customers paste on their pages.
 *
 * Shape follows the familiar "smart code" convention: a short block with the
 * editable settings at the top (site id, tolerance, which element to hide),
 * a do-not-edit divider, and a minimal bootstrap below it. The bootstrap
 * itself does only three things — hide the page, load the site's single
 * script, and guarantee the page is revealed again (on timeout or on load
 * error) — everything else lives in that one generated file.
 */
class EmbedSnippetBuilder
{
    public function build(Site $site): string
    {
        $siteId = $site->public_id;
        $timeoutMs = (int) ($site->settings['timeout_ms'] ?? 2000);
        $scriptBase = url('/t');

        return <<<HTML
        <!-- Start abify.io code -->
        <script type="text/javascript" id="abifyCode">
        window._abify_code || (function () {
            var site_id = '{$siteId}',
                settings_tolerance = {$timeoutMs},
                hide_element = 'body',
                hide_element_style = 'opacity:0 !important;filter:alpha(opacity=0) !important;background:none !important;';
            /* DO NOT EDIT BELOW THIS LINE */
            var w = window, d = document;
            w._abify_code = { site_id: site_id, hide_element: hide_element };

            var style = d.createElement('style');
            style.id = '_abify_hide';
            style.textContent = hide_element + '{' + hide_element_style + '}';
            (d.head || d.documentElement).appendChild(style);

            var reveal = function () {
                var el = d.getElementById('_abify_hide');
                if (el && el.parentNode) el.parentNode.removeChild(el);
            };
            w.__abReveal = reveal;
            setTimeout(reveal, settings_tolerance);

            var s = d.createElement('script');
            s.src = '{$scriptBase}/' + site_id + '.js';
            s.async = true;
            s.onerror = reveal;
            (d.head || d.documentElement).appendChild(s);
        })();
        </script>
        <!-- End abify.io code -->
        HTML;
    }
}
