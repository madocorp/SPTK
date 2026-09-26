<?php

namespace SPTK\XmlParser;

use SPTK\Core\Style;

/** Reads item records and inherited declarations inside choice and list widgets. */
final class ItemParser {

  use AttributeParser;

  /** Parse one widget's Item, Event, and Style children. */
  public function parse(\XMLReader $reader, Style $style, string $selectionAttribute): array {
    $widgetName = $reader->name;
    $items = [];
    $events = [];
    if ($reader->isEmptyElement) {
      return [$items, $events, $style];
    }
    while ($reader->read()) {
      if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Item') {
        $items[] = $this->item($reader, $selectionAttribute);
      } else if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Event') {
        $events[] = (new EventParser())->parse($reader);
      } else if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Style') {
        $style = (new StyleParser())->parse($reader, $style);
      } else if ($reader->nodeType === \XMLReader::ELEMENT) {
        throw new \RuntimeException("<{$widgetName}> accepts only Item, Event, and Style children.");
      } else if (($reader->nodeType === \XMLReader::TEXT || $reader->nodeType === \XMLReader::CDATA) && trim($reader->value) !== '') {
        throw new \RuntimeException("<{$widgetName}> does not accept text outside Item elements.");
      } else if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === $widgetName) {
        break;
      }
    }
    return [$items, $events, $style];
  }

  /** Parse one Item's label, value, and initial state. */
  private function item(\XMLReader $reader, string $selectionAttribute): array {
    $this->assertAttributes($reader, ['value', 'label', $selectionAttribute]);
    $value = $reader->getAttribute('value');
    $label = $reader->getAttribute('label');
    $selected = $this->attrBoolean($reader, $selectionAttribute, false);
    $text = '';
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::ELEMENT) {
          throw new \RuntimeException('Item cannot contain child elements.');
        }
        if ($reader->nodeType === \XMLReader::TEXT || $reader->nodeType === \XMLReader::CDATA) {
          $text .= $reader->value;
        }
        if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'Item') {
          break;
        }
      }
    }
    $label ??= trim($text);
    return ['value' => $value ?? $label, 'label' => $label, $selectionAttribute => $selected];
  }

}
