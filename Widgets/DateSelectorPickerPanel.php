<?php

namespace SPTK\Widgets;

use SPTK\Core\Element;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;

/**
 * Date selector dialog body with picker grid and free ISO input.
 */
class DateSelectorPickerPanel extends Element {

  protected DatePickerInput $input;
  protected DatePicker $picker;
  protected string $value;

  public function __construct(string $name = '', string $value = '') {
    parent::__construct($name);
    $this->value = $this->normalizeDate($value === '' ? date('Y-m-d') : $value);
    $this->picker = new DatePicker($name === '' ? 'date-selector-picker' : $name . '-picker', $this->value, [
      'onChange' => function(DatePicker $picker): void {
        $this->pickerChanged($picker->getValue());
      },
    ]);
    $this->input = new DatePickerInput($name === '' ? 'date-selector-input' : $name . '-input', $this->value);
    $this->input->setOnChange(function(DatePickerInput $input): void {
      $this->inputChanged($input->text());
    });
    $this->add($this->picker);
    $this->add($this->input);
    $this->setPreferredRows(9);
    $this->setPreferredColumns(28);
  }

  public function setValue(string $value): static {
    $value = $this->normalizeDate($value);
    if ($this->value === $value) {
      return $this;
    }
    $this->value = $value;
    $this->picker->setValue($value);
    $this->input->setText($value);
    $this->invalidateRender();
    return $this;
  }

  public function getValue(): string {
    return $this->value;
  }

  public function picker(): DatePicker {
    return $this->picker;
  }

  public function input(): DatePickerInput {
    return $this->input;
  }

  public function setFrame(Rect $frame): void {
    $this->frame = $frame;
    $this->layout();
  }

  public function preferredRowsForColumns(int $columns): int {
    return $this->preferredRows();
  }

  public function layout(): void {
    $pickerHeight = min($this->picker->preferredRows(), $this->frame->height);
    $inputY = $this->frame->y + $pickerHeight;
    $this->picker->setFrame(new Rect($this->frame->x, $this->frame->y, $this->frame->width, $pickerHeight));
    $this->input->setFrame(new Rect($this->frame->x, $inputY, $this->frame->width, min(1, max(0, $this->frame->bottom() - $inputY))));
  }

  protected function paint(RenderTarget $target): void {
    $target->fill($this->frame, ' ', $this->theme->fg, $this->theme->bg);
  }

  protected function inputChanged(string $text): void {
    $date = $this->validDate($text);
    if ($date === null || $date === $this->value) {
      return;
    }
    $this->value = $date;
    $this->picker->setValue($date);
    $this->invalidateRender();
  }

  protected function pickerChanged(string $date): void {
    $date = $this->normalizeDate($date);
    if ($date === $this->value) {
      return;
    }
    $this->value = $date;
    $this->input->setText($date);
    $this->invalidateRender();
  }

  protected function validDate(string $value): ?string {
    try {
      return $this->normalizeDate($value);
    } catch (\InvalidArgumentException) {
      return null;
    }
  }

  protected function normalizeDate(string $value): string {
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
      throw new \InvalidArgumentException('Date must use YYYY-MM-DD.');
    }
    return $date->format('Y-m-d');
  }

}
