<?php

namespace SPTK\Widgets;

use SPTK\Core\Color;
use SPTK\Core\Element;
use SPTK\Core\ImageRenderTarget;
use SPTK\Core\Len;
use SPTK\Core\PixelSurfaceElement;
use SPTK\Core\Place;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;
use SPTK\Core\SurfaceRenderTarget;

/**
 * Divides an element's box among docked, filled, and grid-positioned children.
 */
class Dock extends Element {

  protected array $placements = [];
  protected array $separators = [];

  public function place(Element $child, Place $place): static {
    if ($place->mode() !== 'at' && $place->mode() !== 'fill') {
      $this->assertNoFillPlacement();
    } else if ($place->mode() === 'fill') {
      $this->assertNoFillPlacement();
    }
    return $this->addPlacement($child, $place);
  }

  protected function addPlacement(Element $child, Place $place): static {
    $this->add($child);
    $this->placements[spl_object_id($child)] = $place;
    return $this;
  }

  public function dock(Element $child, string $edge, array $options = []): static {
    return $this->addPlacement($child, Place::dock($edge, $this->legacyDockSize($edge, $options), $this->legacySeparator($options)));
  }

  public function fill(Element $child): static {
    return $this->addPlacement($child, Place::fill());
  }

  public function atGrid(Element $child, int $x, int $y, array $options = []): static {
    $width = isset($options['cells']) ? Len::cell((int)$options['cells']) : (isset($options['width']) ? Len::cell((int)$options['width']) : null);
    $height = isset($options['rows']) ? Len::cell((int)$options['rows']) : (isset($options['height']) ? Len::cell((int)$options['height']) : null);
    return $this->addPlacement($child, Place::at(Len::cell($x), Len::cell($y), $width, $height));
  }

  public function atPixels(Element $child, int $x, int $y, array $options = []): static {
    $width = isset($options['pixelsWidth']) ? Len::px((int)$options['pixelsWidth']) : (isset($options['cells']) ? Len::cell((int)$options['cells']) : null);
    $height = isset($options['pixelsHeight']) ? Len::px((int)$options['pixelsHeight']) : (isset($options['rows']) ? Len::cell((int)$options['rows']) : null);
    return $this->addPlacement($child, Place::at(Len::px($x), Len::px($y), $width, $height));
  }

  public function layout(): void {
    $remaining = $this->frame;
    $this->separators = [];
    foreach ($this->children as $child) {
      if (!$this->shouldLayoutChild($child)) {
        $child->setFrame(new Rect($this->frame->x, $this->frame->y, 0, 0));
        continue;
      }
      if ($child->isAbsolute()) {
        if ($child instanceof DialogLayer) {
          $child->setFrame($this->frame);
        }
        continue;
      }
      $placement = $this->placements[spl_object_id($child)] ?? Place::fill();
      if ($placement->mode() === 'dock') {
        [$childFrame, $separatorFrame, $remaining] = $this->dockCellFrames($child, $remaining, $placement);
        if ($separatorFrame !== null) {
          $this->separators[] = [
            'frame' => $separatorFrame,
            'orientation' => $this->separatorOrientation($placement->edge()),
            'color' => $this->separatorColor($child, $placement),
          ];
        }
        $child->setFrame($childFrame);
      } else if ($placement->mode() === 'at') {
        $child->setFrame($this->placedCellFrame($child, $remaining, $placement));
      } else if ($placement->mode() === 'cursor') {
        [$frame, $remaining] = $this->cursorCellFrame($child, $remaining, $placement);
        $child->setFrame($frame);
      } else if ($placement->mode() === 'fill') {
        $child->setFrame($remaining);
      }
    }
  }

  protected function paint(RenderTarget $target): void {
    foreach ($this->separators as $separator) {
      $frame = $separator['frame'];
      if ($frame->width <= 0 || $frame->height <= 0) {
        continue;
      }
      $color = $separator['color'];
      $target->fill($frame, ' ', $this->theme->fg, $color);
    }
  }

  public function render(RenderTarget $target): void {
    if (!$target instanceof SurfaceRenderTarget) {
      $target->pushClip($this->frame);
      foreach ($this->children as $child) {
        if ($this->shouldRenderChild($child) && !$child->isAbsolute()) {
          $child->render($target);
        }
      }
      $this->paint($target);
      foreach ($this->children as $child) {
        if ($this->shouldRenderChild($child) && $child->isAbsolute()) {
          $child->render($target);
        }
      }
      $target->popClip();
      return;
    }
    if ($this->shouldRenderCellExactSurfaces()) {
      foreach ($this->children as $child) {
        if ($this->shouldRenderChild($child) && !$child->isAbsolute()) {
          $this->renderChildSurface($target, $child, $this->cellFramePixelRect($target, $child->frame()));
        }
      }
      $this->paintPixelSeparators($target, $this->cellPixelSeparators($target));
      foreach ($this->children as $child) {
        if ($this->shouldRenderChild($child) && $child->isAbsolute()) {
          $child->render($target);
        }
      }
      return;
    }
    $remaining = $this->dockPixelSurfaceRect($target);
    $pixelSeparators = [];
    foreach ($this->children as $child) {
      if (!$this->shouldRenderChild($child)) {
        continue;
      }
      if ($child->isAbsolute()) {
        continue;
      }
      $placement = $this->placements[spl_object_id($child)] ?? Place::fill();
      if ($placement->mode() === 'dock') {
        [$pixelFrame, $separatorFrame, $remaining] = $this->dockPixelFrames($target, $child, $remaining, $placement);
        if ($separatorFrame !== null) {
          $pixelSeparators[] = [
            'frame' => $separatorFrame,
            'orientation' => $this->separatorOrientation($placement->edge()),
            'color' => $this->separatorColor($child, $placement),
          ];
        }
      } else if ($placement->mode() === 'at') {
        $pixelFrame = $this->placedPixelFrame($target, $child, $remaining, $placement);
      } else if ($placement->mode() === 'cursor') {
        [$pixelFrame, $remaining] = $this->cursorPixelFrame($target, $child, $remaining, $placement);
      } else if ($placement->mode() === 'fill') {
        $pixelFrame = $remaining;
      } else {
        continue;
      }
      $this->renderChildSurface($target, $child, $pixelFrame);
    }
    $this->paintPixelSeparators($target, $pixelSeparators);
    foreach ($this->children as $child) {
      if ($this->shouldRenderChild($child) && $child->isAbsolute()) {
        $child->render($target);
      }
    }
  }

  protected function dockCellFrames(Element $child, Rect $remaining, Place $placement): array {
    $edge = $placement->edge();
    $size = $this->cellDockSize($child, $remaining, $placement);
    $separator = $this->cellSeparatorSize($placement);
    if ($edge === 'top') {
      $size = min($size, $remaining->height);
      $child = new Rect($remaining->x, $remaining->y, $remaining->width, $size);
      $sep = min($separator, max(0, $remaining->height - $size));
      $separatorFrame = $sep > 0 ? new Rect($remaining->x, $remaining->y + $size, $remaining->width, $sep) : null;
      $used = $size + $sep;
      $next = new Rect($remaining->x, $remaining->y + $used, $remaining->width, max(0, $remaining->height - $used));
    } else if ($edge === 'bottom') {
      $size = min($size, $remaining->height);
      $sep = min($separator, max(0, $remaining->height - $size));
      $childY = $remaining->bottom() - $size;
      $separatorFrame = $sep > 0 ? new Rect($remaining->x, $childY - $sep, $remaining->width, $sep) : null;
      $child = new Rect($remaining->x, $childY, $remaining->width, $size);
      $next = new Rect($remaining->x, $remaining->y, $remaining->width, max(0, $remaining->height - $size - $sep));
    } else if ($edge === 'left') {
      $size = min($size, $remaining->width);
      $child = new Rect($remaining->x, $remaining->y, $size, $remaining->height);
      $sep = min($separator, max(0, $remaining->width - $size));
      $separatorFrame = $sep > 0 ? new Rect($remaining->x + $size, $remaining->y, $sep, $remaining->height) : null;
      $used = $size + $sep;
      $next = new Rect($remaining->x + $used, $remaining->y, max(0, $remaining->width - $used), $remaining->height);
    } else {
      $size = min($size, $remaining->width);
      $sep = min($separator, max(0, $remaining->width - $size));
      $childX = $remaining->right() - $size;
      $separatorFrame = $sep > 0 ? new Rect($childX - $sep, $remaining->y, $sep, $remaining->height) : null;
      $child = new Rect($childX, $remaining->y, $size, $remaining->height);
      $next = new Rect($remaining->x, $remaining->y, max(0, $remaining->width - $size - $sep), $remaining->height);
    }
    return [$child, $separatorFrame, $next];
  }

  protected function dockPixelFrames(SurfaceRenderTarget $target, Element $child, Rect $remaining, Place $placement): array {
    $edge = $placement->edge();
    $size = $this->pixelDockSize($target, $child, $remaining, $placement);
    $separator = $this->pixelSeparatorSize($target, $placement);
    if ($edge === 'top') {
      $size = min($size, $remaining->height);
      $child = new Rect($remaining->x, $remaining->y, $remaining->width, $size);
      $sep = min($separator, max(0, $remaining->height - $size));
      $separatorFrame = $sep > 0 ? new Rect($remaining->x, $remaining->y + $size, $remaining->width, $sep) : null;
      $used = $size + $sep;
      $next = new Rect($remaining->x, $remaining->y + $used, $remaining->width, max(0, $remaining->height - $used));
    } else if ($edge === 'bottom') {
      $size = min($size, $remaining->height);
      $sep = min($separator, max(0, $remaining->height - $size));
      $childY = $remaining->bottom() - $size;
      $separatorFrame = $sep > 0 ? new Rect($remaining->x, $childY - $sep, $remaining->width, $sep) : null;
      $child = new Rect($remaining->x, $childY, $remaining->width, $size);
      $next = new Rect($remaining->x, $remaining->y, $remaining->width, max(0, $remaining->height - $size - $sep));
    } else if ($edge === 'left') {
      $size = min($size, $remaining->width);
      $child = new Rect($remaining->x, $remaining->y, $size, $remaining->height);
      $sep = min($separator, max(0, $remaining->width - $size));
      $separatorFrame = $sep > 0 ? new Rect($remaining->x + $size, $remaining->y, $sep, $remaining->height) : null;
      $used = $size + $sep;
      $next = new Rect($remaining->x + $used, $remaining->y, max(0, $remaining->width - $used), $remaining->height);
    } else {
      $size = min($size, $remaining->width);
      $sep = min($separator, max(0, $remaining->width - $size));
      $childX = $remaining->right() - $size;
      $separatorFrame = $sep > 0 ? new Rect($childX - $sep, $remaining->y, $sep, $remaining->height) : null;
      $child = new Rect($childX, $remaining->y, $size, $remaining->height);
      $next = new Rect($remaining->x, $remaining->y, max(0, $remaining->width - $size - $sep), $remaining->height);
    }
    return [$child, $separatorFrame, $next];
  }

  protected function cellDockSize(Element $child, Rect $remaining, Place $placement): int {
    $edge = $placement->edge();
    $available = in_array($edge, ['top', 'bottom'], true) ? $remaining->height : $remaining->width;
    $size = $placement->size();
    if ($size !== null) {
      return max(0, min($available, $size->resolveCells($available, 1, $this->contentCells($child, $remaining, $edge))));
    }
    return in_array($edge, ['top', 'bottom'], true) ? 1 : min(20, $available);
  }

  protected function pixelDockSize(SurfaceRenderTarget $target, Element $child, Rect $remaining, Place $placement): int {
    $edge = $placement->edge();
    $available = in_array($edge, ['top', 'bottom'], true) ? $remaining->height : $remaining->width;
    $size = $placement->size();
    if ($size !== null) {
      $cellSize = $this->axisCellSize($target, $edge);
      return max(0, min($available, $size->resolvePixels($available, $cellSize, $this->contentPixels($target, $child, $remaining, $edge))));
    }
    return in_array($edge, ['top', 'bottom'], true) ? $target->cellHeight() : min($target->cellWidth() * 20, $available);
  }

  protected function cellSeparatorSize(Place $placement): int {
    $separator = $placement->separator();
    if ($separator === null) {
      return 0;
    }
    return max(0, $separator->resolveCells(PHP_INT_MAX));
  }

  protected function pixelSeparatorSize(SurfaceRenderTarget $target, Place $placement): int {
    $separator = $placement->separator();
    if ($separator === null) {
      return 0;
    }
    return max(0, $separator->resolvePixels(PHP_INT_MAX, $this->axisCellSize($target, $placement->edge() ?? 'left')));
  }

  protected function separatorOrientation(string $edge): string {
    return in_array($edge, ['top', 'bottom'], true) ? 'horizontal' : 'vertical';
  }

  protected function placedCellFrame(Element $child, Rect $remaining, Place $placement): Rect {
    $width = $placement->width()?->resolveCells($remaining->width, 1, $child->preferredColumns()) ?? $remaining->width;
    $height = $placement->height()?->resolveCells($remaining->height, 1, $child->preferredRowsForColumns(max(1, $width))) ?? $remaining->height;
    $x = $placement->x()?->resolveOffsetCells($remaining->width, $width) ?? 0;
    $y = $placement->y()?->resolveOffsetCells($remaining->height, $height) ?? 0;
    return new Rect($remaining->x + $x, $remaining->y + $y, max(0, $width), max(0, $height));
  }

  protected function placedPixelFrame(SurfaceRenderTarget $target, Element $child, Rect $remaining, Place $placement): Rect {
    $columns = $target->columnsForWidth($remaining->width);
    $preferredColumns = $child->preferredColumns() ?: $columns;
    $width = $placement->width()?->resolvePixels($remaining->width, $target->cellWidth(), $preferredColumns) ?? $remaining->width;
    $heightContent = $child->preferredRowsForColumns(max(1, $target->columnsForWidth($width)));
    $height = $placement->height()?->resolvePixels($remaining->height, $target->cellHeight(), $heightContent) ?? $remaining->height;
    $x = $placement->x()?->resolveOffsetPixels($remaining->width, $width, $target->cellWidth()) ?? 0;
    $y = $placement->y()?->resolveOffsetPixels($remaining->height, $height, $target->cellHeight()) ?? 0;
    return new Rect($remaining->x + $x, $remaining->y + $y, max(0, $width), max(0, $height));
  }

  protected function cursorCellFrame(Element $child, Rect $remaining, Place $placement): array {
    $width = $placement->width()?->resolveCells($remaining->width, 1, $child->preferredColumns()) ?? $remaining->width;
    $height = $placement->height()?->resolveCells($remaining->height, 1, $child->preferredRowsForColumns(max(1, $width))) ?? $child->preferredRowsForColumns(max(1, $width));
    $height = min(max(0, $height), $remaining->height);
    return [
      new Rect($remaining->x, $remaining->y, max(0, min($width, $remaining->width)), $height),
      new Rect($remaining->x, $remaining->y + $height, $remaining->width, max(0, $remaining->height - $height)),
    ];
  }

  protected function cursorPixelFrame(SurfaceRenderTarget $target, Element $child, Rect $remaining, Place $placement): array {
    $preferredColumns = $child->preferredColumns() ?: $target->columnsForWidth($remaining->width);
    $width = $placement->width()?->resolvePixels($remaining->width, $target->cellWidth(), $preferredColumns) ?? $remaining->width;
    $heightContent = $child->preferredRowsForColumns(max(1, $target->columnsForWidth($width)));
    $height = $placement->height()?->resolvePixels($remaining->height, $target->cellHeight(), $heightContent) ?? $heightContent * $target->cellHeight();
    $height = min(max(0, $height), $remaining->height);
    return [
      new Rect($remaining->x, $remaining->y, max(0, min($width, $remaining->width)), $height),
      new Rect($remaining->x, $remaining->y + $height, $remaining->width, max(0, $remaining->height - $height)),
    ];
  }

  protected function paintPixelSeparators(SurfaceRenderTarget $target, array $separators): void {
    foreach ($separators as $separator) {
      $frame = $separator['frame'];
      if ($frame->width <= 0 || $frame->height <= 0) {
        continue;
      }
      $color = $separator['color'];
      if ($separator['orientation'] === 'vertical') {
        for ($x = 0; $x < $frame->width; $x++) {
          $target->fillPixels(new Rect($frame->x + $x, $frame->y, 1, $frame->height), $color);
        }
      } else {
        for ($y = 0; $y < $frame->height; $y++) {
          $target->fillPixels(new Rect($frame->x, $frame->y + $y, $frame->width, 1), $color);
        }
      }
    }
  }

  protected function renderChildSurface(SurfaceRenderTarget $target, Element $child, Rect $pixelFrame): void {
    if ($pixelFrame->width <= 0 || $pixelFrame->height <= 0) {
      return;
    }
    if ($child instanceof PixelSurfaceElement) {
      $child->renderPixelSurface($target, $pixelFrame);
      return;
    }
    $columns = $target->columnsForWidth($pixelFrame->width);
    $rows = $target->rowsForHeight($pixelFrame->height);
    $logicalFrame = $child->frame();
    $child->setFrame(new Rect(0, 0, $columns, $rows));
    $target->pushSurface($pixelFrame, $columns, $rows, $child->surfaceBackground(), $child->gridAlignment());
    $child->render($target);
    $target->popSurface();
    $child->setFrame($logicalFrame);
  }

  protected function hasPixelPlacement(): bool {
    foreach ($this->children as $child) {
      if (!$this->shouldRenderChild($child) || $child->isAbsolute()) {
        continue;
      }
      $placement = $this->placements[spl_object_id($child)] ?? Place::fill();
      if ($this->placementUsesPixels($placement)) {
        return true;
      }
    }
    return false;
  }

  protected function shouldRenderCellExactSurfaces(): bool {
    return $this->parent() !== null && !$this->hasPixelPlacement();
  }

  protected function placementUsesPixels(Place $placement): bool {
    foreach ([$placement->size(), $placement->separator(), $placement->x(), $placement->y(), $placement->width(), $placement->height()] as $len) {
      if ($len instanceof Len && $len->unit() === 'px') {
        return true;
      }
    }
    return false;
  }

  protected function cellFramePixelRect(SurfaceRenderTarget $target, Rect $frame): Rect {
    $surface = $this->dockPixelSurfaceRect($target);
    return new Rect(
      $surface->x + ($frame->x - $this->frame->x) * $target->cellWidth(),
      $surface->y + ($frame->y - $this->frame->y) * $target->cellHeight(),
      $frame->width * $target->cellWidth(),
      $frame->height * $target->cellHeight()
    );
  }

  protected function cellPixelSeparators(SurfaceRenderTarget $target): array {
    $separators = [];
    foreach ($this->separators as $separator) {
      $separators[] = [
        'frame' => $this->cellFramePixelRect($target, $separator['frame']),
        'orientation' => $separator['orientation'],
        'color' => $separator['color'],
      ];
    }
    return $separators;
  }

  protected function separatorColor(Element $child, Place $placement): Color|string|int {
    return $this->insideDialog() ? $this->theme->bg : $this->theme->muted;
  }

  protected function dockPixelSurfaceRect(SurfaceRenderTarget $target): Rect {
    $surface = $target->currentSurfacePixelRect();
    $columns = $target->columnsForWidth($surface->width);
    $rows = $target->rowsForHeight($surface->height);
    if ($this->frame->x === 0 && $this->frame->y === 0 && $this->frame->width === $columns && $this->frame->height === $rows) {
      return $surface;
    }
    if ($target instanceof ImageRenderTarget) {
      return $target->pixelRectForCells($this->frame);
    }
    return new Rect(
      $surface->x + $this->frame->x * $target->cellWidth(),
      $surface->y + $this->frame->y * $target->cellHeight(),
      $this->frame->width * $target->cellWidth(),
      $this->frame->height * $target->cellHeight()
    );
  }

  protected function insideDialog(): bool {
    for ($element = $this->parent(); $element !== null; $element = $element->parent()) {
      if ($element->focusScope() === 'dialog') {
        return true;
      }
    }
    return false;
  }

  protected function assertNoFillPlacement(): void {
    foreach ($this->children as $child) {
      $placement = $this->placements[spl_object_id($child)] ?? null;
      if ($placement instanceof Place && $placement->mode() === 'fill') {
        throw new \InvalidArgumentException('Fill placement must be the last non-overlay placement.');
      }
    }
  }

  protected function shouldLayoutChild(Element $child): bool {
    return true;
  }

  protected function shouldRenderChild(Element $child): bool {
    return true;
  }

  protected function legacyDockSize(string $edge, array $options): ?Len {
    if (isset($options['ratio'])) {
      return Len::percent((float)$options['ratio'] * 100);
    }
    if (isset($options['pixels'])) {
      return Len::px((int)$options['pixels']);
    }
    if (isset($options['cells'])) {
      return Len::cell((int)$options['cells']);
    }
    return null;
  }

  protected function legacySeparator(array $options): ?Len {
    $separator = $options['separator'] ?? false;
    if ($separator === false || $separator === null) {
      return null;
    }
    if ($separator === true) {
      return Len::separator();
    }
    return Len::px((int)$separator);
  }

  protected function axisCellSize(SurfaceRenderTarget $target, string $edge): int {
    return in_array($edge, ['top', 'bottom'], true) ? $target->cellHeight() : $target->cellWidth();
  }

  protected function contentCells(Element $child, Rect $remaining, string $edge): int {
    if (in_array($edge, ['top', 'bottom'], true)) {
      return $child->preferredRowsForColumns(max(1, $remaining->width));
    }
    return $child->preferredColumns() ?: min(20, $remaining->width);
  }

  protected function contentPixels(SurfaceRenderTarget $target, Element $child, Rect $remaining, string $edge): int {
    if (in_array($edge, ['top', 'bottom'], true)) {
      return $child->preferredRowsForColumns(max(1, $target->columnsForWidth($remaining->width)));
    }
    return $child->preferredColumns() ?: min(20, $target->columnsForWidth($remaining->width));
  }

}
