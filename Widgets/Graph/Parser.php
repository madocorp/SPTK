<?php

namespace SPTK\Widgets\Graph;

use SPTK\Core\{Style, WidgetDefinition};
use SPTK\XmlParser\{AttributeParser, EventParser, StyleParser, WidgetParser};

/** Parses numeric graph options, series, points, inherited style, and events. */
final class Parser implements WidgetParser {

  use AttributeParser;

  /** Accept graph options and the parent layout's sizing and identifier attributes. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, [...$layoutAttributes, ...array_keys(Data::DEFAULTS)]);
  }

  /** Build a graph from internal series data and optional style and event children. */
  public function parse(\XMLReader $reader, Style $style): WidgetDefinition {
    $options = $this->options($reader);
    $series = [];
    $events = [];
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'Graph') {
          break;
        }
        if ($reader->nodeType === \XMLReader::ELEMENT) {
          if ($reader->name === 'Series') {
            $series[] = $this->series($reader);
          } else if ($reader->name === 'Style') {
            $style = (new StyleParser())->parse($reader, $style);
          } else if ($reader->name === 'Event') {
            $events[] = (new EventParser())->parse($reader);
          } else {
            throw new \RuntimeException('Graph accepts only Series, Style, and Event children.');
          }
        } else {
          $this->assertNoText($reader);
        }
      }
    }
    return new WidgetDefinition(new Graph($series, $options, $style), $events);
  }

  /** Read optional graph attributes using the same types as the PHP API. */
  private function options(\XMLReader $reader): array {
    $options = [];
    foreach (Data::DEFAULTS as $name => $default) {
      $value = $reader->getAttribute($name);
      if ($value === null) {
        continue;
      }
      if (in_array($name, ['legend', 'grid'], true)) {
        $value = $this->attrBoolean($reader, $name);
      } else if (in_array($name, ['xMin', 'xMax', 'yMin', 'yMax'], true)) {
        $value = Data::number($value);
      } else if ($name === 'tickCount') {
        if (!preg_match('/^\d+$/D', $value)) {
          throw new \RuntimeException('Graph tickCount must be an integer from 2 through 12.');
        }
        $value = (int)$value;
      }
      $options[$name] = $value;
    }
    return $options;
  }

  /** Read one named series containing only coordinate pairs. */
  private function series(\XMLReader $reader): array {
    $this->assertAttributes($reader, ['name', 'type', 'color']);
    $series = ['points' => []];
    foreach (['name', 'type', 'color'] as $name) {
      $value = $reader->getAttribute($name);
      if ($value !== null) {
        $series[$name] = $value;
      }
    }
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'Series') {
          break;
        }
        if ($reader->nodeType === \XMLReader::ELEMENT) {
          if ($reader->name !== 'Point') {
            throw new \RuntimeException('Graph Series accepts only Point children.');
          }
          $series['points'][] = $this->point($reader);
        } else {
          $this->assertNoText($reader);
        }
      }
    }
    return $series;
  }

  /** Require two finite coordinates and reject any nested content in a point. */
  private function point(\XMLReader $reader): array {
    $this->assertAttributes($reader, ['x', 'y']);
    $point = [Data::number($reader->getAttribute('x')), Data::number($reader->getAttribute('y'))];
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'Point') {
          break;
        }
        if ($reader->nodeType === \XMLReader::ELEMENT) {
          throw new \RuntimeException('Graph Point cannot contain child elements.');
        }
        $this->assertNoText($reader);
      }
    }
    return $point;
  }

  /** Reject visible body text while permitting formatting whitespace and comments. */
  private function assertNoText(\XMLReader $reader): void {
    if (in_array($reader->nodeType, [\XMLReader::TEXT, \XMLReader::CDATA], true) && trim($reader->value) !== '') {
      throw new \RuntimeException('Graph data elements cannot contain body text.');
    }
  }

}
