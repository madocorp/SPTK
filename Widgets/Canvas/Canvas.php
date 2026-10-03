<?php

namespace SPTK\Widgets\Canvas;

use SPTK\Core\{Color, Texture, TextureContext, Widget};
use SPTK\Events\WidgetEventEmitter;
use SPTK\Layout\Tile;
use SPTK\Rendering\{GridWriter, PixelRenderer};

/** Gives applications a pixel canvas painted by their own callback. */
class Canvas extends Widget {

  use WidgetEventEmitter;

  private \Closure|string|null $painter;
  private Color $background;
  private int $revision = 0;

  /** Configure an optional painter and the canvas background. */
  public function __construct(callable|string|null $painter = null, ?Color $background = null) {
    $this->painter = $painter === null || is_string($painter) ? $painter : \Closure::fromCallable($painter);
    $this->background = $background ?? new Color(32, 38, 48);
  }

  /** Replace the application painter and request a redraw. */
  public function setPainter(callable|string|null $painter): void {
    $this->painter = $painter === null || is_string($painter) ? $painter : \Closure::fromCallable($painter);
    $this->refresh();
  }

  /** Request a redraw after application drawing data changes. */
  public function refresh(): void {
    $this->revision++;
    $this->emit('change');
  }

  /** Mark the retained canvas surface as needing repainting. */
  public function invalidate(): void {
    $this->refresh();
  }

  /** Return the drawing revision used by each window's surface cache. */
  public function revision(): int {
    return $this->revision;
  }

  /** Invoke the painter with the current surface and window's texture factory. */
  public function draw(Texture $surface, TextureContext $textures): void {
    if ($this->painter !== null) {
      if (!is_callable($this->painter)) {
        throw new \RuntimeException('Canvas painter is not callable: ' . $this->painter);
      }
      ($this->painter)($surface, $textures, $this);
    }
  }

  /** Let configured XML input actions handle activated canvas input. */
  public function handleInput(mixed $event): bool {
    return false;
  }

  /** Return the opaque color cleared before each paint callback. */
  public function background(): Color {
    return $this->background;
  }

  /** Suggest a natural width in character cells. */
  public function preferredWidth(): ?int {
    return 40;
  }

  /** Suggest a natural height in character cells. */
  public function preferredHeight(): ?int {
    return 12;
  }

  /** Reserve the character tile for pixel content. */
  public function paint(GridWriter $writer): void {
    $writer->fill($this->background, $this->background);
  }

  /** Paint the callback's output into the allocated pixel tile. */
  public function paintPixels(PixelRenderer $renderer, Tile $area, bool $selected): void {
    $renderer->canvas($this, $area, $selected);
  }

  /** Report pixel content so partial redraws clear the complete tile. */
  public function paintsPixels(): bool {
    return true;
  }

}
