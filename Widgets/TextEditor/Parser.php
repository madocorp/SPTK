<?php

namespace SPTK\Widgets\TextEditor;

use SPTK\Core\{Style, WidgetDefinition};
use SPTK\XmlParser\{AttributeParser, EventParser, StyleParser, WidgetParser};

/** Parses multiline editor content, wrapping, styling, and event declarations. */
final class Parser implements WidgetParser {

  use AttributeParser;

  /** Validate TextEditor attributes together with its layout size. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, [...$layoutAttributes, 'value', 'textWrap', 'tabSize', 'label']);
  }

  /** Build a TextEditor from preserved text or its higher-priority value attribute. */
  public function parse(\XMLReader $reader, Style $style): WidgetDefinition {
    $value = $reader->getAttribute('value');
    $label = $reader->getAttribute('label');
    $wrap = $this->attrBoolean($reader, 'textWrap', false);
    $tabSize = $this->attrInteger($reader, 'tabSize', 8);
    if ($tabSize < 1) {
      throw new \RuntimeException('TextEditor tabSize must be positive.');
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
          throw new \RuntimeException('TextEditor accepts only text, Event, and Style children.');
        } else if ($reader->nodeType === \XMLReader::TEXT || $reader->nodeType === \XMLReader::CDATA) {
          $text .= $reader->value;
        } else if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'TextEditor') {
          break;
        }
      }
    }
    return new WidgetDefinition(new TextEditor($value ?? $text, $style->foreground, $style->background, $style->cursorBackground, $style->highlight, $wrap, $tabSize, $label), $events);
  }

}
