<?php

namespace SPTK\Core;

/** Bundles a parsed widget, XML subscriptions, and its effective optional pixel box Style. */
final class WidgetDefinition {

  /** Bundle a widget instance with events parsed from its XML element. */
  public function __construct(public readonly Widget $widget, public readonly array $events = [], public readonly ?Style $style = null) {
  }

}
