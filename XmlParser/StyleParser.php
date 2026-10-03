<?php

namespace SPTK\XmlParser;

use SPTK\Core\{Color, Style};
use SPTK\Layout\PixelBox;

/** Parses inherited colors and item-owned pixel box edges from Style elements. */
final class StyleParser {

  private const COLORS = ['Background', 'Foreground', 'Separator', 'BorderColor', 'Highlight', 'Selected', 'CursorBackground', 'CursorForeground', 'Error'];

  /** Read a Style element and return the inherited style with local overrides. */
  public function parse(\XMLReader $reader, Style $parent): Style {
    $this->assertAttributes($reader);
    $values = [];
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::ELEMENT) {
          if (!in_array($reader->name, [...self::COLORS, 'Margin', 'BorderWidth', 'Padding'], true)) {
            throw new \RuntimeException("Style does not support '{$reader->name}'.");
          }
          $name = $reader->name;
          $value = trim($reader->readString());
          if (in_array($name, ['Margin', 'BorderWidth', 'Padding'], true)) {
            $values[$name] = PixelBox::parseEdges($value);
          } else {
            $values[$name] = Color::from($value);
          }
        } else if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'Style') {
          break;
        }
      }
    }
    return $parent->with($values);
  }

  /** Reject attributes on a Style element. */
  private function assertAttributes(\XMLReader $reader): void {
    if ($reader->moveToFirstAttribute()) {
      $attribute = $reader->name;
      $reader->moveToElement();
      throw new \RuntimeException("<Style> does not accept the '{$attribute}' attribute.");
    }
  }

}
