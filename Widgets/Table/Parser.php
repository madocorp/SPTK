<?php

namespace SPTK\Widgets\Table;

use SPTK\Core\{Style, WidgetDefinition};
use SPTK\XmlParser\{AttributeParser, EventParser, StyleParser, WidgetParser};

/** Parses Table sources, headings, rows, styles, and event declarations. */
final class Parser implements WidgetParser {

  use AttributeParser;

  /** Accept a file source and optional row number display. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, [...$layoutAttributes, 'file', 'rowNumbers']);
  }

  /** Build a table from XML records or an escaped TSV file. */
  public function parse(\XMLReader $reader, Style $style): WidgetDefinition {
    $file = $reader->getAttribute('file');
    $rowNumbers = $this->attrBoolean($reader, 'rowNumbers', false);
    $header = [];
    $rows = [];
    $widths = [];
    $events = [];
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Header') {
          if ($file !== null || $header !== [] || $rows !== []) {
            throw new \RuntimeException('Table Header must appear once before Row and cannot accompany file.');
          }
          [$header, $widths] = $this->record($reader, 'Header');
        } else if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Row') {
          if ($file !== null) {
            throw new \RuntimeException('Table Row cannot accompany file.');
          }
          [$row] = $this->record($reader, 'Row');
          $rows[] = $row;
        } else if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Event') {
          $events[] = (new EventParser())->parse($reader);
        } else if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Style') {
          $style = (new StyleParser())->parse($reader, $style);
        } else if ($reader->nodeType === \XMLReader::ELEMENT) {
          throw new \RuntimeException('Table accepts only Header, Row, Event, and Style children.');
        } else if (in_array($reader->nodeType, [\XMLReader::TEXT, \XMLReader::CDATA], true) && trim($reader->value) !== '') {
          throw new \RuntimeException('Table does not accept text outside fields.');
        } else if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'Table') {
          break;
        }
      }
    }
    if ($widths !== [] && count($widths) !== count($header)) {
      throw new \RuntimeException('Specify widths for all Header fields or none.');
    }
    $table = new Table($header, $rows, $widths, $style, $rowNumbers);
    if ($file !== null) {
      if (trim($file) === '') {
        throw new \RuntimeException('Table file cannot be empty.');
      }
      $base = rawurldecode((string)parse_url($reader->baseURI, PHP_URL_PATH));
      $path = str_starts_with($file, '/') ? $file : dirname($base) . '/' . $file;
      $table->setTsvFile($path);
    }
    return new WidgetDefinition($table, $events);
  }

  /** Read Header or Row fields and optional header widths. */
  private function record(\XMLReader $reader, string $name): array {
    $this->assertAttributes($reader, []);
    $fields = [];
    $widths = [];
    if ($reader->isEmptyElement) {
      return [$fields, $widths];
    }
    while ($reader->read()) {
      if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Field') {
        [$value, $width] = $this->field($reader, $name === 'Header');
        $fields[] = $value;
        if ($width !== null) {
          $widths[] = $width;
        }
      } else if ($reader->nodeType === \XMLReader::ELEMENT) {
        throw new \RuntimeException("<{$name}> accepts only Field children.");
      } else if (in_array($reader->nodeType, [\XMLReader::TEXT, \XMLReader::CDATA], true) && trim($reader->value) !== '') {
        throw new \RuntimeException("<{$name}> does not accept text outside Field.");
      } else if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === $name) {
        break;
      }
    }
    return [$fields, $widths];
  }

  /** Read a text-only field with optional null or fixed width. */
  private function field(\XMLReader $reader, bool $header): array {
    $this->assertAttributes($reader, $header ? ['width'] : ['null']);
    $width = $reader->getAttribute('width');
    if ($width !== null && (!ctype_digit($width) || (int)$width < 1)) {
      throw new \RuntimeException('Header Field width must be a positive integer.');
    }
    $null = !$header && $this->attrBoolean($reader, 'null', false);
    $text = '';
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::ELEMENT) {
          throw new \RuntimeException('Field cannot contain child elements.');
        }
        if ($reader->nodeType === \XMLReader::TEXT || $reader->nodeType === \XMLReader::CDATA) {
          $text .= $reader->value;
        }
        if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'Field') {
          break;
        }
      }
    }
    if ($null && trim($text) !== '') {
      throw new \RuntimeException('Null Field cannot contain text.');
    }
    return [$null ? null : trim($text), $width === null ? null : (int)$width];
  }

}
