<?php

namespace SPTK\Widgets\TextEditor;

use SPTK\Core\{Color, TextEdit, Widget};
use SPTK\Events\{KeyNormalizer, WidgetEventEmitter};
use SPTK\Rendering\GridWriter;
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\Text\Painter;

/** Edits multiline text with wrapped navigation, history, selection, and clipboard. */
final class TextEditor extends Widget {

  use WidgetEventEmitter;

  private TextEdit $document;
  private Navigator $navigator;
  private Painter $painter;
  private bool $active = false;

  /** Create a multiline editor with inherited style colors. */
  public function __construct(string $value = '', private readonly Color $fg = new Color(255, 255, 255), private readonly Color $bg = new Color(0, 0, 0), Color $cursorBg = new Color(85, 85, 85), private readonly Color $indicatorFg = new Color(0, 255, 255), private readonly bool $wrap = false, private readonly int $tabSize = 8, private readonly ?string $label = null) {
    if ($tabSize < 1) {
      throw new \InvalidArgumentException('TextEditor tabSize must be positive.');
    }
    if ($label !== null && (!mb_check_encoding($label, 'UTF-8') || preg_match('/[\x00-\x1f\x7f]/', $label))) {
      throw new \InvalidArgumentException('TextEditor label must be printable single-line UTF-8 text.');
    }
    $this->document = new TextEdit(true, $value, $tabSize);
    $this->navigator = new Navigator($wrap, $tabSize);
    $this->painter = new Painter($fg, $bg, $fg, $cursorBg, $indicatorFg);
    $this->on('activate', $this->activate(...));
    $this->on('deactivate', $this->deactivate(...));
  }

  /** Return the current multiline value, including edits in progress. */
  public function getValue(): string {
    return $this->document->text();
  }

  /** Return the current multiline text. */
  public function text(): string {
    return $this->getValue();
  }

  /** Replace the document and reset cursor, viewport, and history. */
  public function setValue(string $value): void {
    $this->document->setValue($value);
    $this->navigator->reset();
  }

  /** Return the zero-based document row and grapheme index. */
  public function cursorPosition(): array {
    return $this->document->cursor()->position();
  }

  /** Report whether the widget is activated. */
  public function editing(): bool {
    return $this->active;
  }

  /** Reserve sixteen editor rows and one more when a label is present. */
  public function preferredHeight(): ?int {
    return $this->label === null ? 16 : 17;
  }

  /** Paint text, selection, indicators, and the active block cursor. */
  public function paint(GridWriter $writer): void {
    if ($this->label !== null) {
      $writer->fill($this->fg, $this->bg);
      $writer->write(0, 0, $this->label, $this->indicatorFg, $this->bg);
      $writer = $writer->below(min(1, $writer->height()));
    }
    $lines = $this->document->lines();
    [$rows, $caret, $scrollY, $scrollX] = $this->navigator->layout($lines, $this->document->cursor(), $writer->width(), $writer->height(), $this->active);
    $this->painter->paint($writer, $rows, $lines, $this->document->cursor(), $scrollY, $scrollX, $this->wrap, $this->active ? $caret : [-1, -1], $this->tabSize, $this->active);
  }

  /** Return the tile background color. */
  public function background(): Color {
    return $this->bg;
  }

  /** Apply multiline input and shared editing shortcuts. */
  public function handleInput(mixed $event): bool {
    if ($event->type === SDL::SDL_EVENT_TEXT_INPUT) {
      $this->document->replace(\FFI::string($event->text->text), true);
      return true;
    }
    if ($event->type !== SDL::SDL_EVENT_KEY_DOWN) {
      return false;
    }
    $mod = (int)$event->key->mod;
    $key = KeyNormalizer::normalize((int)$event->key->key, $mod);
    if ($this->navigator->handle($key, $mod, $this->document->lines(), $this->document->cursor())) {
      return true;
    }
    return $this->document->handleKey($key, $mod);
  }

  /** Keep edits on Escape and reserve plain Return for a newline. */
  public function releaseNotification(int $key, int $modifiers): ?string {
    if ($key === SDL::KEY_ESCAPE) {
      return 'accept';
    }
    if ($key === SDL::KEY_RETURN) {
      return ($modifiers & SDL::MOD_CTRL) !== 0 ? 'accept' : null;
    }
    return null;
  }

  /** Show the caret when the screen activates this widget. */
  private function activate(): void {
    $this->active = true;
  }

  /** Hide the caret and collapse selection after release. */
  private function deactivate(): void {
    $this->active = false;
    $this->document->cursor()->collapse();
  }

}
