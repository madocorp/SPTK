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

  public function visibleCells(): array {
    return [
      'header' => $this->visibleCellsForRow(-1, $this->header, $this->geometry->borderTop + $this->geometry->paddingTop),
      'body' => $this->visibleBodyCells()
    ];
  }

  public function headerBand(): array {
    $style = $this->isActive() ? $this->styleFor('TableHeader', ['TableHeader:active']) : $this->styleFor('TableHeader');
    return [
      'x' => $this->geometry->borderLeft + $this->geometry->paddingLeft,
      'y' => $this->geometry->borderTop + $this->geometry->paddingTop,
      'width' => $this->geometry->innerWidth,
      'height' => $this->rowHeight,
      'backgroundColor' => $style->get('backgroundColor')
    ];
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

    $geometry = $table->getGeometry();
    $cells = $table->visibleCells();
    assertSame(0, $table->countDescendants(), 'table uses direct grid rendering instead of row and cell descendants');
    assertSame(
      $geometry->paddingTop + $geometry->borderTop,
      $cells['header'][0]['y'],
      'fixed header stays at the top of the table'
    );
    assertSame(
      $cells['header'][0]['x'],
      $cells['body'][0]['x'],
      'header and body cells start at the same x position'
    );
    assertSame(
      $cells['header'][0]['height'],
      $cells['body'][0]['height'],
      'header and body rows use the same padded row height'
    );
    assertSame([85, 85, 85, 255], $cells['header'][0]['backgroundColor'], 'header cells use TableHeader background color');
    assertSame([255, 255, 255, 255], $cells['header'][0]['color'], 'header cells use TableHeader text color');
    $table->addVariant('active');
    $table->recalculateGeometry();
    $cells = $table->visibleCells();
    assertSame([119, 119, 119, 255], $cells['header'][0]['backgroundColor'], 'active header cells use TableHeader:active background color');
  },

  'table keeps visible cell geometry when columns are narrow' => function (): void {
    $root = root();
    $file = tempFile("id\n1\n", 'tsv');

    $table = new HeadlessTable($root, 'narrow', null, 'Table');
    $table->setFile($file);
    $table->recalculateGeometry();

    $cells = $table->visibleCells();

    assertSame(
      $cells['header'][0]['width'],
      $cells['body'][0]['width'],
      'header and body cells keep matching column widths'
    );
    assertTrue(
      $cells['header'][0]['width'] < $table->getGeometry()->innerWidth,
      'narrow column cell width stays narrower than the table viewport'
    );
    assertSame(
      $table->getGeometry()->innerWidth,
      $table->headerBand()['width'],
      'header background band spans the full table viewport'
    );
  },

  'table caps wide columns to one half of the box when content overflows' => function (): void {
    $root = root();
    $long = str_repeat('x', 1000);
    $file = tempFile("a\tb\tc\n{$long}\tshort\tshort\n", 'tsv');

    $table = new HeadlessTable($root, 'wide', null, 'Table');
    $table->setFile($file);
    $table->recalculateGeometry();

    $widths = $table->getColumnWidths();
    $max = (int)floor($table->getGeometry()->innerWidth * 0.5);

    assertTrue($widths[0] <= $max, 'wide column is capped to at most 50% of table inner width');
    assertTrue(array_sum($widths) > 0, 'table keeps measured column widths');
  },

  'table formats null truncated and multiline cell values' => function (): void {
    $root = root();
    $long = str_repeat('x', 1000);
    $file = tempFile("nullable\tlong\tmulti\n\\N\t{$long}\tline\\nbreak\n", 'tsv');

    $table = new HeadlessTable($root, 'formatting', null, 'Table');
    $table->setFile($file);
    $table->recalculateGeometry();

    $row = array_values(array_filter($table->visibleCells()['body'], fn($cell) => $cell['row'] === 0));
    $nullCell = $row[0];
    $longCell = $row[1];
    $multiCell = $row[2];

    assertSame(null, $nullCell['value'], 'null cell keeps its raw null value');
    assertSame('NULL', $nullCell['segments'][0]['text'], 'null cell displays NULL text');
    assertSame('tableNull', $nullCell['segments'][0]['variant'], 'null display text uses the null variant');

    $longMarker = $longCell['segments'][count($longCell['segments']) - 1];
    assertSame('…', $longMarker['text'], 'wide text is truncated with an ellipsis marker');
    assertSame('tableMarker', $longMarker['variant'], 'ellipsis marker uses the marker variant');

    $multiMarker = $multiCell['segments'][count($multiCell['segments']) - 1];
    assertSame("line\nbreak", $multiCell['value'], 'multiline cell keeps its raw multiline value');
    assertSame('line', $multiCell['segments'][0]['text'], 'multiline cell displays the first line');
    assertSame('>', $multiMarker['text'], 'multiline cell ends with a continuation marker');
    assertSame('tableMarker', $multiMarker['variant'], 'return marker uses the marker variant');
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
    $firstCell = $table->visibleCells()['body'][0];
    assertTrue($firstCell['cursor'], 'current cell gets cursor state');
    assertSame([85, 85, 85, 255], $firstCell['backgroundColor'], 'inactive current cell uses inactive cursor background color');
    $table->addVariant('active');
    $table->recalculateGeometry();
    $firstCell = $table->visibleCells()['body'][0];
    assertSame([0, 0, 0, 255], $firstCell['backgroundColor'], 'current cell uses cursor background color');
    assertSame([0, 0, 0, 255], $firstCell['borderColor'], 'cursor cell keeps the base table cell border color');

    $table->keyPressHandler($table, ['mod' => KeyModifier::NONE, 'scancode' => ScanCode::RIGHT, 'key' => KeyCode::RIGHT]);
    $table->keyPressHandler($table, ['mod' => KeyModifier::NONE, 'scancode' => ScanCode::DOWN, 'key' => KeyCode::DOWN]);
    assertSame([1, 1], $table->getCursor(), 'arrow keys move by one cell');

    $table->keyPressHandler($table, ['mod' => KeyModifier::NONE, 'scancode' => ScanCode::END, 'key' => KeyCode::END]);
    assertSame([1, 2], $table->getCursor(), 'end moves to the last cell in the current row');

    $table->keyPressHandler($table, ['mod' => KeyModifier::NONE, 'scancode' => ScanCode::HOME, 'key' => KeyCode::HOME]);
    assertSame([1, 0], $table->getCursor(), 'home moves to the first cell in the current row');

    $table->keyPressHandler($table, ['mod' => KeyModifier::CTRL, 'scancode' => ScanCode::END, 'key' => KeyCode::END]);
    assertSame([999, 2], $table->getCursor(), 'ctrl end moves to the last data cell');
    assertTrue(propertyValue($table, 'scrollY') > 0, 'table scrolls vertically when cursor leaves the viewport');

    $table->keyPressHandler($table, ['mod' => KeyModifier::NONE, 'scancode' => ScanCode::PAGEUP, 'key' => KeyCode::PAGEUP]);
    assertTrue($table->getCursor()[0] < 999, 'page up moves the cursor by a screen of rows');
  },

  'table supports text editor movement keys and cell range selection' => function (): void {
    KeyCombo::init();
    $root = root();
    $file = tempFile("id\tname\tstatus\n1\tAlice\tactive\n2\tBob\tidle\n3\tCara\tactive\n", 'tsv');

    $table = new HeadlessTable($root, 'selection', null, 'Table');
    $table->setFile($file);
    $table->recalculateGeometry();

    $table->keyPressHandler($table, ['mod' => KeyModifier::SHIFT, 'scancode' => ScanCode::RIGHT, 'key' => KeyCode::RIGHT]);
    $table->keyPressHandler($table, ['mod' => KeyModifier::SHIFT, 'scancode' => ScanCode::DOWN, 'key' => KeyCode::DOWN]);

    assertSame([1, 1], $table->getCursor(), 'shift movement moves the cursor');
    assertSame([0, 0, 1, 1], $table->getSelection(), 'shift movement extends a rectangular cell range');

    $cells = $table->visibleCells()['body'];
    $byPosition = [];
    foreach ($cells as $cell) {
      $byPosition[$cell['row'] . ':' . $cell['column']] = $cell;
    }
    assertTrue($byPosition['0:0']['selected'], 'selection marks the first selected cell');
    assertSame([85, 85, 85, 255], $byPosition['0:0']['backgroundColor'], 'inactive selected cells use inactive selection background color');
    $table->addVariant('active');
    $table->recalculateGeometry();
    $cells = $table->visibleCells()['body'];
    $byPosition = [];
    foreach ($cells as $cell) {
      $byPosition[$cell['row'] . ':' . $cell['column']] = $cell;
    }
    assertSame([0, 0, 0, 255], $byPosition['0:0']['backgroundColor'], 'selected cells use selected background color');
    assertSame([0, 0, 0, 255], $byPosition['0:0']['borderColor'], 'selected cells keep the base table cell border color');
    assertTrue($byPosition['0:1']['selected'], 'selection marks selected cells across columns');
    assertTrue($byPosition['1:0']['selected'], 'selection marks selected cells across rows');
    assertTrue($byPosition['1:1']['cursor'], 'cursor remains on the active selected cell');
    assertFalse($byPosition['1:2']['selected'], 'selection excludes cells outside the range');

    $table->keyPressHandler($table, ['mod' => KeyModifier::NONE, 'scancode' => ScanCode::RIGHT, 'key' => KeyCode::RIGHT]);
    assertSame([1, 2, 1, 2], $table->getSelection(), 'plain movement resets selection to the cursor cell');

    $table->keyPressHandler($table, ['mod' => KeyModifier::CTRL, 'scancode' => ScanCode::PAGEUP, 'key' => KeyCode::PAGEUP]);
    assertSame([0, 0], $table->getCursor(), 'ctrl page up moves to the first table cell');

    $table->keyPressHandler($table, ['mod' => KeyModifier::CTRL, 'scancode' => ScanCode::PAGEDOWN, 'key' => KeyCode::PAGEDOWN]);
    assertSame([2, 2], $table->getCursor(), 'ctrl page down moves to the last table cell');
  },
];
