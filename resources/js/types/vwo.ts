export type Company = {
    id: number;
    name: string;
    slug: string;
    uid: string;
    database: string | null;
    tenant_provisioned_at: string | null;
    trial_ends_at?: string | null;
    sites_count?: number;
    active_tests_count?: number;
    created_at: string;
    updated_at: string;
};

export type SiteOption = {
    id: number;
    name: string;
};

export type PlanLimits = {
    label: string;
    sites: number;
    tests_per_site: number;
};

export type Site = {
    id: number;
    company_id: number;
    name: string;
    domain: string | null;
    public_id: string;
    status: "active" | "paused";
    settings: { timeout_ms?: number } | null;
    experiments_count?: number;
    variations_count?: number;
    created_at: string;
    updated_at: string;
};

export type VariationChange = {
    selector: string;
    action: "text" | "html" | "hide";
    value?: string | null;
};

export type VariationPayload = {
    css?: string;
    js?: string;
    changes?: VariationChange[];
    redirect_url?: string;
};

export type ExperimentVariation = {
    id: number;
    experiment_id: number;
    key: string;
    name: string;
    is_control: boolean;
    traffic_weight: number;
    payload: VariationPayload | null;
    created_at: string;
    updated_at: string;
};

export type ExperimentGoal = {
    id: number;
    experiment_id: number;
    /** Stable identifier server-side integrations address, e.g. `purchase`. */
    key: string;
    name: string;
    type: "pageview" | "click" | "custom_event";
    match_type: "exact" | "contains" | "regex" | null;
    target: string | null;
    created_at: string;
    updated_at: string;
};

export type Experiment = {
    id: number;
    company_id: number;
    site_id: number;
    key: string;
    name: string;
    status: "draft" | "running" | "paused" | "completed";
    type: "ab" | "split_url" | "mvt";
    url_match_type: "exact" | "contains" | "regex";
    url_pattern: string | null;
    traffic_allocation: number;
    started_at: string | null;
    ended_at: string | null;
    variations?: ExperimentVariation[];
    goals?: ExperimentGoal[];
    variations_count?: number;
    created_at: string;
    updated_at: string;
};

export type AnalyticsGoalResult = {
    goal: ExperimentGoal;
    /** Total conversion events — for a revenue goal, every order. */
    conversions: number;
    /** Distinct visitors who converted. The binomial count the stats use. */
    converters: number;
    rate: number;
    /** Null when the goal records no money. Single currency per site — see `currencies`. */
    revenue: number | null;
    revenue_per_visitor: number | null;
    average_order_value: number | null;
};

export type AnalyticsVariationResult = {
    variation: ExperimentVariation;
    impressions: number;
    goals: AnalyticsGoalResult[];
};

// --- Heatmap ---------------------------------------------------------------

export type HeatmapDevice = "desktop" | "tablet" | "mobile";

/**
 * Variations available to filter a heatmap by, grouped under the experiment
 * they belong to.
 */
export type HeatmapVariationGroup = {
    id: number;
    name: string;
    variations: { id: number; name: string; is_control: boolean }[];
};

export type HeatmapType = "click" | "move" | "scroll";

export type HeatmapPalette = "thermal" | "mono";

/**
 * A bucketed density cell. x/y are hundredths of a percent of the full
 * document (0–10000), matching what the tracking script records — so a point
 * lands in the same relative place whatever size the page was rendered at.
 */
export type HeatmapPoint = {
    x: number;
    y: number;
    weight: number;
};

export type HeatmapPage = {
    url: string;
    url_hash: string;
    sessions: number;
    clicks: number;
    events: number;
    last_seen: string | null;
    devices: Partial<Record<HeatmapDevice, number>>;
};

export type ScrollBand = {
    /** Depth into the document, in percent. */
    depth: number;
    /** Share of sessions that got at least this far. */
    reach: number;
    sessions: number;
};

export type ScrollDepth = {
    bands: ScrollBand[];
    sessions: number;
    average: number;
    /** Average first screen as a percentage of the document, if known. */
    fold: number | null;
};

export type HeatmapSummary = {
    sessions: number;
    clicks: number;
    moves: number;
    clicks_per_session: number;
    first_seen: string | null;
    last_seen: string | null;
    viewport_width: number | null;
    viewport_height: number | null;
    doc_width: number | null;
    doc_height: number | null;
};

export type DeviceOption = {
    value: HeatmapDevice;
    label: string;
    width: number;
    height: number;
};

export type PageScreenshot = {
    id: number;
    status: "pending" | "ready" | "failed";
    width: number | null;
    height: number | null;
    full_height: number | null;
    truncated: boolean;
    stale: boolean;
    error: string | null;
    captured_at: string | null;
    src: string | null;
};

// --- Site overview ---------------------------------------------------------

export type MetricDelta = {
    value: number;
    previous: number;
    /** Percentage change against the previous period; null with no baseline. */
    change: number | null;
};

export type OverviewTotals = {
    pageviews: MetricDelta;
    visitors: MetricDelta;
    sessions: MetricDelta;
    conversions: MetricDelta;
    pages_per_session: MetricDelta;
};

export type OverviewSeriesPoint = {
    date: string;
    visitors: number;
    pageviews: number;
    sessions: number;
};

export type OverviewDevice = {
    device: HeatmapDevice;
    label: string;
    visitors: number;
    pageviews: number;
    share: number;
};

export type OverviewPage = {
    url: string;
    path: string;
    pageviews: number;
    visitors: number;
};

export type OverviewReferrer = {
    source: string;
    visitors: number;
};

export type OverviewActivity = {
    cells: { day: number; hour: number; pageviews: number }[];
    max: number;
};

export type OverviewExperiment = {
    id: number;
    name: string;
    type: Experiment["type"];
    status: Experiment["status"];
    started_at: string | null;
    visitors: number;
    conversions: number;
    rate: number;
};

export type SiteOverviewMetrics = {
    totals: OverviewTotals;
    series: OverviewSeriesPoint[];
    devices: OverviewDevice[];
    topPages: OverviewPage[];
    topReferrers: OverviewReferrer[];
    activity: OverviewActivity;
    experiments: OverviewExperiment[];
};

export type ReportingRange = {
    /** A preset key ('7d', '30d', '90d') or 'custom'. */
    value: string;
    label: string;
    days: number;
    /** Resolved window bounds as Y-m-d, set whichever way the range was picked. */
    from: string;
    to: string;
    options: { value: string; label: string }[];
};

export type AccountUser = {
    id: number;
    role: "owner" | "member";
    is_super_admin: boolean;
    name: string;
    email: string;
};

export type Invitation = {
    id: number;
    email: string;
    role: "owner" | "member";
    expires_at: string;
    created_at: string;
};
