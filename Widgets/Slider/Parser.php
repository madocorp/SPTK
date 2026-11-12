<?php

namespace SPTK\Widgets\Slider;

use SPTK\Core\{Style, WidgetDefinition};
use SPTK\XmlParser\{AttributeParser, EventParser, StyleParser, WidgetParser};

/** Parses a numeric slider and its optional event actions. */
final class Parser implements WidgetParser {

  use AttributeParser;

  /** Accept range, steps, display text, mute state, and shared tile attributes. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, [...$layoutAttributes, 'value', 'min', 'max', 'step', 'smallStep', 'title', 'label', 'unit', 'muted']);
  }

  /** Build a slider from XML numbers, style, and events. */
  public function parse(\XMLReader $reader, Style $style): WidgetDefinition {
    $numbers = [];
    foreach (['value' => '0', 'min' => '0', 'max' => '100', 'step' => '10', 'smallStep' => '1'] as $name => $default) {
      $text = $reader->getAttribute($name) ?? $default;
      if (!is_numeric($text) || !is_finite((float)$text)) {
        throw new \RuntimeException("Slider {$name} must be a finite number.");
      }
      $numbers[$name] = (float)$text;
    }
    $label = $reader->getAttribute('label') ?? '';
    $unit = $reader->getAttribute('unit') ?? '%';
    $title = $reader->getAttribute('title');
    $muted = $this->attrBoolean($reader, 'muted', false);
    $events = [];
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Event') {
          $events[] = (new EventParser())->parse($reader);
        } else if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Style') {
          $style = (new StyleParser())->parse($reader, $style);
        } else if ($reader->nodeType === \XMLReader::ELEMENT
          || (in_array($reader->nodeType, [\XMLReader::TEXT, \XMLReader::CDATA], true) && trim($reader->value) !== '')) {
          throw new \RuntimeException('Slider accepts only Event and Style children.');
        } else if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'Slider') {
          break;
        }
      }
    }
    $slider = new Slider($numbers['value'], $numbers['min'], $numbers['max'], $numbers['step'], $label, $unit, $style, $title, $numbers['smallStep']);
    $slider->setMuted($muted);
    return new WidgetDefinition($slider, $events);
  }

}
