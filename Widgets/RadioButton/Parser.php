<?php

namespace SPTK\Widgets\RadioButton;

use SPTK\Core\{Style, WidgetDefinition};
use SPTK\XmlParser\{AttributeParser, ItemParser, WidgetParser};

/** Parses RadioButton Item records, style, and change handlers. */
final class Parser implements WidgetParser {

  use AttributeParser;

  /** Accept an optional title alongside the parent layout's sizing attributes. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, [...$layoutAttributes, 'title']);
  }

  /** Build a radio group from checked Item records. */
  public function parse(\XMLReader $reader, Style $style): WidgetDefinition {
    $title = $reader->getAttribute('title');
    [$items, $events, $style] = (new ItemParser())->parse($reader, $style, 'checked');
    return new WidgetDefinition(new RadioButton($items, $style, $title), $events);
  }

}
