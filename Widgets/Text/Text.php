<?php

namespace SPTK\Widgets\Text;

use SPTK\Core\{Clipboard, Color, TextCursor, TextRows, Widget};
use SPTK\Events\{KeyNormalizer, WidgetEventEmitter};
use SPTK\Rendering\{GridWriter, TextMetrics};
use SPTK\SDLWrapper\SDL;

/** Displays read-only text with word wrapping, scrolling, and an active grid cursor. */
final class Text extends Widget {

  use WidgetEventEmitter;

  private array $lines;
  private TextCursor $cursor;
  private Painter $painter;
  private TextRows $rows;
  private int $scrollY = 0;
  private int $scrollX = 0;
  private bool $active = false;
  private int $viewportWidth = 1;
  private int $viewportHeight = 1;
  private int|false $preferredColumn = false;
  private int $cachedVisualWidth = -1;
  private array $cachedVisualRows = [];
  private ?int $contentWidth = null;
  private ?array $paintedCursorCell = null;
  private int $paintedScrollY = 0;
  private int $paintedScrollX = 0;
  private bool $paintedSelection = false;

  /** Create read-only text with optional row alignment. */
  public function __construct(
    string $text,
    private readonly Color $fg = new Color(230, 235, 245),
    private readonly Color $bg = new Color(24, 28, 36),
    private readonly Color $cursorBg = new Color(85, 85, 85),
    private readonly Color $cursorFg = new Color(255, 255, 255),
    private readonly Color $indicatorFg = new Color(0, 255, 255),
    private readonly bool $wrap = true,
    private readonly int $tabSize = 8,
    private readonly string $align = 'left',
  ) {
    if (!in_array($align, ['left', 'center', 'right'], true)) {
      throw new \InvalidArgumentException('Text align must be left, center, or right.');
    }
    if (!mb_check_encoding($text, 'UTF-8')) {
      throw new \InvalidArgumentException('Text must be valid UTF-8.');
    }
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    if (preg_match('/[\x00-\x08\x0b-\x1f\x7f]/', $text)) {
      throw new \InvalidArgumentException('Text supports printable characters and newlines.');
    }
    $this->lines = explode("\n", $text);
    $this->cursor = new TextCursor($this->lines, $this->tabSize);
    $this->rows = new TextRows();
    $this->painter = new Painter($this->fg, $this->bg, $this->cursorFg, $this->cursorBg, $this->indicatorFg);
    $this->on('activate', $this->activateCursor(...));
    $this->on('deactivate', $this->deactivateCursor(...));
  }

  /** Paint the visible wrapped rows, scroll marks, and active cursor. */
  public function paint(GridWriter $writer): void {
    if ($writer->width() < 1 || $writer->height() < 1) {
      $this->paintedCursorCell = null;
      return;
    }
    [$rows, $cursor] = $this->layout($writer);
    $this->paintedCursorCell = $this->painter->paint($writer, $rows, $this->lines, $this->cursor, $this->scrollY, $this->scrollX, $this->wrap, $this->active ? $cursor : [-1, -1], $this->tabSize, align: $this->align, contentWidth: $this->wrap ? null : $this->contentWidth());
    $this->paintedScrollY = $this->scrollY;
    $this->paintedScrollX = $this->scrollX;
    $this->paintedSelection = $this->cursor->hasSelection();
  }

  /** Move only the old and new cursor cells when the text viewport stays fixed. */
  public function paintUpdate(GridWriter $writer): bool {
    if (!$this->active || $this->paintedCursorCell === null || $this->paintedSelection || $this->cursor->hasSelection()
      || $writer->width() !== $this->viewportWidth || $writer->height() !== $this->viewportHeight) {
      return false;
    }
    [$rows, $cursor] = $this->layout($writer);
    if ($this->scrollY !== $this->paintedScrollY || $this->scrollX !== $this->paintedScrollX) {
      return false;
    }
    [$x, $y, $cell] = $this->paintedCursorCell;
    $writer->put($x, $y, $cell);
    $this->paintedCursorCell = $this->painter->paintActiveCursor($writer, $rows, $this->lines, $this->cursor, $cursor, $this->scrollY, $this->scrollX, $this->tabSize, $this->align);
    return $this->paintedCursorCell !== null;
  }

  /** Resolve wrapped rows and keep the active cursor inside the viewport. */
  private function layout(GridWriter $writer): array {
    $this->viewportWidth = $writer->width();
    $this->viewportHeight = $writer->height();
    $rows = $this->visualRows($writer->width());
    $this->scrollY = min($this->scrollY, max(0, count($rows) - $writer->height()));
    $cursor = $this->visualCursor($rows);
    if ($this->active) {
      $this->scrollY = max(0, min($this->scrollY, $cursor[0]));
      $this->scrollY = max($this->scrollY, $cursor[0] - $writer->height() + 1);
      $this->syncHorizontalCursor($cursor);
      if ($this->wrap) {
        $cursor[1] = min($writer->width() - 1, $cursor[1]);
      }
    }
    return [$rows, $cursor];
  }

  /** Return the background used behind the text tile. */
  public function background(): Color {
    return $this->bg;
  }

  /** Move the active cursor through the text grid or scroll by a page. */
  public function handleInput(mixed $event): bool {
    if ($event->type !== SDL::SDL_EVENT_KEY_DOWN) {
      return false;
    }
    $modifiers = (int)$event->key->mod;
    $key = KeyNormalizer::normalize((int)$event->key->key, $modifiers);
    $select = ($modifiers & SDL::MOD_SHIFT) !== 0;
    $document = ($modifiers & SDL::MOD_CTRL) !== 0;
    if ($document && $key === ord('a')) {
      $this->cursor->selectAll();
      return true;
    }
    if (($document && $key === ord('c')) || ($document && $key === SDL::KEY_INSERT)) {
      Clipboard::set($this->cursor->selectedText());
      return true;
    }
    if ($document && $key === SDL::KEY_PAGEUP) {
      $this->cursor->moveDocumentStart($select);
      $this->scrollY = 0;
      $this->scrollX = 0;
      $this->preferredColumn = false;
      return true;
    } else if ($document && $key === SDL::KEY_PAGEDOWN) {
      $this->cursor->moveDocumentEnd($select);
      $this->preferredColumn = false;
      return true;
    }
    if ($document && ($key === SDL::KEY_HOME || $key === SDL::KEY_END)) {
      $this->moveHorizontalViewportEdge($key === SDL::KEY_END, $select);
      $this->preferredColumn = false;
      return true;
    }
    if ($key === SDL::KEY_LEFT) {
      $this->cursor->moveLeft($select);
      $this->preferredColumn = false;
    } else if ($key === SDL::KEY_RIGHT) {
      $this->cursor->moveRight($select);
      $this->preferredColumn = false;
    } else if ($key === SDL::KEY_UP || $key === SDL::KEY_DOWN || $key === SDL::KEY_PAGEUP || $key === SDL::KEY_PAGEDOWN) {
      if ($key === SDL::KEY_PAGEUP || $key === SDL::KEY_PAGEDOWN) {
        $this->movePageEdge($key === SDL::KEY_PAGEDOWN, $select);
      } else {
        $this->moveVisualCursor($key === SDL::KEY_UP ? -1 : 1, $select);
      }
    } else if ($key === SDL::KEY_HOME) {
      $this->cursor->moveLineStart($select);
      $this->preferredColumn = false;
    } else if ($key === SDL::KEY_END) {
      $this->cursor->moveLineEnd($select);
      $this->preferredColumn = false;
    } else {
      return false;
    }
    return true;
  }

  /** Show the cursor when this widget enters input mode. */
  private function activateCursor(): void {
    $this->active = true;
  }

  /** Hide the cursor when this widget leaves input mode. */
  private function deactivateCursor(): void {
    $this->active = false;
  }

  /** Split document lines into word-wrapped visual rows. */
  private function visualRows(int $width): array {
    if ($this->cachedVisualWidth !== $width) {
      $this->cachedVisualRows = $this->rows->build($this->lines, $width, $this->wrap ? 'word' : 'none', $this->tabSize);
      $this->cachedVisualWidth = $width;
    }
    return $this->cachedVisualRows;
  }

  /** Find the wrapped row and cell column for the logical text cursor. */
  private function visualCursor(array $rows): array {
    return $this->rows->cursor($rows, $this->cursor, $this->tabSize);
  }

  /** Move vertically between wrapped rows while retaining the display column. */
  private function moveVisualCursor(int $distance, bool $select): void {
    $rows = $this->visualRows($this->viewportWidth);
    [$visualRow, $column] = $this->visualCursor($rows);
    $this->preferredColumn = $this->preferredColumn === false ? $column : $this->preferredColumn;
    $target = max(0, min(count($rows) - 1, $visualRow + $distance));
    $row = $rows[$target];
    $grapheme = 0;
    while ($grapheme < $row['length'] && TextMetrics::width(TextMetrics::slice($row['text'], 0, $grapheme + 1), $this->tabSize) <= $this->preferredColumn) {
      $grapheme++;
    }
    $this->cursor->setPosition($row['line'], $row['start'] + $grapheme, $select);
  }

  /** Move to a vertical viewport edge, scrolling a page when already there. */
  private function movePageEdge(bool $end, bool $select): void {
    $rows = $this->visualRows($this->viewportWidth);
    [$visualRow, $column] = $this->visualCursor($rows);
    $edge = $end ? min(count($rows) - 1, $this->scrollY + $this->viewportHeight - 1) : $this->scrollY;
    if ($visualRow === $edge) {
      $this->scrollY = max(0, min(max(0, count($rows) - $this->viewportHeight), $this->scrollY + ($end ? 1 : -1) * $this->viewportHeight));
      $edge = $end ? min(count($rows) - 1, $this->scrollY + $this->viewportHeight - 1) : $this->scrollY;
    }
    $this->setVisualPosition($rows, $edge, $this->wrap ? $column : $column - $this->scrollX, $select);
  }

  /** Move to a horizontal viewport edge, paging when the caret is already there. */
  private function moveHorizontalViewportEdge(bool $end, bool $select): void {
    $rows = $this->visualRows($this->viewportWidth);
    [$visualRow, $column] = $this->visualCursor($rows);
    $atEdge = $end ? $column >= $this->scrollX + $this->viewportWidth - 1 : $column <= $this->scrollX;
    if ($atEdge) {
      $this->scrollX = max(0, $this->scrollX + ($end ? 1 : -1) * $this->viewportWidth);
    }
    $column = $end ? $this->viewportWidth - 1 : 0;
    $this->setVisualPosition($rows, $visualRow, $column, $select);
  }

  /** Place the caret at a display column in a visual row. */
  private function setVisualPosition(array $rows, int $visualRow, int $column, bool $select): void {
    $row = $rows[$visualRow];
    $text = $row['text'];
    $target = max(0, $column);
    if (!$this->wrap) {
      $target += $this->scrollX;
    }
    $index = 0;
    while ($index < TextMetrics::length($text)
      && TextMetrics::width(TextMetrics::slice($text, 0, $index + 1), $this->tabSize) <= $target) {
      $index++;
    }
    $this->cursor->setPosition($row['line'], $row['start'] + $index, $select);
  }

  /** Keep the active caret inside the horizontal viewport and clamp its scroll. */
  private function syncHorizontalCursor(array $cursor): void {
    if ($this->wrap) {
      $this->scrollX = 0;
      return;
    }
    $this->scrollX = min($this->scrollX, max(0, $this->contentWidth() - $this->viewportWidth + 1));
    if ($cursor[1] < $this->scrollX) {
      $this->scrollX = $cursor[1];
    } else if ($cursor[1] >= $this->scrollX + $this->viewportWidth) {
      $this->scrollX = $cursor[1] - $this->viewportWidth + 1;
    }
  }

  /** Measure the widest source line once for horizontal scrolling. */
  private function contentWidth(): int {
    if ($this->contentWidth === null) {
      $this->contentWidth = 0;
      foreach ($this->lines as $line) {
        $this->contentWidth = max($this->contentWidth, TextMetrics::width($line, $this->tabSize));
      }
    }
    return $this->contentWidth;
  }

}
