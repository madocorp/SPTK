<?php

namespace SPTK\XmlParser;

use SPTK\Core\{Color, Style};

/** Parses Style elements and applies their color values to inherited styles. */
final class StyleParser {

  private const COLORS = ['Background', 'Foreground', 'Separator', 'Highlight', 'Selected', 'CursorBackground', 'CursorForeground'];

  /** Read a Style element and return the inherited style with local overrides. */
  public function parse(\XMLReader $reader, Style $parent): Style {
    $this->assertAttributes($reader);
    $colors = [];
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::ELEMENT) {
          if (!in_array($reader->name, self::COLORS, true)) {
            throw new \RuntimeException("Style does not support '{$reader->name}'.");
          }
          $name = $reader->name;
          $colors[$name] = Color::from($reader->readString());
        } else if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'Style') {
          break;
        }
      }
    }
    return $parent->with($colors);
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
