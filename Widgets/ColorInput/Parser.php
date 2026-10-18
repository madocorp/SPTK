<?php

namespace SPTK\Widgets\ColorInput;

use SPTK\Core\{Style, WidgetDefinition};
use SPTK\XmlParser\{AttributeParser, ItemParser, WidgetParser};

/** Parses a hex color field with optional predefined palette items. */
final class Parser implements WidgetParser {

  use AttributeParser;

  /** Accept the field label, color value, and parent layout attributes. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, [...$layoutAttributes, 'value', 'label']);
  }

  /** Build a color input from its palette items and event declarations. */
  public function parse(\XMLReader $reader, Style $style): WidgetDefinition {
    $value = $reader->getAttribute('value') ?? '#ff0000';
    $label = $reader->getAttribute('label');
    [$items, $events, $style] = (new ItemParser())->parse($reader, $style, 'selected');
    return new WidgetDefinition(new ColorInput($value, array_column($items, 'value'), $style, $label), $events);
  }

}
