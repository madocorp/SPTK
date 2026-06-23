<?php

namespace Examples\Tests;

use SPTK\Element;
use SPTK\Elements\Button;
use SPTK\Elements\CheckBox;
use SPTK\Elements\Tab;
use SPTK\LayoutXmlReader;

return [
  'template parser builds generic and known elements' => function (): void {
    $root = root();
    $xml = tempFile(<<<'XML'
<Root>
  <Unknown name="generic" class="red big">Alpha beta</Unknown>
  <Button name="submit" onPress="Controller::noop">OK</Button>
  <CheckBox name="accepted" value="true" />
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

    $tab = Element::firstByType('Tab', $root);
    assertInstanceOf(Tab::class, $tab, 'tab elements resolve to the Tab class');
    assertSame('tab-content', $tab->getContentName(), 'tab contentName attributes are applied');
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
