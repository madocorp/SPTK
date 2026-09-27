<?php

namespace SPTK\Widgets\Button;

use SPTK\Core\{Style, WidgetDefinition};
use SPTK\Events\KeyNormalizer;
use SPTK\XmlParser\{AttributeParser, WidgetParser};

/** Parses Button labels, hotkeys, and static actions. */
final class Parser implements WidgetParser {

  use AttributeParser;

  /** Validate button and layout attributes. */
  public function validateAttributes(\XMLReader $reader, array $layoutAttributes): void {
    $this->assertAttributes($reader, [...$layoutAttributes, 'label', 'hotkey', 'action']);
  }

  /** Build a button from an empty XML element. */
  public function parse(\XMLReader $reader, Style $style): WidgetDefinition {
    $label = $reader->getAttribute('label');
    $action = $reader->getAttribute('action');
    $hotkey = $reader->getAttribute('hotkey');
    if ($label === null || $label === '' || !mb_check_encoding($label, 'UTF-8') || preg_match('/[\x00-\x1f\x7f]/', $label)) {
      throw new \RuntimeException('Button requires a printable one-line label.');
    }
    if ($action === null || !preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*::[A-Za-z_][A-Za-z0-9_]*$/', $action)) {
      throw new \RuntimeException('Button action must name a static method as Class::method.');
    }
    if ($hotkey !== null) {
      $hotkey = KeyNormalizer::normalizeName($hotkey);
      if ($hotkey === null) {
        throw new \RuntimeException('Invalid Button hotkey.');
      }
    }
    if (!$reader->isEmptyElement) {
      while ($reader->read()) {
        if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'Button') {
          break;
        }
        if ($reader->nodeType === \XMLReader::ELEMENT || ($reader->nodeType === \XMLReader::TEXT && trim($reader->value) !== '')) {
          throw new \RuntimeException('Button must be empty.');
        }
      }
    }
    return new WidgetDefinition(new Button($label, $hotkey, $action, $style->foreground, $style->background, $style->highlight));
  }

}
