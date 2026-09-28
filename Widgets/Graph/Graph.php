<?php

namespace SPTK\Widgets\Graph;

use SPTK\Core\{Color, RasterImage, Style, Widget};
use SPTK\Events\WidgetEventEmitter;
use SPTK\Layout\Tile;
use SPTK\Rendering\{GridWriter, PixelRenderer};

/** Displays numeric series using a raster cached at the allocated pixel size. */
final class Graph extends Widget {

  use WidgetEventEmitter;

  private array $series = [];
  private array $options = Data::DEFAULTS;
  private ?RasterImage $image = null;
  private array $imageSize = [];

  /** Initialize numeric series, axis options, and inherited colors. */
  public function __construct(array $series = [], array $options = [], private Style $style = new Style()) {
    $this->setSeries($series);
    $this->setOptions($options);
  }

  /** Return normalized series data. */
  public function series(): array {
    return $this->series;
  }

  /** Return all current axis and labeling options. */
  public function options(): array {
    return $this->options;
  }

  /** Replace the series and discard the raster only when the data changes. */
  public function setSeries(array $series): void {
    $next = Data::series($series);
    if ($next !== $this->series) {
      $this->series = $next;
      $this->image = null;
    }
  }

  /** Append one series using the same validation as replacement. */
  public function addSeries(array $series): void {
    $this->setSeries([...$this->series, $series]);
  }

  /** Merge validated options and discard the raster when something changes. */
  public function setOptions(array $options): void {
    $next = Data::options($this->options, $options);
    if ($next !== $this->options) {
      $this->options = $next;
      $this->image = null;
    }
  }

  /** Suggest the natural graph width in cells. */
  public function preferredWidth(): ?int {
    return 40;
  }

  /** Suggest the natural graph height in cells. */
  public function preferredHeight(): ?int {
    return 12;
  }

  /** Return the inherited graph background. */
  public function background(): Color {
    return $this->style->background;
  }

  /** Clear the character cells beneath the raster. */
  public function paint(GridWriter $writer): void {
    $writer->fill($this->style->foreground, $this->style->background);
  }

  /** Report that this widget paints over the character grid. */
  public function paintsPixels(): bool {
    return true;
  }

  /** Render a graph at the requested pixel size, retaining unchanged rasters. */
  public function raster(int $width, int $height, int $rowHeight = 16): RasterImage {
    if ($width < 1 || $height < 1 || $rowHeight < 1) {
      throw new \InvalidArgumentException('Graph raster dimensions must be positive.');
    }
    $size = [$width, $height, $rowHeight];
    if ($this->image === null || $size !== $this->imageSize) {
      $this->image = (new Raster())->render($this->series, $this->options, $this->style, ...$size);
      $this->imageSize = $size;
    }
    return $this->image;
  }

  /** Draw the raster through the window renderer with normal selection shading. */
  public function paintPixels(PixelRenderer $renderer, Tile $area, bool $selected): void {
    if ($area->width < 1 || $area->height < 1) {
      return;
    }
    $rowHeight = \SPTK\App::fontOrNull()?->cellHeight() ?? 16;
    $renderer->image($this->raster($area->width, $area->height, $rowHeight), $area, $area, $selected);
  }

  /** Keep the read-only graph out of input mode. */
  public function canActivate(): bool {
    return false;
  }

  /** Ignore input because graph data is updated programmatically. */
  public function handleInput(mixed $event): bool {
    return false;
  }

}
