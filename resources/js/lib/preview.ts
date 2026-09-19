import type { Experiment, Site } from '@/types';

/**
 * The page a test actually runs on: the site's own origin joined with the
 * URI the test targets — so previewing test "/pricing" opens
 * https://yoursite.com/pricing, not the site's homepage.
 *
 * The URI is taken from the test's url_pattern, which may be written as a
 * full URL or as a path. A regex pattern isn't a literal URI, so those fall
 * back to the site root rather than producing a nonsense URL.
 */
function targetUrl(site: Site, experiment: Experiment): URL | null {
    if (!site.domain) return null;

    const origin = site.domain.startsWith('http') ? site.domain : `https://${site.domain}`;

    let url: URL;
    try {
        url = new URL(origin);
    } catch {
        return null;
    }

    const pattern = experiment.url_pattern?.trim();

    if (pattern && experiment.url_match_type !== 'regex') {
        if (/^https?:\/\//i.test(pattern)) {
            try {
                const patternUrl = new URL(pattern);
                url.pathname = patternUrl.pathname;
                url.search = patternUrl.search;
            } catch {
                // Malformed pattern — keep the site root.
            }
        } else {
            const [path, query] = pattern.split('?');
            url.pathname = path.startsWith('/') ? path : `/${path}`;
            if (query) url.search = query;
        }
    }

    return url;
}

/** The test's page with the tracking script's preview mode switched on. */
export function experimentPreviewUrl(site: Site, experiment: Experiment): string | null {
    const url = targetUrl(site, experiment);
    if (!url) return null;

    url.searchParams.set('_ab_preview', '1');

    return url.toString();
}

/** As above, but forcing one specific variation. */
export function variationPreviewUrl(
    site: Site,
    experiment: Experiment,
    variationKey: string,
): string | null {
    const url = targetUrl(site, experiment);
    if (!url) return null;

    url.searchParams.set('_ab_preview', '1');
    url.searchParams.set(`_ab_variation_${experiment.key}`, variationKey);

    return url.toString();
}
