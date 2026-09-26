<?php

namespace SPTK\Widgets\Text;

use SPTK\Core\{Color, TextCursor, Widget};
use SPTK\Events\{KeyNormalizer, WidgetEventEmitter};
use SPTK\Rendering\{GridWriter, TextMetrics};
use SPTK\SDLWrapper\SDL;

/** Displays read-only text with word wrapping, scrolling, and an active grid cursor. */
final class Text implements Widget {

  use WidgetEventEmitter;

  private array $lines;
  private TextCursor $cursor;
  private Painter $painter;
  private int $scrollY = 0;
  private int $scrollX = 0;
  private bool $active = false;
  private int $viewportWidth = 1;
  private int $viewportHeight = 1;
  private int|false $preferredColumn = false;

  public function __construct(
    string $text,
    private readonly Color $fg = new Color(230, 235, 245),
    private readonly Color $bg = new Color(24, 28, 36),
    private readonly Color $cursorBg = new Color(85, 85, 85),
    private readonly Color $cursorFg = new Color(255, 255, 255),
    private readonly Color $indicatorFg = new Color(0, 255, 255),
    private readonly bool $wrap = true,
  ) {
    if (!mb_check_encoding($text, 'UTF-8')) {
      throw new \InvalidArgumentException('Text must be valid UTF-8.');
    }
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    if (preg_match('/[\x00-\x09\x0b-\x1f\x7f]/', $text)) {
      throw new \InvalidArgumentException('Text supports printable characters and newlines.');
    }
    $this->lines = explode("\n", $text);
    $this->cursor = new TextCursor($this->lines);
    $this->painter = new Painter($this->fg, $this->bg, $this->cursorFg, $this->cursorBg, $this->indicatorFg);
    $this->on('activate', $this->activateCursor(...));
    $this->on('deactivate', $this->deactivateCursor(...));
  }

  /** Paint the visible wrapped rows, scroll marks, and active cursor. */
  public function paint(GridWriter $writer): void {
    $writer->fill($this->fg, $this->bg);
    if ($writer->width() < 1 || $writer->height() < 1) {
      return;
    }
    $this->viewportWidth = $writer->width();
    $this->viewportHeight = $writer->height();
    $rows = $this->visualRows($writer->width());
    $this->scrollY = min($this->scrollY, max(0, count($rows) - $writer->height()));
    $cursor = $this->visualCursor($rows);
    if ($this->active) {
      $this->scrollY = max(0, min($this->scrollY, $cursor[0]));
      $this->scrollY = max($this->scrollY, $cursor[0] - $writer->height() + 1);
      $this->syncHorizontalCursor($cursor);
      $cursor[1] -= $this->scrollX;
      if ($this->wrap) {
        $cursor[1] = min($writer->width() - 1, $cursor[1]);
      }
    }
    $this->painter->paint($writer, $rows, $this->cursor, $this->scrollY, $this->scrollX, $this->wrap, $this->active ? $cursor : [-1, -1]);
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
    $rows = [];
    foreach ($this->lines as $lineNumber => $line) {
      foreach ($this->wrapLine($line, $width) as $row) {
        $row['line'] = $lineNumber;
        $rows[] = $row;
      }
    }
    return $rows;
  }

  /** Wrap at whitespace and split any word wider than the available row. */
  private function wrapLine(string $line, int $width): array {
    if (!$this->wrap) {
      return [['start' => 0, 'length' => TextMetrics::length($line), 'text' => $line]];
    }
    preg_match_all('/\S+/u', $line, $matches, PREG_OFFSET_CAPTURE);
    if ($matches[0] === []) {
      return [['start' => 0, 'length' => 0, 'text' => '']];
    }
    $rows = [];
    $current = null;
    $previousEnd = 0;
    foreach ($matches[0] as [$word, $byteOffset]) {
      $wordStart = TextMetrics::length(substr($line, 0, $byteOffset));
      $wordLength = TextMetrics::length($word);
      $separator = $current === null ? '' : TextMetrics::slice($line, $previousEnd, $wordStart - $previousEnd);
      if (TextMetrics::width($word) > $width) {
        if ($current !== null) {
          $rows[] = $current;
          $current = null;
        }
        $chunk = '';
        $chunkStart = $wordStart;
        foreach (TextMetrics::glyphs($word) as $index => $glyph) {
          if ($chunk !== '' && TextMetrics::width($chunk . $glyph) > $width) {
            $rows[] = ['start' => $chunkStart, 'length' => TextMetrics::length($chunk), 'text' => $chunk];
            $chunkStart = $wordStart + $index;
            $chunk = '';
          }
          $chunk .= $glyph;
        }
        $rows[] = ['start' => $chunkStart, 'length' => TextMetrics::length($chunk), 'text' => $chunk];
      } else if ($current === null) {
        $current = ['start' => $wordStart, 'length' => $wordLength, 'text' => $word];
      } else if (TextMetrics::width($current['text'] . $separator . $word) <= $width) {
        $current['length'] = $wordStart + $wordLength - $current['start'];
        $current['text'] .= $separator . $word;
      } else {
        $rows[] = $current;
        $current = ['start' => $wordStart, 'length' => $wordLength, 'text' => $word];
      }
      $previousEnd = $wordStart + $wordLength;
    }
    if ($current !== null) {
      $rows[] = $current;
    }
    return $rows;
  }

  /** Find the wrapped row and cell column for the logical text cursor. */
  private function visualCursor(array $rows): array {
    [$line, $column] = $this->cursor->position();
    $visualRow = 0;
    $cellColumn = 0;
    foreach ($rows as $index => $row) {
      if ($row['line'] !== $line || $column < $row['start']) {
        continue;
      }
      $visualRow = $index;
      $offset = min($row['length'], max(0, $column - $row['start']));
      $cellColumn = TextMetrics::width(TextMetrics::slice($row['text'], 0, $offset));
    }
    return [$visualRow, $cellColumn];
  }

  /** Move vertically between wrapped rows while retaining the display column. */
  private function moveVisualCursor(int $distance, bool $select): void {
    $rows = $this->visualRows($this->viewportWidth);
    [$visualRow, $column] = $this->visualCursor($rows);
    $this->preferredColumn = $this->preferredColumn === false ? $column : $this->preferredColumn;
    $target = max(0, min(count($rows) - 1, $visualRow + $distance));
    $row = $rows[$target];
    $grapheme = 0;
    while ($grapheme < $row['length'] && TextMetrics::width(TextMetrics::slice($row['text'], 0, $grapheme + 1)) <= $this->preferredColumn) {
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
      && TextMetrics::width(TextMetrics::slice($text, 0, $index + 1)) <= $target) {
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
    $lineWidths = array_map(static fn(string $line): int => TextMetrics::width($line), $this->lines);
    $this->scrollX = min($this->scrollX, max(0, max($lineWidths) - $this->viewportWidth + 1));
    if ($cursor[1] < $this->scrollX) {
      $this->scrollX = $cursor[1];
    } else if ($cursor[1] >= $this->scrollX + $this->viewportWidth) {
      $this->scrollX = $cursor[1] - $this->viewportWidth + 1;
    }
  }

}
