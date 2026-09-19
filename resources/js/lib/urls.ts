// Plain URL builders for the account-scoped dashboard routes.
//
// The Wayfinder-generated helpers under resources/js/routes/** assume every
// route-model-bound segment is a numeric id, but {account} binds against
// Company::uid (a string) — see App\Http\Middleware\ResolveAccountFromRoute.
// Rather than fight the generator's inferred types, these routes are built
// as plain strings.

export const accountsIndexUrl = () => "/accounts";
export const accountsCreateUrl = () => "/accounts/create";
export const accountShowUrl = (account: string) => `/accounts/${account}`;

export const selectAccountUrl = () => "/select-account";

export const adminUsersUrl = () => "/admin/users";
export const adminImpersonateUrl = (user: number) =>
    `/admin/users/${user}/impersonate`;
export const adminStopImpersonatingUrl = () => "/admin/impersonate/stop";

export const invitationShowUrl = (token: string) => `/invitations/${token}`;
export const invitationAcceptUrl = (token: string) =>
    `/invitations/${token}/accept`;

export const accountUsersUrl = (account: string) => `/a/${account}/users`;
export const accountUserToggleSuperAdminUrl = (account: string, user: number) =>
    `/a/${account}/users/${user}/super-admin`;

export const billingUrl = (account: string) => `/a/${account}/billing`;
export const billingCheckoutUrl = (
    account: string,
    plan: "monthly" | "yearly",
) => `/a/${account}/billing/checkout/${plan}`;
export const billingPortalUrl = (account: string) =>
    `/a/${account}/billing/portal`;

export const sitesIndexUrl = (account: string) => `/a/${account}/sites`;
export const sitesCreateUrl = (account: string) => `/a/${account}/sites/create`;
export const activeSiteUrl = (account: string) => `/a/${account}/active-site`;

// Everything below acts on the user's active site (users.current_site_id),
// so none of these carry a site id — see routes/dashboard.php.
export const siteOverviewUrl = (account: string, range?: string) =>
    `/a/${account}/overview${range ? `?range=${range}` : ""}`;

export const siteRecordingsUrl = (
    account: string,
    params?: { filter?: string; variation?: number; page?: number },
) => {
    const query = new URLSearchParams();

    if (params?.filter && params.filter !== "all")
        query.set("filter", params.filter);
    if (params?.variation) query.set("variation", String(params.variation));
    if (params?.page && params.page > 1) query.set("page", String(params.page));

    const search = query.toString();

    return `/a/${account}/recordings${search ? `?${search}` : ""}`;
};

export const shopifyIntegrationUrl = (account: string) =>
    `/a/${account}/integrations/shopify`;

export const recordingShowUrl = (account: string, recording: number) =>
    `/a/${account}/recordings/${recording}`;

export const recordingEventsUrl = (account: string, recording: number) =>
    `/a/${account}/recordings/${recording}/events`;

export const siteHeatmapUrl = (
    account: string,
    params?: {
        url?: string;
        device?: string;
        type?: string;
        range?: string;
        from?: string;
        to?: string;
        variation?: number;
    },
) => {
    const query = new URLSearchParams();

    if (params?.url) query.set("url", params.url);
    if (params?.device) query.set("device", params.device);
    if (params?.type) query.set("type", params.type);

    // Carried alongside the rest so switching page or device keeps the
    // reporting window the reader chose.
    if (params?.range) query.set("range", params.range);
    if (params?.from) query.set("from", params.from);
    if (params?.to) query.set("to", params.to);

    // Which variation's visitors the map is drawn from.
    if (params?.variation) query.set("variation", String(params.variation));

    const search = query.toString();

    return `/a/${account}/heatmap${search ? `?${search}` : ""}`;
};

export const heatmapScreenshotStoreUrl = (account: string) =>
    `/a/${account}/heatmap/screenshots`;

export const experimentsIndexUrl = (account: string) => `/a/${account}/tests`;
export const experimentsAbIndexUrl = (account: string) =>
    `/a/${account}/tests/ab`;
export const experimentsSplitUrlIndexUrl = (account: string) =>
    `/a/${account}/tests/split-url`;
export const experimentsMvtIndexUrl = (account: string) =>
    `/a/${account}/tests/mvt`;
// A/B and split URL tests are created through separate forms.
export const experimentsAbCreateUrl = (account: string) =>
    `/a/${account}/tests/ab/create`;
export const experimentsAbStoreUrl = (account: string) =>
    `/a/${account}/tests/ab`;
export const experimentsSplitUrlCreateUrl = (account: string) =>
    `/a/${account}/tests/split-url/create`;
export const experimentsSplitUrlStoreUrl = (account: string) =>
    `/a/${account}/tests/split-url`;
export const experimentsMvtCreateUrl = (account: string) =>
    `/a/${account}/tests/mvt/create`;
export const experimentsMvtStoreUrl = (account: string) =>
    `/a/${account}/tests/mvt`;
export const experimentShowUrl = (account: string, test: number) =>
    `/a/${account}/tests/${test}`;
export const experimentEditUrl = (account: string, test: number) =>
    `/a/${account}/tests/${test}/edit`;
export const experimentUpdateUrl = (account: string, test: number) =>
    `/a/${account}/tests/${test}`;
export const experimentStartUrl = (account: string, test: number) =>
    `/a/${account}/tests/${test}/start`;
export const experimentPauseUrl = (account: string, test: number) =>
    `/a/${account}/tests/${test}/pause`;
export const experimentAnalyticsUrl = (account: string, test: number) =>
    `/a/${account}/tests/${test}/analytics`;

export const variationsUrl = (account: string, test: number) =>
    `/a/${account}/tests/${test}/variations`;
export const variationUrl = (
    account: string,
    test: number,
    variation: number,
) => `/a/${account}/tests/${test}/variations/${variation}`;

export const goalsUrl = (account: string, test: number) =>
    `/a/${account}/tests/${test}/goals`;
export const goalUrl = (account: string, test: number, goal: number) =>
    `/a/${account}/tests/${test}/goals/${goal}`;
