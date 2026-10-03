<?php

namespace SPTK\Widgets\FileSelector;

use SPTK\Core\{Style, WidgetDefinition};
use SPTK\XmlParser\{AttributeParser, EventParser, StyleParser, WidgetParser};

/** Parses FileSelector path, search settings, style, and events. */
final class Parser implements WidgetParser {

  use AttributeParser;

  /** Accept a directory path and list behavior flags. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, [...$layoutAttributes, 'path', 'multiple', 'filterable', 'searchable']);
  }

  /** Build a selector with a path relative to its XML screen file. */
  public function parse(\XMLReader $reader, Style $style): WidgetDefinition {
    $path = $reader->getAttribute('path') ?? '.';
    $multiple = $this->attrBoolean($reader, 'multiple', false);
    $filterable = $this->attrBoolean($reader, 'filterable', true);
    $searchable = $this->attrBoolean($reader, 'searchable', true);
    $base = rawurldecode((string)parse_url($reader->baseURI, PHP_URL_PATH));
    $path = str_starts_with($path, '/') ? $path : dirname($base) . '/' . $path;
    $events = [];
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Event') {
          $events[] = (new EventParser())->parse($reader);
        } else if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Style') {
          $style = (new StyleParser())->parse($reader, $style);
        } else if ($reader->nodeType === \XMLReader::ELEMENT || (in_array($reader->nodeType, [\XMLReader::TEXT, \XMLReader::CDATA], true) && trim($reader->value) !== '')) {
          throw new \RuntimeException('FileSelector accepts only Event and Style children.');
        } else if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'FileSelector') {
          break;
        }
      }
    }
    return new WidgetDefinition(new FileSelector($path, $multiple, $filterable, $searchable, $style), $events);
  }

}
