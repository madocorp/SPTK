<?php

namespace SPTK\Widgets;

use SPTK\Core\Color;
use SPTK\Core\Element;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;

/**
 * Color selector dialog body with picker grid, free input, and preview strip.
 */
class ColorSelectorPickerPanel extends Element {

  protected ColorPickerInput $input;
  protected ColorPreview $preview;
  protected ColorPicker $picker;
  protected string $value;

  public function __construct(string $name = '', string $value = '#000000') {
    parent::__construct($name);
    $this->value = $this->normalizeColor($value);
    $this->input = new ColorPickerInput($name === '' ? 'color-selector-input' : $name . '-input', $this->value);
    $this->input->setOnChange(function(ColorPickerInput $input): void {
      $this->inputChanged($input->text());
    });
    $this->preview = new ColorPreview($name === '' ? 'color-selector-preview' : $name . '-preview', $this->value);
    $this->picker = new ColorPicker($name === '' ? 'color-selector-grid' : $name . '-grid', $this->value, [
      'onChange' => function(ColorPicker $picker): void {
        $this->pickerChanged($picker->getValue());
      },
    ]);
    $this->add($this->picker);
    $this->add($this->input);
    $this->add($this->preview);
    $this->setPreferredRows(7);
    $this->setPreferredColumns(64);
  }

  public function getValue(): string {
    return $this->value;
  }

  public function input(): ColorPickerInput {
    return $this->input;
  }

  public function preview(): ColorPreview {
    return $this->preview;
  }

  public function picker(): ColorPicker {
    return $this->picker;
  }

  public function setFrame(Rect $frame): void {
    $this->frame = $frame;
    $this->layout();
  }

  public function preferredRowsForColumns(int $columns): int {
    return $this->preferredRows();
  }

  public function layout(): void {
    $inputHeight = min(1, $this->frame->height);
    $inputWidth = min(9, $this->frame->width);
    $gap = $this->frame->width > $inputWidth ? min(2, max(0, $this->frame->width - $inputWidth)) : 0;
    $gridHeight = min($this->picker->preferredRows(), $this->frame->height);
    $inputY = $this->frame->y + $gridHeight;
    $previewX = $this->frame->x + $inputWidth + $gap;
    $this->picker->setFrame(new Rect($this->frame->x, $this->frame->y, $this->frame->width, $gridHeight));
    $this->input->setFrame(new Rect($this->frame->x, $inputY, $inputWidth, min($inputHeight, max(0, $this->frame->bottom() - $inputY))));
    $this->preview->setFrame(new Rect($previewX, $inputY, max(0, $this->frame->right() - $previewX), min($inputHeight, max(0, $this->frame->bottom() - $inputY))));
  }

  protected function paint(RenderTarget $target): void {
    $target->fill($this->frame, ' ', $this->theme->fg, $this->theme->bg);
  }

  protected function inputChanged(string $text): void {
    $color = $this->validColor($text);
    if ($color === null || $color === $this->value) {
      return;
    }
    $this->value = $color;
    $this->preview->setColor($color);
    $this->picker->setValue($color);
  }

  protected function pickerChanged(string $color): void {
    $color = $this->normalizeColor($color);
    if ($color === $this->value) {
      return;
    }
    $this->value = $color;
    $this->input->setText($color);
    $this->preview->setColor($color);
  }

  protected function validColor(string $value): ?string {
    try {
      return $this->normalizeColor($value);
    } catch (\InvalidArgumentException) {
      return null;
    }
  }

  protected function normalizeColor(string $value): string {
    return Color::from($value)->hex();
  }

}
