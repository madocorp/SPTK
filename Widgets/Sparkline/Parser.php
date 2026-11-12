<?php

namespace SPTK\Widgets\Sparkline;

use SPTK\Core\{Style, WidgetDefinition};
use SPTK\XmlParser\{AttributeParser, EventParser, StyleParser, WidgetParser};

/** Parses a chart's initial samples, scale, style, and events. */
final class Parser implements WidgetParser {

  use AttributeParser;

  /** Accept chart values and shared widget layout attributes. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, [...$layoutAttributes, 'samples', 'max', 'label', 'showValue', 'unit']);
  }

  /** Build a sparkline from optional comma-separated values. */
  public function parse(\XMLReader $reader, Style $style): WidgetDefinition {
    $sampleText = trim($reader->getAttribute('samples') ?? '');
    $samples = $sampleText === '' ? [] : array_map('trim', explode(',', $sampleText));
    foreach ($samples as $sample) {
      if (!is_numeric($sample)) {
        throw new \RuntimeException('Sparkline samples must be comma-separated numbers.');
      }
    }
    $maximum = $reader->getAttribute('max') ?? '100';
    if (!is_numeric($maximum)) {
      throw new \RuntimeException('Sparkline max must be a positive number.');
    }
    $events = [];
    $label = $reader->getAttribute('label') ?? '';
    $showValue = $this->attrBoolean($reader, 'showValue', false);
    $unit = $reader->getAttribute('unit') ?? '';
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Event') {
          $events[] = (new EventParser())->parse($reader);
        } else if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Style') {
          $style = (new StyleParser())->parse($reader, $style);
        } else if ($reader->nodeType === \XMLReader::ELEMENT) {
          throw new \RuntimeException('Sparkline accepts only Event and Style children.');
        } else if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'Sparkline') {
          break;
        }
      }
    }
    return new WidgetDefinition(new Sparkline(array_map('floatval', $samples), (float)$maximum, $style, $label, $showValue, $unit), $events);
  }

}
