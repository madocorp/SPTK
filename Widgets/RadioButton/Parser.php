<?php

namespace SPTK\Widgets\RadioButton;

use SPTK\Core\{Style, WidgetDefinition};
use SPTK\XmlParser\{AttributeParser, ItemParser, WidgetParser};

/** Parses RadioButton Item records, style, and change handlers. */
final class Parser implements WidgetParser {

  use AttributeParser;

  /** Accept only the size attribute supplied by the parent layout. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, $layoutAttributes);
  }

  /** Build a radio group from checked Item records. */
  public function parse(\XMLReader $reader, Style $style): WidgetDefinition {
    [$items, $events, $style] = (new ItemParser())->parse($reader, $style, 'checked');
    return new WidgetDefinition(new RadioButton($items, $style->foreground, $style->background, $style->cursorBackground, $style->highlight), $events);
  }

}
