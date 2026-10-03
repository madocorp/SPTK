<?php

namespace SPTK\Widgets\Title;

use SPTK\Core\{Style, WidgetDefinition};
use SPTK\XmlParser\{AttributeParser, EventParser, StyleParser, WidgetParser};

/** Parses one-line headings with inherited palette colors. */
final class Parser implements WidgetParser {

  use AttributeParser;

  /** Accept ordinary tile attributes and an optional identifier or tip. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, $layoutAttributes);
  }

  /** Parse heading text, local style overrides, and event subscriptions. */
  public function parse(\XMLReader $reader, Style $style): WidgetDefinition {
    $events = [];
    $text = '';
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Event') {
          $events[] = (new EventParser())->parse($reader);
        } else if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Style') {
          $style = (new StyleParser())->parse($reader, $style);
        } else if ($reader->nodeType === \XMLReader::ELEMENT) {
          throw new \RuntimeException('Title accepts only Event and Style children.');
        } else if ($reader->nodeType === \XMLReader::TEXT || $reader->nodeType === \XMLReader::CDATA) {
          $text .= $reader->value;
        } else if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'Title') {
          break;
        }
      }
    }
    return new WidgetDefinition(new Title(trim($text), $style), $events);
  }

}
