<?php

namespace SPTK\Widgets\Text;

use SPTK\Core\{Color, Cursor, InputHandler, ScrollIndicator, Widget, WidgetEventEmitter};
use SPTK\Rendering\{GridWriter, TextMetrics};
use SPTK\SDLWrapper\SDL;

/** Displays read-only text with word wrapping, scrolling, and an active grid cursor. */
final class Text implements Widget, InputHandler {

  use WidgetEventEmitter;

  private array $lines;
  private Cursor $cursor;
  private int $scrollY = 0;
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
  ) {
    if (!mb_check_encoding($text, 'UTF-8')) {
      throw new \InvalidArgumentException('Text must be valid UTF-8.');
    }
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    if (preg_match('/[\x00-\x09\x0b-\x1f\x7f]/', $text)) {
      throw new \InvalidArgumentException('Text supports printable characters and newlines.');
    }
    $this->lines = explode("\n", $text);
    $this->cursor = new Cursor($this->lines);
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
    }
    for ($y = 0; $y < $writer->height(); $y++) {
      $row = $rows[$this->scrollY + $y] ?? null;
      if ($row !== null) {
        $writer->write(0, $y, $row['text'], $this->fg, $this->bg);
      }
    }
    $this->paintIndicators($writer, count($rows));
    if ($this->active) {
      $this->paintCursor($writer, $cursor, $rows);
    }
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
    $key = (int)$event->key->key;
    if ($key === SDL::KEY_LEFT) {
      $this->cursor->moveLeft();
      $this->preferredColumn = false;
    } else if ($key === SDL::KEY_RIGHT) {
      $this->cursor->moveRight();
      $this->preferredColumn = false;
    } else if ($key === SDL::KEY_UP || $key === SDL::KEY_DOWN || $key === SDL::KEY_PAGEUP || $key === SDL::KEY_PAGEDOWN) {
      $distance = match ($key) {
        SDL::KEY_UP => -1,
        SDL::KEY_DOWN => 1,
        SDL::KEY_PAGEUP => -$this->viewportHeight,
        default => $this->viewportHeight,
      };
      $this->moveVisualCursor($distance);
    } else if ($key === SDL::KEY_HOME) {
      [$row] = $this->cursor->position();
      $this->cursor->setPosition($row, 0);
      $this->preferredColumn = false;
    } else if ($key === SDL::KEY_END) {
      [$row] = $this->cursor->position();
      $this->cursor->setPosition($row, TextMetrics::length($this->lines[$row]));
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
      $segments = $this->wrapLine($line, $width);
      $start = 0;
      foreach ($segments as $segment) {
        $length = TextMetrics::length($segment);
        $rows[] = ['line' => $lineNumber, 'start' => $start, 'length' => $length, 'text' => $segment];
        $start += $length;
      }
    }
    return $rows;
  }

  /** Wrap at whitespace and split any word wider than the available row. */
  private function wrapLine(string $line, int $width): array {
    $words = preg_split('/\s+/u', trim($line), -1, PREG_SPLIT_NO_EMPTY);
    if ($words === [] || $words === false) {
      return [''];
    }
    $rows = [];
    $current = '';
    foreach ($words as $word) {
      if (TextMetrics::width($word) > $width) {
        if ($current !== '') {
          $rows[] = $current;
          $current = '';
        }
        foreach (TextMetrics::glyphs($word) as $glyph) {
          if ($current !== '' && TextMetrics::width($current . $glyph) > $width) {
            $rows[] = $current;
            $current = '';
          }
          $current .= $glyph;
        }
        $rows[] = $current;
        $current = '';
      } else if ($current === '') {
        $current = $word;
      } else if (TextMetrics::width($current . ' ' . $word) <= $width) {
        $current .= ' ' . $word;
      } else {
        $rows[] = $current;
        $current = $word;
      }
    }
    if ($current !== '') {
      $rows[] = $current;
    }
    return $rows === [] ? [''] : $rows;
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
      $offset = min($row['length'], $column - $row['start']);
      $cellColumn = TextMetrics::width(TextMetrics::slice($row['text'], 0, $offset));
    }
    return [$visualRow, min($cellColumn, max(0, $this->viewportWidth - 1))];
  }

  /** Move vertically between wrapped rows while retaining the display column. */
  private function moveVisualCursor(int $distance): void {
    $rows = $this->visualRows($this->viewportWidth);
    [$visualRow, $column] = $this->visualCursor($rows);
    $this->preferredColumn = $this->preferredColumn === false ? $column : $this->preferredColumn;
    $target = max(0, min(count($rows) - 1, $visualRow + $distance));
    $row = $rows[$target];
    $grapheme = 0;
    while ($grapheme < $row['length'] && TextMetrics::width(TextMetrics::slice($row['text'], 0, $grapheme + 1)) <= $this->preferredColumn) {
      $grapheme++;
    }
    $this->cursor->setPosition($row['line'], $row['start'] + $grapheme);
  }

  /** Paint mad2-style textual marks for hidden rows above and below. */
  private function paintIndicators(GridWriter $writer, int $rowCount): void {
    $above = ScrollIndicator::label($this->scrollY, $writer->height(), '▲');
    $below = ScrollIndicator::label(max(0, $rowCount - $this->scrollY - $writer->height()), $writer->height(), '▼');
    if ($above !== '') {
      $writer->write(0, 0, $above, $this->indicatorFg, $this->bg);
    }
    if ($below !== '') {
      $writer->write(max(0, $writer->width() - TextMetrics::width($below)), $writer->height() - 1, $below, $this->indicatorFg, $this->bg);
    }
  }

  /** Paint the active cursor over the corresponding visible text cell. */
  private function paintCursor(GridWriter $writer, array $cursor, array $rows): void {
    [$row, $column] = $cursor;
    $y = $row - $this->scrollY;
    if ($y < 0 || $y >= $writer->height()) {
      return;
    }
    $rowData = $rows[$row];
    [, $textColumn] = $this->cursor->position();
    $glyph = TextMetrics::slice($rowData['text'], $textColumn - $rowData['start'], 1);
    if ($glyph === '' || TextMetrics::width($glyph) + $column > $writer->width()) {
      $glyph = ' ';
    }
    $writer->set($column, $y, $glyph, $this->cursorFg, $this->cursorBg);
  }

}
