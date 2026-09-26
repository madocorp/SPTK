<?php

namespace SPTK\Widgets\CheckboxArray;

use SPTK\Core\{Style, WidgetDefinition};
use SPTK\XmlParser\{AttributeParser, ItemParser, WidgetParser};

/** Parses CheckboxArray Item records, style, and change handlers. */
final class Parser implements WidgetParser {

  use AttributeParser;

  /** Accept only the size attribute supplied by the parent layout. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, $layoutAttributes);
  }

  /** Build a checkbox group from checked Item records. */
  public function parse(\XMLReader $reader, Style $style): WidgetDefinition {
    [$items, $events, $style] = (new ItemParser())->parse($reader, $style, 'checked');
    return new WidgetDefinition(new CheckboxArray($items, $style->foreground, $style->background, $style->cursorBackground, $style->highlight), $events);
  }

}
