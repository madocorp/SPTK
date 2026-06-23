<?php

namespace Examples\Tests;

use SPTK\Element;
use SPTK\Elements\Button;
use SPTK\Elements\CheckBox;
use SPTK\Elements\ListBox;
use SPTK\Elements\ListItem;
use SPTK\Elements\Panel;
use SPTK\Elements\Tab;
use SPTK\Elements\Tabs;

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

  'list items and list boxes expose usable values' => function (): void {
    $root = root();
    $list = new ListBox($root, 'fruits');
    $apple = new ListItem($list);
    $apple->setValue('apple');
    $apple->setFilterable('true');
    $banana = new ListItem($list);
    $banana->setValue('banana');
    $banana->setSelectable('true');
    $banana->select();

    assertSame('apple', $list->getValue(), 'simple list value is the active item value');
    assertTrue($apple->match('app'), 'filterable list items match from the start of their text');
    assertFalse($apple->match('pp'), 'filterable list items do not match non-prefix text');

    $list->setValueType('order');
    assertSame(['apple', 'banana'], $list->getValue(), 'order value returns item values in descendant order');

    $list->setValueType('select');
    assertSame(['banana'], $list->getValue(), 'select value returns selected selectable item values');
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
    assertTrue($tabB->hasClass('Tab:active'), 'selected tab gains active class');
    assertFalse(propertyValue($contentA, 'display'), 'unselected tab content is hidden');
    assertTrue(propertyValue($contentB, 'display'), 'selected tab content is shown');
    assertSame($contentB, $tabs->getTabContent(), 'getTabContent returns the current tab content');
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
    assertTrue($tabs->nthChild(0)->hasClass('Tab:focused'), 'focused tab strip marks the selected tab');

    $tabs->selectTab(1);

    $inputNames = array_map(fn($input) => $input['element']->getName(), propertyValue($panel, 'inputList'));
    assertSame(['tabs', 'field-b'], $inputNames, 'panel input list refreshes when the active tab changes');
    assertTrue($tabs->hasClass('Tabs:active'), 'tab strip stays focused after changing tabs');
    assertTrue($tabs->nthChild(1)->hasClass('Tab:focused'), 'focus marker follows the selected tab');

    $tabs->selectTab(0);
    assertSame($contentA, $tabs->getTabContent(), 'focused tab strip can select another tab');
    assertTrue($tabs->nthChild(0)->hasClass('Tab:focused'), 'focus marker follows the reselected tab');
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
];
