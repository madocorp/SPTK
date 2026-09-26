<?php

namespace SPTK\Widgets\Empty;

use SPTK\Core\{Style, WidgetDefinition};
use SPTK\XmlParser\{AttributeParser, EventParser, StyleParser, WidgetParser};

/** Parses Empty widget elements and their event declarations. */
final class Parser implements WidgetParser {

  use AttributeParser;

  /** Validate size attributes inherited from the layout. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, $layoutAttributes);
  }

  /** Parse an Empty widget and its nested event declarations. */
  public function parse(\XMLReader $reader, Style $style): WidgetDefinition {
    $events = [];
    $styleParser = new StyleParser();
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Event') {
          $events[] = (new EventParser())->parse($reader);
        } else if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Style') {
          $style = $styleParser->parse($reader, $style);
        } else if ($reader->nodeType === \XMLReader::ELEMENT) {
          throw new \RuntimeException('Empty does not accept child elements other than Event and Style.');
        } else if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'Empty') {
          break;
        }
      }
    }
    return new WidgetDefinition(new Placeholder($style->background), $events);
  }

}
