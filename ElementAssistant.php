<?php

namespace SPTK;

trait ElementAssistant {

  protected function init(): void {
    ;
  }

  public function postInit(): void {
    ;
  }

  public function getId(): int {
    return $this->id;
  }

  public function getName(): string {
    return $this->name;
  }

  public function getType(): string {
    return $this->type;
  }

  public function getClass(): array {
    return $this->sclass;
  }

  public function getGeometry(): Geometry {
    return $this->geometry;
  }

  public function getStyle(): Style {
    return $this->style;
  }

  public function getDescendants(): array {
    return $this->descendants;
  }

  public function getAncestor(): ?Element {
    return $this->ancestor;
  }

  public function getAttributeList(): array {
    return [];
  }

  public function setValue(mixed $value): void {
    $this->value = $value;
  }

  public function getValue(): mixed {
    return $this->value;
  }

  public function show(): void {
    $this->display = true;
  }

  public function hide(): void {
    $this->display = false;
  }

  public function isDisplayed(): bool {
    return $this->display;
  }

  public function scrollToLeft(): void {
    $this->scrollX = 0;
  }

  public function scrollToRight(): void {
    if ($this->geometry->contentWidth > $this->geometry->innerWidth) {
      $this->scrollX = $this->geometry->contentWidth - $this->geometry->innerWidth;
    } else {
      $this->scrollX = 0;
    }
  }

  public function scrollToTop(): void {
    $this->scrollY = 0;
  }

  public function scrollToBottom(): void {
    $this->scrollY = $this->maxScrollY();
  }

  public function scrollBy(int $x, int $y): bool {
    return $this->scrollTo($this->scrollX + $x, $this->scrollY + $y);
  }

  public function scrollTo(int $x, int $y): bool {
    $scrollX = max(0, min($x, $this->maxScrollX()));
    $scrollY = max(0, min($y, $this->maxScrollY()));
    if ($scrollX === $this->scrollX && $scrollY === $this->scrollY) {
      return false;
    }
    $this->scrollX = $scrollX;
    $this->scrollY = $scrollY;
    return true;
  }

  protected function maxScrollX(): int {
    $contentWidth = $this->geometry->contentWidth - $this->geometry->paddingLeft - $this->geometry->paddingRight;
    return max(0, $contentWidth - $this->geometry->innerWidth);
  }

  protected function maxScrollY(): int {
    $contentHeight = $this->geometry->contentHeight - $this->geometry->paddingBottom;
    return max(0, $contentHeight - $this->geometry->innerHeight);
  }

  public function debug(int $level = 0): void {
    $pad = str_repeat(' ', $level * 4);
    $class = '';
    if (!empty($this->sclass)) {
      $class = '.' . implode('.', $this->sclass);
    }
    $value = '';
    if ($this->value !== false) {
      $value = " [{$this->value}]";
    }
    echo "{$pad}{$this->type}@{$this->id}" . ($this->name !== 0 ? "#{$this->name}" : '') ."{$class}{$value}";
    echo "  {$this->geometry->width}x{$this->geometry->height} {$this->geometry->x}:{$this->geometry->y}\n";
    foreach ($this->events as $event => $handler) {
      echo "{$pad}  - {$event} > " . (is_array($handler) ? (is_object($handler[0]) ? get_class($handler[0]) : $handler[0]) . '::' . $handler[1] : implode('::', $handler)) . "\n";
    }
    foreach ($this->descendants as $element) {
      $element->debug($level + 1);
    }
  }

}
