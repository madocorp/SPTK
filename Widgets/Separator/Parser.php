<?php

namespace SPTK\Widgets\Separator;

/** Reads the Separator widget's XML */
final class Parser {

  use AttributeParser;

  public function parse(\XMLReader $reader): Separator {
    return new Separator();
  }

}
