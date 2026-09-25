<?php

namespace SPTK\Core;

/** Bundles a parsed widget instance with its XML event subscriptions. */
final class WidgetDefinition {

  /** Bundle a widget instance with events parsed from its XML element. */
  public function __construct(public readonly Widget $widget, public readonly array $events = []) {
  }

}
