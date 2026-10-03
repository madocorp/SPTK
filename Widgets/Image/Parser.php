<?php

namespace SPTK\Widgets\Image;

use SPTK\Core\{Style, WidgetDefinition};
use SPTK\XmlParser\{AttributeParser, EventParser, StyleParser, WidgetParser};

/** Parses image paths, fitting, zoom, offsets, styles, and events. */
final class Parser implements WidgetParser {

  use AttributeParser;

  /** Validate Image attributes together with its layout size. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, [...$layoutAttributes, 'src', 'fill', 'fit', 'interactive', 'padding', 'zoom', 'x', 'y', 'title']);
  }

  /** Build an Image with a source relative to its XML file. */
  public function parse(\XMLReader $reader, Style $style): WidgetDefinition {
    $src = $reader->getAttribute('src');
    if ($src === null || trim($src) === '') {
      throw new \RuntimeException('Image requires a src path.');
    }
    $fill = $this->attrBoolean($reader, 'fill', false);
    $fit = $reader->getAttribute('fit');
    $interactive = $this->attrBoolean($reader, 'interactive', true);
    $padding = $this->attrBoolean($reader, 'padding', true);
    $title = $reader->getAttribute('title');
    $zoom = $this->zoom($reader);
    $x = $this->offset($reader, 'x');
    $y = $this->offset($reader, 'y');
    $base = rawurldecode((string)parse_url($reader->baseURI, PHP_URL_PATH));
    $path = str_starts_with($src, '/') ? $src : dirname($base) . '/' . $src;
    $events = [];
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Event') {
          $events[] = (new EventParser())->parse($reader);
        } else if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Style') {
          $style = (new StyleParser())->parse($reader, $style);
        } else if ($reader->nodeType === \XMLReader::ELEMENT) {
          throw new \RuntimeException('Image accepts only Event and Style children.');
        } else if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'Image') {
          break;
        }
      }
    }
    return new WidgetDefinition(new Image($path, $fill, $zoom, $x, $y, $style->background, $fit, $interactive, $padding, $title, $style), $events, $style);
  }

  /** Read a positive finite image scale. */
  private function zoom(\XMLReader $reader): float {
    $value = $reader->getAttribute('zoom') ?? '1';
    if (!is_numeric($value) || !is_finite((float)$value) || (float)$value <= 0) {
      throw new \RuntimeException('Image zoom must be a positive finite number.');
    }
    return (float)$value;
  }

  /** Read a signed whole-pixel position offset. */
  private function offset(\XMLReader $reader, string $name): int {
    $value = $reader->getAttribute($name);
    if ($value === null) {
      return 0;
    }
    $offset = filter_var($value, FILTER_VALIDATE_INT);
    if ($offset === false) {
      throw new \RuntimeException("Image {$name} must be an integer pixel offset.");
    }
    return $offset;
  }

}
