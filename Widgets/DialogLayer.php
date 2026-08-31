<?php

namespace SPTK\Widgets;

use SPTK\Core\Element;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;
use SPTK\Core\SurfaceRenderTarget;

/**
 * Owns a stack of modal dialog panels.
 */
class DialogLayer extends Element {

  public function __construct(string $name = '') {
    parent::__construct($name);
    $this->absolute = true;
    $this->gridAlignment = 'top-left';
  }

  public function push(DialogPanel $panel): static {
    $this->add($panel);
    $this->syncActive();
    $panel->requestFocus();
    return $this;
  }

  public function pop(?DialogPanel $panel = null): ?DialogPanel {
    $panel ??= $this->top();
    if ($panel === null || !$this->remove($panel)) {
      return null;
    }
    $this->syncActive();
    $panel->notifyClosed();
    return $panel;
  }

  public function top(): ?DialogPanel {
    for ($i = count($this->children) - 1; $i >= 0; $i--) {
      if ($this->children[$i] instanceof DialogPanel) {
        return $this->children[$i];
      }
    }
    return null;
  }

  public function focusScope(): string {
    return 'modal';
  }

  public function focusProxy(): ?Element {
    return $this->top();
  }

  public function add(Element $child): static {
    if (!$child instanceof DialogPanel) {
      throw new \InvalidArgumentException('DialogLayer accepts DialogPanel children only.');
    }
    parent::add($child);
    $this->syncActive();
    return $this;
  }

  public function remove(Element $child): bool {
    $removed = parent::remove($child);
    if ($removed) {
      $this->syncActive();
    }
    return $removed;
  }

  public function layout(): void {
    foreach ($this->children as $child) {
      if (!$child instanceof DialogPanel) {
        continue;
      }
      $child->setFrame($this->panelFrame($child, $this->frame));
    }
  }

  public function render(RenderTarget $target): void {
    if (!$target instanceof SurfaceRenderTarget) {
      parent::render($target);
      return;
    }
    foreach ($this->children as $child) {
      if (!$child instanceof DialogPanel) {
        continue;
      }
      $this->renderPanelSurface($target, $child);
    }
  }

  protected function renderPanelSurface(SurfaceRenderTarget $target, DialogPanel $panel): void {
    $surface = $target->currentSurfacePixelRect();
    $border = 2;
    $innerWidth = $this->roundedInnerWidth($target, $surface, $panel, $border);
    $columns = $target->columnsForWidth($innerWidth);
    $innerHeight = $this->roundedInnerHeight($target, $surface, $panel, $border, $columns);
    $pixelWidth = $innerWidth + $border * 2;
    $pixelHeight = $innerHeight + $border * 2;
    $pixelFrame = new Rect(
      $surface->x + max(0, intdiv($surface->width - $pixelWidth, 2)),
      $surface->y + max(0, intdiv($surface->height - $pixelHeight, 2)),
      $pixelWidth,
      $pixelHeight
    );
    $this->paintPixelPanel($target, $panel, $pixelFrame);
    $innerFrame = $pixelFrame->inset($border, $border);
    $rows = $target->rowsForHeight($innerFrame->height);
    $logicalFrame = $panel->frame();
    $panel->setFrame(new Rect(0, 0, $columns, $rows));
    $panel->layoutInterior();
    $target->pushSurface($innerFrame, $columns, $rows, $panel->backgroundColor(), 'top-left');
    $panel->renderInterior($target);
    $target->popSurface();
    $panel->setFrame($logicalFrame);
  }

  protected function paintPixelPanel(SurfaceRenderTarget $target, DialogPanel $panel, Rect $frame): void {
    $target->fillPixels($frame, $panel->backgroundColor());
    $border = $panel->borderColor();
    $target->fillPixels(new Rect($frame->x, $frame->y, $frame->width, 2), $border);
    $target->fillPixels(new Rect($frame->x, $frame->bottom() - 2, $frame->width, 2), $border);
    $target->fillPixels(new Rect($frame->x, $frame->y, 2, $frame->height), $border);
    $target->fillPixels(new Rect($frame->right() - 2, $frame->y, 2, $frame->height), $border);
  }

  protected function roundedInnerWidth(SurfaceRenderTarget $target, Rect $surface, DialogPanel $panel, int $border): int {
    $cellWidth = $target->cellWidth();
    $available = max(0, $surface->width - $border * 2);
    if ($panel->windowMarginColumns() !== null) {
      return max($cellWidth, min($available, $surface->width - $panel->windowMarginColumns() * $cellWidth - $border * 2));
    }
    $targetColumns = $panel->contentColumns() !== null
      ? $panel->contentColumns() + 2
      : max(12, intdiv((int)floor($surface->width * $panel->widthRatio()) - $border * 2, $cellWidth));
    $targetWidth = max($cellWidth, $targetColumns * $cellWidth);
    $columns = max(1, intdiv(min($available, $targetWidth), $cellWidth));
    return $columns * $cellWidth;
  }

  protected function roundedInnerHeight(SurfaceRenderTarget $target, Rect $surface, DialogPanel $panel, int $border, int $columns): int {
    $cellHeight = $target->cellHeight();
    $available = max(0, $surface->height - $border * 2);
    if ($panel->windowMarginRows() !== null) {
      return max($cellHeight, min($available, $surface->height - $panel->windowMarginRows() * $cellHeight - $border * 2));
    }
    $rows = max(1, min(max(1, $panel->preferredRowsForColumns($columns) - 2), intdiv($available, $cellHeight)));
    return $rows * $cellHeight;
  }

  protected function panelFrame(DialogPanel $panel, Rect $container): Rect {
    if ($panel->windowMarginColumns() !== null || $panel->windowMarginRows() !== null) {
      $width = max(1, $container->width - (int)$panel->windowMarginColumns());
      $height = max(1, $container->height - (int)$panel->windowMarginRows());
      return new Rect(
        $container->x + max(0, intdiv($container->width - $width, 2)),
        $container->y + max(0, intdiv($container->height - $height, 2)),
        min($container->width, $width),
        min($container->height, $height)
      );
    }
    $width = $panel->contentColumns() !== null
      ? $panel->contentColumns() + 4
      : max(12, (int)floor($container->width * $panel->widthRatio()));
    $width = min($container->width, $width);
    $height = min($container->height, $panel->preferredRowsForColumns($width));
    return new Rect(
      $container->x + max(0, intdiv($container->width - $width, 2)),
      $container->y + max(0, intdiv($container->height - $height, 2)),
      $width,
      $height
    );
  }

  protected function syncActive(): void {
    $top = $this->top();
    foreach ($this->children as $child) {
      if ($child instanceof DialogPanel) {
        $child->setActive($child === $top);
      }
    }
  }

  protected function paint(RenderTarget $target): void {
    ;
  }

}
