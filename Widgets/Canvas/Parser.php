<?php

namespace SPTK\Widgets\Canvas;

use SPTK\Core\{Style, WidgetDefinition};
use SPTK\XmlParser\{AttributeParser, EventParser, StyleParser, WidgetParser};

/** Parses a canvas painter, inherited background, and ordinary widget events. */
final class Parser implements WidgetParser {

  use AttributeParser;

  /** Validate the painter attribute alongside layout dimensions. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, [...$layoutAttributes, 'painter']);
  }

  /** Build a canvas with an optional static application paint method. */
  public function parse(\XMLReader $reader, Style $style): WidgetDefinition {
    $painter = $reader->getAttribute('painter');
    if ($painter !== null && !preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*::[A-Za-z_][A-Za-z0-9_]*$/', $painter)) {
      throw new \RuntimeException('Canvas painter must name a callable static method as Class::method.');
    }
    $events = [];
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Event') {
          $events[] = (new EventParser())->parse($reader);
        } else if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Style') {
          $style = (new StyleParser())->parse($reader, $style);
        } else if ($reader->nodeType === \XMLReader::ELEMENT) {
          throw new \RuntimeException('Canvas accepts only Event and Style children.');
        } else if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'Canvas') {
          break;
        }
      }
    }
    return new WidgetDefinition(new Canvas($painter, $style->background), $events);
  }

}
