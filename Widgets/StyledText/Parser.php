<?php

namespace SPTK\Widgets\StyledText;

use SPTK\Core\{Style, WidgetDefinition};
use SPTK\XmlParser\{AttributeParser, EventParser, StyleParser, WidgetParser};

/** Parses styled text typography, inline Run and Br elements, and inherited widget events. */
final class Parser implements WidgetParser {

  use AttributeParser;

  /** Accept the normal layout attributes and supported rich text typography. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, [...$layoutAttributes, ...array_keys(Format::DEFAULTS), 'dimmed']);
  }

  /** Build a rich text widget while preserving its inline text and whitespace. */
  public function parse(\XMLReader $reader, Style $style): WidgetDefinition {
    $options = $this->options($reader);
    $dimmed = $this->attrBoolean($reader, 'dimmed', true);
    $events = [];
    $runs = [];
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'StyledText') {
          break;
        }
        if ($reader->nodeType === \XMLReader::ELEMENT) {
          if ($reader->name === 'Run') {
            $runs[] = $this->run($reader);
          } else if ($reader->name === 'Br') {
            $this->assertAttributes($reader, []);
            if (!$reader->isEmptyElement) {
              throw new \RuntimeException('StyledText Br must be empty.');
            }
            $runs[] = ['type' => 'br'];
          } else if ($reader->name === 'Event') {
            $events[] = (new EventParser())->parse($reader);
          } else if ($reader->name === 'Style') {
            $style = (new StyleParser())->parse($reader, $style);
          } else {
            throw new \RuntimeException('StyledText accepts Run, Br, Event, and Style children.');
          }
        } else if (in_array($reader->nodeType, [\XMLReader::TEXT, \XMLReader::CDATA, \XMLReader::SIGNIFICANT_WHITESPACE], true)) {
          $runs[] = ['text' => $reader->value];
        }
      }
    }
    return new WidgetDefinition(new StyledText($runs, $options, $style, $dimmed), $events);
  }

  /** Parse one inline style override containing only text. */
  private function run(\XMLReader $reader): array {
    $this->assertAttributes($reader, array_keys(Format::DEFAULTS));
    $options = $this->options($reader);
    $text = '';
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'Run') {
          break;
        }
        if ($reader->nodeType === \XMLReader::ELEMENT) {
          throw new \RuntimeException('StyledText Run accepts only text.');
        }
        if (in_array($reader->nodeType, [\XMLReader::TEXT, \XMLReader::CDATA, \XMLReader::SIGNIFICANT_WHITESPACE], true)) {
          $text .= $reader->value;
        }
      }
    }
    return ['text' => $text, ...$options];
  }

  /** Read typography attributes with typed booleans and pixel or viewport dimensions. */
  private function options(\XMLReader $reader): array {
    $options = [];
    foreach (Format::DEFAULTS as $name => $default) {
      $value = $reader->getAttribute($name);
      if ($value !== null) {
        $options[$name] = is_bool($default) ? $this->attrBoolean($reader, $name) : $value;
      }
    }
    return Format::style($options);
  }

}
