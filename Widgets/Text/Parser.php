<?php

namespace SPTK\Widgets\Text;

use SPTK\Core\{Style, WidgetDefinition};
use SPTK\XmlParser\{AttributeParser, EventParser, StyleParser, WidgetParser};

/** Reads the Text widget's XML */
final class Parser implements WidgetParser {

  use AttributeParser;

  /** Validate Text attributes together with size attributes inherited from the layout. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, [...$layoutAttributes, 'wrap', 'tabSize', 'align']);
  }

  /** Parse text content and event declarations from a Text element. */
  public function parse(\XMLReader $reader, Style $style): \SPTK\Core\WidgetDefinition {
    $styleParser = new StyleParser();
    $wrap = $this->attrBoolean($reader, 'wrap', true);
    $tabSize = $this->attrInteger($reader, 'tabSize', 8);
    $align = $reader->getAttribute('align') ?? 'left';
    if ($tabSize < 1) {
      throw new \RuntimeException('Text tabSize must be positive.');
    }
    $events = [];
    $text = '';
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Event') {
          $events[] = (new EventParser())->parse($reader);
        } else if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Style') {
          $style = $styleParser->parse($reader, $style);
        } else if ($reader->nodeType === \XMLReader::ELEMENT) {
          throw new \RuntimeException("Text does not accept child elements other than Event.");
        } else if ($reader->nodeType === \XMLReader::TEXT || $reader->nodeType === \XMLReader::CDATA) {
          $text .= $reader->value;
        } else if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'Text') {
          break;
        }
      }
    }
    $text = preg_replace('/\A(?:[ \t]*\R)+|(?:\R[ \t]*)+\z/u', '', $text);
    return new WidgetDefinition(new Text(
      $text,
      $style->foreground,
      $style->background,
      $style->cursorBackground,
      $style->cursorForeground,
      $style->highlight,
      $wrap,
      $tabSize,
      $align,
    ), $events);
  }

}
