<?php

namespace Examples\Tests;

use SPTK\Element;
use SPTK\Elements\Button;
use SPTK\Elements\CheckBox;
use SPTK\Elements\ListBox;
use SPTK\Elements\ListItem;
use SPTK\Elements\Tabs;

class HeadlessElement extends Element {

  public function recalculateGeometry(): void {
    ;
  }

}

return [
  'base elements manage identity text classes and lookup' => function (): void {
    $root = root();
    $box = new Element($root, 'box', 'alpha beta', 'Box');
    $box->setText('hello world');
    $child = new Element($box, 'child', false, 'Thing');

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
    $a = new Element($root, 'a', false, 'Box');
    $b = new Element($root, 'b', false, 'Box');
    $c = new Element($root, 'c', false, 'Box');

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
    $parent = new Element($root, 'parent', false, 'Box');
    $parent->addChildClass('temporary');
    $inside = new Element($parent, 'inside', false, 'Thing');
    $parent->removeChildClass('temporary');
    $outside = new Element($parent, 'outside', false, 'Thing');

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

    $checkBox->addClass('active', true);
    assertTrue($checkBox->nthChild(0)->hasClass('CheckBoxValue:active'), 'dynamic active class propagates to the value box');
    $checkBox->removeClass('active', true);
    assertFalse($checkBox->nthChild(0)->hasClass('CheckBoxValue:active'), 'dynamic active class is removed from the value box');
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
    $tabA = new Element($tabs, false, false, 'Tab');
    $contentA = new HeadlessElement($tabs, 'content-a', false, 'Box');
    $tabB = new Element($tabs, false, false, 'Tab');
    $contentB = new HeadlessElement($tabs, 'content-b', false, 'Box');

    $tabs->selectTab(1);

    assertFalse($tabA->hasClass('Tab:active'), 'unselected tab loses active class');
    assertTrue($tabB->hasClass('Tab:active'), 'selected tab gains active class');
    assertFalse(propertyValue($contentA, 'display'), 'unselected tab content is hidden');
    assertTrue(propertyValue($contentB, 'display'), 'selected tab content is shown');
    assertSame($contentB, $tabs->getTabContent(), 'getTabContent returns the current tab content');
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
