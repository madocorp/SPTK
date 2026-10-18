<?php

namespace SPTK\Widgets\ColorInput;

use SPTK\Core\{Color, Style, Widget};
use SPTK\Events\{KeyNormalizer, WidgetEventEmitter};
use SPTK\Rendering\GridWriter;
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\Input\Input;

/** Edits a hex color or selects one from a compact grid of named colors. */
final class ColorInput extends Widget {

  use WidgetEventEmitter;

  private Input $input;
  private string $value;
  private string $initialValue;
  private array $colors = [];
  private int $columns = 2;
  private int $cursor = 0;
  private bool $paletteMode = false;
  private bool $active = false;

  /** Prepare an RGB field and an optional set of palette swatches. */
  public function __construct(string $value = '#ff0000', array $colors = [], private readonly Style $style = new Style(), private readonly ?string $label = null) {
    if ($label !== null && (!mb_check_encoding($label, 'UTF-8') || preg_match('/[\x00-\x1f\x7f]/', $label))) {
      throw new \InvalidArgumentException('ColorInput label must be printable single-line UTF-8 text.');
    }
    $this->input = new Input(style: $style);
    $this->setValue($value);
    $this->initialValue = $this->value;
    if ($colors !== []) {
      $this->setPalette($colors);
    }
    $this->on('activate', $this->activate(...));
    $this->on('accept', $this->accept(...));
    $this->on('cancel', $this->cancel(...));
    $this->on('deactivate', $this->deactivate(...));
  }

  /** Explain the two editing modes. */
  protected function defaultTip(bool $active): string {
    return $active ? 'Arrows choose a color; Tab switches to #rrggbb input; Return accepts; Esc cancels.' : 'Return opens the color palette.';
  }

  /** Return the last complete color. */
  public function getValue(): string {
    return $this->value;
  }

  /** Replace the selected color without announcing a user change. */
  public function setValue(string $value): void {
    $this->value = self::normalize($value);
    $this->input->setValue($this->value);
    $index = array_search($this->value, $this->colors, true);
    if ($index !== false) {
      $this->cursor = $index;
    }
  }

  /** Set one to 36 predefined colors for the square palette. */
  public function setPalette(array $colors): void {
    $previousHeight = $this->preferredHeight();
    if (count($colors) < 1 || count($colors) > 36) {
      throw new \InvalidArgumentException('ColorInput requires one to 36 palette colors.');
    }
    $normalized = array_map(self::normalize(...), $colors);
    if (count(array_unique($normalized)) !== count($normalized)) {
      throw new \InvalidArgumentException('ColorInput palette colors must be unique.');
    }
    $this->colors = $normalized;
    $this->columns = max(2, (int)ceil(sqrt(count($normalized))));
    $index = array_search($this->value, $this->colors, true);
    $this->cursor = $index === false ? 0 : $index;
    if ($this->preferredHeight() !== $previousHeight) {
      $this->emit('preferredSizeChange');
    }
  }

  /** Return the selected swatch and whether the palette has keyboard focus. */
  public function palettePosition(): int {
    return $this->cursor;
  }

  public function paletteMode(): bool {
    return $this->paletteMode;
  }

  /** Use two rows for input and grow to fit the palette while it is open. */
  public function preferredHeight(): ?int {
    if (!$this->paletteMode || $this->colors === []) {
      return 2;
    }
    $heading = $this->label === null ? 0 : 1;
    return max(2, $heading + 2 * $this->columns - 1);
  }

  /** Use the normal tile background outside the colored value row. */
  public function background(): Color {
    return $this->style->background;
  }

  /** Paint an Input-like hex row or bracketed two-cell color swatches. */
  public function paint(GridWriter $writer): void {
    $writer->fill($this->style->foreground, $this->style->background);
    if ($this->label !== null) {
      $writer->write(0, 0, $this->label, $this->style->highlight, $this->style->background);
    }
    $row = $this->label === null ? 0 : 1;
    if (!$this->paletteMode) {
      $background = Color::from($this->value);
      $foreground = self::ink($background);
      $text = $this->input->getValue();
      $writer->fillRow($row, $foreground, $background);
      $writer->write(0, $row, $text, $foreground, $background);
      if ($this->active && $writer->width() > 0 && $writer->height() > $row) {
        $position = min($this->input->cursorPosition(), $writer->width() - 1);
        $writer->set($position, $row, $text[$position] ?? ' ', $background, $foreground);
      }
      return;
    }
    foreach ($this->colors as $index => $hex) {
      $x = ($index % $this->columns) * 4;
      $y = $row + intdiv($index, $this->columns) * 2;
      $color = Color::from($hex);
      $selected = $index === $this->cursor;
      $markerBackground = $selected && $this->active ? $this->style->cursorBackground : $this->style->background;
      $writer->set($x, $y, $selected ? '[' : ' ', $this->style->foreground, $markerBackground);
      $writer->set($x + 1, $y, ' ', $this->style->foreground, $color);
      $writer->set($x + 2, $y, ' ', $this->style->foreground, $color);
      $writer->set($x + 3, $y, $selected ? ']' : ' ', $this->style->foreground, $markerBackground);
    }
  }

  /** Route Tab and arrows to the palette, or hex keys to the text editor. */
  public function handleInput(mixed $event): bool {
    if ($event->type === SDL::SDL_EVENT_KEY_DOWN) {
      $key = KeyNormalizer::normalize((int)$event->key->key, (int)$event->key->mod);
      if ($key === SDL::KEY_TAB) {
        $this->switchMode();
        return true;
      }
      if ($key === SDL::KEY_RETURN) {
        $this->accept();
        return false;
      }
      if ($this->paletteMode) {
        return $this->movePalette($key);
      }
    }
    if ($this->paletteMode) {
      return true;
    }
    $before = $this->input->getValue();
    if (!$this->input->handleInput($event)) {
      return false;
    }
    $draft = $this->input->getValue();
    if (preg_match('/^#?[0-9a-f]{0,6}$/iD', $draft) !== 1) {
      $this->input->setValue($before);
      return true;
    }
    if (preg_match('/^#?[0-9a-f]{6}$/iD', $draft) === 1) {
      $this->choose(self::normalize($draft));
    }
    return true;
  }

  /** Require a hash-prefixed or plain six-digit RGB value. */
  private static function normalize(string $value): string {
    if (preg_match('/^#?([0-9a-f]{6})$/iD', $value, $match) !== 1) {
      throw new \InvalidArgumentException('ColorInput requires a six-digit hex color.');
    }
    return '#' . strtolower($match[1]);
  }

  /** Choose black or white text for the current swatch. */
  private static function ink(Color $color): Color {
    return 299 * $color->r + 587 * $color->g + 114 * $color->b >= 150000
      ? new Color(0, 0, 0) : new Color(255, 255, 255);
  }

  /** Switch modes and transfer the selected swatch into the hex field. */
  private function switchMode(): void {
    if ($this->paletteMode) {
      $this->choose($this->colors[$this->cursor]);
      $this->setPaletteMode(false);
      $this->input->setValue($this->value);
      return;
    }
    if ($this->colors === []) {
      return;
    }
    $index = array_search($this->value, $this->colors, true);
    $this->cursor = $index === false ? 0 : $index;
    $this->setPaletteMode(true);
  }

  /** Change mode and request a new tile height when needed. */
  private function setPaletteMode(bool $paletteMode): void {
    if ($this->paletteMode === $paletteMode) {
      return;
    }
    $this->paletteMode = $paletteMode;
    $this->emit('preferredSizeChange');
  }

  /** Move within the square, skipping unused cells in the final row. */
  private function movePalette(int $key): bool {
    $next = match ($key) {
      SDL::KEY_LEFT => $this->cursor % $this->columns > 0 ? $this->cursor - 1 : $this->cursor,
      SDL::KEY_RIGHT => $this->cursor % $this->columns < $this->columns - 1 ? $this->cursor + 1 : $this->cursor,
      SDL::KEY_UP => $this->cursor - $this->columns,
      SDL::KEY_DOWN => $this->cursor + $this->columns,
      default => null,
    };
    if ($next === null) {
      return false;
    }
    if ($next >= 0 && $next < count($this->colors)) {
      $this->cursor = $next;
      $this->choose($this->colors[$next]);
    }
    return true;
  }

  /** Update the preview and announce a changed complete color. */
  private function choose(string $value): void {
    if ($value !== $this->value) {
      $this->value = $value;
      $this->emit('change');
    }
  }

  /** Remember the color to restore if this edit is cancelled. */
  private function activate(): void {
    $this->active = true;
    $this->initialValue = $this->value;
    $this->input->emit('activate');
    if ($this->colors !== []) {
      $this->switchMode();
    }
  }

  /** Put the selected color back into the field before screen acceptance. */
  private function accept(): void {
    if ($this->paletteMode) {
      $this->choose($this->colors[$this->cursor]);
    }
    $this->setPaletteMode(false);
    $this->input->setValue($this->value);
  }

  /** Restore the color held before activation. */
  private function cancel(): void {
    $this->setValue($this->initialValue);
  }

  /** End the palette and input edit when the widget loses focus. */
  private function deactivate(): void {
    $this->active = false;
    $this->setPaletteMode(false);
    $this->input->emit('deactivate');
  }

}
