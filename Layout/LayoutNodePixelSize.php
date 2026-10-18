<?php

namespace SPTK\Layout;

/** Resolves the intrinsic pixel size of an auto-sized pixel layout node. */
trait LayoutNodePixelSize {

  /** Sum the exact content and box heights needed by an auto-sized pixel node. */
  public function naturalPixelHeight(int $width, int $viewportWidth, int $viewportHeight): int {
    $box = $this->box ?? new PixelBox();
    $margin = PixelBox::edges($box->margin, $viewportWidth, $viewportHeight);
    $border = PixelBox::edges($box->borderWidth, $viewportWidth, $viewportHeight);
    $padding = PixelBox::edges($box->padding, $viewportWidth, $viewportHeight);
    $innerWidth = max(0, $width - $margin['left'] - $margin['right'] - $border['left'] - $border['right'] - $padding['left'] - $padding['right']);
    $edges = $margin['top'] + $margin['bottom'] + $border['top'] + $border['bottom'] + $padding['top'] + $padding['bottom'];
    $children = array_values(array_filter($this->visibleChildren(), fn(mixed $child): bool => !$child instanceof LayoutSeparator));
    if ($this->direction === 'horizontal') {
      $sizes = array_map(function(mixed $child) use ($viewportWidth, $viewportHeight): string {
        $size = $child instanceof LayoutLeaf ? $child->pixelWidthSize($viewportWidth, $viewportHeight) : $child->width();
        return $size === 'auto' ? (string)$child->naturalPixelWidth($viewportWidth, $viewportHeight) : $size;
      }, $children);
      $tiles = PixelSplitter::split(new Tile(0, 0, $innerWidth, 0), 'horizontal', $sizes, $viewportWidth, $viewportHeight);
      $heights = [];
      foreach ($children as $index => $child) {
        $heights[] = $child->naturalPixelHeight($tiles[$index]->width, $viewportWidth, $viewportHeight);
      }
      return $edges + ($heights === [] ? 0 : max($heights));
    }
    $height = 0;
    foreach ($children as $child) {
      $size = $child->height();
      if ($size === 'auto') {
        $height += $child->naturalPixelHeight($innerWidth, $viewportWidth, $viewportHeight);
      } else if (str_ends_with($size, '*')) {
        continue;
      } else if (str_ends_with($size, '%')) {
        throw new \LogicException('Percentage child height cannot resolve inside an auto-sized pixel layout.');
      } else {
        $height += PixelBox::dimension($size, $viewportWidth, $viewportHeight, $viewportHeight);
      }
    }
    return $edges + $height;
  }

  /** Resolve an auto width for a nested pixel layout. */
  public function naturalPixelWidth(int $viewportWidth, int $viewportHeight): int {
    $box = $this->box ?? new PixelBox();
    $margin = PixelBox::edges($box->margin, $viewportWidth, $viewportHeight);
    $border = PixelBox::edges($box->borderWidth, $viewportWidth, $viewportHeight);
    $padding = PixelBox::edges($box->padding, $viewportWidth, $viewportHeight);
    $edges = $margin['left'] + $margin['right'] + $border['left'] + $border['right'] + $padding['left'] + $padding['right'];
    $widths = [];
    foreach ($this->visibleChildren() as $child) {
      if ($child instanceof LayoutSeparator) {
        continue;
      }
      $size = $child instanceof LayoutLeaf ? $child->pixelWidthSize($viewportWidth, $viewportHeight) : $child->width();
      if ($size === 'auto') {
        $widths[] = $child->naturalPixelWidth($viewportWidth, $viewportHeight);
      } else if (str_ends_with($size, '*')) {
        $widths[] = 0;
      } else if (str_ends_with($size, '%')) {
        throw new \LogicException('Percentage child width cannot resolve inside an auto-sized pixel layout.');
      } else {
        $widths[] = PixelBox::dimension($size, $viewportWidth, $viewportHeight, $viewportWidth);
      }
    }
    return $edges + ($this->direction === 'horizontal' ? array_sum($widths) : ($widths === [] ? 0 : max($widths)));
  }

}
