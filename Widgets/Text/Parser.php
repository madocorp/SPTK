<?php

namespace SPTK\Widgets\Text;

/** Reads the Text widget's XML */
final class Parser {

  use AttributeParser;

  public function parse(\XMLReader $reader): Text {
    return new Text($text, $fg, $bg);
  }

}
