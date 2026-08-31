<?php

namespace SPTK\Core;

/**
 * Allows a widget to render directly into a pixel-bounded surface.
 */
interface PixelSurfaceElement {

  public function renderPixelSurface(SurfaceRenderTarget $target, Rect $pixelFrame): void;

}
