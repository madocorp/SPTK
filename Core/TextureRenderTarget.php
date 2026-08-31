<?php

namespace SPTK\Core;

/**
 * Optional render target extension for custom texture-backed pixel drawing.
 */
interface TextureRenderTarget extends RenderTarget {

  public function createTexture(int $width, int $height, Color|string|int $background = 'transparent'): Texture;

  public function textureForPixels(Rect $pixelRect): Texture;

}
