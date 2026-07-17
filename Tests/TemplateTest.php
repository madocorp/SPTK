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
use SPTK\Elements\TextPreview;
use SPTK\Font;
use SPTK\LayoutXmlReader;
use SPTK\SDLWrapper\KeyCode;
use SPTK\SDLWrapper\KeyCombo;
use SPTK\SDLWrapper\KeyModifier;
use SPTK\SDLWrapper\ScanCode;
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

  'template parser preserves known element key handlers without optional attributes' => function (): void {
    $root = root();
    $xml = tempFile(<<<'XML'
<Root>
  <Menu name="main-menu"></Menu>
</Root>
XML, 'xml');

    new LayoutXmlReader($xml, $root);

    $menu = Element::byName('main-menu', $root);
    assertTrue(isset(propertyValue($menu, 'events')['KeyPress']), 'missing optional attributes do not remove menu key handling');
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
  <TextPreview name="preview">
    Preview line
    Wrapped value
  </TextPreview>
</Root>
XML, 'xml');

    new LayoutXmlReader($xml, $root);

    $notice = Element::byName('notice', $root);
    assertInstanceOf(TextBox::class, $notice, 'TextBox elements resolve to the TextBox class');
    assertInstanceOf(TextEditor::class, $notice, 'TextBox reuses the TextEditor implementation');
    assertSame(['First line', 'Second line'], propertyValue($notice, 'lines'), 'TextBox body text is stored as editor lines');
    assertFalse(isset(propertyValue($notice, 'events')['TextInput']), 'TextBox does not subscribe to text input events');
    assertFalse($notice->textInputHandler($notice, ['text' => 'x']), 'TextBox ignores direct text input calls');
    assertSame(['First line', 'Second line'], propertyValue($notice, 'lines'), 'TextBox text input does not mutate content');
    $notice->keyPressHandler($notice, ['mod' => KeyModifier::NONE, 'scancode' => ScanCode::BACKSPACE, 'key' => KeyCode::BACKSPACE]);
    assertSame(['First line', 'Second line'], propertyValue($notice, 'lines'), 'TextBox delete keys do not mutate content');
    $notice->getGeometry()->width = 100;
    $notice->getGeometry()->height = 20;
    $notice->getGeometry()->innerWidth = 100;
    $notice->getGeometry()->innerHeight = 20;
    $notice->getStyle()->set('color', '#111111');
    $notice->getStyle()->set('backgroundColor', '#222222');
    $buildCells = new \ReflectionMethod($notice, 'buildCells');
    $buildCells->setAccessible(true);
    $cells = $buildCells->invoke($notice);
    assertSame([17, 17, 17, 255], $cells[0][0]['fg'], 'inactive TextBox cursor does not replace text color');
    assertSame([34, 34, 34, 255], $cells[0][0]['bg'], 'inactive TextBox cursor is hidden');
    $active = new \ReflectionProperty(TextEditor::class, 'active');
    $active->setAccessible(true);
    $active->setValue($notice, true);
    $notice->getStyle()->set('color', '#111111');
    $notice->getStyle()->set('backgroundColor', '#222222');
    $cells = $buildCells->invoke($notice);
    assertSame([34, 34, 34, 255], $cells[0][0]['fg'], 'active TextBox cursor uses inverted background as text color');
    assertSame([17, 17, 17, 255], $cells[0][0]['bg'], 'active TextBox cursor uses inverted text color as background');
    $cursor = propertyValue($notice, 'cursor');
    $cursor->set([0, 1, 0, 3]);
    $cells = $buildCells->invoke($notice);
    assertSame([34, 34, 34, 255], $cells[0][1]['fg'], 'active TextBox selection uses cursor foreground');
    assertSame([17, 17, 17, 255], $cells[0][1]['bg'], 'active TextBox selection uses cursor background');

    $editor = Element::byName('editor', $root);
    assertInstanceOf(TextEditor::class, $editor, 'TextEditor elements resolve to the TextEditor class');
    assertSame(['Editable line', 'Next line'], $editor->getValue(), 'TextEditor body text is stored as editable lines');

    $preview = Element::byName('preview', $root);
    assertInstanceOf(TextPreview::class, $preview, 'TextPreview elements resolve to the TextPreview class');
    assertSame("Preview line\nWrapped value", $preview->getValue(), 'TextPreview body text is stored as preview text');
  },

  'text editor read only mode blocks edits but keeps navigation' => function (): void {
    $root = root();
    KeyCombo::init();
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
  <TextEditor name="readonly" readOnly="true">abc</TextEditor>
</Root>
XML, 'xml');

    new LayoutXmlReader($xml, $root);

    $editor = Element::byName('readonly', $root);
    assertInstanceOf(TextEditor::class, $editor, 'read-only TextEditor resolves to the editor class');
    $editor->getGeometry()->width = 100;
    $editor->getGeometry()->height = 20;
    $editor->getGeometry()->innerWidth = 100;
    $editor->getGeometry()->innerHeight = 20;
    $editor->hide();
    assertTrue($editor->getReadOnly(), 'readOnly XML attribute enables read-only mode');
    assertSame(['abc'], $editor->getValue(), 'read-only editor keeps initial content');

    $editor->textInputHandler($editor, ['text' => 'x']);
    assertSame(['abc'], $editor->getValue(), 'read-only editor ignores text input');
    $editor->insertText('x');
    assertSame(['abc'], $editor->getValue(), 'read-only editor ignores insertText');
    $editor->replaceText('changed');
    assertSame(['abc'], $editor->getValue(), 'read-only editor ignores replaceText');

    $editor->keyPressHandler($editor, ['mod' => KeyModifier::NONE, 'scancode' => ScanCode::RETURN, 'key' => KeyCode::RETURN]);
    $editor->keyPressHandler($editor, ['mod' => KeyModifier::NONE, 'scancode' => ScanCode::BACKSPACE, 'key' => KeyCode::BACKSPACE]);
    $editor->keyPressHandler($editor, ['mod' => KeyModifier::NONE, 'scancode' => ScanCode::DELETE, 'key' => KeyCode::DELETE]);
    $editor->keyPressHandler($editor, ['mod' => KeyModifier::PRIMARY, 'scancode' => ScanCode::X, 'key' => KeyCode::X]);
    $editor->keyPressHandler($editor, ['mod' => KeyModifier::PRIMARY, 'scancode' => ScanCode::V, 'key' => KeyCode::V]);
    $editor->keyPressHandler($editor, ['mod' => KeyModifier::PRIMARY, 'scancode' => ScanCode::Z, 'key' => KeyCode::Z]);
    $editor->keyPressHandler($editor, ['mod' => KeyModifier::PRIMARY, 'scancode' => ScanCode::Y, 'key' => KeyCode::Y]);
    assertSame(['abc'], $editor->getValue(), 'read-only editor ignores editing key actions');

    $editor->keyPressHandler($editor, ['mod' => KeyModifier::NONE, 'scancode' => ScanCode::RIGHT, 'key' => KeyCode::RIGHT]);
    $cursor = propertyValue($editor, 'cursor');
    assertSame([0, 1, 0, 1], $cursor->get(), 'read-only editor still allows cursor movement');
    $editor->keyPressHandler($editor, ['mod' => KeyModifier::SHIFT, 'scancode' => ScanCode::RIGHT, 'key' => KeyCode::RIGHT]);
    assertSame([0, 2, 0, 1], $cursor->get(), 'read-only editor still allows selection movement');

    $editor->setValue('loaded');
    assertSame(['loaded'], $editor->getValue(), 'setValue can still load read-only editor content');
    $editor->setReadOnly(false);
    $editor->insertText('!');
    assertSame(['!loaded'], $editor->getValue(), 'editable editor accepts insertText after read-only mode is disabled');
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

  'select panels support multiple selection actions' => function (): void {
    $root = root();
    $panel = new SelectPanel($root, 'columns/panel');
    $panel->setMultiple(true);
    $panel->setOptions(['id', 'name', 'email'], 'id, email');
    $list = Element::byName('columns/list', $root);

    assertSame(['id', 'email'], $list->getValue(), 'multiple select panel initializes selected values from comma list');

    $panel->selectAll();

    assertSame(['id', 'name', 'email'], $list->getValue(), 'multiple select panel All selects every option');

    $panel->clearSelection();

    assertSame([], $list->getValue(), 'multiple select panel Clear removes every selection');
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
