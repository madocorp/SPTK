<?php

namespace SPTK\Widgets\StyledText;

use SPTK\Core\{Color, RasterImage, Style, Widget};
use SPTK\Events\WidgetEventEmitter;
use SPTK\Layout\Tile;
use SPTK\Rendering\{GridWriter, PixelRenderer};

/** Displays read-only rich text with proportional fonts and per-run styling inside one layout tile. */
class StyledText extends Widget {

  use WidgetEventEmitter;

  private array $runs = [];
  private array $options = [];
  private ?RasterImage $image = null;
  private array $imageKey = [];

  /** Set initial rich text, pixel typography, inherited background, and optional focus dimming. */
  public function __construct(string|array $runs = '', array $options = [], private Style $style = new Style(), private bool $dimmed = true) {
    $this->setContent($runs, $options);
  }

  /** Atomically replace text and formatting, invalidating the cached raster only when needed. */
  public function setContent(string|array $runs, array $options = []): void {
    $runs = Format::runs($runs);
    $options = Format::style($options);
    if ($runs !== $this->runs || $options !== $this->options) {
      $this->runs = $runs;
      $this->options = $options;
      $this->image = null;
      $this->emit('change');
    }
  }

  /** Return the normalized rich text runs. */
  public function runs(): array {
    return $this->runs;
  }

  /** Return the widget's typography overrides. */
  public function options(): array {
    return $this->options;
  }

  /** Resolve the widget's text and typography relative to its allocated pixel tile. */
  protected function content(int $width, int $height): array {
    $color = sprintf('#%02x%02x%02x', $this->style->foreground->r, $this->style->foreground->g, $this->style->foreground->b);
    $style = array_replace(Format::DEFAULTS, ['color' => $color], $this->options);
    return [$this->runs, $style, $width, $height];
  }

  /** Measure the text's natural pixel height at a given width. */
  public function contentHeight(int $width, int $referenceHeight = 600): int {
    [$runs, $style, $referenceWidth, $height] = $this->content($width, $referenceHeight);
    return (new Raster())->contentHeight($runs, $style, $width, $referenceWidth, $height);
  }

  /** Return a cached rich text raster at exactly the requested dimensions. */
  public function raster(int $width, int $height): RasterImage {
    $content = $this->content($width, $height);
    $key = [$width, $height, $content];
    if ($this->image === null || $key !== $this->imageKey) {
      [$runs, $style, $referenceWidth, $referenceHeight] = $content;
      $this->image = (new Raster())->render($runs, $style, $width, $height, $referenceWidth, $referenceHeight);
      $this->imageKey = $key;
    }
    return $this->image;
  }

  /** Return the inherited opaque tile background. */
  public function background(): Color {
    return $this->style->background;
  }

  /** Clear the character layer beneath the styled text. */
  public function paint(GridWriter $writer): void {
    $writer->fill($this->style->foreground, $this->background());
  }

  /** Draw only within the allocated tile using the window's retained image textures. */
  public function paintPixels(PixelRenderer $renderer, Tile $area, bool $selected): void {
    if ($area->width > 0 && $area->height > 0) {
      $renderer->image($this->raster($area->width, $area->height), $area, $area, $selected || !$this->dimmed);
    }
  }

  /** Report pixel content so redraws clear the whole tile. */
  public function paintsPixels(): bool {
    return true;
  }

  /** Keep read-only typography out of input mode. */
  public function canActivate(): bool {
    return false;
  }

  /** Let screen-level actions process keyboard input. */
  public function handleInput(mixed $event): bool {
    return false;
  }

}
