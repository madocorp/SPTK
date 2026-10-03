<?php

namespace SPTK\Widgets\TextEditor;

use SPTK\Core\{TextCursor, TextRows};
use SPTK\Rendering\TextMetrics;
use SPTK\SDLWrapper\SDL;

/** Tracks an editor's wrapped rows, viewport, and visual cursor movement. */
final class Navigator {

  private TextRows $layout;
  private int $width = 1;
  private int $height = 1;
  private int $scrollX = 0;
  private int $scrollY = 0;
  private ?int $preferredColumn = null;
  private int $revision = -1;
  private int $cachedRevision = -2;
  private int $cachedVisualWidth = -1;
  private array $cachedVisualRows = [];
  private int $contentWidth = 0;

  /** Create a navigator for a fixed wrapping mode and tab size. */
  public function __construct(private readonly bool $wrap, private readonly int $tabSize) {
    $this->layout = new TextRows();
  }

  /** Reset scroll and preferred column after an external value replacement. */
  public function reset(): void {
    $this->scrollX = 0;
    $this->scrollY = 0;
    $this->preferredColumn = null;
    $this->cachedRevision = -2;
  }

  /** Use the document version to keep visual rows until text or width changes. */
  public function setRevision(int $revision): void {
    $this->revision = $revision;
  }

  /** Return the current horizontal and vertical scroll offsets. */
  public function scroll(): array {
    return [$this->scrollX, $this->scrollY];
  }

  /** Return the cached display width of the widest document line. */
  public function contentWidth(): int {
    return $this->contentWidth;
  }

  /** Measure rows and keep an active caret visible inside the viewport. */
  public function layout(array $lines, TextCursor $cursor, int $width, int $height, bool $active): array {
    $this->width = max(1, $width);
    $this->height = max(1, $height);
    $rows = $this->rows($lines);
    $this->scrollY = min($this->scrollY, max(0, count($rows) - $this->height));
    $maxWidth = $this->contentWidth + ($active ? 1 : 0);
    $this->scrollX = $this->wrap ? 0 : min($this->scrollX, max(0, $maxWidth - $this->width));
    $visual = $this->layout->cursor($rows, $cursor, $this->tabSize);
    if ($active) {
      $this->scrollY = max(0, min($this->scrollY, $visual[0]));
      $this->scrollY = max($this->scrollY, $visual[0] - $this->height + 1);
      if (!$this->wrap) {
        $this->scrollX = max(0, min($this->scrollX, $visual[1]));
        $this->scrollX = max($this->scrollX, $visual[1] - $this->width + 1);
      }
    }
    return [$rows, $visual, $this->scrollY, $this->scrollX];
  }

  /** Handle visual navigation keys not shared with the one-line editor. */
  public function handle(int $key, int $modifiers, array $lines, TextCursor $cursor): bool {
    $ctrl = ($modifiers & SDL::MOD_CTRL) !== 0;
    $select = ($modifiers & SDL::MOD_SHIFT) !== 0;
    if ($key === SDL::KEY_UP || $key === SDL::KEY_DOWN) {
      $this->moveVertical($key === SDL::KEY_UP ? -1 : 1, $lines, $cursor, $select);
    } else if ($key === SDL::KEY_PAGEUP || $key === SDL::KEY_PAGEDOWN) {
      if ($ctrl) {
        $key === SDL::KEY_PAGEUP ? $cursor->moveDocumentStart($select) : $cursor->moveDocumentEnd($select);
      } else {
        $this->pageVertical($key === SDL::KEY_PAGEUP ? -1 : 1, $lines, $cursor, $select);
      }
    } else if ($ctrl && ($key === SDL::KEY_HOME || $key === SDL::KEY_END)) {
      $this->pageHorizontal($key === SDL::KEY_HOME ? -1 : 1, $lines, $cursor, $select);
    } else {
      $this->preferredColumn = null;
      return false;
    }
    return true;
  }

  /** Build visual rows from the current document lines. */
  private function rows(array $lines): array {
    if ($this->cachedRevision !== $this->revision || $this->cachedVisualWidth !== $this->width) {
      $this->cachedVisualRows = $this->layout->build($lines, $this->width, $this->wrap ? 'character' : 'none', $this->tabSize, true);
      $this->contentWidth = 0;
      if (!$this->wrap) {
        foreach ($lines as $line) {
          $this->contentWidth = max($this->contentWidth, TextMetrics::width($line, $this->tabSize));
        }
      }
      $this->cachedVisualWidth = $this->width;
      $this->cachedRevision = $this->revision;
    }
    return $this->cachedVisualRows;
  }

  /** Move by visual rows while retaining the preferred display column. */
  private function moveVertical(int $distance, array $lines, TextCursor $cursor, bool $select): void {
    $rows = $this->rows($lines);
    [$visual, $column] = $this->layout->cursor($rows, $cursor, $this->tabSize);
    $this->preferredColumn ??= $column;
    $target = max(0, min(count($rows) - 1, $visual + $distance));
    $row = $rows[$target];
    $index = TextMetrics::index($row['text'], $this->preferredColumn, $this->tabSize);
    $cursor->setPosition($row['line'], $row['start'] + $index, $select);
  }

  /** Move first to a viewport edge and then by a page. */
  private function pageVertical(int $direction, array $lines, TextCursor $cursor, bool $select): void {
    $rows = $this->rows($lines);
    [$visual] = $this->layout->cursor($rows, $cursor, $this->tabSize);
    $edge = $direction < 0 ? $this->scrollY : min(count($rows) - 1, $this->scrollY + $this->height - 1);
    if ($visual === $edge) {
      $this->scrollY = max(0, min(max(0, count($rows) - $this->height), $this->scrollY + $direction * $this->height));
      $edge = $direction < 0 ? $this->scrollY : min(count($rows) - 1, $this->scrollY + $this->height - 1);
    }
    $this->moveVertical($edge - $visual, $lines, $cursor, $select);
  }

  /** Move first to a horizontal viewport edge and then by a page. */
  private function pageHorizontal(int $direction, array $lines, TextCursor $cursor, bool $select): void {
    [$line, $index] = $cursor->position();
    $text = $lines[$line];
    if ($this->wrap) {
      $rows = $this->rows($lines);
      [$visual] = $this->layout->cursor($rows, $cursor, $this->tabSize);
      $row = $rows[$visual];
      $edge = $row['start'] + TextMetrics::index($row['text'], $direction < 0 ? 0 : $this->width - 1, $this->tabSize);
      if ($index === $edge) {
        $next = max(0, min(count($rows) - 1, $visual + $direction));
        if ($rows[$next]['line'] === $line) {
          $row = $rows[$next];
          $edge = $row['start'] + TextMetrics::index($row['text'], $direction < 0 ? 0 : $this->width - 1, $this->tabSize);
        }
      }
    } else {
      $edge = TextMetrics::index($text, $direction < 0 ? $this->scrollX : $this->scrollX + $this->width - 1, $this->tabSize);
      if ($index === $edge) {
        $extent = TextMetrics::width($text, $this->tabSize) + 1;
        $this->scrollX = max(0, min(max(0, $extent - $this->width), $this->scrollX + $direction * $this->width));
        $edge = TextMetrics::index($text, $direction < 0 ? $this->scrollX : $this->scrollX + $this->width - 1, $this->tabSize);
      }
    }
    $cursor->setPosition($line, $edge, $select);
  }

}
