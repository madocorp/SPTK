<?php

namespace SPTK\Widgets\ColorSelector;

use SPTK\Core\{Color, Style, Widget};
use SPTK\Events\{KeyNormalizer, WidgetEventEmitter};
use SPTK\Rendering\GridWriter;
use SPTK\SDLWrapper\SDL;

/** Edits an RGB color through rainbow, tone, shortcut, and hexadecimal controls. */
final class ColorSelector extends Widget {

  use WidgetEventEmitter;

  private Palette $palette;
  private bool $active = false;
  private ?string $hexDraft = null;

  /** Initialize the color and inherited visual style. */
  public function __construct(string $value = '#ff0000', private readonly Style $style = new Style()) {
    $this->palette = new Palette($value);
    $this->on('activate', $this->activate(...));
    $this->on('deactivate', $this->deactivate(...));
  }

  /** Return the last complete selected RGB color. */
  public function getValue(): string {
    return $this->palette->value();
  }

  /** Select a color without firing a user change event. */
  public function setValue(string $value): void {
    $this->palette->setValue($value);
    $this->hexDraft = null;
  }

  /** Return the currently displayed hex text, including an incomplete draft. */
  public function hexText(): string {
    return $this->hexDraft === null ? $this->getValue() : '#' . $this->hexDraft;
  }

  /** Return the zero-based palette cursor position. */
  public function cursorPosition(): int {
    return $this->palette->position();
  }

  /** Report whether the selector receives keyboard input. */
  public function active(): bool {
    return $this->active;
  }

  /** Suggest the full sixteen-swatch width. */
  public function preferredWidth(): ?int {
    return 64;
  }

  /** Reserve three palette rows, separators, and a preview row. */
  public function preferredHeight(): ?int {
    return 7;
  }

  /** Return the inherited tile background. */
  public function background(): Color {
    return $this->style->background;
  }

  /** Paint three clipped palette rows, the hex field, and the preview stripe. */
  public function paint(GridWriter $writer): void {
    $writer->fill($this->style->foreground, $this->style->background);
    for ($row = 0; $row < 3; $row++) {
      for ($column = 0; $column < Palette::COLUMNS; $column++) {
        $x = $column * 4;
        if ($x >= $writer->width()) {
          break;
        }
        $color = Color::from($this->palette->colorAt($row, $column));
        $cursor = $this->palette->selected($row, $column);
        $base = $row === 0 && $this->palette->baseSelected($column);
        $markerBg = $cursor && $this->active && $this->hexDraft === null ? $this->style->cursorBackground : $this->style->background;
        $markerFg = $base && !$cursor ? $this->style->selected : $this->style->foreground;
        $writer->set($x, $row * 2, $cursor || $base ? '[' : ' ', $markerFg, $markerBg);
        $writer->set($x + 1, $row * 2, ' ', $this->style->foreground, $color);
        $writer->set($x + 2, $row * 2, ' ', $this->style->foreground, $color);
        $writer->set($x + 3, $row * 2, $cursor || $base ? ']' : ' ', $markerFg, $markerBg);
      }
    }
    $fieldBg = $this->hexDraft === null ? $this->style->background : $this->style->cursorBackground;
    for ($x = 0; $x < 7; $x++) {
      $writer->set($x, 6, $this->hexText()[$x] ?? ' ', $this->style->foreground, $fieldBg);
    }
    $preview = Color::from($this->getValue());
    for ($x = 11; $x < $writer->width(); $x++) {
      $writer->set($x, 6, ' ', $this->style->foreground, $preview);
    }
  }

  /** Apply hex text, deletion, and palette navigation while activated. */
  public function handleInput(mixed $event): bool {
    if ($event->type === SDL::SDL_EVENT_TEXT_INPUT) {
      $text = \FFI::string($event->text->text);
      if (preg_match('/^[0-9a-f]+$/iD', $text) !== 1) {
        return false;
      }
      $before = $this->getValue();
      $draft = $this->hexDraft ?? '';
      $this->hexDraft = substr((strlen($draft) === 6 ? '' : $draft) . strtolower($text), 0, 6);
      if (strlen($this->hexDraft) === 6) {
        $this->palette->setValue($this->hexDraft);
      }
      if ($before !== $this->getValue()) {
        $this->emit('change');
      }
      return true;
    }
    if ($event->type !== SDL::SDL_EVENT_KEY_DOWN) {
      return false;
    }
    $key = KeyNormalizer::normalize((int)$event->key->key, (int)$event->key->mod);
    if ($key === SDL::KEY_BACKSPACE || $key === SDL::KEY_DELETE) {
      $this->hexDraft = $key === SDL::KEY_DELETE ? '' : substr($this->hexDraft ?? substr($this->getValue(), 1), 0, -1);
      return true;
    }
    $before = $this->getValue();
    if (!$this->palette->move($key)) {
      return false;
    }
    $this->hexDraft = null;
    if ($before !== $this->getValue()) {
      $this->emit('change');
    }
    return true;
  }

  /** Keep completed color changes when Escape releases the widget. */
  public function releaseNotification(int $key, int $modifiers): ?string {
    return $key === SDL::KEY_ESCAPE ? 'accept' : parent::releaseNotification($key, $modifiers);
  }

  /** Mark the selector active after tile activation. */
  private function activate(): void {
    $this->active = true;
  }

  /** Discard incomplete hex text after leaving input mode. */
  private function deactivate(): void {
    $this->active = false;
    $this->hexDraft = null;
  }

}
