<?php

namespace SPTK\Widgets;

use SPTK\Core\Clipboard;
use SPTK\Core\Color;
use SPTK\Core\InputAction;
use SPTK\Core\InputEvent;
use SPTK\Core\PlainTextHighlighter;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;
use SPTK\Core\RuleSyntaxHighlighter;
use SPTK\Core\Scrollbar;
use SPTK\Core\SyntaxHighlighter;
use SPTK\Core\TextCursor;
use SPTK\Core\TextEditorHistory;
use SPTK\Core\Element;

/**
 * Provides a multiline text editor
 */
class TextEditor extends Element {

  protected array $lines = [''];
  protected TextCursor $cursor;
  protected TextEditorHistory $history;
  protected SyntaxHighlighter $highlighter;
  protected array $tokens = [];
  protected bool $tokensDirty = true;
  protected bool $readOnly = false;
  protected bool $autoWrap = false;
  protected array $wrapExemptStyles = [];
  protected array $wrapIndentStyles = [];
  protected array $lineFillStyles = [];
  protected int $scrollX = 0;
  protected int $scrollY = 0;
  protected int|false $preferredVisualColumn = false;
  protected array $highlightRanges = [];
  protected Color|string|int|null $fieldFg = null;
  protected Color|string|int|null $fieldBg = null;
  protected array $styleColors = [
    'plain' => [],
    'matched' => ['fg' => '#000000', 'bg' => '#ffff00'],
    'selected' => ['fg' => '#ffffff', 'bg' => '#000000'],
    'inactive-selected' => ['fg' => '#cccccc', 'bg' => '#555555'],
  ];

  public function __construct(string $name = '', string $text = '') {
    parent::__construct($name);
    $this->focusable = true;
    $this->setPreferredRows(16);
    $this->highlighter = new PlainTextHighlighter();
    $this->setLines(explode("\n", $text));
  }

  public function text(): string {
    return implode("\n", $this->lines);
  }

  public function getValue(): array {
    return $this->lines;
  }

  public function setFrame(Rect $frame): void {
    parent::setFrame($frame);
    $this->syncScroll(false);
  }

  public function setValue(mixed $value): void {
    $this->setText(is_array($value) ? implode("\n", $value) : (string)$value);
  }

  public function setText(string $text): static {
    $this->setLines(explode("\n", $text));
    $this->scrollX = 0;
    $this->scrollY = 0;
    $this->highlightRanges = [];
    $this->history = new TextEditorHistory();
    $this->invalidateRender();
    return $this;
  }

  public function setValueAndState(string $value, array $state): void {
    $this->setLines(explode("\n", $value));
    $this->restoreState($state);
  }

  public function saveState(): array {
    return [
      'cursor' => $this->cursor->selectionState(),
      'history' => $this->history->saveState(),
      'scrollX' => $this->scrollX,
      'scrollY' => $this->scrollY,
      'readOnly' => $this->readOnly,
      'autoWrap' => $this->autoWrap,
      'wrapExemptStyles' => $this->wrapExemptStyles,
      'wrapIndentStyles' => $this->wrapIndentStyles,
      'lineFillStyles' => $this->lineFillStyles,
    ];
  }

  public function restoreState(array $state): void {
    if (isset($state['cursor']) && is_array($state['cursor'])) {
      $this->cursor->restoreState($state['cursor']);
    }
    if (isset($state['history']) && is_array($state['history'])) {
      $this->history->restoreState($state['history']);
    }
    $this->scrollX = max(0, (int)($state['scrollX'] ?? $this->scrollX));
    $this->scrollY = max(0, (int)($state['scrollY'] ?? $this->scrollY));
    $this->readOnly = (bool)($state['readOnly'] ?? $this->readOnly);
    $this->autoWrap = (bool)($state['autoWrap'] ?? $this->autoWrap);
    if (isset($state['wrapExemptStyles']) && is_array($state['wrapExemptStyles'])) {
      $this->wrapExemptStyles = array_values(array_unique(array_map('strval', $state['wrapExemptStyles'])));
    }
    if (isset($state['wrapIndentStyles']) && is_array($state['wrapIndentStyles'])) {
      $this->wrapIndentStyles = array_values(array_unique(array_map('strval', $state['wrapIndentStyles'])));
    }
    if (isset($state['lineFillStyles']) && is_array($state['lineFillStyles'])) {
      $this->lineFillStyles = array_values(array_unique(array_map('strval', $state['lineFillStyles'])));
    }
    $this->syncScroll(false);
    $this->invalidateRender();
  }

  public function setReadOnly(mixed $value): static {
    $this->readOnly = $value === true || $value === 1 || $value === '1' || $value === 'true';
    return $this;
  }

  public function getReadOnly(): bool {
    return $this->readOnly;
  }

  public function setFile(string|false $file): static {
    if ($file === false) {
      return $this;
    }
    if ($file !== '' && $file[0] !== '/') {
      $file = getcwd() . '/' . $file;
    }
    if (is_file($file)) {
      $content = file_get_contents($file);
      if ($content !== false) {
        $this->setText($content);
      }
    }
    return $this;
  }

  public function setCursorPosition(int $row, int $column): static {
    $this->cursor->setPosition($row, $column);
    $this->syncScroll();
    $this->invalidateRender();
    return $this;
  }

  public function cursorPosition(): array {
    return $this->cursor->position();
  }

  public function setHighlightRanges(array $ranges): static {
    $this->highlightRanges = [];
    foreach ($ranges as $range) {
      if (is_array($range) && count($range) >= 4) {
        $this->highlightRanges[] = [(int)$range[0], (int)$range[1], (int)$range[2], (int)$range[3]];
      }
    }
    $this->invalidateRender();
    return $this;
  }

  public function clearHighlightRanges(): static {
    $this->highlightRanges = [];
    $this->invalidateRender();
    return $this;
  }

  public function scrollToRow(int $row): static {
    $this->scrollY = max(0, $row);
    $this->syncScroll(false);
    $this->invalidateRender();
    return $this;
  }

  public function scrollState(): array {
    return ['x' => $this->scrollX, 'y' => $this->scrollY];
  }

  public function setAutoWrap(bool $autoWrap = true): static {
    $this->autoWrap = $autoWrap;
    $this->syncScroll();
    $this->invalidateRender();
    return $this;
  }

  public function autoWrap(): bool {
    return $this->autoWrap;
  }

  public function setWrapExemptStyles(array $styles): static {
    $this->wrapExemptStyles = array_values(array_unique(array_filter(array_map('strval', $styles), fn(string $style): bool => $style !== '')));
    $this->syncScroll(false);
    $this->invalidateRender();
    return $this;
  }

  public function setWrapIndentStyles(array $styles): static {
    $this->wrapIndentStyles = array_values(array_unique(array_filter(array_map('strval', $styles), fn(string $style): bool => $style !== '')));
    $this->syncScroll(false);
    $this->invalidateRender();
    return $this;
  }

  public function setLineFillStyles(array $styles): static {
    $this->lineFillStyles = array_values(array_unique(array_filter(array_map('strval', $styles), fn(string $style): bool => $style !== '')));
    $this->invalidateRender();
    return $this;
  }

  public function setStyleColors(array $colors): static {
    foreach ($colors as $style => $pair) {
      if (is_array($pair)) {
        $this->styleColors[(string)$style] = [
          'fg' => $pair['fg'] ?? $pair[0] ?? null,
          'bg' => $pair['bg'] ?? $pair[1] ?? null,
        ];
      }
    }
    $this->invalidateRender();
    return $this;
  }

  public function setFieldColors(Color|string|int|null $fg = null, Color|string|int|null $bg = null): static {
    $this->fieldFg = $fg;
    $this->fieldBg = $bg;
    $this->invalidateRender();
    return $this;
  }

  public function setHighlighter(SyntaxHighlighter|array|null $highlighter): static {
    if ($highlighter === null) {
      $this->highlighter = new PlainTextHighlighter();
    } else if (is_array($highlighter)) {
      $this->highlighter = new RuleSyntaxHighlighter($highlighter);
    } else {
      $this->highlighter = $highlighter;
    }
    $this->tokensDirty = true;
    $this->invalidateRender();
    return $this;
  }

  public function setTokenizer(mixed $tokenizer): static {
    if ($tokenizer === false || $tokenizer === 'false' || $tokenizer === null) {
      return $this->setHighlighter(null);
    }
    if (is_array($tokenizer) || $tokenizer instanceof SyntaxHighlighter) {
      return $this->setHighlighter($tokenizer);
    }
    if (is_string($tokenizer) && class_exists($tokenizer)) {
      $instance = new $tokenizer();
      if ($instance instanceof SyntaxHighlighter) {
        return $this->setHighlighter($instance);
      }
    }
    return $this;
  }

  public function insertText(string $text): void {
    if ($this->readOnly || $text === '') {
      return;
    }
    $beforePosition = $this->cursor->position();
    $this->replaceSelection(explode("\n", $text), count(preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []) === 1 ? $beforePosition[0] : null);
  }

  public function replaceText(string $text): void {
    if ($this->readOnly) {
      return;
    }
    $beforeLines = $this->lines;
    $beforeCursor = $this->cursor->selectionState();
    $this->setLines(explode("\n", $text));
    $last = count($this->lines) - 1;
    $this->cursor->setPosition($last, mb_strlen($this->lines[$last]));
    $this->history->record($beforeLines, $beforeCursor, $this->lines, $this->cursor->selectionState());
    $this->afterEdit();
  }

  public function handle(InputEvent $event): bool {
    if ($event->type === 'text') {
      if ($this->readOnly) {
        return true;
      }
      $this->insertText($event->text);
      return true;
    }
    if ($event->type !== 'key') {
      return false;
    }
    return $this->handleKey($event);
  }

  protected function handleKey(InputEvent $event): bool {
    $ctrl = !empty($event->modifiers['ctrl']);
    $shift = !empty($event->modifiers['shift']);

    if (InputAction::selectAll($event, 'editor')) {
      $this->cursor->selectAll();
      $this->syncScroll();
      $this->invalidateRender();
      return true;
    }
    if (InputAction::copy($event, 'editor')) {
      Clipboard::set($this->cursor->selectedText());
      $this->cursor->collapse();
      $this->invalidateRender();
      return true;
    }
    if (!$this->readOnly && InputAction::cut($event, 'editor')) {
      Clipboard::set($this->cursor->selectedText());
      $this->deleteSelection();
      return true;
    }
    if (!$this->readOnly && InputAction::paste($event, 'editor')) {
      $this->insertText(Clipboard::get());
      return true;
    }
    if (InputAction::undo($event, 'editor')) {
      if (!$this->readOnly) {
        $this->undo();
      }
      return true;
    }
    if (InputAction::redo($event, 'editor')) {
      if (!$this->readOnly) {
        $this->redo();
      }
      return true;
    }

    $handled = true;
    if (InputAction::left($event, 'editor')) {
      if ($ctrl) {
        $this->cursor->moveLineStart($shift);
        $this->preferredVisualColumn = false;
      } else {
        $this->moveHorizontal(-1, $shift);
      }
    } else if (InputAction::right($event, 'editor')) {
      if ($ctrl) {
        $this->cursor->moveLineEnd($shift);
        $this->preferredVisualColumn = false;
      } else {
        $this->moveHorizontal(1, $shift);
      }
    } else if (InputAction::up($event, 'editor')) {
      $this->moveVerticalVisual(-1, $shift);
    } else if (InputAction::down($event, 'editor')) {
      $this->moveVerticalVisual(1, $shift);
    } else if (InputAction::home($event, 'editor')) {
      $ctrl ? $this->pageToFirstHorizontalEdge($shift) : $this->cursor->moveLineStart($shift);
      $this->preferredVisualColumn = false;
    } else if (InputAction::end($event, 'editor')) {
      $ctrl ? $this->pageToLastHorizontalEdge($shift) : $this->cursor->moveLineEnd($shift);
      $this->preferredVisualColumn = false;
    } else if (InputAction::pageUp($event, 'editor')) {
      $ctrl ? $this->moveToDocumentRowPreservingColumn(0, $shift) : $this->cursor->moveUp($shift, $this->visibleRows());
      $this->preferredVisualColumn = false;
    } else if (InputAction::pageDown($event, 'editor')) {
      $ctrl ? $this->moveToDocumentRowPreservingColumn(count($this->lines) - 1, $shift) : $this->cursor->moveDown($shift, $this->visibleRows());
      $this->preferredVisualColumn = false;
    } else if (!$this->readOnly && InputAction::newline($event, 'editor')) {
      $this->insertText("\n");
    } else if (!$this->readOnly && InputAction::backspace($event, 'editor')) {
      $this->backspace();
    } else if (!$this->readOnly && InputAction::delete($event, 'editor')) {
      $this->deleteForward();
    } else if ($this->readOnly && (InputAction::newline($event, 'editor') || InputAction::backspace($event, 'editor') || InputAction::delete($event, 'editor'))) {
      return true;
    } else {
      $handled = false;
    }

    if ($handled && !InputAction::newline($event, 'editor') && !InputAction::backspace($event, 'editor') && !InputAction::delete($event, 'editor')) {
      $this->syncScroll();
      $this->invalidateRender();
    }
    return $handled;
  }

  protected function paint(RenderTarget $target): void {
    $this->syncScroll(false);
    $target->fill($this->frame, ' ', $this->fieldFg(), $this->fieldBg());
    $content = $this->contentRect();
    $rows = $this->visibleDocumentRows();
    foreach ($rows as $offset => $rowInfo) {
      $screenY = $content->y + $offset;
      if ($screenY >= $content->bottom()) {
        break;
      }
      $this->paintRow($target, $content, $screenY, $rowInfo);
    }
    $this->paintCursor($target, $content, $rows);
    $this->paintScrollbars($target, $content);
  }

  protected function setLines(array $lines): void {
    $this->lines = $lines === [] ? [''] : array_values($lines);
    if ($this->lines === []) {
      $this->lines = [''];
    }
    $this->cursor = new TextCursor($this->lines);
    $this->history = new TextEditorHistory();
    $this->tokensDirty = true;
  }

  protected function replaceSelection(array $replacement, ?int $groupRow = null): void {
    $beforeLines = $this->lines;
    $beforeCursor = $this->cursor->selectionState();
    [$row1, $col1, $row2, $col2] = $this->cursor->range();
    $insertedLastLength = mb_strlen($replacement[count($replacement) - 1] ?? '');
    $before = mb_substr($this->lines[$row1], 0, $col1);
    $after = mb_substr($this->lines[$row2], $col2);
    $last = count($replacement) - 1;
    $replacement[0] = $before . $replacement[0];
    $replacement[$last] .= $after;
    array_splice($this->lines, $row1, $row2 - $row1 + 1, $replacement);
    $newRow = $row1 + $last;
    $newCol = $last === 0 ? $col1 + $insertedLastLength : $insertedLastLength;
    $this->cursor->setPosition($newRow, $newCol);
    $this->history->record($beforeLines, $beforeCursor, $this->lines, $this->cursor->selectionState(), $groupRow);
    $this->afterEdit();
  }

  protected function deleteSelection(): void {
    if (!$this->cursor->hasSelection()) {
      return;
    }
    $this->replaceSelection(['']);
  }

  protected function backspace(): void {
    if ($this->cursor->hasSelection()) {
      $this->deleteSelection();
      return;
    }
    [$row, $col] = $this->cursor->position();
    if ($row === 0 && $col === 0) {
      return;
    }
    $this->cursor->moveLeft(true);
    $this->replaceSelection(['']);
  }

  protected function deleteForward(): void {
    if ($this->cursor->hasSelection()) {
      $this->deleteSelection();
      return;
    }
    [$row, $col] = $this->cursor->position();
    if ($row === count($this->lines) - 1 && $col === mb_strlen($this->lines[$row])) {
      return;
    }
    $this->cursor->moveRight(true);
    $this->replaceSelection(['']);
  }

  protected function undo(): void {
    $state = $this->history->undo($this->lines);
    if ($state === null) {
      return;
    }
    $this->cursor = new TextCursor($this->lines);
    $this->cursor->restoreState($state);
    $this->afterEdit(false);
  }

  protected function redo(): void {
    $state = $this->history->redo($this->lines);
    if ($state === null) {
      return;
    }
    $this->cursor = new TextCursor($this->lines);
    $this->cursor->restoreState($state);
    $this->afterEdit(false);
  }

  protected function afterEdit(bool $clearHighlights = true): void {
    if ($clearHighlights) {
      $this->highlightRanges = [];
    }
    $this->tokensDirty = true;
    $this->syncScroll();
    $this->invalidateRender();
  }

  protected function tokens(): array {
    if ($this->tokensDirty) {
      $this->tokens = $this->highlighter->highlight($this->lines);
      $this->tokensDirty = false;
    }
    return $this->tokens;
  }

  protected function contentRect(): Rect {
    return new Rect($this->frame->x, $this->frame->y, max(0, $this->frame->width), max(0, $this->frame->height));
  }

  protected function visibleRows(): int {
    return max(1, $this->contentRect()->height);
  }

  protected function contentColumns(): int {
    return max(1, $this->contentRect()->width);
  }

  protected function visibleDocumentRows(): array {
    if ($this->autoWrap) {
      return array_slice($this->wrappedRows(), $this->scrollY, $this->contentRect()->height);
    }
    $rows = [];
    for ($i = 0; $i < $this->contentRect()->height; $i++) {
      $row = $this->scrollY + $i;
      if (!isset($this->lines[$row])) {
        break;
      }
      $rows[] = ['row' => $row, 'start' => $this->scrollX, 'length' => $this->contentColumns(), 'text' => $this->lines[$row]];
    }
    return $rows;
  }

  protected function wrappedRows(): array {
    $rows = [];
    $columns = $this->contentColumns();
    foreach ($this->lines as $row => $line) {
      $row = (int)$row;
      if ($this->lineWrapExempt((int)$row)) {
        $rows[] = [
          'row' => $row,
          'start' => 0,
          'length' => mb_strlen($line),
          'text' => $line,
          'wrapped' => false,
          'screenIndent' => 0,
        ];
        continue;
      }
      $indent = $this->wrapIndentForLine($row);
      foreach ($this->wrappedLineSegments($line, $columns, $indent) as $offset => $segment) {
        $rows[] = [
          'row' => $row,
          'start' => $segment['start'],
          'length' => $segment['length'],
          'text' => $line,
          'wrapped' => true,
          'screenIndent' => $offset === 0 ? 0 : $indent,
        ];
      }
    }
    return $rows === [] ? [['row' => 0, 'start' => 0, 'length' => 0, 'text' => '', 'wrapped' => true]] : $rows;
  }

  protected function wrappedLineSegments(string $line, int $columns, int $continuationIndent = 0): array {
    $length = mb_strlen($line);
    if ($length === 0) {
      return [['start' => 0, 'length' => 0]];
    }
    $segments = [];
    $start = 0;
    while ($start < $length) {
      $availableColumns = count($segments) === 0 ? $columns : max(1, $columns - $continuationIndent);
      if ($start > 0) {
        while ($start < $length && preg_match('/\s/u', mb_substr($line, $start, 1)) === 1) {
          $start++;
        }
      }
      if ($start >= $length) {
        break;
      }
      $remaining = $length - $start;
      if ($remaining <= $availableColumns) {
        $segments[] = ['start' => $start, 'length' => $remaining];
        break;
      }
      $take = $availableColumns;
      $nextStart = $start + $availableColumns;
      if (preg_match('/\s/u', mb_substr($line, $start + $availableColumns, 1)) === 1) {
        $nextStart++;
      } else {
        for ($i = $availableColumns - 1; $i > 0; $i--) {
          if (preg_match('/\s/u', mb_substr($line, $start + $i, 1)) === 1) {
            $take = $i;
            $nextStart = $start + $i + 1;
            break;
          }
        }
      }
      if ($take <= 0 || $nextStart <= $start) {
        $take = min($availableColumns, $remaining);
        $nextStart = $start + $take;
      }
      $segments[] = ['start' => $start, 'length' => $take];
      $start = $nextStart;
    }
    return $segments;
  }

  protected function paintRow(RenderTarget $target, Rect $content, int $screenY, array $rowInfo): void {
    $row = $rowInfo['row'];
    $wrapped = (bool)($rowInfo['wrapped'] ?? false);
    $firstCol = $wrapped ? (int)$rowInfo['start'] : (int)($this->autoWrap ? $this->scrollX : $rowInfo['start']);
    $screenIndent = (int)($rowInfo['screenIndent'] ?? 0);
    $line = $rowInfo['text'];
    $tokens = $this->tokens()[$row] ?? [];
    for ($x = 0; $x < $content->width; $x++) {
      $sourceX = $x - $screenIndent;
      $col = $firstCol + $sourceX;
      $inSegment = $sourceX >= 0 && ($wrapped ? $sourceX < $rowInfo['length'] : $col <= mb_strlen($line));
      $glyph = $inSegment ? mb_substr($line, $col, 1) : '';
      if ($glyph === '') {
        $glyph = ' ';
      }
      $style = $inSegment ? $this->styleAt($tokens, $col) : 'plain';
      if ($glyph === ' ' && (!$inSegment || $col >= mb_strlen($line))) {
        $style = $this->lineFillStyle($tokens);
      }
      $colors = $this->colorsFor($style);
      if ($this->inHighlight($row, $col)) {
        $colors = $this->colorsFor('matched');
      }
      if ($this->inSelection($row, $col)) {
        $colors = $this->focused
          ? $this->colorsFor('selected')
          : ['fg' => $this->inactiveCursorFg(), 'bg' => $this->theme->inactiveCursorBg];
      }
      $target->put($content->x + $x, $screenY, $glyph, $colors['fg'], $colors['bg']);
    }
  }

  protected function paintCursor(RenderTarget $target, Rect $content, array $rows): void {
    if ($content->width <= 0 || $content->height <= 0) {
      return;
    }
    [$row, $col] = $this->cursor->position();
    foreach ($rows as $offset => $rowInfo) {
      if ($rowInfo['row'] !== $row) {
        continue;
      }
      $firstCol = (bool)($rowInfo['wrapped'] ?? false)
        ? (int)$rowInfo['start']
        : (int)($this->autoWrap ? $this->scrollX : $rowInfo['start']);
      $screenIndent = (int)($rowInfo['screenIndent'] ?? 0);
      if ((bool)($rowInfo['wrapped'] ?? false)) {
        $lineLength = $this->lineLength($row);
        $end = $firstCol + (int)$rowInfo['length'];
        $nextStart = $this->nextWrappedSegmentStart($row, $firstCol);
        $canShowSegmentEnd = $col === $end
          && $screenIndent + (int)$rowInfo['length'] < $content->width
          && ($col === $lineLength || ($nextStart !== null && $nextStart > $end));
        if (($col < $firstCol || $col >= $end) && !$canShowSegmentEnd) {
          continue;
        }
      } else if ($col < $firstCol || $col > $firstCol + $content->width - $screenIndent - 1) {
        continue;
      }
      $x = $content->x + $screenIndent + $col - $firstCol;
      $y = $content->y + $offset;
      $glyph = mb_substr($this->lines[$row] ?? '', $col, 1);
      if ($glyph === '') {
        $glyph = '¶';
      }
      $target->put(
        $x,
        $y,
        $glyph,
        $this->focused ? $this->theme->cursorFg : $this->inactiveCursorFg(),
        $this->focused ? $this->theme->cursorBg : $this->theme->inactiveCursorBg
      );
      return;
    }
  }

  protected function paintScrollbars(RenderTarget $target, Rect $content): void {
    if ($this->needsVerticalScrollbar()) {
      Scrollbar::paintBar(
        $target,
        new Rect($this->frame->right() - 1, $this->frame->y, 1, $this->frame->height),
        $this->scrollY,
        max(1, $this->frame->height),
        $this->visualRowCount(),
        'vertical',
        $this->theme->markerFg
      );
    }
    if ($this->needsHorizontalScrollbar()) {
      Scrollbar::paintBar(
        $target,
        new Rect($this->frame->x, $this->frame->bottom() - 1, $this->frame->width, 1),
        $this->scrollX,
        max(1, $this->frame->width),
        $this->horizontalColumnCount(),
        'horizontal',
        $this->theme->markerFg
      );
    }
  }

  protected function syncScroll(bool $followCursor = true): void {
    $content = $this->contentRect();
    if ($content->width <= 0 || $content->height <= 0) {
      return;
    }
    if ($followCursor) {
      [$row, $col] = $this->cursor->position();
      $visualRow = $this->autoWrap ? $this->visualRowForCursor($row, $col) : $row;
      if ($visualRow < $this->scrollY) {
        $this->scrollY = $visualRow;
      } else if ($visualRow >= $this->scrollY + $content->height) {
        $this->scrollY = $visualRow - $content->height + 1;
      }
      if ($this->autoWrap) {
        if ($this->lineWrapExempt($row)) {
          if ($col < $this->scrollX) {
            $this->scrollX = $col;
          } else if ($col >= $this->scrollX + $content->width) {
            $this->scrollX = $col - $content->width + 1;
          }
        } else {
          $this->scrollX = 0;
        }
      } else if ($col < $this->scrollX) {
        $this->scrollX = $col;
      } else if ($col >= $this->scrollX + $content->width) {
        $this->scrollX = $col - $content->width + 1;
      }
    }
    $this->scrollY = max(0, min($this->scrollY, max(0, $this->visualRowCount() - $content->height)));
    $this->scrollX = max(0, min($this->scrollX, max(0, $this->horizontalColumnCount() - $content->width)));
  }

  protected function moveToDocumentRowPreservingColumn(int $row, bool $select): void {
    [, $col] = $this->cursor->position();
    $this->cursor->setPosition($row, $col, $select);
  }

  protected function moveHorizontal(int $delta, bool $select): void {
    if ($delta < 0) {
      $this->cursor->moveLeft($select);
    } else {
      $this->cursor->moveRight($select);
    }
    $this->preferredVisualColumn = false;
    $this->normalizeWrappedCursor($delta, $select);
  }

  protected function moveVerticalVisual(int $delta, bool $select): void {
    if (!$this->autoWrap) {
      $delta < 0 ? $this->cursor->moveUp($select) : $this->cursor->moveDown($select);
      $this->preferredVisualColumn = false;
      return;
    }

    [$row, $col] = $this->cursor->position();
    if ($this->lineWrapExempt($row)) {
      $delta < 0 ? $this->cursor->moveUp($select) : $this->cursor->moveDown($select);
      $this->preferredVisualColumn = false;
      return;
    }

    $rows = $this->wrappedRows();
    $current = $this->wrappedVisualIndexForCursor($row, $col);
    $target = max(0, min(count($rows) - 1, $current + $delta));
    if ($target === $current) {
      return;
    }

    $currentInfo = $rows[$current] ?? null;
    $targetInfo = $rows[$target] ?? null;
    if ($currentInfo === null || $targetInfo === null || !(bool)($targetInfo['wrapped'] ?? false)) {
      $delta < 0 ? $this->cursor->moveUp($select) : $this->cursor->moveDown($select);
      $this->preferredVisualColumn = false;
      return;
    }

    if ($this->preferredVisualColumn === false) {
      $this->preferredVisualColumn = $this->visualColumnInWrappedRow($currentInfo, $col);
    }
    $newCol = $this->documentColumnForWrappedVisualColumn($targetInfo, $this->preferredVisualColumn);
    $this->cursor->setPosition((int)$targetInfo['row'], $newCol, $select);
  }

  protected function normalizeWrappedCursor(int $direction, bool $select): void {
    [$row, $col] = $this->cursor->position();
    if (!$this->autoWrap || $this->lineWrapExempt($row)) {
      return;
    }
    $segments = $this->wrappedLineSegments($this->lines[$row] ?? '', $this->contentColumns(), $this->wrapIndentForLine($row));
    foreach ($segments as $index => $segment) {
      $start = (int)$segment['start'];
      $end = $start + (int)$segment['length'];
      if ($col >= $start && $col < $end) {
        return;
      }
      $next = $segments[$index + 1] ?? null;
      if ($next !== null && $col >= $end && $col < (int)$next['start']) {
        $this->cursor->setPosition(
          $row,
          $direction < 0 ? $this->visibleWrappedSegmentEndColumn($row, $segment) : (int)$next['start'],
          $select
        );
        return;
      }
    }
  }

  protected function pageToFirstHorizontalEdge(bool $select): void {
    $content = $this->contentRect();
    $viewport = max(1, $content->width);
    [$row, $col] = $this->cursor->position();
    if ($this->autoWrap && !$this->lineWrapExempt($row)) {
      $segment = $this->wrappedSegmentForCursor($row, $col);
      $this->cursor->setPosition($row, (int)($segment['start'] ?? 0), $select);
      return;
    }
    $first = min($this->lineLength($row), $this->scrollX);
    if ($col === $first && $this->scrollX > 0) {
      $this->scrollX = max(0, $this->scrollX - $viewport);
      $first = min($this->lineLength($row), $this->scrollX);
    }
    $this->cursor->setPosition($row, $first, $select);
    if ($first === 0) {
      $this->scrollX = 0;
    }
  }

  protected function pageToLastHorizontalEdge(bool $select): void {
    $content = $this->contentRect();
    $viewport = max(1, $content->width);
    [$row, $col] = $this->cursor->position();
    if ($this->autoWrap && !$this->lineWrapExempt($row)) {
      $segment = $this->wrappedSegmentForCursor($row, $col);
      $start = (int)($segment['start'] ?? 0);
      $length = (int)($segment['length'] ?? 0);
      $this->cursor->setPosition($row, $length > 0 ? $this->visibleWrappedSegmentEndColumn($row, $segment) : $start, $select);
      return;
    }
    $maxOffset = max(0, $this->horizontalColumnCount() - $viewport);
    $last = min($this->lineLength($row), $this->scrollX + $viewport - 1);
    if ($col === $last && $this->scrollX < $maxOffset) {
      $this->scrollX = min($maxOffset, $this->scrollX + $viewport);
      $last = min($this->lineLength($row), $this->scrollX + $viewport - 1);
    }
    $this->cursor->setPosition($row, $last, $select);
  }

  protected function visualRowForCursor(int $row, int $col): int {
    $visual = 0;
    $columns = $this->contentColumns();
    for ($i = 0; $i < $row; $i++) {
      $visual += $this->lineWrapExempt($i) ? 1 : count($this->wrappedLineSegments($this->lines[$i] ?? '', $columns, $this->wrapIndentForLine($i)));
    }
    if ($this->lineWrapExempt($row)) {
      return $visual;
    }
    return $visual + $this->wrappedSegmentIndexForColumn($row, $col);
  }

  protected function wrappedVisualIndexForCursor(int $row, int $col): int {
    $visual = 0;
    $columns = $this->contentColumns();
    for ($i = 0; $i < $row; $i++) {
      $visual += $this->lineWrapExempt($i) ? 1 : count($this->wrappedLineSegments($this->lines[$i] ?? '', $columns, $this->wrapIndentForLine($i)));
    }
    return $visual + ($this->lineWrapExempt($row) ? 0 : $this->wrappedSegmentIndexForColumn($row, $col));
  }

  protected function wrappedSegmentForCursor(int $row, int $col): array {
    $segments = $this->wrappedLineSegments($this->lines[$row] ?? '', $this->contentColumns(), $this->wrapIndentForLine($row));
    return $segments[$this->wrappedSegmentIndexForColumn($row, $col)] ?? ['start' => 0, 'length' => 0];
  }

  protected function wrappedSegmentIndexForColumn(int $row, int $col): int {
    $segments = $this->wrappedLineSegments($this->lines[$row] ?? '', $this->contentColumns(), $this->wrapIndentForLine($row));
    $last = max(0, count($segments) - 1);
    foreach ($segments as $offset => $segment) {
      $start = (int)$segment['start'];
      $end = $start + (int)$segment['length'];
      if ($col >= $start && $col < $end) {
        return (int)$offset;
      }
      $next = $segments[$offset + 1] ?? null;
      if ($next !== null && $col >= $end && $col < (int)$next['start']) {
        return (int)$offset;
      }
    }
    return $col >= $this->lineLength($row) ? $last : 0;
  }

  protected function visibleWrappedSegmentEndColumn(int $row, array $segment): int {
    $start = (int)$segment['start'];
    $length = (int)$segment['length'];
    if ($length <= 0) {
      return $start;
    }
    $end = $start + $length;
    $nextStart = $this->nextWrappedSegmentStart($row, $start);
    if ($this->wrappedSegmentScreenIndent($row, $start) + $length < $this->contentColumns() && ($end === $this->lineLength($row) || ($nextStart !== null && $nextStart > $end))) {
      return $end;
    }
    return $end - 1;
  }

  protected function wrappedSegmentScreenIndent(int $row, int $segmentStart): int {
    if ($segmentStart === 0) {
      return 0;
    }
    $segments = $this->wrappedLineSegments($this->lines[$row] ?? '', $this->contentColumns(), $this->wrapIndentForLine($row));
    return isset($segments[0]) && (int)$segments[0]['start'] === $segmentStart ? 0 : $this->wrapIndentForLine($row);
  }

  protected function nextWrappedSegmentStart(int $row, int $segmentStart): ?int {
    $segments = $this->wrappedLineSegments($this->lines[$row] ?? '', $this->contentColumns(), $this->wrapIndentForLine($row));
    foreach ($segments as $index => $segment) {
      if ((int)$segment['start'] === $segmentStart) {
        return isset($segments[$index + 1]) ? (int)$segments[$index + 1]['start'] : null;
      }
    }
    return null;
  }

  protected function visualColumnInWrappedRow(array $rowInfo, int $col): int {
    $start = (int)$rowInfo['start'];
    $length = (int)$rowInfo['length'];
    $sourceColumn = max(0, min(max(0, $length - 1), $col - $start));
    return (int)($rowInfo['screenIndent'] ?? 0) + $sourceColumn;
  }

  protected function documentColumnForWrappedVisualColumn(array $rowInfo, int $visualColumn): int {
    $start = (int)$rowInfo['start'];
    $length = (int)$rowInfo['length'];
    $sourceColumn = max(0, $visualColumn - (int)($rowInfo['screenIndent'] ?? 0));
    return $start + min(max(0, $length - 1), $sourceColumn);
  }

  protected function visualRowCount(): int {
    return $this->autoWrap ? count($this->wrappedRows()) : count($this->lines);
  }

  protected function needsVerticalScrollbar(): bool {
    if ($this->autoWrap) {
      return $this->wrappedRowCount(max(1, $this->frame->width)) > max(1, $this->frame->height);
    }
    return $this->visualRowCount() > max(1, $this->frame->height);
  }

  protected function needsHorizontalScrollbar(): bool {
    return $this->horizontalColumnCount() > max(1, $this->frame->width);
  }

  protected function visualColumnCount(): int {
    return $this->maxLineLength() + 1;
  }

  protected function horizontalColumnCount(): int {
    return $this->autoWrap ? $this->maxWrapExemptLineLength() + 1 : $this->visualColumnCount();
  }

  protected function maxLineLength(): int {
    $max = 0;
    foreach ($this->lines as $line) {
      $max = max($max, mb_strlen($line));
    }
    return max(1, $max);
  }

  protected function lineLength(int $row): int {
    return mb_strlen($this->lines[$row] ?? '');
  }

  protected function maxWrapExemptLineLength(): int {
    $max = 0;
    foreach ($this->lines as $row => $line) {
      if ($this->lineWrapExempt((int)$row)) {
        $max = max($max, mb_strlen($line));
      }
    }
    return $max;
  }

  protected function lineWrapExempt(int $row): bool {
    if ($this->wrapExemptStyles === []) {
      return false;
    }
    $line = $this->lines[$row] ?? '';
    if (trim($line) === '') {
      return false;
    }
    $exempt = array_flip($this->wrapExemptStyles);
    $tokens = $this->tokens()[$row] ?? [];
    $hasStyledContent = false;
    foreach ($tokens as $token) {
      $start = (int)($token['start'] ?? 0);
      $length = (int)($token['length'] ?? mb_strlen((string)($token['value'] ?? '')));
      if ($length <= 0) {
        continue;
      }
      $text = (string)($token['value'] ?? mb_substr($line, $start, $length));
      if (trim($text) === '') {
        continue;
      }
      $hasStyledContent = true;
      $style = (string)($token['style'] ?? $token['type'] ?? 'plain');
      if (!isset($exempt[$style])) {
        return false;
      }
    }
    return $hasStyledContent;
  }

  protected function wrapIndentForLine(int $row): int {
    if ($this->wrapIndentStyles === []) {
      return 0;
    }
    $line = $this->lines[$row] ?? '';
    if (trim($line) === '') {
      return 0;
    }
    $styles = array_flip($this->wrapIndentStyles);
    foreach ($this->tokens()[$row] ?? [] as $token) {
      $start = (int)($token['start'] ?? 0);
      $length = (int)($token['length'] ?? mb_strlen((string)($token['value'] ?? '')));
      if ($length <= 0) {
        continue;
      }
      $text = (string)($token['value'] ?? mb_substr($line, $start, $length));
      if (trim($text) === '') {
        continue;
      }
      $style = (string)($token['style'] ?? $token['type'] ?? 'plain');
      return isset($styles[$style]) ? $start + $length : 0;
    }
    return 0;
  }

  protected function lineFillStyle(array $tokens): string {
    if ($this->lineFillStyles === []) {
      return 'plain';
    }
    $styles = array_flip($this->lineFillStyles);
    for ($i = count($tokens) - 1; $i >= 0; $i--) {
      $token = $tokens[$i];
      $length = (int)($token['length'] ?? mb_strlen((string)($token['value'] ?? '')));
      $style = (string)($token['style'] ?? $token['type'] ?? 'plain');
      if (isset($styles[$style])) {
        return $style;
      }
      if ($length <= 0) {
        continue;
      }
      return 'plain';
    }
    return 'plain';
  }

  protected function wrappedRowCount(int $columns): int {
    $count = 0;
    foreach ($this->lines as $row => $line) {
      $count += $this->lineWrapExempt((int)$row) ? 1 : count($this->wrappedLineSegments($line, $columns, $this->wrapIndentForLine((int)$row)));
    }
    return max(1, $count);
  }

  protected function styleAt(array $tokens, int $column): string {
    foreach ($tokens as $token) {
      $start = (int)($token['start'] ?? 0);
      $length = (int)($token['length'] ?? mb_strlen((string)($token['value'] ?? '')));
      if ($column >= $start && $column < $start + $length) {
        return (string)($token['style'] ?? $token['type'] ?? 'plain');
      }
    }
    return 'plain';
  }

  protected function colorsFor(string $style): array {
    $colors = $this->styleColors[$style] ?? $this->highlighter->styleColors()[$style] ?? $this->styleColors['plain'];
    return [
      'fg' => $colors['fg'] ?? $this->fieldFg(),
      'bg' => $colors['bg'] ?? $this->fieldBg(),
    ];
  }

  protected function fieldFg(): Color {
    return Color::from($this->fieldFg ?? $this->theme->fg);
  }

  protected function inactiveCursorFg(): Color {
    return $this->fieldFg();
  }

  protected function fieldBg(): Color {
    $color = Color::from($this->fieldBg ?? $this->theme->bg);
    if ($this->readOnly) {
      $color = $this->desaturateColor($color);
    }
    if ($this->focused) {
      return $this->brightenColor($color);
    }
    return $color;
  }

  protected function brightenColor(Color $color): Color {
    return new Color(
      min(255, (int)round($color->r * 1.2)),
      min(255, (int)round($color->g * 1.2)),
      min(255, (int)round($color->b * 1.2)),
      $color->a
    );
  }

  protected function desaturateColor(Color $color): Color {
    $gray = (int)round($color->r * 0.3 + $color->g * 0.59 + $color->b * 0.11);
    $mix = 0.65;
    return new Color(
      (int)round($color->r * (1 - $mix) + $gray * $mix),
      (int)round($color->g * (1 - $mix) + $gray * $mix),
      (int)round($color->b * (1 - $mix) + $gray * $mix),
      $color->a
    );
  }

  protected function inSelection(int $row, int $col): bool {
    if (!$this->cursor->hasSelection()) {
      return false;
    }
    [$row1, $col1, $row2, $col2] = $this->cursor->range();
    if ($row < $row1 || $row > $row2) {
      return false;
    }
    if ($row === $row1 && $col < $col1) {
      return false;
    }
    if ($row === $row2 && $col >= $col2) {
      return false;
    }
    return true;
  }

  protected function inHighlight(int $row, int $col): bool {
    foreach ($this->highlightRanges as [$row1, $col1, $row2, $col2]) {
      if ($row < $row1 || $row > $row2) {
        continue;
      }
      if ($row === $row1 && $col < $col1) {
        continue;
      }
      if ($row === $row2 && $col >= $col2) {
        continue;
      }
      return true;
    }
    return false;
  }

}
