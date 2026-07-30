<?php

namespace Examples\Tests;

use SPTK\Elements\TextBox;

class HeadlessTextBox extends TextBox {

  public function setBox(int $columns, int $height, int $rowHeight): void {
    $this->letterWidth = 1;
    $this->letterHeight = $rowHeight;
    $this->lineHeight = $rowHeight;
    $this->lineOffset = 0;
    $this->geometry->width = $columns;
    $this->geometry->height = $height;
    $this->geometry->innerWidth = $columns;
    $this->geometry->innerHeight = $height;
  }

  public function cellRows(): array {
    return $this->cells;
  }

  public function setCursorForTest(int $row, int $col): void {
    $this->cursor->set([$row, $col, $row, $col]);
    $this->cursor->save();
    $this->refreshCells(false);
  }

  public function scrollY(): int {
    return $this->scrollY;
  }

}

function textBoxRows(HeadlessTextBox $box): array {
  return array_map(
    fn($row) => rtrim(implode('', array_map(fn($cell) => $cell['glyph'], $row))),
    $box->cellRows()
  );
}

return [
  'text editor keeps cursor inside full visible rows' => function (): void {
    $box = new HeadlessTextBox(root(), 'box', null, 'TextBox');
    $box->setBox(8, 25, 10);
    $box->setValue("zero\none\ntwo\nthree\nfour");

    $box->setCursorForTest(2, 0);

    assertSame(10, $box->scrollY(), 'cursor on row 2 scrolls to a full-row boundary');
    assertSame(['one', 'two', 'three'], textBoxRows($box), 'partial bottom row is rendered as a continuation hint');

    $box->setCursorForTest(4, 0);

    assertSame(30, $box->scrollY(), 'last cursor row remains fully visible');
    assertSame(['three', 'four'], textBoxRows($box), 'bottom viewport does not render beyond the document');
  },
];
