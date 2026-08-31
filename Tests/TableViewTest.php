<?php

namespace SPTK\Tests;

use SPTK\Core\Clipboard;
use SPTK\Core\Color;
use SPTK\Core\GridBuffer;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Core\SurfaceRenderTarget;
use SPTK\Core\Theme;
use SPTK\Widgets\TableView;

class TableSurfaceTarget extends GridBuffer implements SurfaceRenderTarget {
  public array $lines = [];

  public function __construct(int $width, int $height, protected int $cellWidth = 10, protected int $cellHeight = 20) {
    parent::__construct($width, $height);
  }

  public function drawLine(float $x1, float $y1, float $x2, float $y2, string|int|Color $color, int $thickness = 1): void {
    $this->lines[] = [$x1, $y1, $x2, $y2, Color::from($color)->hex(), $thickness];
  }

  public function columnsForWidth(int $pixelWidth): int {
    return max(1, intdiv($pixelWidth, $this->cellWidth));
  }

  public function rowsForHeight(int $pixelHeight): int {
    return max(1, intdiv($pixelHeight, $this->cellHeight));
  }

  public function cellWidth(): int {
    return $this->cellWidth;
  }

  public function cellHeight(): int {
    return $this->cellHeight;
  }

  public function currentSurfacePixelRect(): Rect {
    return new Rect(0, 0, $this->width() * $this->cellWidth, $this->height() * $this->cellHeight);
  }

  public function pushSurface(Rect $pixelRect, int $columns, int $rows, Color|string|int|null $background = null, string $gridAlignment = 'center'): void {
  }

  public function popSurface(): void {
  }

  public function fillPixels(Rect $pixelRect, Color|string|int $color): void {
  }

  public function drawPixelLine(float $x1, float $y1, float $x2, float $y2, Color|string|int $color, int $thickness = 1): void {
  }
}

return [
  'table view loads TSV headers rows nulls escapes and chunks' => function(): void {
    $path = tempnam(sys_get_temp_dir(), 'sptk-table-');
    file_put_contents($path, implode("\n", [
      "id\tname\tnote",
      "1\tAlice\thello\\tthere",
      "2\tBob\t\\N",
      "3\tCara\tline\\nbreak",
      "4\tDora\tplain",
    ]) . "\n");

    $table = new TableView('table');
    $table->setTsvFile($path, ['chunkSize' => 2]);

    assertSame(['id', 'name', 'note'], $table->header(), 'First TSV row should become the header.');
    assertSame(5, $table->lineCount(), 'Line count should include the header.');
    assertSame(4, $table->rowCount(), 'Row count should exclude the header.');
    assertSame(["1", "Alice", "hello\tthere"], $table->chunk()[0], 'Escaped tab should be decoded.');
    assertSame(["2", "Bob", null], $table->chunk()[1], '\N should be decoded as null.');

    $table->setCursor(3, 1);
    assertSame(2, $table->chunkStart(), 'Moving outside the current chunk should load the requested chunk.');
    assertSame(["4", "Dora", "plain"], $table->activeRowValues(), 'Chunk reload should preserve active row access.');

    unlink($path);
  },

  'table view supports row numbers and horizontal cursor scrolling' => function(): void {
    $wide = str_repeat('x', 40);
    $table = new TableView('table', ['id', 'c1', 'c2', 'c3'], [[1, $wide, $wide, $wide]]);
    $table->setOptions(['rowNumbers' => true]);
    $table->setFrame(new Rect(0, 0, 16, 3));

    $buffer = new GridBuffer(16, 3);
    $table->render($buffer);
    assertSame('# ', mb_substr($buffer->line(0), 0, 2), 'Row number header should render before data columns with one trailing space.');

    $table->handle(InputEvent::key('Right'));
    $table->handle(InputEvent::key('Right'));
    $table->handle(InputEvent::key('Right'));
    assertSame(3, $table->cursorColumn(), 'Right movement should reach later data columns.');

    $table->handle(InputEvent::key('Home'));
    assertSame(0, $table->cursorColumn(), 'Home should return to the first data column on the current row.');
    $buffer = new GridBuffer(16, 3);
    $table->render($buffer);
    assertSame('# ', mb_substr($buffer->line(0), 0, 2), 'Returning to the line start should expose row numbers with one trailing space.');
  },

  'table view handles key aliases through input actions' => function(): void {
    $table = new TableView('table', ['id', 'name'], [
      [1, 'Ada'],
      [2, 'Grace'],
    ]);

    $table->handle(InputEvent::key('KeypadRight'));
    $table->handle(InputEvent::key('KeypadDown'));
    assertSame([1, 1], [$table->cursorRow(), $table->cursorColumn()], 'Keypad aliases should move the table cursor.');
  },

  'table view uses field background for body cells' => function(): void {
    $table = new TableView('table', ['id', 'name'], [
      [1, 'Ada'],
      [2, 'Grace'],
    ], [4, 8]);
    $table->setTheme(new Theme(bg: '#338833'));
    $table->setFrame(new Rect(0, 0, 14, 4));
    $buffer = new GridBuffer(14, 4);
    $table->render($buffer);

    assertSame('#aaaaaa', $buffer->cell(0, 0)?->bg->hex(), 'Table header should keep the header background.');
    assertSame('#555555', $buffer->cell(0, 1)?->bg->hex(), 'Active cursor cell should keep the inactive cursor background.');
    assertSame('#338833', $buffer->cell(0, 2)?->bg->hex(), 'Normal table body cells should use the table field background.');
    assertSame('#338833', $buffer->cell(0, 3)?->bg->hex(), 'Empty table body space should use the table field background.');

    $table->setFocused(true);
    $buffer = new GridBuffer(14, 4);
    $table->render($buffer);

    assertSame('#3da33d', $buffer->cell(0, 2)?->bg->hex(), 'Focused table body cells should brighten the table field background.');
    assertSame('#3da33d', $buffer->cell(0, 3)?->bg->hex(), 'Focused empty table body space should brighten the table field background.');
  },

  'table view aligns columns by index and header name' => function(): void {
    $table = new TableView('table', ['id', 'name', 'score'], [
      [7, 'Ada', 42],
      [12, 'Grace', 100],
    ], [5, 10, 8]);
    $table->setColumnAlignments([
      0 => 'right',
      'name' => 'center',
      'score' => 'right',
    ]);
    $table->setFrame(new Rect(0, 0, 23, 3));
    $buffer = new GridBuffer(23, 3);
    $table->render($buffer);

    assertSame([0 => 'right', 1 => 'center', 2 => 'right'], $table->columnAlignments(), 'Column alignments should normalize index and header-name keys.');
    assertSame('  id   name      score ', $buffer->line(0), 'Header cells should use configured column alignment.');
    assertSame('   7    Ada         42 ', $buffer->line(1), 'Body cells should use configured column alignment.');
    assertSame('  12   Grace       100 ', $buffer->line(2), 'Centered content should split padding around the value.');
  },

  'table view can draw optional pixel column separator lines' => function(): void {
    $table = new TableView('table', ['id', 'name'], [[1, 'Ada']], [4, 8]);
    $table->setFrame(new Rect(0, 0, 12, 2));
    $target = new TableSurfaceTarget(12, 2);
    $table->render($target);

    assertSame('id  name    ', $target->line(0), 'Text fallback should leave separator cells blank.');
    assertSame([[3.5, 0.0, 3.5, 1.0, '#000000', 1], [3.5, 1.0, 3.5, 2.0, '#aaaaaa', 1]], $target->lines, 'Surface targets should receive one-pixel separator lines.');

    $table->setColumnSeparatorLines(false);
    $target = new TableSurfaceTarget(12, 2);
    $table->render($target);

    assertSame([], $target->lines, 'Column separator lines should be optional.');
  },

  'table view supports percentage column widths' => function(): void {
    $table = new TableView('table', ['name', 'status', 'score'], [
      ['Ada', 'active', 42],
    ], ['50%', '25%', '25%']);
    $table->setFrame(new Rect(0, 0, 40, 2));

    assertSame([20, 10, 10], $table->columnWidths(), 'Percentage widths should be calculated from the table width.');

    $table->setRowNumbers(true);
    $table->setFrame(new Rect(0, 0, 42, 2));

    assertSame([20, 10, 10], $table->columnWidths(), 'Percentage widths should use the data area when row numbers are visible.');
  },

  'table view can use a row cursor' => function(): void {
    Clipboard::setProvider(null);
    Clipboard::set('');
    $table = new TableView('table', ['id', 'name', 'role'], [
      [1, 'Ada', 'engineer'],
      [2, 'Grace', 'compiler'],
      [3, 'Linus', 'kernel'],
    ], [4, 8, 10]);
    $table->setOptions(['rowCursor' => true]);
    $table->setFrame(new Rect(0, 0, 22, 4));

    assertSame(true, $table->rowCursor(), 'Row cursor mode should be enabled through table options.');
    assertSame('row', $table->selectionMode(), 'Row cursor mode should select whole rows by default.');
    assertSame([0, 0, 0, 2], $table->selection(), 'Initial row cursor selection should cover the current row.');

    $buffer = new GridBuffer(22, 4);
    $table->render($buffer);
    assertSame('#555555', $buffer->cell(0, 1)?->bg->hex(), 'Unfocused row cursor should highlight the first body cell.');
    assertSame('#555555', $buffer->cell(5, 1)?->bg->hex(), 'Unfocused row cursor should highlight later body cells.');
    assertSame('#555555', $buffer->cell(13, 1)?->bg->hex(), 'Unfocused row cursor should highlight the whole body row.');
    assertSame('#555555', $buffer->cell(21, 1)?->bg->hex(), 'Unfocused row cursor should fill the full visible row.');

    assertSame(false, $table->handle(InputEvent::key('Right')), 'Right should not move a row-level cursor between cells.');
    $table->handle(InputEvent::key('Down'));
    assertSame([1, 0], [$table->cursorRow(), $table->cursorColumn()], 'Down should move the row cursor while keeping the first data column active.');
    assertSame([1, 0, 1, 2], $table->selection(), 'Moving without Shift should select the new active row.');

    $table->handle(InputEvent::key('Down', ['shift' => true]));
    assertSame([1, 0, 2, 2], $table->selection(), 'Shift movement should extend a row range.');
    $table->handle(InputEvent::key('c', ['ctrl' => true]));
    assertSame("2\tGrace\tcompiler\n3\tLinus\tkernel", Clipboard::get(), 'Row cursor copy should include all columns for selected rows.');

    $table->handle(InputEvent::key('Home'));
    assertSame(0, $table->cursorRow(), 'Home should move a row cursor to the first row.');
    $table->handle(InputEvent::key('End'));
    assertSame(2, $table->cursorRow(), 'End should move a row cursor to the last row.');
  },

  'table row cursor keeps row numbers separate' => function(): void {
    $table = new TableView('table', ['id', 'name'], [
      [1, 'Ada'],
      [2, 'Grace'],
    ], [4, 8]);
    $table->setRowCursor();
    $table->setRowNumbers(true);
    $table->setFrame(new Rect(0, 0, 16, 3));
    $buffer = new GridBuffer(16, 3);
    $table->render($buffer);

    assertSame('#555555', $buffer->cell(0, 1)?->bg->hex(), 'Row cursor should highlight the row number cell as part of the full line.');
    assertSame('#555555', $buffer->cell(2, 1)?->bg->hex(), 'Row cursor should highlight data cells after the row number column.');
  },

  'table view supports page movement and rectangular selection' => function(): void {
    $table = new TableView('table', ['id', 'name', 'status'], [
      [1, 'Alice', 'active'],
      [2, 'Bob', 'idle'],
      [3, 'Cara', 'active'],
      [4, 'Dora', 'idle'],
    ]);
    $table->setFrame(new Rect(0, 0, 24, 3));

    $table->handle(InputEvent::key('PageDown'));
    assertSame(2, $table->cursorRow(), 'PageDown should move by the visible body row count.');
    assertSame(0, $table->cursorColumn(), 'PageDown should keep the horizontal position.');
    $table->setCursor(1, 1);
    $table->handle(InputEvent::key('PageDown', ['ctrl' => true]));
    assertSame([3, 1], [$table->cursorRow(), $table->cursorColumn()], 'Ctrl+PageDown should move to the last row without changing columns.');
    $table->handle(InputEvent::key('PageUp', ['ctrl' => true]));
    assertSame([0, 1], [$table->cursorRow(), $table->cursorColumn()], 'Ctrl+PageUp should move to the first row without changing columns.');

    $table->setCursor(2, 1);
    $table->handle(InputEvent::key('End'));
    assertSame([2, 2], [$table->cursorRow(), $table->cursorColumn()], 'End should move to the end of the current row.');
    $table->handle(InputEvent::key('Home'));
    assertSame([2, 0], [$table->cursorRow(), $table->cursorColumn()], 'Home should move to the start of the current row.');

    $table->setCursor(0, 0);
    $table->handle(InputEvent::key('Right', ['shift' => true]));
    $table->handle(InputEvent::key('Down', ['shift' => true]));
    assertSame([0, 0, 1, 1], $table->selection(), 'Shift movement should extend a rectangular selection.');

    $buffer = new GridBuffer(24, 3);
    $table->render($buffer);
    assertSame('#aaaaaa', $buffer->cell(0, 1)?->fg->hex(), 'Selected cells should use the table foreground when unfocused.');
    assertSame('#555555', $buffer->cell(0, 1)?->bg->hex(), 'Selected cells should use inactive cursor background when unfocused.');
    assertSame('#555555', $buffer->cell(6, 2)?->bg->hex(), 'Selection should extend across selected rows and columns.');
  },

  'table view searches columns wraps and highlights matches' => function(): void {
    $table = new TableView('table', ['id', 'name', 'status'], [
      [1, 'Alice', 'active'],
      [2, 'Bob', 'idle'],
      [3, 'ALAN', 'active'],
    ]);
    $table->setFrame(new Rect(0, 0, 30, 4));

    $match = $table->search('al', ['columns' => ['name']]);
    assertSame(['row' => 0, 'column' => 1, 'value' => 'Alice', 'header' => 'name'], $match, 'Named column search should find the first case-insensitive match.');
    $match = $table->search('^AL', ['regexp' => true, 'caseSensitive' => true, 'columns' => [1]]);
    assertSame([2, 1], [$table->cursorRow(), $table->cursorColumn()], 'Regex case-sensitive search should respect column indexes.');
    assertSame('ALAN', $match['value'], 'Regex search should return the matched value.');
    assertSame(false, $table->search('[', ['regexp' => true]), 'Invalid regex should return false.');

    $table->setCursor(0, 0);
    $table->search('active');
    assertSame(2, $table->nextMatch()['row'], 'Next match should advance to the next matching row.');
    assertSame(0, $table->nextMatch()['row'], 'Next match should wrap to the first matching row.');
    assertSame(2, $table->previousMatch()['row'], 'Previous match should wrap backwards.');

    $buffer = new GridBuffer(30, 4);
    $table->render($buffer);
    assertSame('#000000', $buffer->cell(15, 1)?->fg->hex(), 'Visible non-active search matches should use black foreground.');
    assertSame('#aaaa00', $buffer->cell(15, 1)?->bg->hex(), 'Visible non-active search matches should use darker yellow background.');
    assertSame('#aaaaaa', $buffer->cell(15, 3)?->fg->hex(), 'Cursor styling should use the table foreground when unfocused.');
    assertSame('#555555', $buffer->cell(15, 3)?->bg->hex(), 'Cursor styling should override active search background when unfocused.');

    $table->setCursor(0, 0);
    $buffer = new GridBuffer(30, 4);
    $table->render($buffer);
    assertSame('#000000', $buffer->cell(15, 3)?->fg->hex(), 'Active search match should use black foreground away from the cursor.');
    assertSame('#ffff00', $buffer->cell(15, 3)?->bg->hex(), 'Active search match should use yellow background away from the cursor.');
  },

  'table view formats null truncated multiline cells and copies selection' => function(): void {
    $table = new TableView('table', ['nullable', 'long', 'multi'], [
      [null, str_repeat('x', 20), "line\nbreak"],
      ['ok', 'short', "tab\tvalue"],
    ], [9, 8, 8]);
    $table->setFrame(new Rect(0, 0, 25, 3));

    $buffer = new GridBuffer(25, 3);
    $table->render($buffer);
    assertSame('NULL', mb_substr($buffer->line(1), 0, 4), 'Null cells should display NULL.');
    assertSame('~', $buffer->cell(15, 1)?->glyph, 'Long cells should end with a truncation marker.');
    assertSame('v', $buffer->cell(21, 1)?->glyph, 'Multiline cells should end with a continuation marker.');
    assertSame('#00ffff', $buffer->cell(0, 1)?->fg->hex(), 'Null display text should render with the marker color.');
    assertSame('#00ffff', $buffer->cell(15, 1)?->fg->hex(), 'Truncation marker should render with the marker color.');
    assertSame('#00ffff', $buffer->cell(21, 1)?->fg->hex(), 'Multiline marker should render with the marker color.');

    $table->handle(InputEvent::key('Right', ['shift' => true]));
    $table->handle(InputEvent::key('Down', ['shift' => true]));
    $table->handle(InputEvent::key('c', ['ctrl' => true]));
    assertSame("NULL\txxxxxxxxxxxxxxxxxxxx\nok\tshort", Clipboard::get(), 'Range copy should include selected data cells only.');

    $table->clearSelection();
    $table->setCursor(1, 2);
    $table->handle(InputEvent::key('c', ['ctrl' => true]));
    assertSame('tab\\tvalue', Clipboard::get(), 'Single-cell copy should escape tab characters.');
  },

  'table view selects rows and columns through public api and keyboard shortcuts' => function(): void {
    $table = new TableView('table', ['id', 'name', 'status'], [
      [1, 'Alice', 'active'],
      [2, 'Bob', 'idle'],
      [3, 'Cara', 'active'],
    ], [4, 8, 8]);
    $table->setFrame(new Rect(0, 0, 20, 4));

    $table->selectRow(1);
    assertSame('row', $table->selectionMode(), 'selectRow should switch to row selection mode.');
    assertSame([1, 0, 1, 2], $table->selection(), 'Row selection should expand across all data columns.');
    assertSame([1], $table->selectedRows(), 'selectedRows should report the selected row indexes.');
    assertSame([0, 1, 2], $table->selectedColumns(), 'Row selection should report every data column.');

    $buffer = new GridBuffer(20, 4);
    $table->render($buffer);
    assertSame('#555555', $buffer->cell(0, 2)?->bg->hex(), 'Selected rows should highlight the first visible cell.');
    assertSame('#555555', $buffer->cell(12, 2)?->bg->hex(), 'Selected rows should highlight later visible cells.');

    $table->handle(InputEvent::key('c', ['ctrl' => true]));
    assertSame("2\tBob\tidle", Clipboard::get(), 'Row copy should include the selected data row only.');

    $table->selectColumn(1);
    assertSame('column', $table->selectionMode(), 'selectColumn should switch to column selection mode.');
    assertSame([0, 1, 2, 1], $table->selection(), 'Column selection should expand across all rows.');
    assertSame([0, 1, 2], $table->selectedRows(), 'Column selection should report every data row.');
    assertSame([1], $table->selectedColumns(), 'selectedColumns should report the selected column indexes.');

    $buffer = new GridBuffer(20, 4);
    $table->render($buffer);
    assertSame('#aaaaaa', $buffer->cell(4, 0)?->bg->hex(), 'Selected columns should not highlight the header cell.');
    assertSame('#555555', $buffer->cell(4, 1)?->bg->hex(), 'Selected columns should highlight body cells.');
    assertSame('#0000aa', $buffer->cell(0, 1)?->bg->hex(), 'Unselected cells outside the column should keep the table background.');

    $table->handle(InputEvent::key('c', ['ctrl' => true]));
    assertSame("Alice\nBob\nCara", Clipboard::get(), 'Column copy should include selected data values only.');

    $table->setCursor(2, 2);
    $table->handle(InputEvent::key('Space', ['shift' => true]));
    assertSame('row', $table->selectionMode(), 'Shift+Space should select the current row with SDL-style modifiers.');
    assertSame([2, 0, 2, 2], $table->selection(), 'Shift+Space row selection should expand across columns.');

    $table->handle(InputEvent::key('Space', ['ctrl' => true]));
    assertSame('column', $table->selectionMode(), 'Ctrl+Space should select the current column with SDL-style modifiers.');
    assertSame([0, 2, 2, 2], $table->selection(), 'Ctrl+Space column selection should expand across rows.');

    $table->handle(InputEvent::key('a', ['ctrl' => true]));
    assertSame('cell', $table->selectionMode(), 'Ctrl+A should select the whole table as a cell range.');
    assertSame([0, 0, 2, 2], $table->selection(), 'Ctrl+A should select every row and column.');
  },

  'table view ctrl home and end move to horizontal screen edges' => function(): void {
    $table = new TableView('table', ['a', 'b', 'c', 'd', 'e'], [
      ['aa', 'bb', 'cc', 'dd', 'ee'],
      ['ff', 'gg', 'hh', 'ii', 'jj'],
    ], [6, 6, 6, 6, 6]);
    $table->setFrame(new Rect(0, 0, 12, 3));

    $table->setCursor(1, 4);
    $table->handle(InputEvent::key('Home', ['ctrl' => true]));
    assertSame([1, 3], [$table->cursorRow(), $table->cursorColumn()], 'Ctrl+Home should move to the first visible column without changing rows.');
    $table->handle(InputEvent::key('Home', ['ctrl' => true]));
    assertSame([1, 1], [$table->cursorRow(), $table->cursorColumn()], 'Repeated Ctrl+Home should page left horizontally.');
    $table->handle(InputEvent::key('Home', ['ctrl' => true]));
    assertSame([1, 0], [$table->cursorRow(), $table->cursorColumn()], 'Repeated Ctrl+Home should reach the first column.');

    $table->setCursor(1, 2);
    $table->handle(InputEvent::key('Home'));
    assertSame([1, 0], [$table->cursorRow(), $table->cursorColumn()], 'Plain Home should move to the first column in the row.');

    $table->handle(InputEvent::key('End', ['ctrl' => true]));
    assertSame([1, 1], [$table->cursorRow(), $table->cursorColumn()], 'Ctrl+End should move to the last visible column without changing rows.');
    $table->handle(InputEvent::key('End', ['ctrl' => true]));
    assertSame([1, 3], [$table->cursorRow(), $table->cursorColumn()], 'Repeated Ctrl+End should page right horizontally.');
    $table->handle(InputEvent::key('End', ['ctrl' => true]));
    assertSame([1, 4], [$table->cursorRow(), $table->cursorColumn()], 'Repeated Ctrl+End should reach the last column.');

    $table->setCursor(1, 2);
    $table->handle(InputEvent::key('End'));
    assertSame([1, 4], [$table->cursorRow(), $table->cursorColumn()], 'Plain End should move to the last column in the row.');
  },

  'table view does not select row number cells' => function(): void {
    $table = new TableView('table', ['id', 'name'], [[1, 'Alice'], [2, 'Bob']], [4, 8]);
    $table->setRowNumbers(true);
    $table->setFrame(new Rect(0, 0, 15, 3));
    $table->selectRow(1);

    $buffer = new GridBuffer(15, 3);
    $table->render($buffer);
    assertSame('#aaaaaa', $buffer->cell(0, 2)?->bg->hex(), 'Row selection should not highlight the row number cell.');
    assertSame('#555555', $buffer->cell(3, 2)?->bg->hex(), 'Row selection should still highlight data cells.');
  },

  'table view right aligns row numbers without a separator' => function(): void {
    $rows = [];
    for ($i = 1; $i <= 12; $i++) {
      $rows[] = [$i, 'x'];
    }
    $table = new TableView('table', ['id', 'x'], $rows, [4, 2]);
    $table->setRowNumbers(true);
    $table->setFrame(new Rect(0, 0, 9, 13));

    $buffer = new GridBuffer(9, 13);
    $table->render($buffer);
    assertSame(' # id  x ', $buffer->line(0), 'Row number header should right align to the largest row number width.');
    assertSame(' 1 1   x ', $buffer->line(1), 'Single-digit row numbers should be right aligned with one trailing space.');
    assertSame('12 12  x ', $buffer->line(12), 'The widest row number should keep one space before the first data column.');
  },

  'table view scrollbars overlay content without stealing rows or columns' => function(): void {
    $table = new TableView('table', ['value'], [['row1'], ['row2'], ['row3'], ['row4']], [6]);
    $table->setFrame(new Rect(0, 0, 6, 4));

    $buffer = new GridBuffer(6, 4);
    $table->render($buffer);
    assertSame('value ', $buffer->line(0), 'Vertical scrollbar should not reduce the table content width.');
    assertSame('row1  ', $buffer->line(1), 'First visible row should render below the header.');
    assertSame('row2  ', $buffer->line(2), 'Second visible row should render below the header.');
    assertSame('row3  ', $buffer->line(3), 'Horizontal scrollbar should not reserve the bottom content row.');
  },

  'table view caps overflowing measured columns' => function(): void {
    $table = new TableView('table', ['a', 'b'], [[str_repeat('x', 100), 'short']]);
    $table->setFrame(new Rect(0, 0, 20, 2));
    $widths = $table->columnWidths();
    assertTrue($widths[0] <= 10, 'Wide measured columns should cap at half the table width when content overflows.');
    assertTrue(array_sum($widths) > 0, 'Measured column widths should remain populated.');
  },
];
