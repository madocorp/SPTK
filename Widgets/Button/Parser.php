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
    $this->assertAttributes($reader, [...$layoutAttributes, 'label', 'hotkey', 'action', 'opens', 'restores']);
  }

  /** Build a button from an empty XML element. */
  public function parse(\XMLReader $reader, Style $style): WidgetDefinition {
    $label = $reader->getAttribute('label');
    $action = $reader->getAttribute('action');
    $hotkey = $reader->getAttribute('hotkey');
    if ($label === null || $label === '' || !mb_check_encoding($label, 'UTF-8') || preg_match('/[\x00-\x1f\x7f]/', $label)) {
      throw new \RuntimeException('Button requires a printable one-line label.');
    }
    $opens = $reader->getAttribute('opens');
    $restores = $reader->getAttribute('restores');
    if ($opens !== null && !preg_match('/^#[A-Za-z_][A-Za-z0-9_-]*$/', $opens)) {
      throw new \RuntimeException('Button opens must name an element as #id.');
    }
    if ($restores !== null && $restores !== 'parent' && !preg_match('/^#[A-Za-z_][A-Za-z0-9_-]*$/', $restores)) {
      throw new \RuntimeException('Button restores must be "parent" or #id.');
    }
    // The action is optional only when the button opens or restores an overwrite element.
    $invalidAction = $action === null
      ? $opens === null && $restores === null
      : !preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*::[A-Za-z_][A-Za-z0-9_]*$/', $action);
    if ($invalidAction) {
      throw new \RuntimeException('Button action must name a static method as Class::method.');
    }
    if ($hotkey !== null) {
      $hotkey = KeyNormalizer::normalizeChord($hotkey);
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
    $button = new Button($label, $hotkey, $action, $style);
    $button->setOverwrite($opens, $restores);
    return new WidgetDefinition($button);
  }

}
