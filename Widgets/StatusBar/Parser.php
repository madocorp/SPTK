<?php

namespace SPTK\Widgets\StatusBar;

use SPTK\Core\{Style, WidgetDefinition};
use SPTK\XmlParser\{AttributeParser, WidgetParser};

/** Parses an empty status tile with inherited app colors and common widget attributes. */
final class Parser implements WidgetParser {

  use AttributeParser;

  /** Accept common layout, identifier, and tip attributes only. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, $layoutAttributes);
  }

  /** Create a status widget and reject body content. */
  public function parse(\XMLReader $reader, Style $style): WidgetDefinition {
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'StatusBar') {
          break;
        }
        if ($reader->nodeType === \XMLReader::ELEMENT || ($reader->nodeType === \XMLReader::TEXT && trim($reader->value) !== '')) {
          throw new \RuntimeException('StatusBar must be empty.');
        }
      }
    }
    return new WidgetDefinition(new StatusBar($style), []);
  }

}
