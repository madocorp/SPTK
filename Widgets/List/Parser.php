<?php

namespace SPTK\Widgets\List;

use SPTK\Core\{Style, WidgetDefinition};
use SPTK\XmlParser\{AttributeParser, ItemParser, WidgetParser};

/** Parses List item records, behavior flags, styles, and events. */
final class Parser implements WidgetParser {

  use AttributeParser;

  /** Validate List attributes alongside layout dimensions. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, [...$layoutAttributes, 'multiple', 'filterable', 'searchable', 'reorderable']);
  }

  /** Build a ListView from selected Item records. */
  public function parse(\XMLReader $reader, Style $style): WidgetDefinition {
    $multiple = $this->attrBoolean($reader, 'multiple', false);
    $filterable = $this->attrBoolean($reader, 'filterable', true);
    $searchable = $this->attrBoolean($reader, 'searchable', true);
    $reorderable = $this->attrBoolean($reader, 'reorderable', false);
    [$items, $events, $style] = (new ItemParser())->parse($reader, $style, 'selected');
    return new WidgetDefinition(new ListView($items, $multiple, $filterable, $searchable, $reorderable, $style->foreground, $style->background, $style->cursorBackground, $style->highlight), $events);
  }

}
