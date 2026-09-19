import type { ScrollBand } from '@/types';

/**
 * The share of visitors still on the page at each depth, laid over the
 * screenshot.
 *
 * Reach only ever falls as you go down, so this is an ordered scale, not a
 * categorical one: one hue, deepening as the audience thins, plus a labelled
 * ruler and the fold line. The percentages are printed on the ruler rather
 * than left to the colour — the wash is there to draw the eye to where the
 * audience drops off, not to be measured.
 */
export function ScrollMap({
    bands,
    fold,
    opacity,
}: {
    bands: ScrollBand[];
    /** Average first-screen height as a percentage of the document. */
    fold: number | null;
    opacity: number;
}) {
    if (bands.length === 0) {
        return null;
    }

    const step = bands.length > 1 ? bands[1].depth - bands[0].depth : 100;

    // Milestones worth a label, in reach terms — where half and a quarter of
    // the audience is left is the number people act on.
    const milestones = [75, 50, 25].map((target) => ({
        target,
        band: bands.find((band) => band.reach <= target),
    }));

    return (
        <>
            <div className="pointer-events-none absolute inset-0" aria-hidden>
                {bands.map((band) => (
                    <div
                        key={band.depth}
                        className="absolute inset-x-0"
                        style={{
                            top: `${band.depth}%`,
                            height: `${step}%`,
                            // Deepening blue as the audience thins: 100% reach
                            // is almost clear, the tail is nearly solid.
                            backgroundColor: `rgba(13, 54, 107, ${((100 - band.reach) / 100) * opacity})`,
                        }}
                    />
                ))}

                {fold !== null && (
                    <div
                        className="absolute inset-x-0"
                        style={{ top: `${fold}%` }}
                    >
                        <div className="h-px w-full bg-white/90 shadow-[0_0_0_1px_rgba(0,0,0,0.45)]" />
                        <span className="absolute top-1 left-2 bg-black/70 px-1.5 py-0.5 text-[10px] font-medium text-white">
                            Average fold — {fold.toFixed(0)}% down the page
                        </span>
                    </div>
                )}

                {milestones.map(({ target, band }) =>
                    band ? (
                        <div
                            key={target}
                            className="absolute inset-x-0"
                            style={{ top: `${band.depth}%` }}
                        >
                            <div className="h-px w-full bg-white/60" />
                            <span className="absolute top-1 right-2 bg-black/70 px-1.5 py-0.5 text-[10px] font-medium text-white tabular-nums">
                                {band.reach.toFixed(0)}% still here
                            </span>
                        </div>
                    ) : null,
                )}
            </div>
        </>
    );
}
