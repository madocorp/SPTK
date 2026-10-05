<?php

namespace SPTK\Widgets\Input;

use SPTK\Core\{Color, Style, TextEdit, Widget};
use SPTK\Events\{KeyNormalizer, WidgetEventEmitter};
use SPTK\Rendering\{GridWriter, TextMetrics};
use SPTK\SDLWrapper\SDL;

/** Edits one line with selection, clipboard shortcuts, history, and scrolling. */
final class Input extends Widget {

  use WidgetEventEmitter;

  private TextEdit $document;
  private Painter $painter;
  private bool $active = false;
  private int $scroll = 0;
  private int $viewport = 1;
  private ?string $label = null;
  private $inputInterceptor = null;

  /** Create a single-line editor with inherited style colors. */
  public function __construct(
    string $value = '',
    private readonly Style $style = new Style(),
    private readonly int $tabSize = 8,
    ?string $label = null,
  ) {
    if ($tabSize < 1) {
      throw new \InvalidArgumentException('Input tabSize must be positive.');
    }
    $this->setLabel($label);
    $this->document = new TextEdit(false, $value, $tabSize);
    $this->painter = new Painter($style);
    $this->on('activate', $this->activate(...));
    $this->on('deactivate', $this->deactivate(...));
  }

  /** Explain one-line editing and the input label when available. */
  protected function defaultTip(bool $active): string {
    return $active ? 'Type to edit; Esc or Return finishes.' : 'Return edits' . ($this->label !== null ? ' ' . $this->label : ' this input') . '.';
  }

  /** Return the current text, including ongoing edits. */
  public function getValue(): string {
    return $this->document->text();
  }

  /** Return the current text. */
  public function text(): string {
    return $this->getValue();
  }

  /** Replace the value and reset editing history and scroll. */
  public function setValue(string $value): void {
    $this->document->setValue($value);
    $this->scroll = 0;
  }

  /** Change the fixed label without resetting the edited input text. */
  public function setLabel(?string $label): void {
    if ($label !== null && (!mb_check_encoding($label, 'UTF-8') || preg_match('/[\x00-\x1f\x7f]/', $label))) {
      throw new \InvalidArgumentException('Input label must be printable single-line UTF-8 text.');
    }
    if ($this->label !== $label) {
      $this->label = $label;
      $this->emit('change');
    }
  }

  /** Return the zero-based grapheme index of the caret. */
  public function cursorPosition(): int {
    return $this->document->cursor()->position()[1];
  }

  /** Let an owner handle shortcuts before this editor changes its text. */
  public function setInputInterceptor(?callable $interceptor): void {
    $this->inputInterceptor = $interceptor;
  }

  /** Replace the graphemes just before the caret while preserving the suffix and undo history. */
  public function replaceBeforeCursor(int $count, string $replacement): void {
    $cursor = $this->cursorPosition();
    $this->document->replace($replacement, false, [0, max(0, $cursor - $count), 0, $cursor]);
  }

  /** Report whether the widget is activated. */
  public function editing(): bool {
    return $this->active;
  }

  /** Reserve one cell for editing and one more when a label is present. */
  public function preferredHeight(): ?int {
    return $this->label === null ? 1 : 2;
  }

  /** Paint the visible input row and record its viewport dimensions. */
  public function paint(GridWriter $writer): void {
    if ($this->label !== null) {
      $writer->fill($this->style->foreground, $this->style->background);
      $writer->write(0, 0, $this->label, $this->style->highlight, $this->style->background);
      $writer = $writer->below(min(1, $writer->height()));
    }
    [$this->scroll, $this->viewport] = $this->painter->paint($writer, $this->text(), $this->document->cursor(), $this->scroll, $this->active, $this->tabSize);
  }

  /** Return the tile background color. */
  public function background(): Color {
    return $this->style->background;
  }

  /** Apply text input, editing keys, and horizontal viewport paging. */
  public function handleInput(mixed $event): bool {
    if ($this->inputInterceptor !== null && ($this->inputInterceptor)($event)) {
      return true;
    }
    if ($event->type === SDL::SDL_EVENT_TEXT_INPUT) {
      $this->document->replace(\FFI::string($event->text->text), true);
      return true;
    }
    if ($event->type !== SDL::SDL_EVENT_KEY_DOWN) {
      return false;
    }
    $mod = (int)$event->key->mod;
    $key = KeyNormalizer::normalize((int)$event->key->key, $mod);
    if (($mod & SDL::MOD_CTRL) !== 0 && ($key === SDL::KEY_HOME || $key === SDL::KEY_END)) {
      $this->pageHorizontal($key === SDL::KEY_END, ($mod & SDL::MOD_SHIFT) !== 0);
      return true;
    }
    return $this->document->handleKey($key, $mod);
  }

  /** Make Escape accept the current text like the mad2 editor. */
  public function releaseNotification(int $key, int $modifiers): ?string {
    return $key === SDL::KEY_ESCAPE ? 'accept' : parent::releaseNotification($key, $modifiers);
  }

  /** Move to the viewport edge, paging when the caret is already there. */
  private function pageHorizontal(bool $right, bool $select): void {
    $text = $this->text();
    $caret = TextMetrics::column($text, $this->cursorPosition(), $this->tabSize);
    $edge = $right ? $this->scroll + $this->viewport - 1 : $this->scroll;
    if ($caret === $edge) {
      $extent = TextMetrics::width($text, $this->tabSize) + 1;
      $this->scroll = max(0, min(max(0, $extent - $this->viewport), $this->scroll + ($right ? 1 : -1) * $this->viewport));
      $edge = $right ? $this->scroll + $this->viewport - 1 : $this->scroll;
    }
    $this->document->cursor()->setPosition(0, TextMetrics::index($text, $edge, $this->tabSize), $select);
  }

  /** Mark this widget active when its tile is activated. */
  private function activate(): void {
    $this->active = true;
  }

  /** Hide the caret and collapse selection on release. */
  private function deactivate(): void {
    $this->active = false;
    $this->document->cursor()->collapse();
  }

}
