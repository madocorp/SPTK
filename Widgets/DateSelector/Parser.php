<?php

namespace SPTK\Widgets\DateSelector;

use SPTK\Core\{Style, WidgetDefinition};
use SPTK\XmlParser\{AttributeParser, EventParser, StyleParser, WidgetParser};

/** Parses DateSelector value, style, and event declarations. */
final class Parser implements WidgetParser {

  use AttributeParser;

  /** Accept an ISO date and parent layout attributes. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, [...$layoutAttributes, 'value', 'title']);
  }

  /** Build a calendar from its optional date and nested declarations. */
  public function parse(\XMLReader $reader, Style $style): WidgetDefinition {
    $value = $reader->getAttribute('value');
    $title = $reader->getAttribute('title');
    $events = [];
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Event') {
          $events[] = (new EventParser())->parse($reader);
        } else if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Style') {
          $style = (new StyleParser())->parse($reader, $style);
        } else if ($reader->nodeType === \XMLReader::ELEMENT || (in_array($reader->nodeType, [\XMLReader::TEXT, \XMLReader::CDATA], true) && trim($reader->value) !== '')) {
          throw new \RuntimeException('DateSelector accepts only Event and Style children.');
        } else if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'DateSelector') {
          break;
        }
      }
    }
    return new WidgetDefinition(new DateSelector($value, $style, $title), $events);
  }

}
