<?php

namespace App\Support\Screenshots;

/**
 * The result of one full-page capture: the JPEG bytes plus the CSS-pixel
 * geometry they were taken at.
 *
 * The geometry matters as much as the image. Heatmap coordinates are stored
 * as permille-of-document (see the tracking script), so the dashboard needs
 * the document's own width/height to place a point, and needs to know whether
 * the image was truncated at the height ceiling to say so honestly.
 */
class CapturedPage
{
    public function __construct(
        public string $contents,
        public int $width,
        public int $height,
        public int $fullHeight,
        public int $viewportHeight,
    ) {}

    public function wasTruncated(): bool
    {
        return $this->fullHeight > $this->height;
    }
}
