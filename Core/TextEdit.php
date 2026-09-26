<?php

namespace SPTK\Core;

use SPTK\Rendering\TextMetrics;
use SPTK\SDLWrapper\SDL;

/** Owns editable text, a grapheme cursor, and its undo history. */
final class TextEdit {

  private array $lines = [''];
  private TextCursor $cursor;
  private TextHistory $history;

  /** Create a single-line or multiline editing document. */
  public function __construct(private readonly bool $multiline, string $value = '', int $tabSize = 8) {
    $this->cursor = new TextCursor($this->lines, $tabSize);
    $this->setValue($value);
  }

  /** Return the current lines for layout and painting. */
  public function lines(): array {
    return $this->lines;
  }

  /** Return the shared grapheme cursor. */
  public function cursor(): TextCursor {
    return $this->cursor;
  }

  /** Return the current text including edits still in progress. */
  public function text(): string {
    return implode("\n", $this->lines);
  }

  /** Replace all content and reset the cursor and history. */
  public function setValue(string $value): void {
    $this->lines = explode("\n", $this->normalize($value));
    $this->cursor->setPosition(0, 0);
    $this->history = new TextHistory();
  }

  /** Replace the inclusive selection or insert at the caret. */
  public function replace(string $text, bool $typing = false, ?array $range = null): bool {
    $replacement = explode("\n", $this->normalize($text));
    $before = $this->lines;
    $beforeCursor = $this->cursor->selectionState();
    [$row1, $col1, $row2, $col2] = $range ?? ($this->cursor->hasSelection() ? $this->cursor->selectionRange() : $this->cursor->range());
    if ($row1 === $row2 && $col1 === $col2 && $replacement === ['']) {
      return false;
    }
    $last = count($replacement) - 1;
    $prefix = TextMetrics::slice($this->lines[$row1], 0, $col1);
    $suffix = TextMetrics::slice($this->lines[$row2], $col2);
    $replacement[0] = $prefix . $replacement[0];
    $caret = TextMetrics::length($replacement[$last]);
    $replacement[$last] .= $suffix;
    array_splice($this->lines, $row1, $row2 - $row1 + 1, $replacement);
    $this->cursor->setPosition($row1 + $last, $caret);
    $this->history->record($before, $beforeCursor, $this->lines, $this->cursor->selectionState(), $typing && $last === 0 ? $row1 : null);
    return $before !== $this->lines;
  }

  /** Delete the selection or one grapheme in the requested direction. */
  public function delete(bool $backward): bool {
    $range = $this->cursor->selectionRange();
    if (!$this->cursor->hasSelection()) {
      $backward ? $this->cursor->moveLeft(true) : $this->cursor->moveRight(true);
      $range = $this->cursor->range();
    }
    return $this->replace('', false, $range);
  }

  /** Restore the preceding or following content snapshot. */
  public function travel(bool $redo): bool {
    $state = $redo ? $this->history->redo($this->lines) : $this->history->undo($this->lines);
    if ($state === null) {
      return false;
    }
    $this->cursor->restoreState($state);
    return true;
  }

  /** Handle editing shortcuts and horizontal cursor movement common to both editors. */
  public function handleKey(int $key, int $modifiers): bool {
    $ctrl = ($modifiers & SDL::MOD_CTRL) !== 0;
    $shift = ($modifiers & SDL::MOD_SHIFT) !== 0;
    if ($ctrl && $key === ord('a')) {
      $this->cursor->selectAll();
    } else if (($ctrl && $key === ord('c')) || ($ctrl && $key === SDL::KEY_INSERT)) {
      Clipboard::set($this->cursor->selectedText());
    } else if (($ctrl && $key === ord('x')) || ($ctrl && $key === SDL::KEY_DELETE)) {
      Clipboard::set($this->cursor->selectedText());
      $this->replace('', false, $this->cursor->selectionRange());
    } else if (($ctrl && $key === ord('v')) || ($shift && $key === SDL::KEY_INSERT)) {
      $this->replace(Clipboard::get());
    } else if ($ctrl && $key === ord('z')) {
      $this->travel($shift);
    } else if ($ctrl && $key === ord('y')) {
      $this->travel(true);
    } else if ($key === SDL::KEY_BACKSPACE || $key === SDL::KEY_DELETE) {
      $this->delete($key === SDL::KEY_BACKSPACE);
    } else if ($key === SDL::KEY_TAB) {
      $this->replace("\t");
    } else if ($key === SDL::KEY_RETURN && $this->multiline && !$ctrl) {
      $this->replace("\n");
    } else if ($key === SDL::KEY_LEFT) {
      $ctrl ? $this->cursor->moveLineStart($shift) : $this->cursor->moveLeft($shift);
    } else if ($key === SDL::KEY_RIGHT) {
      $ctrl ? $this->cursor->moveLineEnd($shift) : $this->cursor->moveRight($shift);
    } else if (($key === SDL::KEY_HOME || $key === SDL::KEY_END) && !$ctrl) {
      $key === SDL::KEY_HOME ? $this->cursor->moveLineStart($shift) : $this->cursor->moveLineEnd($shift);
    } else {
      return false;
    }
    return true;
  }

  /** Normalize line endings and keep Input content on one line. */
  private function normalize(string $text): string {
    if (!mb_check_encoding($text, 'UTF-8')) {
      throw new \InvalidArgumentException('Editor text must be valid UTF-8.');
    }
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    return $this->multiline ? $text : preg_replace('/\n+/u', ' ', $text);
  }

}
