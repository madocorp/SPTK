<?php

namespace SPTK\Widgets\ColorSelector;

use SPTK\Core\{Style, WidgetDefinition};
use SPTK\XmlParser\{AttributeParser, EventParser, StyleParser, WidgetParser};

/** Parses ColorSelector value, style, and event declarations. */
final class Parser implements WidgetParser {

  use AttributeParser;

  /** Accept a color value and parent layout attributes. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, [...$layoutAttributes, 'value']);
  }

  /** Build a selector from its value and optional nested declarations. */
  public function parse(\XMLReader $reader, Style $style): WidgetDefinition {
    $value = $reader->getAttribute('value') ?? '#ff0000';
    $events = [];
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Event') {
          $events[] = (new EventParser())->parse($reader);
        } else if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Style') {
          $style = (new StyleParser())->parse($reader, $style);
        } else if ($reader->nodeType === \XMLReader::ELEMENT || (in_array($reader->nodeType, [\XMLReader::TEXT, \XMLReader::CDATA], true) && trim($reader->value) !== '')) {
          throw new \RuntimeException('ColorSelector accepts only Event and Style children.');
        } else if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'ColorSelector') {
          break;
        }
      }
    }
    return new WidgetDefinition(new ColorSelector($value, $style), $events);
  }

}
