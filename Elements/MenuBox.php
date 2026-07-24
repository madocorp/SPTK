<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\StyleSheet;
use \SPTK\SDLWrapper\KeyCode;
use \SPTK\SDLWrapper\KeyCombo;
use \SPTK\SDLWrapper\Action;

class MenuBox extends ListBox {

  public $belongsTo = false;
  public $submenu = false;
  protected bool $jumpToSelected = false;
  protected int $separatorThickness = 3;

  protected function init(): void {
    parent::init();
    $this->display = false;
    $this->addVariant('active');
  }

  protected function makeRow(array|string $item, ?Element $source = null): MenuBoxRow {
    return new MenuBoxRow($this, $item, $source);
  }

  protected function rowStyleType(): string {
    return 'MenuBoxItem';
  }

  public function addItem(array|string $item): MenuBoxRow {
    /** @var MenuBoxRow $row */
    $row = parent::addItem($item);
    return $row;
  }

  public function registerItemElement(ListItem $element): ListBoxRow {
    $row = $this->makeRow([], $element);
    $this->items[] = $row;
    $this->rowChanged();
    $element->setBackingRow($row);
    return $row;
  }

  public function getAttributeList(): array {
    return array_merge(parent::getAttributeList(), ['belongsTo', 'submenu', 'jumpToSelected']);
  }

  public function setBelongsTo($value): void {
    $this->belongsTo = $value;
  }

  public function setSubmenu($value): void {
    if ($value === true || $value === 'true') {
      $this->submenu = true;
    }
  }

  public function setJumpToSelected($value): void {
    if ($value === true || $value === 'true') {
      $this->jumpToSelected = true;
    }
  }

  public function gotoSelected(): void {
    if (!$this->jumpToSelected) {
      return;
    }
    foreach ($this->items as $i => $item) {
      if ($item->isSelected()) {
        $this->activeItem = $i;
        return;
      }
    }
  }

  public function hide(): void {
    $this->resetSearch();
    parent::hide();
  }

  protected function calculateWidth(): void {
    $this->geometry->width =
      $this->menuColumns($this->items) * max(1, (int)$this->letterWidth) +
      $this->rowPaddingLeft +
      $this->rowPaddingRight +
      $this->geometryValue($this->geometry->borderLeft) +
      $this->geometryValue($this->geometry->borderRight);
  }

  protected function menuColumns(array $items): int {
    return $this->leftItemColumns($items) + $this->menuItemColumns($items) + $this->rightItemColumns($items);
  }

  protected function leftItemColumns(array $items): int {
    $columns = 1;
    foreach ($items as $item) {
      $columns = max($columns, $item->getLeftReserve());
      if ($item->isSelectable() !== false) {
        $columns = max($columns, 2);
      }
    }
    return $columns;
  }

  protected function menuItemColumns(array $items): int {
    $max = 1;
    foreach ($items as $item) {
      $right = $this->menuRightText($item);
      $width = mb_strlen($this->menuText($item)) + mb_strlen($right) + ($right === '' ? 0 : 2);
      $max = max($max, $width);
    }
    return $max;
  }

  protected function rightItemColumns(array $items): int {
    foreach ($items as $item) {
      if ($this->isSubmenuRow($item)) {
        return 2;
      }
    }
    return 1;
  }

  protected function isSeparatorRow(ListBoxRow $item): bool {
    return in_array('MenuSeparator', $item->getClass(), true);
  }

  protected function isSubmenuRow(ListBoxRow $item): bool {
    return $item instanceof MenuBoxRow && $item->isSubmenu() !== false;
  }

  protected function menuRightText(ListBoxRow $item): string {
    if ($this->isSubmenuRow($item) && $item->getRight() === '>') {
      return '';
    }
    return $item->getRight();
  }

  protected function menuText(ListBoxRow $item): string {
    $prefix = $item->getPrefix();
    return ($prefix === '' ? '' : $prefix . $item->getPrefixSeparator()) . $item->getText();
  }

  protected function normalRowHeight(): int {
    return parent::rowHeight();
  }

  protected function rowHeight(): int {
    return $this->normalRowHeight() + $this->separatorThickness();
  }

  protected function buildCells(): array {
    [$first, $last] = $this->screenRows();
    $leftColumns = $this->leftItemColumns($this->items);
    $menuColumns = $this->menuItemColumns($this->items);
    $rightColumns = $this->rightItemColumns($this->items);
    $cols = min(
      $leftColumns + $menuColumns + $rightColumns,
      max(1, (int)floor($this->viewportWidth() / max(1, (int)$this->letterWidth)))
    );
    $cells = [];
    $plainColors = $this->colorsForStyle('MenuBoxItem');
    $visible = $this->visibleItems();
    $i = 0;
    foreach ($visible as $index => $row) {
      if ($i >= $first && $i < $last) {
        $cells[] = $this->buildMenuRowCells($row, $index, $cols, $leftColumns, $menuColumns, $rightColumns);
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

  protected function buildMenuRowCells(ListBoxRow $row, int $index, int $cols, int $leftColumns, int $menuColumns, int $rightColumns): array {
    $classes = $this->rowClasses($row, $index);
    $baseColors = $this->colorsForStyle($row->getType(), $classes);
    $leftColors = $this->colorsForStyle('ItemLeft');
    $leftColors['bg'] = $baseColors['bg'];
    $prefixColors = $this->colorsForStyle('ItemPrefix');
    $prefixColors['bg'] = $baseColors['bg'];
    $rightColors = $this->colorsForStyle('ItemRight');
    $rightColors['bg'] = $baseColors['bg'];
    $matchColors = $this->colorsForStyle('InputValue', ['InputValue:matched']);
    $cells = [];
    $this->appendMenuLeftCells($cells, $row->getLeft(), $leftColumns, $leftColors, $cols);
    $itemStart = count($cells);
    if ($row->getPrefix() !== '') {
      $this->appendTextCells($cells, $row->getPrefix() . $row->getPrefixSeparator(), $prefixColors, min($cols, $itemStart + $menuColumns));
    }
    $textLimit = min($cols, $itemStart + $menuColumns);
    $text = $row->getText();
    if ($row->isMatched() && $row->getMatchLength() !== false) {
      $matchLength = $row->getMatchLength();
      $this->appendTextCells($cells, mb_substr($text, 0, $matchLength), $matchColors, $textLimit);
      $this->appendTextCells($cells, mb_substr($text, $matchLength), $baseColors, $textLimit);
    } else {
      $this->appendTextCells($cells, $text, $baseColors, $textLimit);
    }
    $right = $this->menuRightText($row);
    if ($right !== '') {
      $this->appendTextCells($cells, '  ', $baseColors, $textLimit);
      $this->appendTextCells($cells, $right, $rightColors, $textLimit);
    }
    while (count($cells) < min($cols, $itemStart + $menuColumns)) {
      $cells[] = $this->cell(' ', $baseColors);
    }
    $rightStart = $leftColumns + $menuColumns;
    while (count($cells) < min($cols, $rightStart)) {
      $cells[] = $this->cell(' ', $baseColors);
    }
    if ($this->isSubmenuRow($row)) {
      while (count($cells) < min($cols, $rightStart + $rightColumns - 1)) {
        $cells[] = $this->cell(' ', $baseColors);
      }
      if (count($cells) < $cols) {
        $cells[] = $this->cell('>', $rightColors);
      }
    }
    while (count($cells) < $cols) {
      $cells[] = $this->cell(' ', $baseColors);
    }
    return $cells;
  }

  protected function separatorLineColor(): array {
    try {
      return StyleSheet::get($this->style, $this->style, 'MenuBoxItem', ['MenuSeparator'])->get('borderColorBottom');
    } catch (\Exception $e) {
      try {
        return $this->style->get('borderColorBottom');
      } catch (\Exception $e) {
        return $this->style->get('color');
      }
    }
  }

  protected function separatorThickness(): int {
    try {
      $thickness = StyleSheet::get($this->style, $this->style, 'MenuBoxItem', ['MenuSeparator'])->get('borderBottom', $this->geometry);
      return is_int($thickness) ? max(0, $thickness) : $this->separatorThickness;
    } catch (\Exception $e) {
      return $this->separatorThickness;
    }
  }

  protected function draw(): void {
    parent::draw();
    $this->drawSeparatorLines();
  }

  protected function drawSeparatorLines(): void {
    if ($this->texture === false || !is_int($this->geometry->innerWidth)) {
      return;
    }
    [$first, $last] = $this->screenRows();
    $rowHeight = $this->rowHeight();
    $normalRowHeight = $this->normalRowHeight();
    $color = $this->separatorLineColor();
    $background = $this->colorsForStyle('MenuBoxItem')['bg'];
    $thickness = $this->separatorThickness();
    if ($thickness <= 0) {
      return;
    }
    $visibleOffset = 0;
    foreach ($this->visibleItems() as $row) {
      if ($visibleOffset >= $last) {
        break;
      }
      if ($visibleOffset >= $first) {
        $screenRow = $visibleOffset - $first;
        $x1 = $this->geometryValue($this->geometry->borderLeft);
        $x2 = $this->geometryValue($this->geometry->width) - $this->geometryValue($this->geometry->borderRight);
        $stripY = $this->geometryValue($this->geometry->borderTop) +
          $this->geometryValue($this->geometry->paddingTop) +
          $screenRow * $rowHeight -
          ($this->scrollY % $rowHeight) +
          $normalRowHeight;
        $this->texture->drawFillRect($x1, $stripY, $x2, $stripY + $thickness, $background);
        if ($this->isSeparatorRow($row)) {
          $this->texture->drawFillRect($x1, $stripY, $x2, $stripY + $thickness, $color);
        }
      }
      $visibleOffset++;
    }
  }

  protected function appendMenuLeftCells(array &$cells, string $text, int $width, array $colors, int $limit): void {
    $glyphs = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    for ($i = 0; $i < $width && count($cells) < $limit; $i++) {
      $cells[] = $this->cell($i === 0 ? ($glyphs[0] ?? ' ') : ' ', $colors);
    }
  }

  protected function verticalScrollbarWidth(): int {
    return 0;
  }

  protected function layout(): void {
    parent::layout();
    $this->scrollX = 0;
    if (is_int($this->geometry->innerWidth)) {
      $this->geometry->contentWidth = $this->geometry->innerWidth;
    }
  }

  public function activeRowPosition(int &$x, int &$y): bool {
    $offset = $this->activeVisibleOffset();
    if ($offset === false) {
      return false;
    }
    $rowHeight = $this->rowHeight();
    $normalRowHeight = $this->normalRowHeight();
    $x =
      $this->geometryValue($this->geometry->x) +
      $this->geometryValue($this->geometry->width) -
      $this->geometryValue($this->geometry->borderRight);
    $y = $this->geometryValue($this->geometry->y) + $this->geometryValue($this->geometry->borderTop) + $this->geometryValue($this->geometry->paddingTop) + $offset * $rowHeight - $this->scrollY + (int)floor($normalRowHeight / 2);
    return true;
  }

  protected function geometryValue(mixed $value): int {
    return is_int($value) ? $value : 0;
  }

  public function openSubmenuForRow(MenuBoxRow $row): bool {
    $submenu = $this->findAncestorByType('SubMenu');
    if ($submenu === false) {
      return false;
    }
    $target = $row->isSubmenu() === true ? $row->getName() : $row->isSubmenu();
    foreach ($submenu->getDescendants() as $menuBox) {
      if ($menuBox instanceof MenuBox && $menuBox->belongsTo == $target) {
        $row->open();
        $x = 0;
        $y = 0;
        if (!$this->activeRowPosition($x, $y)) {
          return false;
        }
        $y -= $menuBox->geometry->marginTop + $menuBox->geometry->borderTop;
        $submenu->showMenuBox($target, $x, $y, false);
        return true;
      }
    }
    return false;
  }

  public function keyPressHandler($element, $event): bool {
    if (!$this->display) {
      return false;
    }
    $keyCombo = KeyCombo::resolve($event['mod'], $event['scancode'], $event['key']);
    $active = $this->getActive();
    switch ($keyCombo) {
      case Action::CLOSE:
        if ($this->submenu) {
          $this->hide();
          Element::refresh();
          return true;
        }
        break;
      case Action::MOVE_LEFT:
        if ($this->submenu) {
          $this->lower();
          $this->hide();
          Element::refresh();
        } else {
          $menu = $this->findAncestorByType('Menu');
          $menu->previousMenu();
        }
        return true;
      case Action::SWITCH_PREVIOUS:
        $menu = $this->findAncestorByType('Menu');
        $menu->previousMenu();
        return true;
      case Action::SWITCH_NEXT:
        $menu = $this->findAncestorByType('Menu');
        $menu->nextMenu();
        return true;
      case Action::MOVE_RIGHT:
        if ($active instanceof MenuBoxRow && $active->isSubmenu()) {
          return $active->openSubmenu();
        }
        $menu = $this->findAncestorByType('Menu');
        $menu->nextMenu();
        return true;
      case Action::SELECT_ITEM:
        if ($active instanceof MenuBoxRow && $active->isSubmenu()) {
          return $active->openSubmenu();
        }
        if (!$this->isActiveItemSelectable()) {
          return true;
        }
        parent::keyPressHandler($element, $event);
        if ($active instanceof MenuBoxRow) {
          $active->open();
        }
        $this->raise();
        Element::refresh();
        return true;
      case Action::DO_IT:
        if ($active instanceof MenuBoxRow && $active->isSubmenu()) {
          return $active->openSubmenu();
        }
        $menu = $this->findAncestorByType('Menu');
        $menu->closeMenu();
        $selectOnReturn = $this->selectOnReturn;
        $this->selectOnReturn = true;
        parent::keyPressHandler($element, $event);
        $this->selectOnReturn = $selectOnReturn;
        if ($active instanceof MenuBoxRow) {
          $active->open();
        }
        return true;
    }
    $handled = parent::keyPressHandler($element, $event);
    if (!$handled && in_array($keyCombo, [
      KeyCode::F1, KeyCode::F2, KeyCode::F3, KeyCode::F4,
      KeyCode::F5, KeyCode::F6, KeyCode::F7, KeyCode::F8,
      KeyCode::F9, KeyCode::F10, KeyCode::F11, KeyCode::F12,
      Action::CLOSE
    ], true)) {
      return false;
    }
    return true;
  }

  private function isActiveItemSelectable(): bool {
    $active = $this->getActive();
    return $active !== false && $active->isSelectable() !== false;
  }

}
