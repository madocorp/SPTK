<?php

namespace SPTK\Elements;

use \SPTK\Element;

class TableCell extends Element {

  protected int $x = 0;
  protected int $width = 0;
  protected int $height = 0;
  protected int $letterWidth = 8;
  protected mixed $cellValue = null;
  protected string $displayKey = '';

  public function setCell(int $x, int $width, int $height, mixed $value): void {
    $this->setCellGeometry($x, $width, $height);
    $this->setCellValue($value);
  }

  public function setCellGeometry(int $x, int $width, int $height): void {
    $this->x = $x;
    $this->width = $width;
    $this->height = $height;
  }

  public function setCellTextMetrics(int $letterWidth): void {
    $letterWidth = max(1, $letterWidth);
    if ($this->letterWidth !== $letterWidth) {
      $this->letterWidth = $letterWidth;
      $this->displayKey = '';
    }
  }

  public function setCellValue(mixed $value): void {
    $key = serialize([$value, $this->width, $this->letterWidth]);
    if ($this->cellValue === $value && $this->displayKey === $key && isset($this->descendants[0])) {
      return;
    }
    $this->cellValue = $value;
    $this->displayKey = $key;
    $this->clear();
    foreach ($this->displaySegments($value) as $segment) {
      $word = new Word($this);
      if ($segment['variant'] !== null) {
        $word->addVariant($segment['variant']);
      }
      $word->setValue($segment['text']);
    }
  }

  public function getCellWidth(): int {
    return $this->width;
  }

  public function getValue(): mixed {
    return $this->cellValue;
  }

  protected function displaySegments(mixed $value): array {
    if ($value === null) {
      return [['text' => 'NULL', 'variant' => 'tableNull']];
    }

    $text = (string)$value;
    $multiline = str_contains($text, "\n") || str_contains($text, "\r");
    if ($multiline) {
      $parts = preg_split("/\r\n|\r|\n/", $text, 2);
      $text = $parts[0] ?? '';
    }

    $markerLength = $multiline ? 1 : 0;
    $maxChars = $this->maxVisibleCharacters();
    $truncated = mb_strlen($text) + $markerLength > $maxChars;
    if ($truncated) {
      $markerLength += 1;
      $text = mb_substr($text, 0, max(0, $maxChars - $markerLength));
    }

    $segments = [];
    if ($text !== '') {
      $segments[] = ['text' => $text, 'variant' => null];
    }
    if ($truncated) {
      $segments[] = ['text' => '…', 'variant' => 'tableMarker'];
    }
    if ($multiline) {
      $segments[] = ['text' => '↵', 'variant' => 'tableMarker'];
    }
    if (empty($segments)) {
      $segments[] = ['text' => '', 'variant' => null];
    }
    return $segments;
  }

  protected function maxVisibleCharacters(): int {
    $chrome =
      $this->style->get('paddingLeft', $this->geometry) +
      $this->style->get('paddingRight', $this->geometry) +
      $this->style->get('borderLeft', $this->geometry) +
      $this->style->get('borderRight', $this->geometry);
    return max(0, (int)floor(max(0, $this->width - $chrome) / $this->letterWidth));
  }

  protected function measure(): void {
    $this->geometry->setValues($this->ancestor->geometry, $this->style);
    $this->geometry->x = $this->x;
    $this->geometry->y = 0;
    $this->geometry->width = $this->width;
    $this->geometry->height = $this->height;
    $this->geometry->setDerivedWidths();
    $this->geometry->setDerivedHeights();
    foreach ($this->descendants as $descendant) {
      $descendant->measure();
    }
  }

}
