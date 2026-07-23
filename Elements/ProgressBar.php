<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\StyleSheet;
use \SPTK\Texture;

class ProgressBar extends Element {

  protected InputValue $labelElement;
  protected ProgressBarBox $barElement;
  protected string $label = '';
  protected string $progressType = 'percent';
  protected bool $showJob = true;
  protected int|float $progress = 0;
  protected int $stepNumber = 0;
  protected string $jobName = '';

  protected function init(): void {
    $this->labelElement = new InputValue($this, null, null, 'ProgressBarLabel');
    new Space($this);
    $this->barElement = new ProgressBarBox($this);
    $this->updateDisplay();
  }

  public function getAttributeList(): array {
    return ['label', 'type', 'showJob', 'stepNumber', 'value'];
  }

  public function setLabel($label): void {
    if ($label === false) {
      $label = '';
    }
    $this->label = (string)$label;
    $this->updateDisplay();
  }

  public function setType($type): void {
    if (!in_array($type, ['percent', 'steps', 'hidden'], true)) {
      $type = 'percent';
    }
    $this->progressType = $type;
    $this->updateDisplay();
  }

  public function getProgressType(): string {
    return $this->progressType;
  }

  public function setShowJob($value): void {
    if ($value === false) {
      return;
    }
    $this->showJob = $this->booleanValue($value, true);
    $this->updateDisplay();
  }

  public function getShowJob(): bool {
    return $this->showJob;
  }

  public function setStepNumber($value): void {
    $this->stepNumber = max(0, (int)$value);
    $this->updateDisplay();
  }

  public function getStepNumber(): int {
    return $this->stepNumber;
  }

  public function setValue($value): void {
    if ($value === false || $value === '') {
      $value = 0;
    }
    $this->progress = is_numeric($value) ? $value + 0 : 0;
    $this->updateDisplay();
  }

  public function getValue(): mixed {
    return $this->progress;
  }

  public function increment(int $amount = 1): void {
    $this->setValue($this->progress + $amount);
  }

  public function setJobName($jobName): void {
    if ($jobName === false) {
      $jobName = '';
    }
    $this->jobName = (string)$jobName;
    $this->updateDisplay();
  }

  public function getJobName(): string {
    return $this->jobName;
  }

  public function getProgressRatio(): float {
    if ($this->progressType === 'percent') {
      return max(0, min(100, (float)$this->progress)) / 100;
    }
    if ($this->progressType === 'steps') {
      if ($this->stepNumber <= 0) {
        return 0;
      }
      return max(0, min($this->stepNumber, (float)$this->progress)) / $this->stepNumber;
    }
    return 0;
  }

  private function updateDisplay(): void {
    $this->labelElement->setValue($this->displayText());
    $this->barElement->markChanged();
    $this->refreshDisplay();
  }

  private function displayText(): string {
    $parts = [];
    if ($this->progressType === 'percent') {
      $parts[] = (int)round(max(0, min(100, (float)$this->progress))) . '%';
    } else if ($this->progressType === 'steps') {
      $parts[] = (int)$this->progress . ' / ' . $this->stepNumber;
    }
    if ($this->showJob && $this->jobName !== '') {
      $parts[] = $this->jobName;
    }
    $suffix = implode(' ', $parts);
    if ($this->label === '') {
      return $suffix;
    }
    return $suffix === '' ? $this->label : $this->label . ': ' . $suffix;
  }

  private function booleanValue($value, bool $default): bool {
    if ($value === null) {
      return $default;
    }
    if (is_bool($value)) {
      return $value;
    }
    return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
  }

  private function refreshDisplay(): void {
    $this->changed = true;
    if (!$this->isVisibleInTree()) {
      return;
    }
    $window = $this->findAncestorByType('Window');
    if ($window === false) {
      return;
    }
    Element::immediateRender($this);
  }

  private function isVisibleInTree(): bool {
    $element = $this;
    while ($element !== null) {
      if (!$element->isDisplayed()) {
        return false;
      }
      $element = $element->getAncestor();
    }
    return true;
  }

}

class ProgressBarBox extends Element {

  public function markChanged(): void {
    $this->changed = true;
  }

  protected function draw(): void {
    $this->texture = new Texture($this->renderer, $this->geometry->width, $this->geometry->height, $this->style->get('backgroundColor'));
    $owner = $this->getAncestor();
    if (!$owner instanceof ProgressBar) {
      $this->changed = false;
      return;
    }
    $width = (int)round(max(0, $this->geometry->innerWidth) * $owner->getProgressRatio());
    if ($width > 0 && $this->geometry->innerHeight > 0) {
      $fillStyle = StyleSheet::get($this->style, $this->style, 'ProgressBarFill');
      $this->texture->drawFillRect(
        $this->geometry->borderLeft + $this->geometry->paddingLeft,
        $this->geometry->borderTop + $this->geometry->paddingTop,
        $this->geometry->borderLeft + $this->geometry->paddingLeft + $width,
        $this->geometry->borderTop + $this->geometry->paddingTop + $this->geometry->innerHeight,
        $fillStyle->get('backgroundColor')
      );
    }
    $this->changed = false;
  }

}
