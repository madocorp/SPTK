<?php

namespace SPTK\Widgets;

/**
 * Read-only date field that opens a DatePicker panel.
 */
class DateSelector extends Selector {

  public function __construct(string $name = '', mixed $value = '', array $options = []) {
    parent::__construct($name, [], null, $options + [
      'title' => 'Select Date',
      'panelSize' => 'normal',
      'panelRows' => 8,
    ]);
    $this->setValue($value === '' ? date('Y-m-d') : $value);
  }

  public function setValue(mixed $value): static {
    return parent::setValue($this->normalizeDate((string)$value));
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
    $panel = new DialogPanel($this->name === '' ? 'date-selector-panel' : $this->name . '-date-selector-panel', [
      'title' => $this->title,
      'size' => $this->panelSize,
      'contentColumns' => 28,
    ]);
    $picker = new DateSelectorPickerPanel($this->name === '' ? 'date-selector-picker' : $this->name . '-picker', $this->getValue());
    $panel->addContent($picker, $this->panelRows() + 1);
    $ok = new Button($panel->name() . '-ok', 'OK');
    $ok->setOnPress(function() use ($layer, $panel, $picker): void {
      $this->setValue($picker->getValue());
      $layer->pop($panel);
      $this->requestFocus();
    });
    $today = new Button($panel->name() . '-today', 'Today');
    $today->setOnPress(function() use ($picker): void {
      $picker->setValue(date('Y-m-d'));
      $picker->picker()->grid()->requestFocus();
    });
    $cancel = new Button($panel->name() . '-cancel', 'Cancel');
    $cancel->setOnPress(function() use ($layer, $panel): void {
      $layer->pop($panel);
      $this->requestFocus();
    });
    $panel->addButton($ok)->addButton($today)->addButton($cancel);
    $layer->push($panel);
    return true;
  }

  protected function syncPreferredColumns(): void {
    $this->setPreferredColumns(max(13, mb_strlen($this->text()) + 3));
  }

  protected function normalizeDate(string $value): string {
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
      throw new \InvalidArgumentException('Date must use YYYY-MM-DD.');
    }
    return $date->format('Y-m-d');
  }

}
