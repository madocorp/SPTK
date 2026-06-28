<?php

namespace Examples\Tests;

use SPTK\Elements\Table;
use SPTK\SDLWrapper\KeyCode;
use SPTK\SDLWrapper\KeyCombo;
use SPTK\SDLWrapper\KeyModifier;
use SPTK\SDLWrapper\ScanCode;

class HeadlessTable extends Table {

  public function recalculateGeometry(): void {
    $this->measure();
    $this->calculateWidths();
    $this->calculateHeights();
    $this->layout();
  }

  public function keyPressHandler($element, $event): bool {
    $result = parent::keyPressHandler($element, $event);
    if ($result) {
      $this->recalculateGeometry();
    }
    return $result;
  }

}

return [
  'table scans TSV header rows nulls and chunk boundaries' => function (): void {
    $root = root();
    $rows = [
      "id\tname\tnote",
      "1\tAlice\thello\\tthere",
      "2\tBob\t\\N",
      "3\tCara\tline\\nbreak",
      "4\tDora\tplain",
      "5\tEve\tplain",
      "6\tFrank\tplain",
      "7\tGrace\tplain",
      "8\tHeidi\tplain",
      "9\tIvan\tplain",
      "10\tJudy\tplain",
      "11\tMallory\tplain",
      "12\tOscar\t" . str_repeat('late', 100),
    ];
    for ($i = 13; $i <= 1000; $i++) {
      $rows[] = "{$i}\tName {$i}\tplain";
    }
    $file = tempFile(implode("\n", $rows) . "\n", 'tsv');

    $table = new HeadlessTable($root, 'people', null, 'Table');
    $table->setChunkSize(5);
    $table->setFile($file);

    assertSame(['id', 'name', 'note'], $table->getHeader(), 'first TSV row is stored as the header');
    assertSame(1001, $table->getLineCount(), 'line count includes the header');
    assertSame(1000, $table->getRowCount(), 'row count excludes the header');
    assertSame(0, $table->getChunkStart(), 'initial chunk starts at the first data row');
    assertSame(["1", "Alice", "hello\tthere"], $table->getChunk()[0], 'backslash tab escape is decoded');
    assertSame(["2", "Bob", null], $table->getChunk()[1], '\N is decoded as null');
    $widths = $table->getColumnWidths();

    $table->scrollToRow(500);
    $table->recalculateGeometry();

    assertTrue($table->getChunkStart() <= 500, 'scrolling outside the current chunk reloads before the new viewport');
    $chunkIndex = 500 - $table->getChunkStart();
    assertSame(["501", "Name 501", "plain"], $table->getChunk()[$chunkIndex], 'reloaded chunk includes the requested viewport row');
    assertSame($widths, $table->getColumnWidths(), 'column widths stay based on the first loaded chunk and header');

    $children = $table->getDescendants();
    $headerBox = $children[0];
    $contentBox = $children[1];
    $header = $headerBox->nthChild(0);
    $geometry = $table->getGeometry();
    assertSame('TableHeader', $headerBox->getType(), 'table contains a fixed header box');
    assertSame('TableContent', $contentBox->getType(), 'table contains a scrollable content box');
    assertSame('TableHeaderRow', $header->getType(), 'fixed header box contains the header row');
    assertSame(
      $geometry->paddingTop + $geometry->borderTop,
      $headerBox->getGeometry()->y,
      'fixed header stays at the top of the table'
    );
    assertSame(
      $header->nthChild(0)->getGeometry()->x,
      $contentBox->nthChild(0)->nthChild(0)->getGeometry()->x,
      'header and body cells start at the same x position'
    );
    assertSame(
      $header->getGeometry()->height,
      $contentBox->nthChild(0)->getGeometry()->height,
      'header and body rows use the same padded row height'
    );
  },

  'table caps wide columns to one third of the box when content overflows' => function (): void {
    $root = root();
    $long = str_repeat('x', 1000);
    $file = tempFile("a\tb\tc\n{$long}\tshort\tshort\n", 'tsv');

    $table = new HeadlessTable($root, 'wide', null, 'Table');
    $table->setFile($file);
    $table->recalculateGeometry();

    $widths = $table->getColumnWidths();
    $max = (int)floor($table->getGeometry()->innerWidth * 0.33);

    assertTrue($widths[0] <= $max, 'wide column is capped to at most 33% of table inner width');
    assertTrue(array_sum($widths) > 0, 'table keeps measured column widths');
  },

  'table moves a cell cursor and scrolls it into view' => function (): void {
    KeyCombo::init();
    $root = root();
    $rows = ["id\tname\tstatus"];
    for ($i = 1; $i <= 1000; $i++) {
      $rows[] = "{$i}\tName {$i}\tactive";
    }
    $file = tempFile(implode("\n", $rows) . "\n", 'tsv');

    $table = new HeadlessTable($root, 'cursor', null, 'Table');
    $table->setFile($file);
    $table->recalculateGeometry();

    assertSame([0, 0], $table->getCursor(), 'cursor starts at the first data cell');
    $content = $table->getDescendants()[1];
    $firstRow = $content->nthChild(0);
    assertTrue($firstRow->nthChild(0)->hasClass('TableCell:cursor'), 'current cell gets cursor variant');

    $table->keyPressHandler($table, ['mod' => KeyModifier::NONE, 'scancode' => ScanCode::RIGHT, 'key' => KeyCode::RIGHT]);
    $table->keyPressHandler($table, ['mod' => KeyModifier::NONE, 'scancode' => ScanCode::DOWN, 'key' => KeyCode::DOWN]);
    assertSame([1, 1], $table->getCursor(), 'arrow keys move by one cell');

    $table->keyPressHandler($table, ['mod' => KeyModifier::NONE, 'scancode' => ScanCode::END, 'key' => KeyCode::END]);
    assertSame([1, 2], $table->getCursor(), 'end moves to the last cell in the current row');

    $table->keyPressHandler($table, ['mod' => KeyModifier::NONE, 'scancode' => ScanCode::HOME, 'key' => KeyCode::HOME]);
    assertSame([1, 0], $table->getCursor(), 'home moves to the first cell in the current row');

    $table->keyPressHandler($table, ['mod' => KeyModifier::CTRL, 'scancode' => ScanCode::END, 'key' => KeyCode::END]);
    assertSame([999, 2], $table->getCursor(), 'ctrl end moves to the last data cell');
    assertTrue(propertyValue($content, 'scrollY') > 0, 'content box scrolls vertically when cursor leaves the viewport');

    $table->keyPressHandler($table, ['mod' => KeyModifier::NONE, 'scancode' => ScanCode::PAGEUP, 'key' => KeyCode::PAGEUP]);
    assertTrue($table->getCursor()[0] < 999, 'page up moves the cursor by a screen of rows');
  },
];
