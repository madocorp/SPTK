<?php

namespace Examples\Tests;

use SPTK\Element;
use SPTK\Elements\Button;
use SPTK\Elements\CheckBox;
use SPTK\Elements\Field;
use SPTK\Elements\ListBox;
use SPTK\Elements\Select;
use SPTK\Elements\SelectPanel;
use SPTK\Elements\Tab;
use SPTK\Elements\TextBox;
use SPTK\Elements\TextEditor;
use SPTK\Font;
use SPTK\LayoutXmlReader;
use SPTK\StyleSheet;

return [
  'template parser builds generic and known elements' => function (): void {
    $root = root();
    $xml = tempFile(<<<'XML'
<Root>
  <Unknown name="generic" class="red big">Alpha beta</Unknown>
  <Button name="submit" onPress="Controller::noop">OK</Button>
  <CheckBox name="accepted" value="true" />
  <Field name="schema" value="main" />
  <Tab contentName="tab-content">Tab</Tab>
</Root>
XML, 'xml');

    new LayoutXmlReader($xml, $root);

    $generic = Element::byName('generic', $root);
    assertInstanceOf(Element::class, $generic, 'unknown tags become generic elements');
    assertSame('Unknown', $generic->getType(), 'unknown tag type is preserved');
    assertSame(['red', 'big'], $generic->getClass(), 'class attributes split into class lists');
    assertSame('Alpha beta', $generic->getText(), 'text content is split into word descendants');

    $button = Element::byName('submit', $root);
    assertInstanceOf(Button::class, $button, 'known tags resolve to element classes');
    assertSame(['Controller', 'noop'], propertyValue($button, 'onPress'), 'known element attributes are applied');
    assertSame('OK', $button->getText(), 'known element text remains usable');

    $checkBox = Element::byName('accepted', $root);
    assertInstanceOf(CheckBox::class, $checkBox, 'known self-closing elements resolve to classes');
    assertTrue($checkBox->getValue(), 'known element attributes are applied to self-closing tags');

    $field = Element::byName('schema', $root);
    assertInstanceOf(Field::class, $field, 'field elements resolve to the Field class');
    assertSame('main', $field->getValue(), 'field value attributes are applied');

    $tab = Element::firstByType('Tab', $root);
    assertInstanceOf(Tab::class, $tab, 'tab elements resolve to the Tab class');
    assertSame('tab-content', $tab->getContentName(), 'tab contentName attributes are applied');
  },

  'template parser assigns body text to text box values' => function (): void {
    $root = root();
    $fonts = new \ReflectionProperty(Font::class, 'fonts');
    $fonts->setAccessible(true);
    $fonts->setValue([
      'inherit' => [
        0 => [
          'handle' => null,
          'ascent' => 0,
          'descent' => 0,
          'height' => 1,
          'letterWidth' => 1,
          'letterHeight' => 1
        ]
      ]
    ]);
    $xml = tempFile(<<<'XML'
<Root>
  <TextBox name="notice">
    First line
    Second line
  </TextBox>
  <TextEditor name="editor">
    Editable line
    Next line
  </TextEditor>
</Root>
XML, 'xml');

    new LayoutXmlReader($xml, $root);

    $notice = Element::byName('notice', $root);
    assertInstanceOf(TextBox::class, $notice, 'TextBox elements resolve to the TextBox class');
    assertSame(['First line', 'Second line'], propertyValue($notice, 'lines'), 'TextBox body text is stored as editor lines');

    $editor = Element::byName('editor', $root);
    assertInstanceOf(TextEditor::class, $editor, 'TextEditor elements resolve to the TextEditor class');
    assertSame(['Editable line', 'Next line'], $editor->getValue(), 'TextEditor body text is stored as editable lines');
  },

  'template parser builds select options from descendants' => function (): void {
    $root = root();
    $xml = tempFile(<<<'XML'
<Root>
  <Select name="color" hint="Color">
    <Option>Red</Option>
    <Option value="green">Green label</Option>
    <Option>Blue</Option>
  </Select>
</Root>
XML, 'xml');

    new LayoutXmlReader($xml, $root);

    $select = Element::byName('color', $root);
    assertInstanceOf(Select::class, $select, 'select elements resolve to the Select class');
    assertSame(['Red', 'green', 'Blue'], $select->getOptions(), 'Option descendants are collected as select options');
    assertSame('Color', $select->nthChild(0)->getValue(), 'select hint is shown in the input field');
  },

  'select supports onChange callbacks' => function (): void {
    $root = root();
    $select = new Select($root, 'color');
    $listener = new class {
      public array $values = [];

      public function changed($element): void {
        $this->values[] = $element->getValue();
      }
    };
    $select->setOnChange([$listener, 'changed']);

    $select->setValue('green');
    assertSame([], $listener->values, 'programmatic select value changes stay quiet');

    $select->setValue('Blue');
    $changed = new \ReflectionMethod(Select::class, 'changed');
    $changed->setAccessible(true);
    $changed->invoke($select);
    assertSame(['Blue'], $listener->values, 'select invokes onChange with the select element');
  },

  'select panels derive styleable names from source select names' => function (): void {
    $root = root();
    StyleSheet::load(tempFile(<<<'XSS'
country/panel {
  width: 70%w;
}

country/list {
  height: 70%h;
}
XSS, 'xss'));

    $panel = new SelectPanel($root, 'country/panel');
    $list = Element::byName('country/list', $root);

    assertInstanceOf(SelectPanel::class, $panel, 'named select panel resolves to the SelectPanel class');
    assertInstanceOf(ListBox::class, $list, 'select panel option list gets a derived name');
    assertSame(['select-panel-list'], $list->getClass(), 'select panel option list gets the semantic default class');
    assertSame(560, $panel->getStyle()->get('width', $root->getGeometry()), 'derived panel name can override width through XSS');
    assertSame(420, $list->getStyle()->get('height', $root->getGeometry()), 'derived list name can override height through XSS');
  },

  'template parser handles events child classes includes and cdata' => function (): void {
    $root = root();
    $include = tempFile('<Included name="included">From include</Included>', 'xml');
    $xml = tempFile(<<<XML
<Root>
  <Box name="evented">
    <Event type="KeyPress">Controller::refresh</Event>
  </Box>
  <AC class="child-class">
    <Box name="childed" />
  </AC>
  <Box name="cdata"><![CDATA[
Line one
Line two
  ]]></Box>
  <Include file="{$include}" />
</Root>
XML, 'xml');

    new LayoutXmlReader($xml, $root);

    $evented = Element::byName('evented', $root);
    assertSame(['Controller', 'refresh'], propertyValue($evented, 'events')['KeyPress'], 'Event nodes attach callbacks to the current element');

    $childed = Element::byName('childed', $root);
    assertSame(['child-class'], $childed->getClass(), 'AC applies child classes only inside its block');

    $cdata = Element::byName('cdata', $root);
    assertSame('Line one Line two', $cdata->getText(), 'CDATA content is normalized through setText');

    $included = Element::byName('included', $root);
    assertInstanceOf(Element::class, $included, 'Include nodes parse external XML into the current tree');
    assertSame('From include', $included->getText(), 'included XML content is parsed');
  },
];
