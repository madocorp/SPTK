<?php

namespace SPTK\Widgets\Text;

use SPTK\Core\{AttributeParser, Color, WidgetParser};

/** Reads the Text widget's XML */
final class Parser implements WidgetParser {

  use AttributeParser;

  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, array_merge($layoutAttributes, ['fg', 'bg']));
  }

  public function parse(\XMLReader $reader): Text {
    $fg = $this->attrColor($reader, 'fg', new Color(230, 235, 245));
    $bg = $this->attrColor($reader, 'bg', new Color(24, 28, 36));
    $text = $reader->readString();
    return new Text($text, $fg, $bg);
  }

}
