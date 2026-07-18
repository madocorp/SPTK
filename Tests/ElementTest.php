<?php

namespace Examples\Tests;

use SPTK\Element;
use SPTK\Elements\Button;
use SPTK\Elements\CheckBox;
use SPTK\Elements\ErrorPanel;
use SPTK\Elements\File;
use SPTK\Elements\Input;
use SPTK\Elements\ListBox;
use SPTK\Elements\ListHeaderRow;
use SPTK\Elements\ListItem;
use SPTK\Elements\MenuBox;
use SPTK\Elements\MenuBoxItem;
use SPTK\Elements\Panel;
use SPTK\Elements\PasswordInput;
use SPTK\Elements\RadioButton;
use SPTK\Elements\Select;
use SPTK\Elements\Tab;
use SPTK\Elements\Tabs;
use SPTK\Elements\TextReader;
use SPTK\Elements\WarningPanel;
use SPTK\Geometry;
use SPTK\Scrollbar;
use SPTK\SDLWrapper\KeyCode;
use SPTK\SDLWrapper\KeyCombo;
use SPTK\SDLWrapper\KeyModifier;
use SPTK\SDLWrapper\ScanCode;

class HeadlessElement extends Element {

  public function recalculateGeometry(): void {
    ;
  }

}

class HeadlessPanel extends Panel {

  public function recalculateGeometry(): void {
    ;
  }

  public function raise(): void {
    ;
  }

}

class CountingTabBox extends Element {

  public int $recalculateCount = 0;

  public function __construct(?Element $ancestor = null, ?string $name = null, ?string $class = null, ?string $type = null) {
    parent::__construct($ancestor, $name, $class, 'TabBox');
  }

  public function recalculateGeometry(): void {
    $this->recalculateCount++;
    $this->changed = false;
    $this->texture = true;
  }

}

class StyleCountingElement extends Element {

  public int $recalculateStyleCount = 0;

  public function recalculateStyle(): void {
    $this->recalculateStyleCount++;
    parent::recalculateStyle();
  }

}

class InspectableScrollElement extends HeadlessElement {

  public function maxX(): int {
    return $this->maxScrollX();
  }

  public function maxY(): int {
    return $this->maxScrollY();
  }

}

class InspectableTextReader extends TextReader {

  public function options(): array {
    return $this->scrollbarOptions();
  }

}

class InspectableFile extends File {

  public function options(): array {
    return $this->scrollbarOptions();
  }

}

class InspectableSelect extends Select {

  public function options(): array {
    return $this->scrollbarOptions();
  }

}

class ForgeHeadlessPanel extends Panel {

  public function __construct(?Element $ancestor = null, ?string $name = null, ?string $class = null, ?string $type = null) {
    parent::__construct($ancestor, $name, $class, 'Panel');
  }

  public function recalculateGeometry(): void {
    ;
  }

  public function raise(): void {
    ;
  }

}

class ForgeHeadlessWarningPanel extends WarningPanel {

  public function __construct(?Element $ancestor = null, ?string $name = null, ?string $class = null, ?string $type = null) {
    parent::__construct($ancestor, $name, $class, 'WarningPanel');
  }

  public function recalculateGeometry(): void {
    ;
  }

  public function raise(): void {
    ;
  }

  public function exposedCopyableBodyText(): string|false {
    return $this->copyableBodyText();
  }

}

class ForgeHeadlessErrorPanel extends ErrorPanel {

  public function __construct(?Element $ancestor = null, ?string $name = null, ?string $class = null, ?string $type = null) {
    parent::__construct($ancestor, $name, $class, 'ErrorPanel');
  }

  public function recalculateGeometry(): void {
    ;
  }

  public function raise(): void {
    ;
  }

  public function exposedCopyableBodyText(): string|false {
    return $this->copyableBodyText();
  }

}

class TabChangeListener {

  public static array $changes = [];

  public static function changed($tabs): void {
    self::$changes[] = $tabs->getCurrentTabContentName();
  }

}

class StackPanel extends Panel {

  public function recalculateGeometry(): void {
    ;
  }

}

class InspectableListBox extends ListBox {

  public function setGridSize(int $columns, int $rows = 1): void {
    $this->letterWidth = 1;
    $this->lineHeight = 1;
    $this->geometry->innerWidth = $columns;
    $this->geometry->innerHeight = $rows;
    $this->rowPaddingLeft = 0;
    $this->rowPaddingRight = 0;
    $this->listRowHeight = 1;
  }

  public function renderedText(): string {
    $rows = $this->buildCells();
    return implode('', array_map(fn($cell) => $cell['glyph'], $rows[0] ?? []));
  }

  public function renderedCells(): array {
    return $this->buildCells();
  }

}

class GreedyInput extends HeadlessElement {

  protected function init(): void {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
  }

  public function keyPressHandler($element, $event): bool {
    return true;
  }

}

class PassiveInput extends HeadlessElement {

  protected function init(): void {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
  }

  public function keyPressHandler($element, $event): bool {
    return false;
  }

}

class HeadlessInput extends Input {

  protected function init(): void {
    parent::init();
    $this->letterWidth = 1;
  }

  public function recalculateGeometry(): void {
    ;
  }

  protected function update() {
    $this->nthChild(0)->setValue($this->placeholderVisible() ? $this->placeholder : $this->getValue());
    if ($this->placeholderVisible()) {
      $this->nthChild(0)->addVariant('placeholder');
    } else {
      $this->nthChild(0)->removeVariant('placeholder');
    }
  }

  public function placeholderText(): string {
    return $this->nthChild(0)->getValue();
  }

  public function placeholderActive(): bool {
    return $this->nthChild(0)->hasClass('InputValue:placeholder');
  }

}

class SegmentedHeadlessInput extends Input {

  protected function init(): void {
    parent::init();
    $this->letterWidth = 1;
  }

  public function recalculateGeometry(): void {
    ;
  }

  protected function update() {
    $this->cursor->save();
    $this->cursor->toCoordinates($row1, $col1, $row2, $col2);
    $before = mb_substr($this->lines[0], 0, $col1);
    $selected = mb_substr($this->lines[0], $col1, $col2 - $col1);
    $after = mb_substr($this->lines[0], $col2);
    if ($this->placeholderVisible()) {
      $this->elementBefore->setValue($this->placeholder);
      $this->elementBefore->addVariant('placeholder');
    } else {
      $this->elementBefore->setValue($this->displayValue($before));
      $this->elementBefore->removeVariant('placeholder');
    }
    $this->elementSelected->setValue($selected === '' ? ' ' : $this->displayValue($selected));
    $this->elementAfter->setValue($this->displayValue($after));
  }

  public function moveCursorTo(int $col): void {
    $this->cursor->modify(0, $col, 0, $col);
    $this->update();
  }

  public function segments(): array {
    return [
      $this->nthChild(0)->getValue(),
      $this->nthChild(1)->getValue(),
      $this->nthChild(2)->getValue()
    ];
  }

}

class HeadlessPasswordInput extends PasswordInput {

  protected function init(): void {
    parent::init();
    $this->letterWidth = 1;
  }

  public function recalculateGeometry(): void {
    ;
  }

  protected function update() {
    $this->cursor->save();
    $this->cursor->toCoordinates($row1, $col1, $row2, $col2);
    $before = mb_substr($this->lines[0], 0, $col1);
    $selected = mb_substr($this->lines[0], $col1, $col2 - $col1);
    $after = mb_substr($this->lines[0], $col2);
    if ($this->placeholderVisible()) {
      $this->elementBefore->setValue($this->placeholder);
      $this->elementBefore->addVariant('placeholder');
    } else {
      $this->elementBefore->setValue($this->displayValue($before));
      $this->elementBefore->removeVariant('placeholder');
    }
    $this->elementSelected->setValue($selected === '' ? ' ' : $this->displayValue($selected));
    $this->elementAfter->setValue($this->displayValue($after));
  }

  public function moveCursorTo(int $col): void {
    $this->cursor->modify(0, $col, 0, $col);
    $this->update();
  }

  public function segments(): array {
    return [
      $this->nthChild(0)->getValue(),
      $this->nthChild(1)->getValue(),
      $this->nthChild(2)->getValue()
    ];
  }

  public function placeholderActive(): bool {
    return $this->nthChild(0)->hasClass('InputValue:placeholder');
  }

}

class ButtonTestAction {

  public static int $pressed = 0;

  public static function press($panel): void {
    self::$pressed++;
  }

}

return [
  'base elements manage identity text classes and lookup' => function (): void {
    $root = root();
    $box = new Element($root, 'box', 'alpha beta', 'Box');
    $box->setText('hello world');
    $child = new Element($box, 'child', null, 'Thing');

    assertSame(0, $root->getId(), 'root receives the first element id');
    assertSame(1, $box->getId(), 'child ids increment');
    assertSame('Box', $box->getType(), 'explicit element type is stored');
    assertSame(['alpha', 'beta'], $box->getClass(), 'constructor classes are split');
    assertSame('hello world', $box->getText(), 'setText and getText round-trip words');
    assertSame($child, Element::byName('child', $root), 'byName finds nested descendants');
    assertSame($box, Element::firstByType('Box', $root), 'firstByType finds the first matching element');
    assertSame([$box], Element::allByType('Box', $root), 'allByType returns matching elements');
  },

  'base elements maintain descendant and stack order' => function (): void {
    $root = root();
    $a = new Element($root, 'a', null, 'Box');
    $b = new Element($root, 'b', null, 'Box');
    $c = new Element($root, 'c', null, 'Box');

    $a->raise();
    assertSame(['b', 'c', 'a'], array_map(fn($e) => $e->getName(), propertyValue($root, 'stack')), 'raise moves an element to the end of the render stack');

    $a->lower();
    assertSame(['a', 'b', 'c'], array_map(fn($e) => $e->getName(), propertyValue($root, 'stack')), 'lower moves an element to the front of the render stack');

    $a->moveAfter($c);
    assertSame(['b', 'c', 'a'], array_map(fn($e) => $e->getName(), $root->getDescendants()), 'moveAfter changes descendant order');

    $b->remove();
    assertSame(['c', 'a'], array_map(fn($e) => $e->getName(), $root->getDescendants()), 'remove detaches descendants');
  },

  'text reader handles vertical navigation keys' => function (): void {
    KeyCombo::init();
    $root = root();
    $box = new TextReader($root, 'scroll-box');
    $box->getGeometry()->lineHeight = 10;
    $box->getGeometry()->innerHeight = 30;
    $box->getGeometry()->contentHeight = 100;

    assertTrue($box->eventHandler([
      'name' => 'KeyPress',
      'mod' => KeyModifier::NONE,
      'scancode' => ScanCode::DOWN,
      'key' => KeyCode::DOWN
    ]), 'down scrolls a focusable box');
    assertSame(10, propertyValue($box, 'scrollY'), 'down scrolls by one line');

    $box->eventHandler([
      'name' => 'KeyPress',
      'mod' => KeyModifier::NONE,
      'scancode' => ScanCode::PAGEDOWN,
      'key' => KeyCode::PAGEDOWN
    ]);
    assertSame(30, propertyValue($box, 'scrollY'), 'page down scrolls by one viewport minus one line');

    $box->eventHandler([
      'name' => 'KeyPress',
      'mod' => KeyModifier::NONE,
      'scancode' => ScanCode::END,
      'key' => KeyCode::END
    ]);
    assertSame(70, propertyValue($box, 'scrollY'), 'end scrolls to the bottom');

    $box->eventHandler([
      'name' => 'KeyPress',
      'mod' => KeyModifier::NONE,
      'scancode' => ScanCode::PAGEUP,
      'key' => KeyCode::PAGEUP
    ]);
    assertSame(50, propertyValue($box, 'scrollY'), 'page up scrolls by one viewport minus one line');

    $box->eventHandler([
      'name' => 'KeyPress',
      'mod' => KeyModifier::NONE,
      'scancode' => ScanCode::HOME,
      'key' => KeyCode::HOME
    ]);
    assertSame(0, propertyValue($box, 'scrollY'), 'home scrolls to the top');
  },

  'text reader active state does not dirty text texture' => function (): void {
    $root = root();
    $reader = new TextReader($root, 'reader');
    $child = new StyleCountingElement($reader, 'child', null, 'Word');
    $changed = new \ReflectionProperty(Element::class, 'changed');
    $changed->setAccessible(true);
    $changed->setValue($reader, false);
    $before = $child->recalculateStyleCount;

    $reader->addVariant('active');
    $reader->removeVariant('active');

    assertSame($before, $child->recalculateStyleCount, 'text reader focus changes do not restyle descendant words');
    assertFalse(propertyValue($reader, 'changed'), 'text reader focus changes do not mark the reader texture dirty');
  },

  'geometry preserves unresolved content dimensions while deriving sizes' => function (): void {
    $geometry = new Geometry(null);
    $geometry->width = 'calculated';
    $geometry->height = 'content';

    $geometry->setDerivedWidths();
    $geometry->setDerivedHeights();
    $geometry->limitateWidth();
    $geometry->limitateHeight();

    assertSame('calculated', $geometry->innerWidth, 'unresolved calculated width remains available');
    assertSame('calculated', $geometry->fullWidth, 'unresolved calculated full width remains available');
    assertSame('content', $geometry->innerHeight, 'unresolved content height remains available');
    assertSame('content', $geometry->fullHeight, 'unresolved content full height remains available');
  },

  'scroll range ignores padding only overflow' => function (): void {
    $root = root();
    $element = new InspectableScrollElement($root, 'scrollable');
    $geometry = $element->getGeometry();
    $geometry->innerWidth = 100;
    $geometry->innerHeight = 80;
    $geometry->paddingLeft = 10;
    $geometry->paddingRight = 10;
    $geometry->paddingBottom = 10;
    $geometry->contentWidth = 120;
    $geometry->contentHeight = 90;

    assertSame(0, $element->maxX(), 'horizontal padding alone does not create scroll range');
    assertSame(0, $element->maxY(), 'bottom padding alone does not create scroll range');

    $geometry->contentWidth = 121;
    $geometry->contentHeight = 91;

    assertSame(1, $element->maxX(), 'real horizontal overflow still creates scroll range');
    assertSame(1, $element->maxY(), 'real vertical overflow still creates scroll range');
  },

  'scrollbar visibility ignores padding only overflow' => function (): void {
    $geometry = new Geometry(null);
    $geometry->width = 140;
    $geometry->height = 100;
    $geometry->innerWidth = 100;
    $geometry->innerHeight = 80;
    $geometry->paddingLeft = 10;
    $geometry->paddingRight = 10;
    $geometry->paddingBottom = 10;
    $scrollbar = (new \ReflectionClass(Scrollbar::class))->newInstanceWithoutConstructor();
    $vertical = new \ReflectionMethod(Scrollbar::class, 'vertical');
    $vertical->setAccessible(true);
    $horizontal = new \ReflectionMethod(Scrollbar::class, 'horizontal');
    $horizontal->setAccessible(true);

    assertFalse($vertical->invoke($scrollbar, $geometry, 0, 90, 10, []), 'vertical scrollbar ignores bottom padding overflow');
    assertFalse(
      $horizontal->invoke($scrollbar, $geometry, 0, 120, 10, false, ['horizontalContentIncludesPadding' => true]),
      'horizontal scrollbar ignores horizontal padding overflow'
    );
    assertTrue(
      is_array($vertical->invoke($scrollbar, $geometry, 0, 92, 10, [])),
      'vertical scrollbar appears for real content overflow'
    );
    assertTrue(
      is_array($horizontal->invoke($scrollbar, $geometry, 0, 122, 10, false, ['horizontalContentIncludesPadding' => true])),
      'horizontal scrollbar appears for real content overflow'
    );
  },

  'text reader disables horizontal scrollbar' => function (): void {
    $root = root();
    $reader = new InspectableTextReader($root, 'reader');
    $options = $reader->options();

    assertSame(false, $options['horizontal'] ?? null, 'text reader does not draw a horizontal scrollbar');
    assertSame(true, $options['horizontalContentIncludesPadding'] ?? null, 'text reader keeps generic padding-aware width math');
  },

  'file selector disables horizontal scrollbar' => function (): void {
    $root = root();
    $file = new InspectableFile($root, 'file');
    $options = $file->options();

    assertSame(false, $options['vertical'] ?? null, 'file selector does not draw a vertical scrollbar');
    assertSame(false, $options['horizontal'] ?? null, 'file selector does not draw a horizontal scrollbar');
    assertSame(true, $options['horizontalContentIncludesPadding'] ?? null, 'file selector keeps generic padding-aware width math');
  },

  'select disables scrollbars' => function (): void {
    $root = root();
    $select = new InspectableSelect($root, 'select');
    $options = $select->options();

    assertSame(false, $options['vertical'] ?? null, 'select does not draw a vertical scrollbar');
    assertSame(false, $options['horizontal'] ?? null, 'select does not draw a horizontal scrollbar');
    assertSame(true, $options['horizontalContentIncludesPadding'] ?? null, 'select keeps generic padding-aware width math');
  },

  'child classes are inherited only while active' => function (): void {
    $root = root();
    $parent = new Element($root, 'parent', null, 'Box');
    $parent->addChildClass('temporary');
    $inside = new Element($parent, 'inside', null, 'Thing');
    $parent->removeChildClass('temporary');
    $outside = new Element($parent, 'outside', null, 'Thing');

    assertSame(['temporary'], $inside->getClass(), 'child class is inherited by new descendants');
    assertSame([], $outside->getClass(), 'removed child class is not inherited by later descendants');
  },

  'checkbox coerces values and mirrors active state' => function (): void {
    $root = root();
    $checkBox = new CheckBox($root, 'accepted');

    $checkBox->setValue('true');
    assertTrue($checkBox->getValue(), 'string true sets checkbox value');
    assertSame('X', $checkBox->nthChild(0)->getText(), 'checked state writes the value marker');

    $checkBox->setValue('0');
    assertFalse($checkBox->getValue(), 'string zero clears checkbox value');
    assertSame('', $checkBox->nthChild(0)->getText(), 'unchecked state clears the value marker');

    $checkBox->addVariant('active');
    assertTrue($checkBox->nthChild(0)->hasClass('CheckBoxValue:active'), 'variant active class propagates to the value box');
    $checkBox->removeVariant('active');
    assertFalse($checkBox->nthChild(0)->hasClass('CheckBoxValue:active'), 'variant active class is removed from the value box');
  },

  'radio buttons mark with O and exclude group peers' => function (): void {
    $root = root();
    $first = new RadioButton($root, 'first');
    $first->setGroup('mode');
    $second = new RadioButton($root, 'second');
    $second->setGroup('mode');
    $other = new RadioButton($root, 'other');
    $other->setGroup('other');

    $first->setValue(true);
    assertTrue($first->getValue(), 'radio button accepts true values');
    assertSame('O', $first->nthChild(0)->getText(), 'selected radio button writes the value marker');

    $second->setValue(true);
    assertFalse($first->getValue(), 'selecting a radio button clears a peer in the same group');
    assertSame('', $first->nthChild(0)->getText(), 'cleared radio button removes the marker');
    assertTrue($second->getValue(), 'selected peer remains active');

    $other->setValue(true);
    assertTrue($second->getValue(), 'selecting another group does not clear this group');

    $second->addVariant('active');
    assertTrue($second->nthChild(0)->hasClass('RadioButtonValue:active'), 'variant active class propagates to the radio value box');
    $second->removeVariant('active');
    assertFalse($second->nthChild(0)->hasClass('RadioButtonValue:active'), 'variant active class is removed from the radio value box');
  },

  'list items and list boxes expose usable values' => function (): void {
    $root = root();
    $list = new ListBox($root, 'fruits');
    $apple = new ListItem($list);
    $apple->setValue('apple');
    $apple->setFilterable('true');
    $apple->setRight('fresh');
    $apple->addRightClass('preview');
    $banana = new ListItem($list);
    $banana->setValue('banana');
    $banana->setSelectable('true');
    $banana->select();

    assertSame('apple', $list->getValue(), 'simple list value is the active item value');
    assertSame('fresh', $list->getItems()[0]->getRight(), 'list item right text can be set');
    assertSame(['preview'], $list->getItems()[0]->getClass(), 'list item right classes are stored on the row model');
    $list->addVariant('active');
    assertTrue($apple->match('app'), 'filterable list items match from the start of their text');
    assertTrue($list->getItems()[0]->isMatched(), 'matched list items mark the backing row');
    assertSame(3, $list->getItems()[0]->getMatchLength(), 'matched list items store the match length for grid rendering');
    $list->resetSearch();
    assertFalse($list->getItems()[0]->isMatched(), 'resetting list search clears the row match state');
    assertTrue($apple->match('app'), 'filterable list items can match again after reset');
    assertFalse($apple->match('pp'), 'filterable list items do not match non-prefix text');
    assertFalse($list->getItems()[0]->isMatched(), 'failed matching clears stale row match state');

    $list->setValueType('order');
    assertSame(['apple', 'banana'], $list->getValue(), 'order value returns item values in descendant order');

    $list->setValueType('select');
    assertSame(['banana'], $list->getValue(), 'select value returns selected selectable item values');

    $columns = new ListBox($root, 'columns');
    $columns->setValueType('select');
    $columns->setSelectionOrder('true');
    $id = new ListItem($columns);
    $id->setValue('id');
    $id->setSelectable('true');
    $name = new ListItem($columns);
    $name->setValue('name');
    $name->setSelectable('true');
    $email = new ListItem($columns);
    $email->setValue('email');
    $email->setSelectable('true');

    $columns->setSelectedValues(['email', 'id']);

    assertSame(['email', 'id'], $columns->getValue(), 'ordered select value returns values in selection order');
    assertSame('1', $columns->getItems()[2]->getLeft(), 'first ordered selection shows marker 1');
    assertSame('2', $columns->getItems()[0]->getLeft(), 'second ordered selection shows marker 2');
    assertTrue($email->hasVariant('selected'), 'ordered selected items keep the selected visual variant');
    assertTrue($id->hasVariant('selected'), 'ordered selected items apply the selected visual variant');
    $columns->moveCursor(2);
    assertSame('email', $columns->getValue()[0], 'cursor movement preserves ordered selection value');
    $columns->moveCursor(1);
    assertFalse($name->hasVariant('selected'), 'cursor movement does not apply the selected visual variant');

    $columns->selectAll();

    assertSame(['id', 'name', 'email'], $columns->getValue(), 'selectAll selects every selectable item');

    $columns->clearSelection();

    assertSame([], $columns->getValue(), 'clearSelection deselects every selectable item');

    $bulk = new ListBox($root, 'bulk');
    $bulk->setItems([
      ['value' => 'one', 'filterable' => true],
      ['value' => 'two', 'right' => '2']
    ]);
    assertSame(0, $bulk->countDescendants(), 'bulk list rows do not create per-row elements');
    assertSame('one', $bulk->getValue(), 'bulk list rows expose simple values');
    assertSame('2', $bulk->getItems()[1]->getRight(), 'bulk list rows keep right text');

    $plain = new InspectableListBox($root, 'plain');
    $plain->setGridSize(8);
    $plain->addItem('abc');
    assertSame('abc     ', $plain->renderedText(), 'plain lists reserve no leading marker column');

    $selectableList = new InspectableListBox($root, 'selectable-list');
    $selectableList->setGridSize(8);
    $selectableList->addItem(['text' => 'abc', 'selectable' => true]);
    assertSame('  abc   ', $selectableList->renderedText(), 'selectable lists reserve two leading marker columns');

    $pinList = new InspectableListBox($root, 'pin-list');
    $pinList->setGridSize(8);
    $pinList->addItem(['text' => 'abc', 'leftReserve' => 2]);
    assertSame('  abc   ', $pinList->renderedText(), 'plain lists can reserve a marker column without making rows selectable');

    $queries = new InspectableListBox($root, 'queries');
    $queries->setGridSize(20);
    $queries->addItem([
      'text' => 'very-long-query-title',
      'leftReserve' => 2,
      'right' => 'done',
      'rightReserve' => 6,
      'truncateMarker' => '~'
    ]);
    assertSame('  very-long-q~  done', $queries->renderedText(), 'long text is clamped between reserved marker slots');
    $queries->addVariant('active');
    $cells = $queries->renderedCells();
    assertSame([0, 0, 255, 255], $cells[0][16]['fg'], 'right markers keep the normal marker color on active rows');
    assertSame($cells[0][0]['bg'], $cells[0][16]['bg'], 'right markers keep the active row background');

    $wide = new ListBox($root, 'wide');
    $wide->addItem(['text' => 'this is wider than the visible list area']);
    $measure = new \ReflectionMethod($wide, 'measure');
    $measure->setAccessible(true);
    $measure->invoke($wide);
    $wide->getGeometry()->contentWidth = 999;
    $layout = new \ReflectionMethod($wide, 'layout');
    $layout->setAccessible(true);
    $layout->invoke($wide);

    assertSame($wide->getGeometry()->innerWidth, $wide->getGeometry()->contentWidth, 'list boxes never preserve horizontal overflow width');

    $header = new ListHeaderRow($root);
    new Element($header, null, 'w50', 'Header');
    new Element($header, null, 'w50', 'Header');
    $grid = new ListBox($root, 'grid-list');
    $grid->getStyle()->set('width', '200px');
    $grid->getStyle()->set('height', '100px');
    $letterWidth = new \ReflectionProperty(ListBox::class, 'letterWidth');
    $letterWidth->setAccessible(true);
    $letterWidth->setValue($grid, 10);
    $grid->addItem(['columns' => ['abcdefghi', 'xy']]);
    $measureHeader = new \ReflectionMethod($header, 'measure');
    $measureHeader->setAccessible(true);
    $measureHeader->invoke($header);
    $measureGrid = new \ReflectionMethod($grid, 'measure');
    $measureGrid->setAccessible(true);
    $measureGrid->invoke($grid);
    $calculateWidths = new \ReflectionMethod($header, 'calculateWidths');
    $calculateWidths->setAccessible(true);
    $calculateWidths->invoke($header);
    $buildCells = new \ReflectionMethod($grid, 'buildCells');
    $buildCells->setAccessible(true);
    $cells = $buildCells->invoke($grid);

    assertSame('abcdefghixy      ', implode('', array_column($cells[0], 'glyph')), 'column list rows align values to text-grid columns');
    assertSame(11, $header->nthChild(0)->getGeometry()->width, 'list headers reserve the list grid text offset');
    assertSame(90, $header->nthChild(1)->getGeometry()->width, 'first header uses the first grid column width');
    assertSame(80, $header->nthChild(2)->getGeometry()->width, 'second header uses the second grid column width');
  },

  'menu space acts only on submenu and selectable items' => function (): void {
    $root = root();
    $menu = new MenuBox($root, 'menu');
    $action = new MenuBoxItem($menu);
    $action->setValue('Action');
    $separator = new MenuBoxItem($menu);
    $separator->setValue('separator text should not matter');
    $separator->addClass('MenuSeparator');
    $multi = new MenuBoxItem($menu);
    $multi->setValue('Multi');
    $multi->setSelectable('true');
    $group = new MenuBoxItem($menu);
    $group->setValue('Group');
    $group->setSelectable('group');
    $selectable = new \ReflectionMethod(MenuBox::class, 'isActiveItemSelectable');
    $selectable->setAccessible(true);

    assertFalse($selectable->invoke($menu), 'action-only menu items are not selectable for space');
    $menu->moveCursor(1);
    assertSame('separator text should not matter', $menu->getActive()->getText(), 'menu separator rows keep their content and cursor position');
    assertFalse($selectable->invoke($menu), 'separator rows remain action-only rows unless selectable');

    $menu->moveCursor(2);
    assertTrue($selectable->invoke($menu), 'true-selectable menu items are selectable for space');

    $menu->moveCursor(3);
    assertTrue($selectable->invoke($menu), 'grouped selectable menu items are selectable for space');
  },

  'menu box width follows marker item and submenu column formula' => function (): void {
    $root = root();
    $menu = new MenuBox($root, 'menu');
    $menu->show();
    $menu->getStyle()->set('minWidth', '150px');
    $menu->getStyle()->set('height', '100px');
    $letterWidth = new \ReflectionProperty(MenuBox::class, 'letterWidth');
    $letterWidth->setAccessible(true);
    $letterWidth->setValue($menu, 7);
    $menu->addItem(['text' => 'A']);
    $menu->addItem(['text' => 'Sep', 'classes' => ['MenuSeparator']]);

    $measure = new \ReflectionMethod($menu, 'measure');
    $measure->setAccessible(true);
    $measure->invoke($menu);

    assertSame(61, $menu->getGeometry()->width, 'separator rows keep their text in menu width calculation');
    $buildCells = new \ReflectionMethod($menu, 'buildCells');
    $buildCells->setAccessible(true);
    $cells = $buildCells->invoke($menu);
    assertSame(' Sep ', implode('', array_column($cells[1], 'glyph')), 'menu separators keep the normal text grid row content');
    $rowHeight = new \ReflectionMethod($menu, 'rowHeight');
    $rowHeight->setAccessible(true);
    $normalRowHeight = new \ReflectionMethod($menu, 'normalRowHeight');
    $normalRowHeight->setAccessible(true);
    $plainMenu = new MenuBox($root, 'plain-menu');
    assertSame($normalRowHeight->invoke($plainMenu) + 3, $rowHeight->invoke($plainMenu), 'plain menus reserve separator space in every row');
    assertSame($normalRowHeight->invoke($menu) + 3, $rowHeight->invoke($menu), 'menu separators use the same reserved row space');

    $scrolling = new MenuBox($root, 'scrolling-menu');
    $scrolling->show();
    $scrolling->getStyle()->set('minWidth', '0px');
    $scrolling->getStyle()->set('maxHeight', '30px');
    $letterWidth->setValue($scrolling, 7);
    $scrolling->addItem(['text' => 'Long', 'submenu' => true]);
    $scrolling->addItem(['text' => 'Two']);
    $scrolling->addItem(['text' => 'Three']);

    $measure->invoke($scrolling);

    assertSame(82, $scrolling->getGeometry()->width, 'submenu menus reserve a two-column right side');

    $withRight = new MenuBox($root, 'right-menu');
    $withRight->show();
    $withRight->getStyle()->set('minWidth', '0px');
    $letterWidth->setValue($withRight, 7);
    $withRight->addItem(['text' => 'Open', 'right' => 'Ctrl+O']);
    $withRight->addItem(['text' => 'Pinned', 'selectable' => true]);

    $measure->invoke($withRight);

    assertSame(131, $withRight->getGeometry()->width, 'selectable and right-text menus reserve their dynamic columns');

    $withRight->getGeometry()->contentWidth = 999;
    $layout = new \ReflectionMethod($withRight, 'layout');
    $layout->setAccessible(true);
    $layout->invoke($withRight);

    assertSame($withRight->getGeometry()->innerWidth, $withRight->getGeometry()->contentWidth, 'menus never preserve horizontal overflow width');

    $x = 0;
    $y = 0;
    $withRight->getGeometry()->x = 20;
    assertTrue($withRight->activeRowPosition($x, $y), 'menu can report active row submenu position');
    assertSame(
      $withRight->getGeometry()->x + $withRight->getGeometry()->width - $withRight->getGeometry()->borderRight,
      $x,
      'submenu x position overlaps the parent menu right border'
    );
  },

  'select accepts option sources and shows hint as placeholder' => function (): void {
    $root = root();
    $select = new Select($root, 'size');
    $select->setHint('Size');
    $select->setOptions('Small, Medium, Large');

    assertSame(['Small', 'Medium', 'Large'], $select->getOptions(), 'comma separated options are parsed and trimmed');
    assertSame('Size', $select->nthChild(0)->getValue(), 'empty select displays its hint');
    assertTrue($select->nthChild(0)->hasClass('InputValue:placeholder'), 'hint uses placeholder styling');

    $select->setValue('Medium');

    assertSame('Medium', $select->getValue(), 'select stores the selected value');
    assertSame('Medium', $select->nthChild(0)->getValue(), 'selected value replaces the hint');
    assertFalse($select->nthChild(0)->hasClass('InputValue:placeholder'), 'selected value removes placeholder styling');

    $select->setOptions(['One', 'Two']);

    assertSame(['One', 'Two'], $select->getOptions(), 'array options can be set from code');
  },

  'select supports comma separated multiple values' => function (): void {
    $root = root();
    $select = new Select($root, 'columns');
    $select->setOptions(['id', 'name', 'email']);
    $select->setMultiple('true');

    assertSame('', $select->getValue(), 'multiple select starts with an empty value');
    assertSame('none', $select->nthChild(0)->getValue(), 'empty multiple select shows none placeholder');
    assertTrue($select->nthChild(0)->hasClass('InputValue:placeholder'), 'none uses placeholder styling');

    $select->selected(['id', 'email']);

    assertSame('id, email', $select->getValue(), 'multiple select stores comma separated values');
    assertSame('id, email', $select->nthChild(0)->getValue(), 'partial multiple selection shows selected values');
    assertFalse($select->nthChild(0)->hasClass('InputValue:placeholder'), 'partial multiple selection is not placeholder text');

    $select->selected(['id', 'name', 'email']);

    assertSame('id, name, email', $select->getValue(), 'all selected values remain stored');
    assertSame('all', $select->nthChild(0)->getValue(), 'complete multiple selection shows all placeholder');
    assertTrue($select->nthChild(0)->hasClass('InputValue:placeholder'), 'all uses placeholder styling');
  },

  'input shows placeholder while empty' => function (): void {
    $root = root();
    $input = new HeadlessInput($root, 'input');

    $input->setPlaceholder('empty means default');

    assertSame('', $input->placeholderText(), 'inactive empty input hides placeholder text');
    assertFalse($input->placeholderActive(), 'inactive empty input does not use placeholder styling');

    $input->addVariant('active');

    assertSame('empty means default', $input->placeholderText(), 'just activated empty input shows placeholder text');
    assertTrue($input->placeholderActive(), 'just activated empty input uses placeholder styling');

    $input->setValue('named');

    assertSame('named', $input->placeholderText(), 'input value replaces placeholder text');
    assertFalse($input->placeholderActive(), 'non-empty input removes placeholder styling');
  },

  'input hides placeholder after any active keypress' => function (): void {
    $root = root();
    $input = new HeadlessInput($root, 'input');
    $input->setPlaceholder('empty means default');
    $input->addVariant('active');

    $input->keyPressHandler($input, [
      'mod' => KeyModifier::NONE,
      'scancode' => ScanCode::BACKSPACE,
      'key' => KeyCode::BACKSPACE
    ]);

    assertSame('', $input->placeholderText(), 'backspace clears the active empty placeholder');
    assertFalse($input->placeholderActive(), 'backspace removes placeholder styling');

    $input->removeVariant('active');
    assertSame('', $input->placeholderText(), 'inactive empty input keeps placeholder hidden');
    assertFalse($input->placeholderActive(), 'inactive empty input keeps placeholder styling disabled');

    $input->addVariant('active');
    $input->keyPressHandler($input, [
      'mod' => KeyModifier::NONE,
      'scancode' => ScanCode::LEFT,
      'key' => KeyCode::LEFT
    ]);

    assertSame('', $input->placeholderText(), 'cursor movement clears the active empty placeholder');
    assertFalse($input->placeholderActive(), 'cursor movement removes placeholder styling');
  },

  'input clears cursor segments when inactive' => function (): void {
    $root = root();
    $input = new SegmentedHeadlessInput($root, 'input');

    $input->setValue('test');
    $input->addVariant('active');
    $input->moveCursorTo(2);

    assertSame(['te', 's', 't'], $input->segments(), 'active input splits text around the cursor');

    $input->removeVariant('active');

    assertSame(['test', '', ''], $input->segments(), 'inactive input clears stale cursor and after-cursor text');
  },

  'password input masks visible value only' => function (): void {
    $root = root();
    $input = new HeadlessPasswordInput($root, 'password', null, 'PasswordInput');

    $input->setValue('secret');

    assertSame('Input', $input->getType(), 'password input uses the normal Input type for styling');
    assertSame('secret', $input->getValue(), 'password input keeps the real value for application code');
    assertSame(['******', '', ''], $input->segments(), 'inactive password input displays one mask per character');

    $input->addVariant('active');
    $input->moveCursorTo(3);

    assertSame(['***', '*', '**'], $input->segments(), 'active password input masks each cursor segment');
  },

  'password input leaves active placeholder readable' => function (): void {
    $root = root();
    $input = new HeadlessPasswordInput($root, 'password');

    $input->setPlaceholder('Password');
    $input->addVariant('active');

    assertSame(['Password', ' ', ''], $input->segments(), 'password placeholder text is not masked');
    assertTrue($input->placeholderActive(), 'password placeholder keeps placeholder styling');
  },

  'tabs select one content section at a time' => function (): void {
    $root = root();
    $tabs = new Tabs($root, 'tabs');
    $tabA = new Tab($tabs);
    $tabA->setContentName('content-a');
    $tabB = new Tab($tabs);
    $tabB->setContentName('content-b');
    $contentA = new HeadlessElement($root, 'content-a', null, 'TabBox');
    $contentB = new HeadlessElement($root, 'content-b', null, 'TabBox');

    $tabs->selectTab(1);

    assertFalse($tabA->hasClass('Tab:active'), 'unselected tab loses active class');
    assertTrue($tabB->hasClass('Tab:cursor'), 'selected tab gains cursor class when the tab strip is inactive');
    assertFalse(propertyValue($contentA, 'display'), 'unselected tab content is hidden');
    assertTrue(propertyValue($contentB, 'display'), 'selected tab content is shown');
    assertSame($contentB, $tabs->getTabContent(), 'getTabContent returns the current tab content');
  },

  'tabs call onChange for direct and relative selection' => function (): void {
    $root = root();
    $tabs = new Tabs($root, 'tabs');
    $tabs->setOnChange([TabChangeListener::class, 'changed']);
    $tabA = new Tab($tabs);
    $tabA->setContentName('content-a');
    $tabB = new Tab($tabs);
    $tabB->setContentName('content-b');
    new HeadlessElement($root, 'content-a', null, 'TabBox');
    new HeadlessElement($root, 'content-b', null, 'TabBox');
    TabChangeListener::$changes = [];

    $tabs->selectTab(1);
    $tabs->selectRelative(-1);

    assertSame(['content-b', 'content-a'], TabChangeListener::$changes, 'tab onChange receives direct and relative tab changes');
  },

  'tabs reject nested content elements' => function (): void {
    $root = root();
    $tabs = new Tabs($root, 'tabs');

    assertThrows(
      fn() => new HeadlessElement($tabs, 'bad-content', null, 'TabBox'),
      'tabs only accept Tab children'
    );
  },

  'panels focus inputs inside the active tab content' => function (): void {
    $root = root();
    $panel = new HeadlessPanel($root, 'panel', null, 'Panel');
    $content = new HeadlessElement($panel, 'content', null, 'PanelContent');
    $tabs = new Tabs($content, 'tabs');
    $tabA = new Tab($tabs);
    $tabA->setContentName('content-a');
    $tabB = new Tab($tabs);
    $tabB->setContentName('content-b');
    $contentA = new HeadlessElement($content, 'content-a', null, 'TabBox');
    new CheckBox($contentA, 'field-a');
    $contentB = new HeadlessElement($content, 'content-b', null, 'TabBox');
    new CheckBox($contentB, 'field-b');

    $panel->show();

    $inputNames = array_map(fn($input) => $input['element']->getName(), propertyValue($panel, 'inputList'));
    assertSame(['tabs', 'field-a'], $inputNames, 'panel includes tabs and controls from the active tab');
    assertTrue($tabs->hasClass('Tabs:active'), 'tab strip starts focused when the panel opens');
    assertTrue($tabs->nthChild(0)->hasClass('Tab:active'), 'focused tab strip marks the selected tab');

    $tabs->selectTab(1);

    $inputNames = array_map(fn($input) => $input['element']->getName(), propertyValue($panel, 'inputList'));
    assertSame(['tabs', 'field-b'], $inputNames, 'panel input list refreshes when the active tab changes');
    assertTrue($tabs->hasClass('Tabs:active'), 'tab strip stays focused after changing tabs');
    assertTrue($tabs->nthChild(1)->hasClass('Tab:active'), 'focus marker follows the selected tab');

    $tabs->selectTab(0);
    assertSame($contentA, $tabs->getTabContent(), 'focused tab strip can select another tab');
    assertTrue($tabs->nthChild(0)->hasClass('Tab:active'), 'focus marker follows the reselected tab');
  },

  'focused tab strip keeps key routing after switching content' => function (): void {
    $root = root();
    KeyCombo::init();
    $panel = new HeadlessPanel($root, 'panel', null, 'Panel');
    $content = new HeadlessElement($panel, 'content', null, 'PanelContent');
    $tabs = new Tabs($content, 'tabs');
    foreach (['content-a', 'content-b', 'content-c'] as $contentName) {
      $tab = new Tab($tabs);
      $tab->setContentName($contentName);
      $tab->setText($contentName);
      new HeadlessElement($content, $contentName, null, 'TabBox');
    }

    $panel->show();
    $event = ['name' => 'KeyPress', 'mod' => KeyModifier::NONE, 'scancode' => ScanCode::RIGHT, 'key' => KeyCode::RIGHT];

    assertTrue($panel->eventHandler($event), 'panel routes first right key to focused tab strip');
    assertSame(1, $tabs->getCurrentTab(), 'first right key switches to second tab');
    assertTrue($tabs->hasClass('Tabs:active'), 'tab strip remains active after first switch');
    assertTrue($panel->eventHandler($event), 'panel routes second right key to focused tab strip');
    assertSame(2, $tabs->getCurrentTab(), 'second right key switches to third tab');
  },

  'tab switches reuse already laid out content' => function (): void {
    $root = root();
    $panel = new HeadlessPanel($root, 'panel', null, 'Panel');
    $content = new HeadlessElement($panel, 'content', null, 'PanelContent');
    $tabs = new Tabs($content, 'tabs');
    foreach (['content-a', 'content-b'] as $contentName) {
      $tab = new Tab($tabs);
      $tab->setContentName($contentName);
      $tab->setText($contentName);
    }
    $contentA = new CountingTabBox($content, 'content-a');
    $contentB = new CountingTabBox($content, 'content-b');

    $panel->show();
    $tabs->selectTab(1);
    $tabs->selectTab(0);
    $firstCounts = [$contentA->recalculateCount, $contentB->recalculateCount];

    $tabs->selectTab(1);
    $tabs->selectTab(0);

    assertSame($firstCounts, [$contentA->recalculateCount, $contentB->recalculateCount], 'tab switches show existing content without laying it out again');
  },

  'panels mark the raised visible sibling active' => function (): void {
    $root = root();
    $panelA = new StackPanel($root, 'panel-a', null, 'Panel');
    $titleA = new HeadlessElement($panelA, 'title-a', null, 'PanelTitle');
    $panelB = new StackPanel($root, 'panel-b', null, 'Panel');
    $titleB = new HeadlessElement($panelB, 'title-b', null, 'PanelTitle');

    $panelA->show();
    $panelB->show();

    assertFalse($panelA->hasClass('Panel:active'), 'older visible panel is not active');
    assertFalse($titleA->hasClass('PanelTitle:active'), 'older visible panel title is not active');
    assertTrue($panelB->hasClass('Panel:active'), 'raised panel is active');
    assertTrue($titleB->hasClass('PanelTitle:active'), 'raised panel title is active');

    $panelA->raise();

    assertTrue($panelA->hasClass('Panel:active'), 'raised panel becomes active');
    assertTrue($titleA->hasClass('PanelTitle:active'), 'raised panel title becomes active');
    assertFalse($panelB->hasClass('Panel:active'), 'previous active panel is no longer active');
    assertFalse($titleB->hasClass('PanelTitle:active'), 'previous active panel title is no longer active');
  },

  'panel input refresh preserves sibling stack order' => function (): void {
    $root = root();
    $panelA = new StackPanel($root, 'panel-a', null, 'Panel');
    new PassiveInput($panelA, 'field-a');
    $panelB = new StackPanel($root, 'panel-b', null, 'Panel');
    new PassiveInput($panelB, 'field-b');

    $panelA->show();
    $panelB->show();
    $stackBefore = array_map(fn($element) => $element->getName(), propertyValue($root, 'stack'));

    $panelA->refreshInputList('field-a');

    assertSame($stackBefore, array_map(fn($element) => $element->getName(), propertyValue($root, 'stack')), 'refreshing inputs does not raise an inactive sibling panel');
    assertTrue($panelA->nthChild(0)->hasVariant('active'), 'requested input still receives active focus state');
  },

  'buttons parse callbacks and expose hotkey labels' => function (): void {
    $root = root();
    $button = new Button($root, 'save');

    $button->setOnPress('Controller::noop');
    assertSame(['Controller', 'noop'], propertyValue($button, 'onPress'), 'button callbacks parse from Class::method strings');

    $button->setHotKey('SPACE');
    assertSame('ButtonHotKey', $button->nthChild(0)->getType(), 'button hotkeys create a hotkey label element');
    assertSame('SPACE', $button->nthChild(0)->getText(), 'button hotkey labels show the key name');
  },

  'forged panels get a default ok button when no buttons are specified' => function (): void {
    $root = root();
    new HeadlessElement($root, 'window', null, 'Window');

    ForgeHeadlessPanel::forge('Notice', 'Read this.');
    $panel = Element::firstByType('Panel');
    $buttons = Element::allByType('Button', $panel);

    assertSame(1, count($buttons), 'default forge creates one button');
    assertSame('RETURN OK', $buttons[0]->getText(), 'default forge button is return ok');
  },

  'forged panels preserve explicit button lists' => function (): void {
    $root = root();
    new HeadlessElement($root, 'window', null, 'Window');

    ForgeHeadlessPanel::forge('Notice', 'Read this.', []);
    $panel = Element::firstByType('Panel');
    assertSame(0, count(Element::allByType('Button', $panel)), 'explicit empty button list stays empty');

    $root = root();
    new HeadlessElement($root, 'window', null, 'Window');
    ForgeHeadlessPanel::forge('Notice', 'Read this.', [
      ['text' => 'Close', 'hotKey' => 'ESCAPE', 'onPress' => 'close']
    ]);
    $panel = Element::firstByType('Panel');
    $buttons = Element::allByType('Button', $panel);

    assertSame(1, count($buttons), 'custom forge button list is preserved');
    assertSame('ESCAPE Close', $buttons[0]->getText(), 'custom forge button is not replaced');
  },

  'warning panels expose only body text for clipboard copy' => function (): void {
    $root = root();
    new HeadlessElement($root, 'window', null, 'Window');

    ForgeHeadlessWarningPanel::forge('Warning', "First line\nSecond line", [
      ['text' => 'OK', 'hotKey' => 'RETURN', 'onPress' => 'close']
    ]);
    $panel = Element::firstByType('WarningPanel');

    assertInstanceOf(ForgeHeadlessWarningPanel::class, $panel, 'forged warning panel is available');
    assertSame("First line\nSecond line", $panel->exposedCopyableBodyText(), 'warning panel clipboard text preserves the body without buttons');

    $panel->setText("Changed\nbody");
    assertSame("Changed\nbody", $panel->exposedCopyableBodyText(), 'warning panel clipboard text follows body updates');

    $root = root();
    new HeadlessElement($root, 'window', null, 'Window');

    ForgeHeadlessErrorPanel::forge('Error', "SQLSTATE[HY000]\nAccess denied", [
      ['text' => 'OK', 'hotKey' => 'RETURN', 'onPress' => 'close']
    ]);
    $panel = Element::firstByType('ErrorPanel');

    assertInstanceOf(ForgeHeadlessErrorPanel::class, $panel, 'forged error panel is available');
    assertSame("SQLSTATE[HY000]\nAccess denied", $panel->exposedCopyableBodyText(), 'error panel clipboard text preserves the body without buttons');
  },

  'panels call default button on ctrl return' => function (): void {
    $root = root();
    $panel = new HeadlessPanel($root, 'panel', null, 'Panel');
    $button = new Button($panel, 'save');
    ButtonTestAction::$pressed = 0;

    $button->setDefault('true');
    $button->setOnPress([ButtonTestAction::class, 'press']);
    $panel->show();
    $panel->keyPressHandler($panel, [
      'mod' => KeyModifier::CTRL,
      'scancode' => ScanCode::RETURN,
      'key' => KeyCode::RETURN,
    ]);

    assertSame(1, ButtonTestAction::$pressed, 'ctrl return invokes the default button callback');
  },

  'plain return advances focus when focused input does not handle it' => function (): void {
    KeyCombo::init();
    $root = root();
    $panel = new HeadlessPanel($root, 'panel', null, 'Panel');
    $first = new PassiveInput($panel, 'first');
    $second = new PassiveInput($panel, 'second');

    $panel->show();
    $panel->keyPressHandler($panel, [
      'name' => 'KeyPress',
      'mod' => KeyModifier::NONE,
      'scancode' => ScanCode::RETURN,
      'key' => KeyCode::RETURN,
    ]);

    assertFalse($first->hasVariant('active'), 'plain return leaves the current input');
    assertTrue($second->hasVariant('active'), 'plain return activates the next input');
  },

  'plain return does not submit panel when focused input does not handle it' => function (): void {
    KeyCombo::init();
    $root = root();
    $panel = new HeadlessPanel($root, 'panel', null, 'Panel');
    new PassiveInput($panel, 'input');
    new PassiveInput($panel, 'next');
    $button = new Button($panel, 'save');
    ButtonTestAction::$pressed = 0;

    $button->setHotKey('RETURN');
    $button->setOnPress([ButtonTestAction::class, 'press']);
    $panel->show();
    $panel->keyPressHandler($panel, [
      'name' => 'KeyPress',
      'mod' => KeyModifier::NONE,
      'scancode' => ScanCode::RETURN,
      'key' => KeyCode::RETURN,
    ]);

    assertSame(0, ButtonTestAction::$pressed, 'plain return fallback does not invoke the panel default button');
  },

  'plain return event advances focus through panel dispatch' => function (): void {
    KeyCombo::init();
    $root = root();
    $panel = new HeadlessPanel($root, 'panel', null, 'Panel');
    $first = new PassiveInput($panel, 'first');
    $second = new PassiveInput($panel, 'second');

    $panel->show();
    $panel->eventHandler([
      'name' => 'KeyPress',
      'mod' => KeyModifier::NONE,
      'scancode' => ScanCode::RETURN,
      'key' => KeyCode::RETURN,
    ]);

    assertFalse($first->hasVariant('active'), 'plain return event leaves the current input');
    assertTrue($second->hasVariant('active'), 'plain return event activates the next input');
  },

  'focused button still handles plain return' => function (): void {
    KeyCombo::init();
    $root = root();
    $panel = new HeadlessPanel($root, 'panel', null, 'Panel');
    $button = new Button($panel, 'save');
    ButtonTestAction::$pressed = 0;

    $button->setOnPress([ButtonTestAction::class, 'press']);
    $panel->show();
    $button->keyPressHandler($button, [
      'name' => 'KeyPress',
      'mod' => KeyModifier::NONE,
      'scancode' => ScanCode::RETURN,
      'key' => KeyCode::RETURN,
    ]);

    assertSame(1, ButtonTestAction::$pressed, 'focused button handles plain return locally');
  },

  'select and file controls leave plain return for panel focus movement' => function (): void {
    KeyCombo::init();
    $root = root();
    $panel = new HeadlessPanel($root, 'panel', null, 'Panel');
    $select = new Select($panel, 'select');
    $file = new File($panel, 'file');
    $after = new PassiveInput($panel, 'after');

    $panel->show();
    $handled = $select->keyPressHandler($select, [
      'name' => 'KeyPress',
      'mod' => KeyModifier::NONE,
      'scancode' => ScanCode::RETURN,
      'key' => KeyCode::RETURN,
    ]);
    assertFalse($handled, 'select does not handle plain return locally');
    $panel->keyPressHandler($panel, [
      'name' => 'KeyPress',
      'mod' => KeyModifier::NONE,
      'scancode' => ScanCode::RETURN,
      'key' => KeyCode::RETURN,
    ]);
    assertFalse($select->hasVariant('active'), 'plain return leaves a select control');
    assertTrue($file->hasVariant('active'), 'plain return moves from select to file');

    $handled = $file->keyPressHandler($file, [
      'name' => 'KeyPress',
      'mod' => KeyModifier::NONE,
      'scancode' => ScanCode::RETURN,
      'key' => KeyCode::RETURN,
    ]);
    assertFalse($handled, 'file does not handle plain return locally');
    $panel->keyPressHandler($panel, [
      'name' => 'KeyPress',
      'mod' => KeyModifier::NONE,
      'scancode' => ScanCode::RETURN,
      'key' => KeyCode::RETURN,
    ]);
    assertFalse($file->hasVariant('active'), 'plain return leaves a file control');
    assertTrue($after->hasVariant('active'), 'plain return moves from file to the next input');
  },

  'space remains the select operation key' => function (): void {
    KeyCombo::init();
    $root = root();
    $select = new Select($root, 'select');

    $handled = $select->keyPressHandler($select, [
      'mod' => KeyModifier::NONE,
      'scancode' => ScanCode::SPACE,
      'key' => KeyCode::SPACE,
    ]);

    assertTrue($handled, 'space is handled by select controls');
  },

  'return hotkey makes buttons default automatically' => function (): void {
    $root = root();
    $panel = new HeadlessPanel($root, 'panel', null, 'Panel');
    $button = new Button($panel, 'save');
    ButtonTestAction::$pressed = 0;

    $button->setHotKey('RETURN');
    $button->setOnPress([ButtonTestAction::class, 'press']);
    $panel->show();
    $panel->keyPressHandler($panel, [
      'mod' => KeyModifier::CTRL,
      'scancode' => ScanCode::RETURN,
      'key' => KeyCode::RETURN,
    ]);

    assertSame(1, ButtonTestAction::$pressed, 'RETURN hotkey registers the button as the default action');
  },

  'explicit default false overrides return hotkey default' => function (): void {
    $root = root();
    $panel = new HeadlessPanel($root, 'panel', null, 'Panel');
    $button = new Button($panel, 'save');
    ButtonTestAction::$pressed = 0;

    $button->setHotKey('RETURN');
    $button->setOnPress([ButtonTestAction::class, 'press']);
    $button->setDefault('false');
    $panel->show();
    $panel->keyPressHandler($panel, [
      'mod' => KeyModifier::CTRL,
      'scancode' => ScanCode::RETURN,
      'key' => KeyCode::RETURN,
    ]);

    assertSame(0, ButtonTestAction::$pressed, 'explicit default false disables the automatic RETURN default');
  },

  'missing default attribute does not override return hotkey default' => function (): void {
    $root = root();
    $panel = new HeadlessPanel($root, 'panel', null, 'Panel');
    $button = new Button($panel, 'save');
    ButtonTestAction::$pressed = 0;

    $button->setHotKey('RETURN');
    $button->setOnPress([ButtonTestAction::class, 'press']);
    $button->setDefault(false);
    $panel->show();
    $panel->keyPressHandler($panel, [
      'mod' => KeyModifier::CTRL,
      'scancode' => ScanCode::RETURN,
      'key' => KeyCode::RETURN,
    ]);

    assertSame(1, ButtonTestAction::$pressed, 'missing XML default attribute keeps the automatic RETURN default');
  },

  'panel default button handles ctrl return before focused children' => function (): void {
    $root = root();
    $panel = new HeadlessPanel($root, 'panel', null, 'Panel');
    new GreedyInput($panel, 'editor');
    $button = new Button($panel, 'save');
    ButtonTestAction::$pressed = 0;

    $button->setHotKey('RETURN');
    $button->setOnPress([ButtonTestAction::class, 'press']);
    $panel->show();
    $panel->eventHandler([
      'name' => 'KeyPress',
      'mod' => KeyModifier::CTRL,
      'scancode' => ScanCode::RETURN,
      'key' => KeyCode::RETURN,
    ]);

    assertSame(1, ButtonTestAction::$pressed, 'ctrl return default action runs before a focused child can consume the key');
  },
];
