const COLUMNS = 22;
const ROWS = 13;
const CELLS = COLUMNS * ROWS;

/**
 * Deterministic pseudo-random in [0,1). Math.sin is stable for a given
 * input, so the server and the client agree — important because this
 * renders under SSR, where Math.random() would cause a hydration mismatch.
 */
function noise(n: number): number {
    const x = Math.sin(n * 12.9898) * 43758.5453;

    return x - Math.floor(x);
}

/**
 * The hero's backdrop: a light blue band carrying a grid that stands in for
 * incoming traffic, with cells lighting up as they're bucketed into
 * variation A (blue) or B (violet).
 *
 * The tint is full-bleed and unmasked so the hero reads as its own band
 * against the white header above it; only the grid fades at the edges.
 * Kept faint — it sits behind the headline, so it has to read as texture
 * rather than content.
 */
export function HeroBackground() {
    return (
        <div
            aria-hidden
            className="pointer-events-none absolute inset-0 -z-10 overflow-hidden select-none"
        >
            {/* The band itself — this is what separates the hero from the header. */}
            <div className="from-primary/[0.09] via-primary/[0.05] absolute inset-0 bg-gradient-to-br to-violet-500/[0.08]" />

            {/* Faded at the edges so the grid doesn't read as a boxed-in panel. */}
            <div
                className="absolute inset-0 [mask-image:radial-gradient(ellipse_at_center,black_30%,transparent_75%)]"
                style={{
                    display: 'grid',
                    gridTemplateColumns: `repeat(${COLUMNS}, minmax(0, 1fr))`,
                    gridTemplateRows: `repeat(${ROWS}, minmax(0, 1fr))`,
                }}
            >
                {Array.from({ length: CELLS }, (_, index) => {
                    const lit = noise(index + 1) > 0.86;
                    const isVariantB = noise((index + 1) * 7) > 0.5;

                    return (
                        <div key={index} className="border-primary/[0.07] relative border-r border-b">
                            {lit && (
                                <div
                                    className={`animate-ab-bucket absolute inset-0 ${
                                        isVariantB ? 'bg-violet-500/[0.09]' : 'bg-primary/[0.09]'
                                    }`}
                                    style={{
                                        animationDelay: `${(noise((index + 1) * 13) * 11).toFixed(2)}s`,
                                    }}
                                />
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
