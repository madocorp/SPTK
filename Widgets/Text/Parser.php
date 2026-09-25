<?php

namespace SPTK\Widgets\Text;

use SPTK\Core\{AttributeParser, Color, WidgetParser};

/** Reads the Text widget's XML */
final class Parser implements WidgetParser {

  use AttributeParser;

  /** Validate Text attributes together with size attributes inherited from the layout. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, array_merge($layoutAttributes, ['fg', 'bg']));
  }

  /** Parse text content and event declarations from a Text element. */
  public function parse(\XMLReader $reader): \SPTK\Core\WidgetDefinition {
    $fg = $this->attrColor($reader, 'fg', new Color(230, 235, 245));
    $bg = $this->attrColor($reader, 'bg', new Color(24, 28, 36));
    $events = [];
    $text = '';
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Event') {
          $events[] = (new \SPTK\Core\EventParser())->parse($reader);
        } else if ($reader->nodeType === \XMLReader::ELEMENT) {
          throw new \RuntimeException("Text does not accept child elements other than Event.");
        } else if ($reader->nodeType === \XMLReader::TEXT || $reader->nodeType === \XMLReader::CDATA) {
          $text .= $reader->value;
        } else if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'Text') {
          break;
        }
      }
    }
    return new \SPTK\Core\WidgetDefinition(new Text($text, $fg, $bg), $events);
  }

}
