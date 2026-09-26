<?php

namespace SPTK\Widgets\Input;

use SPTK\Core\{Style, WidgetDefinition};
use SPTK\XmlParser\{AttributeParser, EventParser, StyleParser, WidgetParser};

/** Parses one-line Input XML content, styling, and event declarations. */
final class Parser implements WidgetParser {

  use AttributeParser;

  /** Validate Input attributes together with its layout size. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, [...$layoutAttributes, 'value', 'tabSize', 'label']);
  }

  /** Build an Input widget from XML text or its higher-priority value attribute. */
  public function parse(\XMLReader $reader, Style $style): WidgetDefinition {
    $value = $reader->getAttribute('value');
    $label = $reader->getAttribute('label');
    $tabSize = $this->attrInteger($reader, 'tabSize', 8);
    if ($tabSize < 1) {
      throw new \RuntimeException('Input tabSize must be positive.');
    }
    $text = '';
    $events = [];
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Event') {
          $events[] = (new EventParser())->parse($reader);
        } else if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Style') {
          $style = (new StyleParser())->parse($reader, $style);
        } else if ($reader->nodeType === \XMLReader::ELEMENT) {
          throw new \RuntimeException('Input accepts only text, Event, and Style children.');
        } else if ($reader->nodeType === \XMLReader::TEXT || $reader->nodeType === \XMLReader::CDATA) {
          $text .= $reader->value;
        } else if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'Input') {
          break;
        }
      }
    }
    return new WidgetDefinition(new Input($value ?? trim($text), $style->foreground, $style->background, $style->cursorBackground, $style->highlight, $tabSize, $label), $events);
  }

}
