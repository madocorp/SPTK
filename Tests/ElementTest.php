<?php

namespace Examples\Tests;

use SPTK\Element;
use SPTK\Elements\Button;
use SPTK\Elements\CheckBox;
use SPTK\Elements\File;
use SPTK\Elements\Input;
use SPTK\Elements\ListBox;
use SPTK\Elements\ListItem;
use SPTK\Elements\Panel;
use SPTK\Elements\RadioButton;
use SPTK\Elements\Select;
use SPTK\Elements\Tab;
use SPTK\Elements\Tabs;
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
    $this->nthChild(0)->setValue($this->getValue() === '' ? $this->placeholder : $this->getValue());
    if ($this->getValue() === '' && $this->placeholder !== '') {
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
    assertSame('fresh', $apple->nthChild(2)->getText(), 'list item right text can be set');
    assertTrue($apple->nthChild(2)->hasClass('preview'), 'list item right text accepts local style classes');
    assertTrue($apple->nthChild(2)->hasClass('ItemRight:cursor'), 'list item right text follows cursor state');
    $list->addVariant('active');
    assertTrue($apple->nthChild(2)->hasClass('ItemRight:active'), 'list item right text follows active state');
    assertFalse($apple->nthChild(2)->hasClass('ItemRight:cursor'), 'active right text clears cursor state');
    assertTrue($apple->match('app'), 'filterable list items match from the start of their text');
    assertSame('', $apple->nthChild(3)->getValue(), 'matched list items move text before the match into the value field');
    assertSame('app', $apple->nthChild(4)->getValue(), 'matched list items render the matching text separately');
    assertSame('le', $apple->nthChild(5)->getValue(), 'matched list items render the text after the match separately');
    $list->resetSearch();
    assertSame('apple', $apple->nthChild(3)->getValue(), 'resetting list search restores the value field text');
    assertSame('', $apple->nthChild(4)->getValue(), 'resetting list search clears the matching text field');
    assertSame('', $apple->nthChild(5)->getValue(), 'resetting list search clears the after-match text field');
    assertTrue($apple->match('app'), 'filterable list items can match again after reset');
    assertFalse($apple->match('pp'), 'filterable list items do not match non-prefix text');
    assertSame('apple', $apple->nthChild(3)->getValue(), 'failed matching restores the value field text');
    assertSame('', $apple->nthChild(4)->getValue(), 'failed matching clears stale matching text');

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
    assertSame('1', $email->nthChild(0)->getText(), 'first ordered selection shows marker 1');
    assertSame('2', $id->nthChild(0)->getText(), 'second ordered selection shows marker 2');
    assertTrue($email->hasVariant('selected'), 'ordered selected items keep the selected visual variant');
    assertTrue($id->hasVariant('selected'), 'ordered selected items apply the selected visual variant');
    $columns->moveCursor(2);
    assertSame([170, 170, 170, 255], $email->getStyle()->get('color'), 'cursor styling overrides selected item color');
    $columns->moveCursor(1);
    assertFalse($name->hasVariant('selected'), 'cursor movement does not apply the selected visual variant');

    $columns->selectAll();

    assertSame(['id', 'name', 'email'], $columns->getValue(), 'selectAll selects every selectable item');

    $columns->clearSelection();

    assertSame([], $columns->getValue(), 'clearSelection deselects every selectable item');
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

    assertSame('empty means default', $input->placeholderText(), 'inactive empty input shows placeholder text');
    assertTrue($input->placeholderActive(), 'inactive empty input uses placeholder styling');

    $input->addVariant('active');

    assertSame('empty means default', $input->placeholderText(), 'active empty input shows placeholder text');
    assertTrue($input->placeholderActive(), 'active empty input uses placeholder styling');

    $input->setValue('named');

    assertSame('named', $input->placeholderText(), 'input value replaces placeholder text');
    assertFalse($input->placeholderActive(), 'non-empty input removes placeholder styling');
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
