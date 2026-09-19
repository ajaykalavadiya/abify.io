import { useEffect, useRef, useState, type ReactNode } from 'react';

export type StageSize = { width: number; height: number };

/**
 * The page image a heatmap is drawn on, and the measured box its overlays need.
 *
 * Heatmap coordinates are relative to the whole document, so an overlay only
 * lands in the right place if it covers exactly the same box as the page
 * image — hence the measurement rather than a CSS aspect-ratio guess. The
 * measured size is handed to children so the canvas can size its backing
 * store to real pixels.
 */
export function HeatmapStage({
    src,
    /** Captured page size in CSS pixels; falls back to the visitors' own document size. */
    aspectWidth,
    aspectHeight,
    alt,
    children,
}: {
    src: string | null;
    aspectWidth: number;
    aspectHeight: number;
    alt: string;
    children: (size: StageSize) => ReactNode;
}) {
    const ref = useRef<HTMLDivElement>(null);
    const [size, setSize] = useState<StageSize>({ width: 0, height: 0 });

    useEffect(() => {
        const element = ref.current;

        if (!element) {
            return;
        }

        const observer = new ResizeObserver(([entry]) => {
            const width = entry.contentRect.width;

            setSize({
                width,
                height: (width * aspectHeight) / Math.max(aspectWidth, 1),
            });
        });

        observer.observe(element);

        return () => observer.disconnect();
    }, [aspectWidth, aspectHeight]);

    return (
        <div
            ref={ref}
            className="border-border bg-muted relative mx-auto w-full overflow-hidden border"
            style={{
                aspectRatio: `${aspectWidth} / ${aspectHeight}`,
                // Never scale a capture past its own CSS width: a 390px mobile
                // page stretched across a 1000px card is unreadable, and shows
                // a layout at a size no visitor ever saw. Wider captures still
                // scale down to fit.
                maxWidth: `${aspectWidth}px`,
            }}
        >
            {src ? (
                <img
                    src={src}
                    alt={alt}
                    className="absolute inset-0 size-full object-cover object-top"
                    // The heatmap is the thing being read; the page behind it
                    // is context, so it is dimmed slightly to let the field
                    // sit on top without fighting it.
                    style={{ opacity: 0.85 }}
                />
            ) : (
                <div
                    className="bg-card absolute inset-0"
                    style={{
                        // A faint grid stands in for the page, so a map with no
                        // capture yet still reads as a page-shaped surface
                        // rather than a floating cloud of colour.
                        backgroundImage:
                            'linear-gradient(to right, var(--border) 1px, transparent 1px), linear-gradient(to bottom, var(--border) 1px, transparent 1px)',
                        backgroundSize: '5% 2.5%',
                    }}
                />
            )}

            {size.width > 0 && children(size)}
        </div>
    );
}
