// Bayesian beta-binomial helpers for A/B test results: each variation's
// conversion rate is modeled as Beta(conversions + 1, visitors - conversions + 1)
// (a uniform Beta(1,1) prior updated by the observed data). This lets us plot a
// probability-density curve per variation and estimate "probability to beat
// baseline" / "probability to be best" by Monte Carlo sampling — no fabricated
// numbers, just the standard closed-form + simulation approach.

/** Lanczos approximation of ln(Gamma(x)) for x > 0. */
function logGamma(x: number): number {
    const g = 7;
    const coefficients = [
        0.99999999999980993, 676.5203681218851, -1259.1392167224028, 771.32342877765313,
        -176.61502916214059, 12.507343278686905, -0.13857109526572012, 9.9843695780195716e-6,
        1.5056327351493116e-7,
    ];

    if (x < 0.5) {
        return Math.log(Math.PI / Math.sin(Math.PI * x)) - logGamma(1 - x);
    }

    x -= 1;
    let a = coefficients[0];
    const t = x + g + 0.5;
    for (let i = 1; i < g + 2; i++) {
        a += coefficients[i] / (x + i);
    }

    return 0.5 * Math.log(2 * Math.PI) + (x + 0.5) * Math.log(t) - t + Math.log(a);
}

function logBeta(a: number, b: number): number {
    return logGamma(a) + logGamma(b) - logGamma(a + b);
}

/** Probability density of Beta(a, b) at x in [0, 1]. */
export function betaPdf(x: number, a: number, b: number): number {
    if (x <= 0 || x >= 1) return 0;

    return Math.exp((a - 1) * Math.log(x) + (b - 1) * Math.log(1 - x) - logBeta(a, b));
}

/** Marsaglia–Tsang sample from Gamma(shape, 1). */
function sampleGamma(shape: number): number {
    if (shape < 1) {
        const u = Math.random();
        return sampleGamma(shape + 1) * Math.pow(u, 1 / shape);
    }

    const d = shape - 1 / 3;
    const c = 1 / Math.sqrt(9 * d);

    for (;;) {
        let x: number;
        let v: number;
        do {
            // Box–Muller for a standard normal sample.
            const u1 = Math.random() || 1e-12;
            const u2 = Math.random();
            x = Math.sqrt(-2 * Math.log(u1)) * Math.cos(2 * Math.PI * u2);
            v = 1 + c * x;
        } while (v <= 0);

        v = v * v * v;
        const u = Math.random();

        if (u < 1 - 0.0331 * x * x * x * x) return d * v;
        if (Math.log(u) < 0.5 * x * x + d * (1 - v + Math.log(v))) return d * v;
    }
}

function sampleBeta(a: number, b: number): number {
    const x = sampleGamma(a);
    const y = sampleGamma(b);

    return x / (x + y);
}

export type Posterior = { alpha: number; beta: number };

/**
 * For each variation, estimate P(this variation's true rate beats the
 * baseline's) and P(this variation is the single best of the set), by
 * drawing `samples` joint samples from every variation's posterior and
 * tallying wins.
 */
export function simulateWinProbabilities(
    posteriors: Posterior[],
    baselineIndex: number,
    samples = 8000,
): { probabilityToBeatBaseline: number[]; probabilityToBeBest: number[] } {
    const beatCounts = new Array(posteriors.length).fill(0);
    const bestCounts = new Array(posteriors.length).fill(0);

    for (let s = 0; s < samples; s++) {
        const draws = posteriors.map((p) => sampleBeta(p.alpha, p.beta));

        let bestIndex = 0;
        for (let i = 1; i < draws.length; i++) {
            if (draws[i] > draws[bestIndex]) bestIndex = i;
        }
        bestCounts[bestIndex]++;

        for (let i = 0; i < draws.length; i++) {
            if (i !== baselineIndex && draws[i] > draws[baselineIndex]) beatCounts[i]++;
        }
    }

    return {
        probabilityToBeatBaseline: beatCounts.map((c) => c / samples),
        probabilityToBeBest: bestCounts.map((c) => c / samples),
    };
}
