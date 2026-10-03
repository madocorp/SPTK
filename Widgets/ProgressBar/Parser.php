<?php

namespace SPTK\Widgets\ProgressBar;

use SPTK\Core\{Style, WidgetDefinition};
use SPTK\XmlParser\{AttributeParser, EventParser, StyleParser, WidgetParser};

/** Parses ProgressBar values, title, label mode, style, and events. */
final class Parser implements WidgetParser {

  use AttributeParser;

  /** Accept progress, display, title, and parent layout attributes. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, [...$layoutAttributes, 'value', 'max', 'display', 'text', 'title']);
  }

  /** Build a progress bar from attributes and optional text or child declarations. */
  public function parse(\XMLReader $reader, Style $style): WidgetDefinition {
    $value = $this->number($reader, 'value', 0);
    $maximum = $this->number($reader, 'max', 100);
    $title = $reader->getAttribute('title') ?? '';
    $textAttribute = $reader->getAttribute('text');
    $displayAttribute = $reader->getAttribute('display');
    $body = '';
    $events = [];
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Event') {
          $events[] = (new EventParser())->parse($reader);
        } else if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Style') {
          $style = (new StyleParser())->parse($reader, $style);
        } else if ($reader->nodeType === \XMLReader::ELEMENT) {
          throw new \RuntimeException('ProgressBar accepts only Event and Style children.');
        } else if ($reader->nodeType === \XMLReader::TEXT || $reader->nodeType === \XMLReader::CDATA) {
          $body .= $reader->value;
        } else if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'ProgressBar') {
          break;
        }
      }
    }
    $body = trim($body);
    if ($textAttribute !== null && $body !== '') {
      throw new \RuntimeException('ProgressBar text attribute and body text cannot be combined.');
    }
    $text = $textAttribute ?? $body;
    $display = $displayAttribute ?? ($text === '' ? 'percent' : 'text');
    return new WidgetDefinition(new ProgressBar($value, $maximum, $display, $text, $title, $style), $events);
  }

  /** Parse one finite numeric XML attribute. */
  private function number(\XMLReader $reader, string $name, float $default): float {
    $value = $reader->getAttribute($name);
    if ($value === null) {
      return $default;
    }
    if (!is_numeric($value) || !is_finite((float)$value)) {
      throw new \RuntimeException("ProgressBar {$name} must be a finite number.");
    }
    return (float)$value;
  }

}
