<?php

namespace SPTK\XmlParser;

use SPTK\Core\{Style, WidgetDefinition};

/** Defines how a widget parser validates XML attributes and creates its widget. */
interface WidgetParser {

  /** Validate attributes declared on the widget element. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void;

  /** Parse a widget and its nested events; pixel widgets return their effective Style in the definition. */
  public function parse(\XMLReader $reader, Style $style): WidgetDefinition;

}
