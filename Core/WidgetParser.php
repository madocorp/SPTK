<?php

namespace SPTK\Core;

/** Defines how a widget parser validates XML attributes and creates its widget. */
interface WidgetParser {

  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void;

  public function parse(\XMLReader $reader): Widget;

}
