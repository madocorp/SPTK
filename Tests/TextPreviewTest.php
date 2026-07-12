<?php

namespace Examples\Tests;

use SPTK\Elements\TextPreview;

class HeadlessTextPreview extends TextPreview {

  public function setBox(int $columns, int $rows): void {
    $this->letterWidth = 1;
    $this->lineHeight = 1;
    $this->geometry->width = $columns;
    $this->geometry->height = $rows;
    $this->geometry->innerWidth = $columns;
    $this->geometry->innerHeight = $rows;
  }

  public function recalculateGeometry(): void {
    $this->layout();
  }

  public function cellRows(): array {
    return $this->cells;
  }

}

function previewTextRows(HeadlessTextPreview $preview): array {
  return array_map(
    fn($row) => rtrim(implode('', array_map(fn($cell) => $cell['glyph'], $row))),
    $preview->cellRows()
  );
}

return [
  'text preview hard wraps long lines with a marker' => function (): void {
    $preview = new HeadlessTextPreview(root(), 'preview', null, 'TextPreview');
    $preview->setBox(5, 4);
    $preview->setValue('abcdefghi');
    $preview->recalculateGeometry();

    assertSame(['abcd~', 'efghi'], previewTextRows($preview), 'long lines hard wrap and mark wrapped rows');
    assertSame([255, 255, 0, 255], $preview->cellRows()[0][4]['fg'], 'wrap marker uses yellow marker color');
  },

  'text preview preserves explicit line breaks while wrapping independently' => function (): void {
    $preview = new HeadlessTextPreview(root(), 'preview', null, 'TextPreview');
    $preview->setBox(4, 5);
    $preview->setValue("abc\ndefghi");
    $preview->recalculateGeometry();

    assertSame(['abc', 'def~', 'ghi'], previewTextRows($preview), 'newlines split rows and later lines still wrap');
  },

  'text preview replaces bottom row with continuation marker on overflow' => function (): void {
    $preview = new HeadlessTextPreview(root(), 'preview', null, 'TextPreview');
    $preview->setBox(6, 2);
    $preview->setValue("first\nsecond\nthird");
    $preview->recalculateGeometry();

    assertSame(['first', 'vvv'], previewTextRows($preview), 'overflow uses a bottom continuation row');
    assertSame([255, 255, 0, 255], $preview->cellRows()[1][0]['fg'], 'continuation marker uses yellow marker color');
    assertSame([255, 255, 0, 255], $preview->cellRows()[1][2]['fg'], 'whole continuation marker uses yellow marker color');
  },

  'text preview is display only' => function (): void {
    $preview = new HeadlessTextPreview(root(), 'preview', null, 'TextPreview');

    assertFalse(propertyValue($preview, 'acceptInput'), 'text preview does not accept input focus');
    assertSame(0, propertyValue($preview, 'scrollX'), 'text preview starts without horizontal scroll');
    assertSame(0, propertyValue($preview, 'scrollY'), 'text preview starts without vertical scroll');
  },
];
