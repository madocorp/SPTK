<?php

namespace SPTK\Widgets;

use SPTK\Core\Element;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;
use SPTK\Core\SurfaceRenderTarget;
use SPTK\Core\TextureRenderTarget;

/**
 * Drawing area for custom texture-backed pixel graphics rendering.
 */
class GraphicsView extends Element {

  protected $painter = null;

  public function __construct(string $name = '', ?callable $painter = null) {
    parent::__construct($name);
    $this->setPreferredRows(12);
    $this->setPreferredColumns(40);
    $this->painter = $painter;
  }

  public function setPainter(?callable $painter): static {
    $this->painter = $painter;
    $this->invalidateRender();
    return $this;
  }

  public function redraw(): static {
    $this->invalidateRender();
    return $this;
  }

  protected function paint(RenderTarget $target): void {
    $target->fill($this->frame, ' ', $this->theme->fg, $this->theme->bg);
    if (!$target instanceof TextureRenderTarget || $this->painter === null) {
      return;
    }
    $pixelRect = $this->drawingPixelRect($target);
    if ($pixelRect->width <= 0 || $pixelRect->height <= 0) {
      return;
    }
    ($this->painter)($target->textureForPixels($pixelRect), $target, $this);
  }

  protected function drawingPixelRect(TextureRenderTarget $target): Rect {
    if ($target instanceof SurfaceRenderTarget) {
      $surface = $target->currentSurfacePixelRect();
      $columns = $target->columnsForWidth($surface->width);
      $rows = $target->rowsForHeight($surface->height);
      if ($this->frame->x === 0 && $this->frame->y === 0 && $this->frame->width === $columns && $this->frame->height === $rows) {
        return $surface;
      }
    }
    return $target->pixelRectForCells($this->frame);
  }

}
