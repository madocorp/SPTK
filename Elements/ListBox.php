<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\StyleSheet;
use \SPTK\SDLWrapper\TTF;
use \SPTK\SDLWrapper\SDL;
use \SPTK\SDLWrapper\KeyCombo;
use \SPTK\SDLWrapper\Action;

class ListBox extends TextGrid {

  protected int $activeItem = 0;
  protected int $num = 0;
  protected bool $movable = false;
  protected bool $selectionOrder = false;
  protected bool $selectOnReturn = true;
  protected array $selectedOrder = [];
  protected $onChange = false;
  protected $onSelect = false;
  protected $valueType = false;
  protected int $pageSize = 1;
  protected bool|string $typing = false;
  protected string $typed = '';
  protected int $activeBeforeType = 0;
  protected int $nextMatch = 0;
  protected array $items = [];
  protected array $styleColorCache = [];
  protected array|false $columnWidths = false;
  protected int $listRowHeight = 1;
  protected int $listGlyphOffsetY = 0;
  protected int $rowPaddingLeft = 10;
  protected int $rowPaddingRight = 10;

  protected function init(): void {
    if ($this->renderer !== false && TTF::$instance !== null && SDL::$instance !== null) {
      parent::init();
    } else {
      $fontSize = max(1, (int)$this->style->get('fontSize', $this->geometry));
      $this->letterWidth = max(1, (int)round($fontSize * 0.6));
      $this->letterHeight = $fontSize;
      $this->lineHeight = max(1, (int)$this->style->get('lineHeight', $this->geometry));
      $this->lineOffset = 0;
    }
    $this->syncRowMetrics();
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
  }

  public function getAttributeList(): array {
    return ['movable', 'selectionOrder', 'selectOnReturn', 'onChange', 'typing', 'onSelect', 'valueType', 'columns'];
  }

  public function rowChanged(): void {
    $this->num = count($this->items);
    $this->changed = true;
  }

  protected function makeRow(array|string $item, ?Element $source = null): ListBoxRow {
    return new ListBoxRow($this, $item, $source);
  }

  protected function rowStyleType(): string {
    return 'ListItem';
  }

  protected function syncRowMetrics(): void {
    $this->listRowHeight = max(1, (int)$this->lineHeight);
    $rowStyle = StyleSheet::get(static::$root->style ?? null, $this->style, $this->rowStyleType());
    $height = $this->styleInt($rowStyle, 'height');
    $verticalChrome =
      $this->styleInt($rowStyle, 'paddingTop') +
      $this->styleInt($rowStyle, 'paddingBottom') +
      $this->styleInt($rowStyle, 'borderTop') +
      $this->styleInt($rowStyle, 'borderBottom');
    if ($height > 0) {
      $this->listRowHeight = max($this->listRowHeight, $height);
    } else {
      $this->listRowHeight += max(2, $verticalChrome);
    }
    $visibleGlyphHeight = max(1, (int)$this->letterHeight);
    $sourceOffset = max(0, (int)$this->lineOffset);
    $this->listGlyphOffsetY = max(0, (int)floor(($this->listRowHeight - $visibleGlyphHeight) / 2) + $sourceOffset);
  }

  protected function styleInt(\SPTK\Style $style, string $name): int {
    $value = $style->get($name, $this->geometry);
    return is_int($value) ? max(0, $value) : 0;
  }

  public function addItem(array|string $item): ListBoxRow {
    $row = $this->makeRow($item);
    $this->items[] = $row;
    $this->rowChanged();
    return $row;
  }

  public function setItems(array $items): void {
    $this->items = [];
    $this->activeItem = 0;
    $this->scrollY = 0;
    $this->selectedOrder = [];
    foreach ($items as $item) {
      $this->items[] = $this->makeRow($item);
    }
    $this->rowChanged();
    $this->activateItem();
  }

  public function setColumns(array|string|false $columns): void {
    $this->columnWidths = $columns === false ? false : $this->parseColumnWidths($columns);
    $this->rowChanged();
  }

  public function getItems(): array {
    return $this->items;
  }

  public function removeItem(ListBoxRow $row): void {
    foreach ($this->items as $i => $item) {
      if ($item->getId() === $row->getId()) {
        unset($this->items[$i]);
        $this->items = array_values($this->items);
        break;
      }
    }
    if ($this->activeItem >= count($this->items)) {
      $this->activeItem = max(0, count($this->items) - 1);
    }
    $this->rowChanged();
    $this->activateItem();
  }

  public function moveItemAfter(ListBoxRow $item, ListBoxRow $after): void {
    $moveFrom = false;
    $afterIndex = false;
    foreach ($this->items as $i => $row) {
      if ($row->getId() === $item->getId()) {
        $moveFrom = $i;
      }
      if ($row->getId() === $after->getId()) {
        $afterIndex = $i;
      }
    }
    if ($moveFrom === false || $afterIndex === false || $moveFrom === $afterIndex) {
      return;
    }
    array_splice($this->items, $moveFrom, 1);
    if ($moveFrom < $afterIndex) {
      $afterIndex--;
    }
    array_splice($this->items, $afterIndex + 1, 0, [$item]);
    $this->activeItem = $afterIndex + 1;
    $this->rowChanged();
    $this->activateItem();
  }

  public function registerItemElement(ListItem $element): ListBoxRow {
    $row = $this->makeRow([], $element);
    $this->items[] = $row;
    $this->rowChanged();
    $element->setBackingRow($row);
    return $row;
  }

  public function addDescendant($element): void {
    parent::addDescendant($element);
  }

  public function removeDescendant($element): void {
    foreach ($this->items as $i => $row) {
      if (method_exists($element, 'getBackingRow') && $element->getBackingRow() === $row) {
        unset($this->items[$i]);
        $this->items = array_values($this->items);
        break;
      }
    }
    parent::removeDescendant($element);
    if ($this->activeItem >= count($this->items)) {
      $this->activeItem = max(0, count($this->items) - 1);
    }
    $this->rowChanged();
    $this->activateItem();
  }

  public function clear(): void {
    parent::clear();
    $this->items = [];
    $this->activeItem = 0;
    $this->num = 0;
    $this->scrollY = 0;
    $this->selectedOrder = [];
    $this->typed = '';
    $this->nextMatch = 0;
    $this->changed = true;
  }

  public function setMovable($value): void {
    $this->movable = ($value === true || $value === 'true');
  }

  public function setSelectionOrder($value): void {
    $this->selectionOrder = ($value === true || $value === 'true');
  }

  public function setSelectOnReturn($value): void {
    $this->selectOnReturn = ($value === true || $value === 'true');
  }

  public function setOnChange($value): void {
    if ($value === false) {
      return;
    }
    $this->onChange = is_array($value) ? $value : self::parseCallback($value);
  }

  public function setTyping($value): void {
    if ($value === 'search' || $value === 'filter') {
      $this->typing = $value;
      $this->addEvent('TextInput', [$this, 'textInputHandler']);
    } else {
      $this->typing = false;
      $this->removeEvent('TextInput');
    }
  }

  public function setOnSelect($value): void {
    if ($value === false) {
      return;
    }
    $this->onSelect = is_array($value) ? $value : self::parseCallback($value);
  }

  public function setValueType($value): void {
    $this->valueType = $value;
  }

  public function getValue(): mixed {
    switch ($this->valueType) {
      case 'order': return $this->getOrderValue();
      case 'select': return $this->getSelectedValue();
      case 'radio': return $this->getRadioValues();
      default: return $this->getSimpleValue();
    }
  }

  public function getSimpleValue(): mixed {
    $row = $this->getActive();
    return $row === false ? false : $row->getValue();
  }

  public function getOrderValue(): array {
    return array_map(fn($row) => $row->getValue(), $this->items);
  }

  public function getSelectedValue(): array {
    $selected = [];
    if ($this->selectionOrder) {
      foreach ($this->selectedOrder as $id) {
        foreach ($this->items as $item) {
          if ($item->getId() === $id && $item->isSelectable() === true && $item->isSelected()) {
            $selected[] = $item->getValue();
            break;
          }
        }
      }
      return $selected;
    }
    foreach ($this->items as $item) {
      if ($item->isSelectable() === true && $item->isSelected()) {
        $selected[] = $item->getValue();
      }
    }
    return $selected;
  }

  public function getRadioValue($group): mixed {
    foreach ($this->items as $item) {
      if ($item->isSelectable() === $group && $item->isSelected()) {
        return $item->getValue();
      }
    }
    return false;
  }

  public function getRadioValues(): array {
    $groups = [];
    foreach ($this->items as $item) {
      $selectable = $item->isSelectable();
      if ($selectable !== false && $selectable !== true && $item->isSelected()) {
        $groups[$selectable] = $item->getValue();
      }
    }
    return $groups;
  }

  public function addVariant(string $class): void {
    $this->styleColorCache = [];
    parent::addVariant($class);
    $this->update();
  }

  public function removeVariant(string $class): void {
    $this->styleColorCache = [];
    parent::removeVariant($class);
    $this->update();
  }

  public function raise(): void {
    parent::raise();
    $this->activateItem();
  }

  protected function visibleItems(): array {
    $visible = [];
    foreach ($this->items as $i => $item) {
      if ($item->isDisplayed()) {
        $visible[$i] = $item;
      }
    }
    return $visible;
  }

  protected function activeVisibleOffset(): int|false {
    $offset = 0;
    foreach ($this->visibleItems() as $i => $item) {
      if ($i === $this->activeItem) {
        return $offset;
      }
      $offset++;
    }
    return false;
  }

  protected function visibleIndexToItemIndex(int $visibleIndex): int|false {
    $offset = 0;
    foreach ($this->visibleItems() as $i => $item) {
      if ($offset === $visibleIndex) {
        return $i;
      }
      $offset++;
    }
    return false;
  }

  protected function rowCanActivate(ListBoxRow $row): bool {
    return $row->isDisplayed();
  }

  public function moveCursor($n, $relative = false): void {
    if (empty($this->items)) {
      $this->activeItem = 0;
      return;
    }
    $this->activeItem = $relative ? $this->activeItem + $n : $n;
    $this->activeItem = max(0, min($this->activeItem, count($this->items) - 1));
    $this->activateItem($relative && $n < 0 ? -1 : 1);
    $this->update();
  }

  public function bringToMiddle(): void {
    $offset = $this->activeVisibleOffset();
    if ($offset === false || !is_int($this->geometry->height)) {
      return;
    }
    $rowHeight = $this->rowHeight();
    $visibleHeight = $this->viewportHeight();
    $this->scrollY = (int)($offset * $rowHeight - $visibleHeight / 2 + $rowHeight / 2);
    $this->clampScrollY();
  }

  protected function maxScrollY(): int {
    return max(0, count($this->visibleItems()) * $this->rowHeight() - $this->viewportHeight());
  }

  protected function clampScrollY(): void {
    $this->scrollY = max(0, min($this->scrollY, $this->maxScrollY()));
  }

  protected function scrollActiveIntoView(): void {
    $offset = $this->activeVisibleOffset();
    if ($offset === false || !is_int($this->geometry->height)) {
      return;
    }
    $rowHeight = $this->rowHeight();
    $viewportHeight = $this->viewportHeight();
    $rowTop = $offset * $rowHeight;
    $rowBottom = $rowTop + $rowHeight;
    if ($rowBottom > $this->scrollY + $viewportHeight) {
      $this->scrollY = $rowBottom - $viewportHeight;
    } else if ($rowTop < $this->scrollY) {
      $this->scrollY = $rowTop;
    }
    $this->clampScrollY();
  }

  public function activateItem($direction = 1): void {
    if (empty($this->items)) {
      $this->activeItem = 0;
      return;
    }
    $n = count($this->items);
    $found = false;
    for ($i = 0; $i < $n; $i++) {
      $idx = ($n + $this->activeItem + $i * $direction) % $n;
      if ($this->rowCanActivate($this->items[$idx])) {
        $this->activeItem = $idx;
        $this->scrollActiveIntoView();
        $found = true;
        break;
      }
    }
    if (!$found) {
      return;
    }
    if ($this->onChange !== false) {
      call_user_func($this->onChange, $this);
    }
  }

  public function inactivateItem(): void {
    ;
  }

  public function setSelectedValues(array $values): void {
    $this->selectedOrder = [];
    foreach ($this->items as $item) {
      $item->deselect();
    }
    foreach ($values as $value) {
      foreach ($this->items as $item) {
        if ($item->isSelectable() === true && $item->getValue() === $value && !$item->isSelected()) {
          $this->selectItem($item);
          break;
        }
      }
    }
    $this->update();
  }

  public function selectAll(): void {
    $values = [];
    foreach ($this->items as $item) {
      if ($item->isSelectable() === true) {
        $values[] = $item->getValue();
      }
    }
    $this->setSelectedValues($values);
  }

  public function clearSelection(): void {
    $this->setSelectedValues([]);
  }

  protected function selectItem(ListBoxRow $item): void {
    if (!$this->selectionOrder) {
      $item->select();
      return;
    }
    $id = $item->getId();
    if ($item->isSelected()) {
      $this->selectedOrder = array_values(array_filter($this->selectedOrder, fn($selectedId) => $selectedId !== $id));
      $item->deselect();
    } else {
      $this->selectedOrder[] = $id;
      $item->select(count($this->selectedOrder));
    }
    $this->refreshSelectionOrder();
  }

  protected function refreshSelectionOrder(): void {
    if (!$this->selectionOrder) {
      return;
    }
    foreach ($this->items as $item) {
      if ($item->isSelectable() === true && $item->isSelected()) {
        $order = array_search($item->getId(), $this->selectedOrder, true);
        if ($order !== false) {
          $item->markSelected($order + 1);
        }
      }
    }
  }

  protected function refreshAfterSelection(): void {
    $scrollY = $this->scrollY;
    $this->resetSearch();
    $this->scrollY = $scrollY;
    $this->clampScrollY();
    $this->scrollActiveIntoView();
    $this->update();
  }

  public function getActive(): ListBoxRow|false {
    return $this->items[$this->activeItem] ?? false;
  }

  protected function measure(): void {
    $this->geometry->setValues($this->ancestor->geometry, $this->style);
    if ($this->geometry->width === 'calculated') {
      $this->calculateWidth();
    }
    $this->geometry->setDerivedWidths();
  }

  protected function calculateWidth(): void {
    $chrome = $this->widthChrome();
    $columnWidth = max(1, (int)$this->letterWidth);
    $columns = max($this->preferredColumns(), $this->minWidthColumns($chrome, $columnWidth));
    $maxColumns = $this->maxWidthColumns($chrome, $columnWidth);
    if ($maxColumns !== false) {
      $columns = min($columns, $maxColumns);
    }
    $this->geometry->width = $columns * $columnWidth + $chrome;
    $this->geometry->setDerivedWidths();
  }

  protected function widthChrome(): int {
    return
      $this->rowPaddingLeft +
      $this->rowPaddingRight +
      $this->verticalScrollbarWidth() +
      $this->geometryValue($this->geometry->paddingLeft) +
      $this->geometryValue($this->geometry->paddingRight) +
      $this->geometryValue($this->geometry->borderLeft) +
      $this->geometryValue($this->geometry->borderRight);
  }

  protected function minWidthColumns(int $chrome, int $columnWidth): int {
    if (!is_int($this->geometry->minWidth) || $this->geometry->minWidth <= $chrome) {
      return 1;
    }
    return max(1, (int)ceil(($this->geometry->minWidth - $chrome) / $columnWidth));
  }

  protected function maxWidthColumns(int $chrome, int $columnWidth): int|false {
    if (!is_int($this->geometry->maxWidth) || $this->geometry->maxWidth >= 10000 || $this->geometry->maxWidth <= $chrome) {
      return false;
    }
    return max(1, (int)floor(($this->geometry->maxWidth - $chrome) / $columnWidth));
  }

  protected function verticalScrollbarWidth(): int {
    if (!$this->style->get('scrollable') || !$this->needsVerticalScrollbar()) {
      return 0;
    }
    $size = $this->style->get('scrollbarSize', $this->geometry);
    return is_int($size) ? max(0, $size) : 0;
  }

  protected function needsVerticalScrollbar(): bool {
    $contentHeight = count($this->visibleItems()) * $this->rowHeight();
    $availableHeight = false;
    if (is_int($this->geometry->height)) {
      $availableHeight = max(0, $this->geometry->height - $this->geometryValue($this->geometry->borderTop) - $this->geometryValue($this->geometry->borderBottom));
    } else if (is_int($this->geometry->maxHeight) && $this->geometry->maxHeight < 10000) {
      $availableHeight = max(0, $this->geometry->maxHeight - $this->geometryValue($this->geometry->borderTop) - $this->geometryValue($this->geometry->borderBottom));
    }
    return $availableHeight !== false && $contentHeight > $availableHeight + 1;
  }

  protected function geometryValue(mixed $value): int {
    return is_int($value) ? $value : 0;
  }

  protected function rowBodyColumns(ListBoxRow $item): int {
    if ($item->getColumns() !== false && $this->effectiveColumnWidths() !== false) {
      return $this->leftSlotColumns() + $this->textGridColumns();
    }
    $prefix = $item->getPrefix();
    return
      $this->leftSlotColumns() +
      ($prefix === '' ? 0 : mb_strlen($prefix) + 1) +
      mb_strlen($item->getText());
  }

  protected function maxBodyColumns(?array $items = null): int {
    $max = 1;
    foreach ($items ?? $this->items as $item) {
      $max = max($max, $this->rowBodyColumns($item));
    }
    return $max;
  }

  protected function rightSlotColumns(?array $items = null): int {
    $max = 0;
    foreach ($items ?? $this->items as $item) {
      $right = mb_strlen($item->getRight());
      $max = max($max, $right === 0 ? 0 : $right + 1, $item->getRightReserve());
    }
    return $max;
  }

  protected function preferredColumns(?array $items = null): int {
    return $this->maxBodyColumns($items) + $this->rightSlotColumns($items);
  }

  protected function calculateHeights(): void {
    if ($this->display === false) {
      return;
    }
    $this->geometry->setDerivedHeights();
    $rowHeight = $this->rowHeight();
    $this->geometry->setContentHeight($rowHeight, count($this->visibleItems()) * $rowHeight);
    $this->pageSize = max(1, (int)($this->viewportHeight() / $rowHeight));
  }

  protected function layout(): void {
    parent::layout();
    $this->scrollX = 0;
    $this->geometry->contentWidth = is_int($this->geometry->innerWidth) ? $this->geometry->innerWidth : $this->viewportWidth();
    $this->refreshCells(false);
  }

  public function resetSearch(): void {
    $this->typed = '';
    $this->nextMatch = 0;
    foreach ($this->items as $item) {
      $item->match(false);
      $item->show();
    }
  }

  protected function lookUp(): void {
    $filter = ($this->typing === 'filter');
    if ($this->typed === '') {
      foreach ($this->items as $item) {
        $item->match(false);
        $item->show();
      }
      $this->activeItem = $this->activeBeforeType;
      $this->activateItem();
      return;
    }
    $matchIndex = false;
    $firstMatchIndex = false;
    $lastMatchIndex = false;
    $matchCount = 0;
    foreach ($this->items as $i => $item) {
      if (!$item->isFilterable()) {
        continue;
      }
      if ($item->match($this->typed)) {
        $firstMatchIndex ??= $i;
        $lastMatchIndex = $i;
        if ($matchIndex === false && $matchCount === $this->nextMatch) {
          $matchIndex = $i;
        }
        $matchCount++;
        if ($filter) {
          $item->show();
        }
      } else if ($filter) {
        $item->hide();
      }
    }
    if ($matchIndex === false && $firstMatchIndex !== false) {
      if ($this->nextMatch < 0) {
        $matchIndex = $lastMatchIndex;
        $this->nextMatch = $matchCount - 1;
      } else {
        $matchIndex = $firstMatchIndex;
        $this->nextMatch = 0;
      }
    }
    if ($matchIndex !== false) {
      $this->moveCursor($matchIndex);
    } else {
      $this->typed = mb_substr($this->typed, 0, -1);
      $this->lookUp();
    }
  }

  protected function nextMatch(): void {
    $this->nextMatch++;
    $this->lookUp();
    $this->bringToMiddle();
    $this->update();
  }

  protected function previousMatch(): void {
    $this->nextMatch--;
    $this->lookUp();
    $this->bringToMiddle();
    $this->update();
  }

  protected function colorsForStyle(string $type, array $classes = [], string|int $name = StyleSheet::ANY): array {
    $cacheKey = $type . '|' . $name . '|' . implode('.', $classes);
    if (!isset($this->styleColorCache[$cacheKey])) {
      $style = StyleSheet::get($this->style, $this->style, $type, $classes, $name);
      $this->styleColorCache[$cacheKey] = [
        'fg' => $style->get('color'),
        'bg' => $style->get('backgroundColor')
      ];
    }
    return $this->styleColorCache[$cacheKey];
  }

  protected function cell(string $glyph, array $colors): array {
    return ['glyph' => $glyph, 'fg' => $colors['fg'], 'bg' => $colors['bg']];
  }

  protected function appendTextCells(array &$cells, string $text, array $colors, int $limit): void {
    foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $glyph) {
      if (count($cells) >= $limit) {
        return;
      }
      $cells[] = $this->cell($glyph, $colors);
    }
  }

  protected function appendTruncatedTextCells(array &$cells, string $text, array $colors, int $limit, string $marker): void {
    if ($marker === '' || mb_strlen($text) <= max(0, $limit - count($cells))) {
      $this->appendTextCells($cells, $text, $colors, $limit);
      return;
    }
    $available = max(0, $limit - count($cells));
    $markerLength = mb_strlen($marker);
    if ($available <= $markerLength) {
      $this->appendTextCells($cells, mb_substr($marker, 0, $available), $colors, $limit);
      return;
    }
    $this->appendTextCells($cells, mb_substr($text, 0, $available - $markerLength) . $marker, $colors, $limit);
  }

  protected function rowClasses(ListBoxRow $row, int $index): array {
    $classes = $row->getClass();
    if ($index === $this->activeItem) {
      $classes[] = $this->hasVariant('active') ? $row->getType() . ':active' : $row->getType() . ':cursor';
    }
    return array_values(array_unique($classes));
  }

  protected function buildRowCells(ListBoxRow $row, int $index, int $cols, int $bodyColumns, int $rightColumns): array {
    $columnValues = $row->getColumns();
    $columnWidths = $this->effectiveColumnWidths();
    if ($columnValues !== false && $columnWidths !== false) {
      return $this->buildColumnRowCells($row, $index, $cols, $columnValues, $columnWidths);
    }
    $type = $row->getType();
    $classes = $this->rowClasses($row, $index);
    $baseColors = $this->colorsForStyle($type, $classes);
    $leftColors = $this->colorsForStyle('ItemLeft');
    $leftColors['bg'] = $baseColors['bg'];
    $prefixColors = $this->colorsForStyle('ItemPrefix', $classes);
    $rightColors = $this->colorsForStyle('ItemRight');
    $rightColors['bg'] = $baseColors['bg'];
    $matchColors = $this->colorsForStyle('InputValue', ['InputValue:matched']);
    $cells = [];
    $this->appendLeftCells($cells, $row->getLeft(), $leftColors, $cols);
    if ($row->getPrefix() !== '') {
      $this->appendTextCells($cells, $row->getPrefix() . ' ', $prefixColors, $cols);
    }
    $right = $row->getRight();
    $textLimit = $rightColumns > 0 ? min($cols, $bodyColumns) : $cols;
    $text = $row->getText();
    if ($row->isMatched() && $row->getMatchLength() !== false) {
      $matchLength = $row->getMatchLength();
      $this->appendTextCells($cells, mb_substr($text, 0, $matchLength), $matchColors, $textLimit);
      $this->appendTextCells($cells, mb_substr($text, $matchLength), $baseColors, $textLimit);
    } else {
      $this->appendTruncatedTextCells($cells, $text, $baseColors, $textLimit, $row->getTruncateMarker());
    }
    while (count($cells) < $cols) {
      $cells[] = $this->cell(' ', $baseColors);
    }
    if ($right !== '') {
      $rightGlyphs = preg_split('//u', $right, -1, PREG_SPLIT_NO_EMPTY);
      $start = $bodyColumns + $rightColumns - count($rightGlyphs);
      if ($start + count($rightGlyphs) > $cols) {
        $start = max(0, $cols - count($rightGlyphs));
      }
      foreach ($rightGlyphs as $i => $glyph) {
        if (isset($cells[$start + $i])) {
          $cells[$start + $i] = $this->cell($glyph, $rightColors);
        }
      }
    }
    return $cells;
  }

  protected function buildColumnRowCells(ListBoxRow $row, int $index, int $cols, array $values, array $widths): array {
    $type = $row->getType();
    $classes = $this->rowClasses($row, $index);
    $baseColors = $this->colorsForStyle($type, $classes);
    $leftColors = $this->colorsForStyle('ItemLeft');
    $leftColors['bg'] = $baseColors['bg'];
    $cells = [];
    $this->appendLeftCells($cells, $row->getLeft(), $leftColors, $cols);
    $textColumns = max(0, $cols - count($cells));
    $columnChars = $this->columnCharWidths($widths, $textColumns);
    foreach ($columnChars as $i => $width) {
      $limit = min($cols, count($cells) + $width);
      $this->appendTextCells($cells, (string)($values[$i] ?? ''), $baseColors, $limit);
      while (count($cells) < $limit) {
        $cells[] = $this->cell(' ', $baseColors);
      }
    }
    while (count($cells) < $cols) {
      $cells[] = $this->cell(' ', $baseColors);
    }
    return $cells;
  }

  protected function appendLeftCells(array &$cells, string $text, array $colors, int $limit): void {
    $slot = $this->leftSlotColumns();
    $glyphs = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    for ($i = 0; $i < $slot && count($cells) < $limit; $i++) {
      $cells[] = $this->cell($i === 0 ? ($glyphs[0] ?? ' ') : ' ', $colors);
    }
  }

  protected function leftSlotColumns(): int {
    $columns = 0;
    foreach ($this->visibleItems() as $item) {
      $columns = max($columns, $item->getLeftReserve());
      if ($item->isSelectable() !== false) {
        $columns = max($columns, 2);
      }
    }
    return $columns;
  }

  protected function parseColumnWidths(array|string $columns): array|false {
    $parts = is_array($columns) ? $columns : preg_split('/\s*,\s*/', trim((string)$columns));
    $widths = [];
    foreach ($parts as $part) {
      $part = trim((string)$part);
      if ($part === '') {
        continue;
      }
      if (preg_match('/^w(\d+(?:\.\d+)?)$/', $part, $matches)) {
        $widths[] = (float)$matches[1];
      } else if (preg_match('/^(\d+(?:\.\d+)?)%?$/', $part, $matches)) {
        $widths[] = (float)$matches[1];
      }
    }
    return empty($widths) ? false : $widths;
  }

  protected function effectiveColumnWidths(): array|false {
    if ($this->columnWidths !== false) {
      return $this->columnWidths;
    }
    $previous = false;
    foreach ($this->ancestor?->getDescendants() ?? [] as $descendant) {
      if ($descendant === $this) {
        break;
      }
      if ($descendant->isDisplayed() && $descendant->getType() !== 'Space' && $descendant->getType() !== 'NL') {
        $previous = $descendant;
      }
    }
    if (!$previous instanceof ListHeaderRow) {
      return false;
    }
    return $previous->columnWidths();
  }

  protected function textGridColumns(): int {
    return max(1, (int)floor($this->viewportWidth() / max(1, (int)$this->letterWidth)));
  }

  protected function columnCharWidths(array $widths, int $totalColumns): array {
    $totalColumns = max(0, $totalColumns);
    $sum = array_sum($widths);
    if ($totalColumns === 0 || $sum <= 0) {
      return array_fill(0, count($widths), 0);
    }
    $columns = [];
    $previous = 0;
    $running = 0.0;
    foreach ($widths as $i => $width) {
      $running += $width;
      if ($i === count($widths) - 1) {
        $next = $totalColumns;
      } else {
        $next = (int)round($totalColumns * $running / $sum);
      }
      $columns[] = max(0, $next - $previous);
      $previous = $next;
    }
    return $columns;
  }

  public function gridTextOffset(): int {
    return
      $this->geometryValue($this->geometry->borderLeft) +
      $this->geometryValue($this->geometry->paddingLeft) +
      $this->rowPaddingLeft +
      $this->leftSlotColumns() * max(1, (int)$this->letterWidth);
  }

  public function gridColumnPixelWidths(array $widths): array {
    $textColumns = max(0, $this->textGridColumns() - $this->leftSlotColumns());
    $letterWidth = max(1, (int)$this->letterWidth);
    return array_map(fn($columns) => $columns * $letterWidth, $this->columnCharWidths($widths, $textColumns));
  }

  protected function screenRows(): array {
    $rowHeight = $this->rowHeight();
    $first = (int)($this->scrollY / $rowHeight);
    $rows = max(1, (int)($this->viewportHeight() / $rowHeight) + 1);
    return [$first, $first + $rows];
  }

  protected function buildCells(): array {
    [$first, $last] = $this->screenRows();
    $cols = max(1, (int)floor($this->viewportWidth() / max(1, (int)$this->letterWidth)));
    $cells = [];
    $visible = $this->visibleItems();
    $bodyColumns = $this->maxBodyColumns($visible);
    $rightColumns = $this->rightSlotColumns($visible);
    if ($rightColumns > 0) {
      $bodyColumns = min($bodyColumns, max(0, $cols - $rightColumns));
    }
    $plainColors = $this->colorsForStyle('ListItem');
    $i = 0;
    foreach ($visible as $index => $row) {
      if ($i >= $first && $i < $last) {
        $cells[] = $this->buildRowCells($row, $index, $cols, $bodyColumns, $rightColumns);
      }
      $i++;
      if ($i >= $last) {
        break;
      }
    }
    while (count($cells) < max(1, $last - $first)) {
      $row = [];
      while (count($row) < $cols) {
        $row[] = $this->cell(' ', $plainColors);
      }
      $cells[] = $row;
    }
    return $cells;
  }

  protected function refreshCells(bool $render): void {
    $this->clampScrollY();
    $this->setCells($this->buildCells(), $this->rowPaddingLeft, -($this->scrollY % $this->rowHeight()));
    if ($render && $this->renderer !== false && $this->isVisibleInTree()) {
      Element::immediateRender($this);
    }
  }

  protected function update(): void {
    $this->refreshCells(true);
  }

  protected function isVisibleInTree(): bool {
    $element = $this;
    while ($element !== null) {
      if (!$element->isDisplayed()) {
        return false;
      }
      $element = $element->getAncestor();
    }
    return true;
  }

  protected function rowHeight(): int {
    return max(1, $this->listRowHeight);
  }

  protected function gridRowHeight(): int {
    return $this->rowHeight();
  }

  protected function glyphOffsetY(): int {
    return $this->listGlyphOffsetY;
  }

  protected function rowBackground(array $row): array|false {
    return $row[0]['bg'] ?? false;
  }

  protected function viewportHeight(): int {
    return is_int($this->geometry->innerHeight) ? max(1, $this->geometry->innerHeight) : $this->rowHeight();
  }

  protected function viewportWidth(): int {
    $padding = $this->rowPaddingLeft + $this->rowPaddingRight + $this->verticalScrollbarWidth();
    if (is_int($this->geometry->innerWidth)) {
      return max(1, $this->geometry->innerWidth - $padding);
    }
    return is_int($this->geometry->width) ? max(1, $this->geometry->width - $padding) : max(1, (int)$this->letterWidth);
  }

  protected function selectActiveItem(): bool {
    $item = $this->items[$this->activeItem] ?? false;
    if ($item === false) {
      return false;
    }
    $selectable = $item->isSelectable();
    if ($selectable === true) {
      $this->selectItem($item);
      $this->refreshAfterSelection();
      if ($this->onSelect !== false) {
        call_user_func($this->onSelect, $item);
      }
      return true;
    }
    if ($selectable !== false) {
      foreach ($this->items as $descendant) {
        if ($descendant->getId() === $item->getId()) {
          $item->select();
        } else if ($selectable === $descendant->isSelectable()) {
          $descendant->deselect();
        }
      }
      $this->refreshAfterSelection();
      if ($this->onSelect !== false) {
        call_user_func($this->onSelect, $item);
      }
      return true;
    }
    if ($this->onSelect !== false) {
      $this->refreshAfterSelection();
      call_user_func($this->onSelect, $item);
      return true;
    }
    return false;
  }

  public function keyPressHandler($element, $event): bool {
    switch (KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
      case Action::SELECT_UP:
        if ($this->typing !== false && mb_strlen($this->typed) > 0) {
          $this->previousMatch();
          return true;
        }
        if ($this->movable && ($this->typing !== 'filter' || $this->typed === '') && $this->activeItem > 0) {
          $item = $this->items[$this->activeItem];
          array_splice($this->items, $this->activeItem, 1);
          $this->activeItem--;
          array_splice($this->items, $this->activeItem, 0, [$item]);
          if ($this->onChange !== false) {
            call_user_func($this->onChange, $this);
          }
          $this->update();
          return true;
        }
        break;
      case Action::SELECT_DOWN:
        if ($this->typing !== false && mb_strlen($this->typed) > 0) {
          $this->nextMatch();
          return true;
        }
        if ($this->movable && ($this->typing !== 'filter' || $this->typed === '') && $this->activeItem < count($this->items) - 1) {
          $item = $this->items[$this->activeItem];
          array_splice($this->items, $this->activeItem, 1);
          $this->activeItem++;
          array_splice($this->items, $this->activeItem, 0, [$item]);
          if ($this->onChange !== false) {
            call_user_func($this->onChange, $this);
          }
          $this->update();
          return true;
        }
        break;
      case Action::MOVE_UP:
        $this->moveCursor($this->activeItem - 1);
        return true;
      case Action::MOVE_DOWN:
        $this->moveCursor($this->activeItem + 1);
        return true;
      case Action::MOVE_FIRST:
        $this->moveCursor(0);
        return true;
      case Action::MOVE_LAST:
        $this->moveCursor(count($this->items) - 1);
        return true;
      case Action::PAGE_UP:
        $this->moveCursor($this->activeItem - $this->pageSize + 1);
        return true;
      case Action::PAGE_DOWN:
        $this->moveCursor($this->activeItem + $this->pageSize - 1);
        return true;
      case Action::DELETE_BACK:
        if ($this->typing !== false && mb_strlen($this->typed) > 0) {
          $this->nextMatch = 0;
          $this->typed = mb_substr($this->typed, 0, -1);
          $this->lookUp();
          $this->bringToMiddle();
          $this->update();
          return true;
        }
        return false;
      case Action::DELETE_FORWARD:
        if ($this->typing !== false && mb_strlen($this->typed) > 0) {
          $this->resetSearch();
          $this->lookUp();
          $this->bringToMiddle();
          $this->update();
          return true;
        }
        return false;
      case Action::SELECT_ITEM:
        return $this->selectActiveItem();
      case Action::DO_IT:
        $item = $this->items[$this->activeItem] ?? false;
        if ($item !== false && $item->isSelectable() !== false && !$this->selectOnReturn) {
          return false;
        }
        return $this->selectActiveItem();
    }
    return false;
  }

  public function textInputHandler($element, $event): bool {
    if ($this->typed === '') {
      $this->activeBeforeType = $this->activeItem;
    }
    $this->nextMatch = 0;
    $this->typed .= $event['text'];
    $this->lookUp();
    $this->bringToMiddle();
    $this->update();
    return true;
  }

}
