<?php

namespace SPTK\Widgets\Image;

use SPTK\Core\{Color, ImageSource, RasterImage, Style, Widget, WidgetTitle};
use SPTK\Events\WidgetEventEmitter;
use SPTK\Events\KeyNormalizer;
use SPTK\Layout\Tile;
use SPTK\Rendering\{GridWriter, PixelRenderer};
use SPTK\SDLWrapper\SDL;

/** Displays a clipped image at native size, zoomed size, or fitted size. */
final class Image extends Widget {

  use WidgetEventEmitter;

  private ImageSource $image;
  private readonly bool $initialFill;
  private readonly float $initialZoom;
  private readonly int $initialX;
  private readonly int $initialY;
  private bool $fill;
  private float $zoom;
  private int $x;
  private int $y;
  private ?Tile $viewport = null;
  private WidgetTitle $title;

  /** Describe the controls available for this widget and its configuration. */
  protected function defaultTip(bool $active): string {
    return !$this->interactive ? 'Image preview; arrow keys move between tiles.' : ($active ? 'Arrows pan; + and - zoom; Space resets; Esc finishes.' : 'Return opens image navigation.');
  }

  /** Retain a shared lazy source and configure fitting or manual zoom and offsets. */
  public function __construct(
    string|\GdImage $src,
    bool $fill = false,
    float $zoom = 1.0,
    int $x = 0,
    int $y = 0,
    private readonly Color $bg = new Color(32, 38, 48),
    private readonly ?string $fit = null,
    private readonly bool $interactive = true,
    private readonly bool $padding = true,
    ?string $title = null,
    ?Style $style = null,
  ) {
    if (!in_array($fit, [null, 'contain', 'cover'], true)) {
      throw new \InvalidArgumentException('Image fit must be contain or cover.');
    }
    if ($fill && $fit !== null) {
      throw new \InvalidArgumentException('Image fit cannot be combined with fill=true.');
    }
    $fill = $fill || $fit !== null;
    if (!is_finite($zoom) || $zoom <= 0) {
      throw new \InvalidArgumentException('Image zoom must be a positive finite number.');
    }
    if ($fill && ($zoom !== 1.0 || $x !== 0 || $y !== 0)) {
      throw new \InvalidArgumentException('Image zoom, x, and y require manual sizing.');
    }
    $this->title = new WidgetTitle($title, ($style ?? new Style())->with(['Background' => $bg]));
    $this->image = ImageSource::from($src);
    $this->initialFill = $fill;
    $this->initialZoom = $zoom;
    $this->initialX = $x;
    $this->initialY = $y;
    $this->fill = $fill;
    $this->zoom = $zoom;
    $this->x = $x;
    $this->y = $y;
  }

  /** Replace the source and restore the initial fitting, zoom, and position. */
  public function setSource(string|\GdImage $src): void {
    $this->image = ImageSource::from($src);
    $this->fill = $this->initialFill;
    $this->zoom = $this->initialZoom;
    $this->x = $this->initialX;
    $this->y = $this->initialY;
    $this->emit('change');
  }

  /** Decode the shared source on demand and return its dimensions and pixel data. */
  public function source(): RasterImage {
    return $this->image->raster();
  }

  /** Prefer the native source width rounded to cells in manual mode. */
  public function preferredWidth(): ?int {
    if ($this->initialFill) {
      return $this->title->width() ?: null;
    }
    $cellWidth = \SPTK\App::fontOrNull()?->cellWidth() ?? 8;
    return max($this->title->width(), (int)ceil($this->image->dimensions()[0] / $cellWidth));
  }

  /** Prefer the native source height rounded to cells in manual mode. */
  public function preferredHeight(): ?int {
    if ($this->initialFill) {
      return null;
    }
    $cellHeight = \SPTK\App::fontOrNull()?->cellHeight() ?? 16;
    return (int)ceil($this->image->dimensions()[1] / $cellHeight) + $this->title->height();
  }

  /** Fill the tile underneath transparent source pixels. */
  public function paint(GridWriter $writer): void {
    $writer->fill(new Color(255, 255, 255), $this->bg);
    $this->title->body($writer);
  }

  /** Report that image movement needs its pixel tile redrawn. */
  public function paintsPixels(): bool {
    return true;
  }

  /** Draw the source after text cells have been rendered. */
  public function paintPixels(PixelRenderer $renderer, Tile $area, bool $selected): void {
    if ($area->width < 1 || $area->height < 1) {
      return;
    }
    $renderer->image($this->source(), $this->destination($area), $area, $selected);
  }

  /** Remember the viewport and compute the centered image with manual offsets. */
  public function destination(Tile $area): Tile {
    $this->viewport = $area;
    if ($area->width < 1 || $area->height < 1) {
      return new Tile($area->x, $area->y, 0, 0);
    }
    $scale = $this->fill ? $this->fitScale($area) : $this->zoom;
    [$width, $height] = $this->image->dimensions();
    $drawWidth = max(1, (int)round($width * $scale));
    $drawHeight = max(1, (int)round($height * $scale));
    $offsetX = 0;
    if ($drawWidth > $area->width) {
      $this->x = $this->clampOffset($this->x, $area->width, $drawWidth);
      $offsetX = $this->x;
    }
    $offsetY = 0;
    if ($drawHeight > $area->height) {
      $this->y = $this->clampOffset($this->y, $area->height, $drawHeight);
      $offsetY = $this->y;
    }
    return new Tile($area->x + intdiv($area->width - $drawWidth, 2) + $offsetX, $area->y + intdiv($area->height - $drawHeight, 2) + $offsetY, $drawWidth, $drawHeight);
  }

  /** Use the widget's background for the tile and transparent pixels. */
  public function background(): Color {
    return $this->bg;
  }

  /** Allow image viewer activation only when interaction is enabled. */
  public function canActivate(): bool {
    return $this->interactive;
  }

  /** Keep pixel content inside the cell area unless padding is disabled. */
  public function pixelPadding(): bool {
    return $this->padding;
  }

  /** Reserve the title's grid row while retaining optional outer image padding. */
  public function pixelArea(Tile $cellArea, Tile $backgroundArea): Tile {
    $area = parent::pixelArea($cellArea, $backgroundArea);
    if ($this->title->height() === 0) {
      return $area;
    }
    $cellHeight = \SPTK\App::fontOrNull()?->cellHeight() ?? 16;
    $top = max($area->y, min($area->y + $area->height, $cellArea->y + $cellHeight));
    return new Tile($area->x, $top, $area->width, $area->y + $area->height - $top);
  }

  /** Zoom, pan by half a viewport, jump to edges, or reset the image view. */
  public function handleInput(mixed $event): bool {
    if (!$this->interactive || $event->type !== SDL::SDL_EVENT_KEY_DOWN) {
      return false;
    }
    $key = KeyNormalizer::normalizeInput($event);
    if (in_array($key, [SDL::KEY_PLUS, SDL::KEY_ASTERISK, SDL::KEY_KP_PLUS, SDL::KEY_KP_MULTIPLY], true)) {
      $this->manual();
      $this->zoom *= 1.25;
      return true;
    }
    if (in_array($key, [SDL::KEY_MINUS, SDL::KEY_SLASH, SDL::KEY_KP_MINUS, SDL::KEY_KP_DIVIDE], true)) {
      $this->manual();
      $this->zoom /= 1.25;
      return true;
    }
    if ($key === SDL::KEY_SPACE) {
      $this->fill = $this->initialFill;
      $this->zoom = $this->initialZoom;
      $this->x = $this->initialX;
      $this->y = $this->initialY;
      return true;
    }
    if ($key === SDL::KEY_EQUALS || $key === SDL::KEY_KP_EQUALS) {
      $this->fill = true;
      $this->zoom = 1.0;
      $this->x = $this->y = 0;
      return true;
    }
    if (in_array($key, [SDL::KEY_LEFT, SDL::KEY_RIGHT, SDL::KEY_UP, SDL::KEY_DOWN, SDL::KEY_HOME, SDL::KEY_END, SDL::KEY_PAGEUP, SDL::KEY_PAGEDOWN], true)) {
      $this->navigate($key);
      return true;
    }
    return false;
  }

  /** Compute uniform scaling for covering, containing, or shrinking the source to fit. */
  private function fitScale(Tile $area): float {
    if ($area->width < 1 || $area->height < 1) {
      return 1.0;
    }
    [$width, $height] = $this->image->dimensions();
    $scaleX = $area->width / $width;
    $scaleY = $area->height / $height;
    return match ($this->fit) {
      'cover' => max($scaleX, $scaleY),
      'contain' => min($scaleX, $scaleY),
      default => min(1, $scaleX, $scaleY),
    };
  }

  /** Switch from fitting to manual scale without a visible size jump. */
  private function manual(): void {
    if ($this->fill) {
      $this->zoom = $this->viewport === null ? 1.0 : $this->fitScale($this->viewport);
      $this->fill = false;
    }
  }

  /** Move the visible image or align an image edge with the tile. */
  private function navigate(int $key): void {
    if ($this->viewport === null) {
      return;
    }
    $this->manual();
    [$sourceWidth, $sourceHeight] = $this->image->dimensions();
    $width = max(1, (int)round($sourceWidth * $this->zoom));
    $height = max(1, (int)round($sourceHeight * $this->zoom));
    if ($key === SDL::KEY_HOME || $key === SDL::KEY_END) {
      if ($width > $this->viewport->width) {
        $this->x = $this->edgeOffset($this->viewport->width, $width, $key === SDL::KEY_HOME);
      }
    } else if ($key === SDL::KEY_PAGEUP || $key === SDL::KEY_PAGEDOWN) {
      if ($height > $this->viewport->height) {
        $this->y = $this->edgeOffset($this->viewport->height, $height, $key === SDL::KEY_PAGEUP);
      }
    } else if ($key === SDL::KEY_LEFT || $key === SDL::KEY_RIGHT) {
      if ($width > $this->viewport->width) {
        $step = max(1, intdiv($this->viewport->width, 2));
        $this->x = $this->clampOffset($this->x + ($key === SDL::KEY_LEFT ? $step : -$step), $this->viewport->width, $width);
      }
    } else {
      if ($height > $this->viewport->height) {
        $step = max(1, intdiv($this->viewport->height, 2));
        $this->y = $this->clampOffset($this->y + ($key === SDL::KEY_UP ? $step : -$step), $this->viewport->height, $height);
      }
    }
  }

  /** Return the offset that aligns one image edge with its viewport edge. */
  private function edgeOffset(int $viewport, int $image, bool $first): int {
    $center = intdiv($viewport - $image, 2);
    return $first ? -$center : $viewport - $image - $center;
  }

  /** Keep arrow navigation between the two edge-aligned positions. */
  private function clampOffset(int $offset, int $viewport, int $image): int {
    $first = $this->edgeOffset($viewport, $image, true);
    $last = $this->edgeOffset($viewport, $image, false);
    return max(min($first, $last), min(max($first, $last), $offset));
  }

}
