<?php

namespace SPTK\Widgets;

use SPTK\Core\Clipboard;
use SPTK\Core\Color;
use SPTK\Core\Element;
use SPTK\Core\InputAction;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;
use SPTK\Core\Scrollbar;
use SPTK\Core\TextCursor;
use SPTK\Core\TextEditorHistory;

/**
 * Provides a one-line text input field.
 */
class Input extends Element {

  protected array $lines = [''];
  protected TextCursor $cursor;
  protected TextEditorHistory $history;
  protected bool $readOnly = false;
  protected bool $password = false;
  protected string $placeholder = '';
  protected int $scrollX = 0;
  protected array $styleColors = [
    'selected' => ['fg' => '#ffffff', 'bg' => '#000000'],
    'inactive-selected' => ['fg' => '#cccccc', 'bg' => '#555555'],
  ];

  public function __construct(string $name = '', string $text = '') {
    parent::__construct($name);
    $this->focusable = true;
    $this->setPreferredRows(1);
    $this->setText($text);
  }

  public function text(): string {
    return $this->lines[0] ?? '';
  }

  public function getValue(): string {
    return $this->text();
  }

  public function setValue(mixed $value): void {
    $this->setText((string)$value);
  }

  public function setText(string $text): static {
    $this->lines = [$this->oneLine($text)];
    $this->cursor = new TextCursor($this->lines);
    $this->history = new TextEditorHistory();
    $this->scrollX = 0;
    $this->syncPreferredColumns();
    $this->invalidateRender();
    return $this;
  }

  public function setPlaceholder(string $placeholder): static {
    $this->placeholder = $this->oneLine($placeholder);
    $this->syncPreferredColumns();
    $this->invalidateRender();
    return $this;
  }

  public function setReadOnly(mixed $value): static {
    $this->readOnly = $value === true || $value === 1 || $value === '1' || $value === 'true';
    return $this;
  }

  public function getReadOnly(): bool {
    return $this->readOnly;
  }

  public function setPassword(bool $password = true): static {
    if ($this->password !== $password) {
      $this->password = $password;
      $this->invalidateRender();
    }
    return $this;
  }

  public function isPassword(): bool {
    return $this->password;
  }

  public function setCursorPosition(int $column): static {
    $this->cursor->setPosition(0, $column);
    $this->syncScroll();
    $this->invalidateRender();
    return $this;
  }

  public function cursorPosition(): int {
    [, $column] = $this->cursor->position();
    return $column;
  }

  public function scrollState(): array {
    return ['x' => $this->scrollX];
  }

  public function saveState(): array {
    return [
      'cursor' => $this->cursor->selectionState(),
      'history' => $this->history->saveState(),
      'scrollX' => $this->scrollX,
      'readOnly' => $this->readOnly,
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
    $this->readOnly = (bool)($state['readOnly'] ?? $this->readOnly);
    $this->syncScroll(false);
    $this->invalidateRender();
  }

  public function insertText(string $text): void {
    if ($this->readOnly || $text === '') {
      return;
    }
    $beforePosition = $this->cursor->position();
    $this->replaceSelection($this->oneLine($text), $beforePosition[0]);
  }

  public function replaceText(string $text): void {
    if ($this->readOnly) {
      return;
    }
    $beforeLines = $this->lines;
    $beforeCursor = $this->cursor->selectionState();
    $this->lines = [$this->oneLine($text)];
    $this->cursor = new TextCursor($this->lines);
    $this->cursor->setPosition(0, mb_strlen($this->text()));
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

    if (InputAction::selectAll($event, 'input')) {
      $this->cursor->selectAll();
      $this->syncScroll();
      $this->invalidateRender();
      return true;
    }
    if (InputAction::copy($event, 'input')) {
      Clipboard::set($this->cursor->selectedText());
      $this->cursor->collapse();
      $this->invalidateRender();
      return true;
    }
    if (!$this->readOnly && InputAction::cut($event, 'input')) {
      Clipboard::set($this->cursor->selectedText());
      $this->deleteSelection();
      return true;
    }
    if (!$this->readOnly && InputAction::paste($event, 'input')) {
      $this->insertText(Clipboard::get());
      return true;
    }
    if (InputAction::undo($event, 'input')) {
      if (!$this->readOnly) {
        $this->undo();
      }
      return true;
    }
    if (InputAction::redo($event, 'input')) {
      if (!$this->readOnly) {
        $this->redo();
      }
      return true;
    }

    $handled = true;
    if (InputAction::left($event, 'input')) {
      $ctrl ? $this->cursor->moveLineStart($shift) : $this->cursor->moveLeft($shift);
    } else if (InputAction::right($event, 'input')) {
      $ctrl ? $this->cursor->moveLineEnd($shift) : $this->cursor->moveRight($shift);
    } else if (InputAction::home($event, 'input')) {
      $ctrl ? $this->pageToFirstHorizontalEdge($shift) : $this->cursor->moveLineStart($shift);
    } else if (InputAction::end($event, 'input')) {
      $ctrl ? $this->pageToLastHorizontalEdge($shift) : $this->cursor->moveLineEnd($shift);
    } else if (!$this->readOnly && InputAction::backspace($event, 'input')) {
      $this->backspace();
    } else if (!$this->readOnly && InputAction::delete($event, 'input')) {
      $this->deleteForward();
    } else if ($this->readOnly && (InputAction::backspace($event, 'input') || InputAction::delete($event, 'input'))) {
      return true;
    } else {
      $handled = false;
    }

    if ($handled && !InputAction::backspace($event, 'input') && !InputAction::delete($event, 'input')) {
      $this->syncScroll();
      $this->invalidateRender();
    }
    return $handled;
  }

  protected function paint(RenderTarget $target): void {
    $this->syncScroll();
    $target->fill($this->frame, ' ', $this->fieldFg(), $this->fieldBg());
    $content = $this->contentRect();
    if ($content->width <= 0 || $content->height <= 0) {
      return;
    }
    $this->paintText($target, $content);
    $this->paintCursor($target, $content);
    $this->paintScrollbar($target, $content);
  }

  protected function paintText(RenderTarget $target, Rect $content): void {
    $placeholder = $this->focused && $this->text() === '' && $this->placeholder !== '';
    $line = $placeholder ? $this->placeholder : $this->text();
    $placeholderOffset = $placeholder ? 1 : 0;
    for ($x = 0; $x < $content->width; $x++) {
      $col = $this->scrollX + $x - $placeholderOffset;
      $glyph = $this->glyphAt($line, $col);
      if ($glyph === '') {
        $glyph = ' ';
      }
      $fg = $placeholder ? $this->theme->markerFg : $this->fieldFg();
      $bg = $this->fieldBg();
      if (!$placeholder && $this->inSelection($col)) {
        if ($this->focused) {
          $colors = $this->styleColors['selected'];
          $fg = $colors['fg'];
          $bg = $colors['bg'];
        } else {
          $fg = $this->inactiveCursorFg();
          $bg = $this->theme->inactiveCursorBg;
        }
      }
      $target->put($content->x + $x, $content->y, $glyph, $fg, $bg);
    }
  }

  protected function paintCursor(RenderTarget $target, Rect $content): void {
    [, $col] = $this->cursor->position();
    if ($col < $this->scrollX || $col > $this->scrollX + $content->width - 1) {
      return;
    }
    $x = $content->x + $col - $this->scrollX;
    $glyph = $this->glyphAt($this->text(), $col);
    if ($glyph === '') {
      $glyph = ' ';
    }
    $target->put(
      $x,
      $content->y,
      $glyph,
      $this->focused ? $this->theme->cursorFg : $this->inactiveCursorFg(),
      $this->focused ? $this->theme->cursorBg : $this->theme->inactiveCursorBg
    );
  }

  protected function paintScrollbar(RenderTarget $target, Rect $content): void {
    if (!$this->needsHorizontalScrollbar()) {
      return;
    }
    Scrollbar::paintBar(
      $target,
      new Rect($content->x, $content->y, $content->width, $content->height),
      $this->scrollX,
      $content->width,
      $this->visualColumnCount(),
      'horizontal',
      $this->theme->markerFg
    );
  }

  protected function replaceSelection(string $replacement, ?int $groupRow = null): void {
    $beforeLines = $this->lines;
    $beforeCursor = $this->cursor->selectionState();
    [, $col1, , $col2] = $this->cursor->range();
    $before = mb_substr($this->text(), 0, $col1);
    $after = mb_substr($this->text(), $col2);
    $this->lines[0] = $before . $replacement . $after;
    $this->cursor->setPosition(0, $col1 + mb_strlen($replacement));
    $this->history->record($beforeLines, $beforeCursor, $this->lines, $this->cursor->selectionState(), $groupRow);
    $this->afterEdit();
  }

  protected function deleteSelection(): void {
    if (!$this->cursor->hasSelection()) {
      return;
    }
    $this->replaceSelection('');
  }

  protected function backspace(): void {
    if ($this->cursor->hasSelection()) {
      $this->deleteSelection();
      return;
    }
    [, $col] = $this->cursor->position();
    if ($col === 0) {
      return;
    }
    $this->cursor->moveLeft(true);
    $this->replaceSelection('');
  }

  protected function deleteForward(): void {
    if ($this->cursor->hasSelection()) {
      $this->deleteSelection();
      return;
    }
    [, $col] = $this->cursor->position();
    if ($col === mb_strlen($this->text())) {
      return;
    }
    $this->cursor->moveRight(true);
    $this->replaceSelection('');
  }

  protected function undo(): void {
    $state = $this->history->undo($this->lines);
    if ($state === null) {
      return;
    }
    $this->cursor = new TextCursor($this->lines);
    $this->cursor->restoreState($state);
    $this->afterEdit();
  }

  protected function redo(): void {
    $state = $this->history->redo($this->lines);
    if ($state === null) {
      return;
    }
    $this->cursor = new TextCursor($this->lines);
    $this->cursor->restoreState($state);
    $this->afterEdit();
  }

  protected function afterEdit(): void {
    $this->syncPreferredColumns();
    $this->syncScroll();
    $this->invalidateRender();
  }

  protected function syncPreferredColumns(): void {
    $this->setPreferredColumns(max(mb_strlen($this->text()), mb_strlen($this->placeholder)));
  }

  protected function syncScroll(bool $followCursor = true): void {
    $content = $this->contentRect();
    if ($content->width <= 0) {
      return;
    }
    if ($followCursor) {
      [, $col] = $this->cursor->position();
      if ($col < $this->scrollX) {
        $this->scrollX = $col;
      } else if ($col >= $this->scrollX + $content->width) {
        $this->scrollX = $col - $content->width + 1;
      }
    }
    $this->scrollX = max(0, min($this->scrollX, max(0, $this->visualColumnCount() - $content->width)));
  }

  protected function pageToFirstHorizontalEdge(bool $select): void {
    $content = $this->contentRect();
    $viewport = max(1, $content->width);
    [, $col] = $this->cursor->position();
    $first = min(mb_strlen($this->text()), $this->scrollX);
    if ($col === $first && $this->scrollX > 0) {
      $this->scrollX = max(0, $this->scrollX - $viewport);
      $first = min(mb_strlen($this->text()), $this->scrollX);
    }
    $this->cursor->setPosition(0, $first, $select);
    if ($first === 0) {
      $this->scrollX = 0;
    }
  }

  protected function pageToLastHorizontalEdge(bool $select): void {
    $content = $this->contentRect();
    $viewport = max(1, $content->width);
    [, $col] = $this->cursor->position();
    $maxOffset = max(0, $this->visualColumnCount() - $viewport);
    $last = min(mb_strlen($this->text()), $this->scrollX + $viewport - 1);
    if ($col === $last && $this->scrollX < $maxOffset) {
      $this->scrollX = min($maxOffset, $this->scrollX + $viewport);
      $last = min(mb_strlen($this->text()), $this->scrollX + $viewport - 1);
    }
    $this->cursor->setPosition(0, $last, $select);
  }

  protected function contentRect(): Rect {
    return new Rect($this->frame->x, $this->frame->y, $this->frame->width, min(1, $this->frame->height));
  }

  protected function needsHorizontalScrollbar(): bool {
    return $this->frame->height > 0 && $this->visualColumnCount() > max(1, $this->frame->width);
  }

  protected function visualColumnCount(): int {
    return max(1, mb_strlen($this->text()) + 1);
  }

  protected function inSelection(int $col): bool {
    if (!$this->cursor->hasSelection()) {
      return false;
    }
    [, $col1, , $col2] = $this->cursor->range();
    return $col >= $col1 && $col < $col2;
  }

  protected function fieldFg(): Color {
    return $this->theme->inverseFg;
  }

  protected function inactiveCursorFg(): Color {
    return $this->fieldFg();
  }

  protected function fieldBg(): Color {
    $color = $this->theme->inverseBg;
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

  protected function glyphAt(string $line, int $col): string {
    if ($col < 0) {
      return '';
    }
    $glyph = mb_substr($line, $col, 1);
    return $this->password && $glyph !== '' ? '*' : $glyph;
  }

  protected function oneLine(string $text): string {
    return (string)preg_replace('/\R/u', ' ', $text);
  }

}
