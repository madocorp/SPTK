<?php

namespace SPTK\Widgets\Separator;

use SPTK\Core\{AttributeParser, WidgetParser};

/** Reads the Separator widget's XML */
final class Parser implements WidgetParser {

  use AttributeParser;

  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, $layoutAttributes);
  }

  public function parse(\XMLReader $reader): Separator {
    return new Separator();
  }

}
