<?php

namespace SPTK\Widgets;

use SPTK\Core\Element;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;

/**
 * Shows operation text and a two-row progress bar.
 */
class ProgressBar extends Element {

  protected string $textMode = 'percent';

  public function __construct(
    string $name = '',
    protected string $text = '',
    protected int $value = 0,
    protected int $max = 100,
    array $options = []
  ) {
    parent::__construct($name);
    $this->setPreferredRows(2);
    $this->setOptions($options);
  }

  public function setOptions(array $options): static {
    if (array_key_exists('textMode', $options)) {
      $this->setTextMode((string)$options['textMode']);
    }
    return $this;
  }

  public function setText(string $text): static {
    if ($this->text !== $text) {
      $this->text = $text;
      $this->invalidateRender();
    }
    return $this;
  }

  public function text(): string {
    return $this->text;
  }

  public function setProgress(int $value, ?int $max = null): static {
    $max ??= $this->max;
    $value = max(0, $value);
    $max = max(1, $max);
    if ($this->value !== $value || $this->max !== $max) {
      $this->value = min($value, $max);
      $this->max = $max;
      $this->invalidateRender();
    }
    return $this;
  }

  public function value(): int {
    return $this->value;
  }

  public function max(): int {
    return $this->max;
  }

  public function setTextMode(string $mode): static {
    if (!in_array($mode, ['percent', 'value', 'none'], true)) {
      throw new \InvalidArgumentException("ProgressBar text mode must be percent, value, or none.");
    }
    if ($this->textMode !== $mode) {
      $this->textMode = $mode;
      $this->invalidateRender();
    }
    return $this;
  }

  public function textMode(): string {
    return $this->textMode;
  }

  protected function paint(RenderTarget $target): void {
    $target->fill($this->frame, ' ', '#000000', $this->theme->inactiveCursorBg);
    if ($this->frame->width <= 0 || $this->frame->height <= 0) {
      return;
    }
    $target->fill(new Rect($this->frame->x, $this->frame->y, $this->frame->width, 1), ' ', '#000000', $this->theme->bg);
    $target->write($this->frame->x, $this->frame->y, mb_substr($this->text, 0, $this->frame->width), '#000000', $this->theme->bg);
    if ($this->frame->height < 2) {
      return;
    }
    $barY = $this->frame->y + 1;
    $filled = $this->filledColumns();
    if ($filled > 0) {
      $target->fill(new Rect($this->frame->x, $barY, $filled, 1), ' ', '#ffffff', $this->theme->inverseBg);
    }
    $this->paintBarText($target, $barY, $filled);
  }

  protected function paintBarText(RenderTarget $target, int $y, int $filled): void {
    $text = $this->barText();
    if ($text === '') {
      return;
    }
    if (mb_strlen($text) > $this->frame->width) {
      $text = mb_substr($text, 0, $this->frame->width);
    }
    $x = max(0, intdiv($this->frame->width - mb_strlen($text), 2));
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    foreach ($chars as $i => $char) {
      $column = $x + $i;
      $bg = $column < $filled ? $this->theme->inverseBg : $this->theme->inactiveCursorBg;
      $target->put($this->frame->x + $column, $y, $char, '#ffffff', $bg);
    }
  }

  protected function filledColumns(): int {
    if ($this->frame->width <= 0) {
      return 0;
    }
    return min($this->frame->width, (int)round($this->frame->width * $this->value / max(1, $this->max)));
  }

  protected function barText(): string {
    if ($this->textMode === 'none') {
      return '';
    }
    if ($this->textMode === 'value') {
      return "{$this->value} / {$this->max}";
    }
    return (string)round(100 * $this->value / max(1, $this->max)) . '%';
  }

}
