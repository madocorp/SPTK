<?php

namespace SPTK\Widgets;

use SPTK\Core\Color;

/**
 * Read-only color field that opens a ColorPicker panel.
 */
class ColorSelector extends Selector {

  public function __construct(string $name = '', mixed $value = '#000000', array $options = []) {
    parent::__construct($name, [], null, $options + [
      'title' => 'Select Color',
      'panelSize' => 'normal',
      'panelRows' => 6,
    ]);
    $this->setValue($value);
  }

  public function setValue(mixed $value): static {
    return parent::setValue($this->normalizeColor((string)$value));
  }

  public function getValue(): string {
    return (string)parent::getValue();
  }

  public function text(): string {
    return $this->getValue() === '' ? $this->placeholder : $this->getValue();
  }

  protected function openPanel(): bool {
    $layer = $this->findDialogLayer($this->root());
    if ($layer === null) {
      return false;
    }
    $panel = new DialogPanel($this->name === '' ? 'color-selector-panel' : $this->name . '-color-selector-panel', [
      'title' => $this->title,
      'size' => $this->panelSize,
      'contentColumns' => 64,
    ]);
    $picker = new ColorSelectorPickerPanel($this->name === '' ? 'color-selector-picker' : $this->name . '-picker', $this->getValue());
    $panel->addContent($picker, $this->panelRows() + 1);
    $ok = new Button($panel->name() . '-ok', 'OK');
    $ok->setOnPress(function() use ($layer, $panel, $picker): void {
      $this->setValue($picker->getValue());
      $layer->pop($panel);
      $this->requestFocus();
    });
    $cancel = new Button($panel->name() . '-cancel', 'Cancel');
    $cancel->setOnPress(function() use ($layer, $panel): void {
      $layer->pop($panel);
      $this->requestFocus();
    });
    $panel->addButton($ok)->addButton($cancel);
    $layer->push($panel);
    return true;
  }

  protected function syncPreferredColumns(): void {
    $this->setPreferredColumns(max(12, mb_strlen($this->text()) + 3));
  }

  protected function normalizeColor(string $value): string {
    return Color::from($value)->hex();
  }

}
