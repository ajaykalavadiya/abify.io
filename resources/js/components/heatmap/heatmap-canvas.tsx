import { useEffect, useRef } from 'react';
import type { HeatmapPalette, HeatmapPoint } from '@/types';

/**
 * Colour ramps for the density field.
 *
 * 'thermal' is the multi-hue ramp every heatmap tool uses. A single hue is the
 * rule for magnitude, and 'mono' honours it — but this ramp is painted over an
 * arbitrary screenshot, and a blue wash on a blue page is invisible. The
 * thermal ramp survives whatever is underneath it, which is why it is the
 * default here and why both are offered.
 */
const RAMPS: Record<HeatmapPalette, [number, string][]> = {
    thermal: [
        [0.0, 'rgba(43,113,190,0)'],
        [0.25, 'rgba(43,113,190,0.75)'],
        [0.45, 'rgba(27,175,122,0.8)'],
        [0.65, 'rgba(237,161,0,0.85)'],
        [0.85, 'rgba(235,104,52,0.9)'],
        [1.0, 'rgba(200,30,30,0.95)'],
    ],
    mono: [
        [0.0, 'rgba(205,226,251,0)'],
        [0.3, 'rgba(134,182,239,0.7)'],
        [0.6, 'rgba(42,120,214,0.85)'],
        [1.0, 'rgba(13,54,107,0.95)'],
    ],
};

/** Build a 256-entry lookup table from the ramp, once per palette. */
function rampLookup(palette: HeatmapPalette): Uint8ClampedArray {
    const canvas = document.createElement('canvas');
    canvas.width = 256;
    canvas.height = 1;

    const context = canvas.getContext('2d');

    if (!context) {
        return new Uint8ClampedArray(256 * 4);
    }

    const gradient = context.createLinearGradient(0, 0, 256, 0);

    for (const [stop, color] of RAMPS[palette]) {
        gradient.addColorStop(stop, color);
    }

    context.fillStyle = gradient;
    context.fillRect(0, 0, 256, 1);

    return context.getImageData(0, 0, 256, 1).data;
}

/**
 * Renders click or move density as a blurred field over the page screenshot.
 *
 * The two-pass technique every heatmap uses: stamp each point as a greyscale
 * radial gradient so overlapping points accumulate, then remap the resulting
 * alpha channel through a colour ramp. Doing it in one pass — drawing coloured
 * blobs directly — makes overlapping points darken rather than intensify, and
 * the map stops meaning anything where the data is densest.
 */
export function HeatmapCanvas({
    points,
    width,
    height,
    radius,
    intensity,
    palette,
}: {
    /** Bucketed points; x and y are hundredths of a percent of the document. */
    points: HeatmapPoint[];
    /** Rendered size of the page image, in CSS pixels. */
    width: number;
    height: number;
    /** Blob radius in CSS pixels. */
    radius: number;
    /** 0–1 multiplier on every point's weight. */
    intensity: number;
    palette: HeatmapPalette;
}) {
    const canvasRef = useRef<HTMLCanvasElement>(null);

    useEffect(() => {
        const canvas = canvasRef.current;

        if (!canvas || width <= 0 || height <= 0) {
            return;
        }

        // Capped at 2x: a full-page heatmap can be 12000px tall, and a 3x
        // backing store of that is tens of millions of pixels for detail
        // nobody can see in a blurred field.
        const scale = Math.min(window.devicePixelRatio || 1, 2);

        canvas.width = Math.round(width * scale);
        canvas.height = Math.round(height * scale);

        const context = canvas.getContext('2d', { willReadFrequently: true });

        if (!context) {
            return;
        }

        context.setTransform(scale, 0, 0, scale, 0, 0);
        context.clearRect(0, 0, width, height);

        if (points.length === 0) {
            return;
        }

        const maxWeight = Math.max(...points.map((point) => point.weight));

        // Pass one: greyscale accumulation.
        for (const point of points) {
            // Points arrive as bucket floors; nudge to the bucket's centre so
            // the field isn't biased up and to the left.
            const x = ((point.x + 25) / 10000) * width;
            const y = ((point.y + 10) / 10000) * height;

            const alpha = Math.min(1, (point.weight / maxWeight) * intensity);

            const gradient = context.createRadialGradient(
                x,
                y,
                0,
                x,
                y,
                radius,
            );
            gradient.addColorStop(0, `rgba(0,0,0,${alpha})`);
            gradient.addColorStop(1, 'rgba(0,0,0,0)');

            context.fillStyle = gradient;
            context.fillRect(x - radius, y - radius, radius * 2, radius * 2);
        }

        // Pass two: alpha → colour.
        const image = context.getImageData(0, 0, canvas.width, canvas.height);
        const pixels = image.data;
        const ramp = rampLookup(palette);

        for (let i = 0; i < pixels.length; i += 4) {
            const value = pixels[i + 3];

            if (value === 0) {
                continue;
            }

            const offset = value * 4;

            pixels[i] = ramp[offset];
            pixels[i + 1] = ramp[offset + 1];
            pixels[i + 2] = ramp[offset + 2];
            pixels[i + 3] = ramp[offset + 3];
        }

        context.putImageData(image, 0, 0);
    }, [points, width, height, radius, intensity, palette]);

    return (
        <canvas
            ref={canvasRef}
            className="pointer-events-none absolute inset-0 size-full"
            aria-hidden
        />
    );
}
