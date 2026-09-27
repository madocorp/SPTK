<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\App', 'load']);

use SPTK\Layout\Tile;
use SPTK\Core\Style;
use SPTK\Rendering\{Grid, GridWriter};
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\Table\Table;
use SPTK\Widgets\Table\TableData;

/** Count user cursor changes during headless checks. */
final class TableListener {

  public static int $changes = 0;

  /** Count one user cursor change. */
  public static function change(): void {
    self::$changes++;
  }

}

/** Check a table result and identify the failed behavior. */
function expectTable(mixed $actual, mixed $expected, string $name): void {
  if ($actual !== $expected) {
    throw new RuntimeException("{$name}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true));
  }
}

/** Make the keyboard event shape used by Screen. */
function tableKey(int $key, int $mod = 0): object {
  return (object)['type' => SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => $key, 'mod' => $mod, 'repeat' => false]];
}

$table = new Table(['Name', 'Count'], [['Alpha', '1'], ['Beta', null], ['Gamma', '300']]);
$grid = new Grid(12, 2);
$table->paint(new GridWriter($grid, new Tile(0, 0, 12, 2)));
expectTable($grid->cell(1, 0)->glyph, 'N', 'header rendered');
expectTable($grid->cell(1, 1)->glyph, 'A', 'first row rendered');
$wideGrid = new Grid(30, 5);
$table->paint(new GridWriter($wideGrid, new Tile(0, 0, 30, 5)));
expectTable($wideGrid->cell(29, 0)->bg, $wideGrid->cell(1, 0)->bg, 'header fills available width');
expectTable($wideGrid->cell(29, 1)->bg, $wideGrid->cell(1, 1)->bg, 'body keeps its background');
$highlightTable = new Table(['First', 'Second'], [['one', 'two'], ['three', 'four']], [7, 7]);
$highlightTable->emit('activate');
$highlightGrid = new Grid(14, 3);
$highlightWriter = new GridWriter($highlightGrid, new Tile(0, 0, 14, 3));
$highlightTable->paint($highlightWriter);
$tableStyle = new Style();
expectTable($highlightGrid->cell(1, 1)->bg == $tableStyle->cursorBackground, true, 'cursor cell has highlight background');
expectTable($highlightGrid->cell(6, 1)->glyph, '│', 'cursor cell ends at separator');
expectTable($highlightGrid->cell(6, 1)->fg == $tableStyle->foreground, true, 'cursor leaves separator foreground unchanged');
expectTable($highlightGrid->cell(6, 1)->bg == $tableStyle->background, true, 'cursor leaves separator background unchanged');
$highlightTable->selectCells(0, 0, 1, 1);
$highlightTable->paint($highlightWriter);
expectTable($highlightGrid->cell(1, 1)->fg == $tableStyle->cursorForeground, true, 'selected cell uses cursor foreground');
expectTable($highlightGrid->cell(1, 1)->bg == $tableStyle->cursorBackground, true, 'selected cell uses cursor background');
expectTable($highlightGrid->cell(8, 2)->bg == $tableStyle->cursorBackground, true, 'selection endpoint uses cursor background');
expectTable($highlightGrid->cell(6, 1)->bg == $tableStyle->background, true, 'selection leaves first separator outside highlight');
expectTable($highlightGrid->cell(13, 2)->bg == $tableStyle->background, true, 'selection leaves last separator outside highlight');
$scrollTable = new Table(['First', 'Second', 'Third'], [['a', 'b', 'c'], ['d', 'e', 'f'], ['g', 'h', 'i'], ['j', 'k', 'l'], ['m', 'n', 'o'], ['p', 'q', 'r']], [7, 7, 7]);
$scrollGrid = new Grid(10, 4);
$scrollWriter = new GridWriter($scrollGrid, new Tile(0, 0, 10, 4));
$scrollTable->paint($scrollWriter);
$bottom = '';
for ($x = 0; $x < 10; $x++) {
  $bottom .= $scrollGrid->cell($x, 3)->glyph;
}
expectTable(str_ends_with($bottom, '▶ 1▼'), true, 'right and bottom indicators share the bottom row');
$scrollTable->setCursor(3, 1);
$scrollTable->paint($scrollWriter);
expectTable($scrollGrid->cell(9, 0)->glyph, '▲', 'vertical top indicator uses top corner');
expectTable($scrollGrid->cell(0, 3)->glyph, '◀', 'horizontal left indicator uses bottom corner');
$bottom = '';
for ($x = 0; $x < 10; $x++) {
  $bottom .= $scrollGrid->cell($x, 3)->glyph;
}
expectTable(str_ends_with($bottom, '▶ ▼'), true, 'horizontal marker precedes vertical marker');
$narrowGrid = new Grid(2, 4);
$scrollTable->setCursor(0, 0);
$scrollTable->paint(new GridWriter($narrowGrid, new Tile(0, 0, 2, 4)));
expectTable($narrowGrid->cell(0, 3)->glyph, '▶', 'narrow tile keeps horizontal marker');
expectTable($narrowGrid->cell(1, 3)->glyph, '▼', 'narrow tile keeps vertical marker');
$partial = new Table(['Name', 'Count'], [['Alpha', '1'], ['Beta', '2'], ['Gamma', '3']]);
$partial->emit('activate');
$partialGrid = new Grid(30, 3);
$partialWriter = new GridWriter($partialGrid, new Tile(0, 0, 30, 3));
$partial->paint($partialWriter);
$partialGrid->beginUpdate();
$partial->handleInput(tableKey(SDL::KEY_DOWN));
expectTable($partial->paintUpdate($partialWriter), true, 'cursor move paints changed rows');
$dirtyRows = array_unique(array_column($partialGrid->dirtyCells(), 1));
sort($dirtyRows);
expectTable($dirtyRows, [1, 2], 'cursor move touches two body rows');
$expectedGrid = new Grid(30, 3);
$partial->paint(new GridWriter($expectedGrid, new Tile(0, 0, 30, 3)));
for ($y = 0; $y < 3; $y++) {
  for ($x = 0; $x < 30; $x++) {
    expectTable($partialGrid->cell($x, $y) == $expectedGrid->cell($x, $y), true, 'partial Table paint matches full paint');
  }
}
$partialGrid->beginUpdate();
$partial->handleInput(tableKey(SDL::KEY_DOWN));
expectTable($partial->paintUpdate($partialWriter), false, 'scroll requests full Table paint');
$partial->paint($partialWriter);
$scrollDirty = $partialGrid->dirtyCells();
expectTable(count($scrollDirty), 90, 'scroll repaints the full Table tile');
$expectedGrid = new Grid(30, 3);
$partial->paint(new GridWriter($expectedGrid, new Tile(0, 0, 30, 3)));
for ($y = 0; $y < 3; $y++) {
  for ($x = 0; $x < 30; $x++) {
    expectTable($partialGrid->cell($x, $y) == $expectedGrid->cell($x, $y), true, 'scroll Table paint matches full paint');
  }
}
$table->emit('activate');
$table->on('change', [TableListener::class, 'change']);
$table->handleInput(tableKey(SDL::KEY_DOWN));
expectTable($table->cursorRow(), 1, 'row navigation');
expectTable($table->activeRowValues(), ['Beta', null], 'active row values');
$table->paint(new GridWriter($grid, new Tile(0, 0, 12, 2)));
expectTable($grid->cell(1, 1)->glyph, 'B', 'scrolled row rendered');
$table->handleInput(tableKey(SDL::KEY_RIGHT));
expectTable($table->activeCellValue(), null, 'null active cell');
expectTable(TableListener::$changes, 2, 'change notifications');
$table->setCursor(2, 1);
expectTable($table->activeCellValue(), '300', 'programmatic cursor');
expectTable(TableListener::$changes, 2, 'programmatic cursor silent');
$table->handleInput(tableKey(SDL::KEY_UP, SDL::MOD_SHIFT));
expectTable($table->selection(), [1, 1, 2, 1], 'shift extends selection');
expectTable($table->copySelection(), true, 'copy selected fields');
expectTable(SPTK\Core\Clipboard::get(), "NULL\n300", 'clipboard TSV values');
$table->handleInput(tableKey(ord('a'), SDL::MOD_CTRL));
expectTable($table->selection(), [0, 0, 2, 1], 'select all');
$table->handleInput(tableKey(ord('c'), SDL::MOD_CTRL));
expectTable(SPTK\Core\Clipboard::get(), "Alpha\t1\nBeta\tNULL\nGamma\t300", 'copy all cells');
$table->emit('deactivate');
expectTable($table->active(), false, 'release state');
$table->setTsvFile(APP_DIR . '/SPTK/Demo/Layout/sample.tsv');
expectTable($table->header(), ['Name', 'Status', 'Owner'], 'TSV header');
expectTable($table->rowValues(1), ['Deploy', 'Pending', 'Lin'], 'TSV row');
expectTable($table->rowValues(3), ['Audit', null, "Ann\nLee"], 'TSV null and escape decoding');
$large = new TableData();
$large->loadFile(APP_DIR . '/SPTK/Demo/Layout/large.tsv');
expectTable($large->count(), 2048, 'large TSV row count');
expectTable($large->columns(), 16, 'large TSV field count');
expectTable($large->cachedRowCount(), TableData::CHUNK_SIZE, 'initial TSV chunk size');
expectTable($large->row(0)[11], "First line for record 1\nSecond line with a tab\tand more detail", 'large TSV multiline field');
expectTable($large->row(0)[14], null, 'large TSV null field');
foreach ([255, 256, 511, 512, 1024, 2047, 0] as $row) {
  expectTable($large->row($row)[0], (string)($row + 1), "indexed TSV row {$row}");
  expectTable($large->cachedRowCount() <= TableData::CHUNK_SIZE * 2, true, 'bounded TSV row cache');
}
$temporary = tmpfile();
if ($temporary === false) {
  throw new RuntimeException('Cannot create temporary TSV test file.');
}
fwrite($temporary, "ID\tValue\n");
for ($row = 0; $row < 60000; $row++) {
  fwrite($temporary, "{$row}\tValue {$row}\n");
}
fflush($temporary);
$stress = new TableData();
$stress->loadFile(stream_get_meta_data($temporary)['uri']);
expectTable($stress->count(), 60000, 'large indexed file row count');
expectTable($stress->row(59999), ['59999', 'Value 59999'], 'large indexed file last row');
expectTable($stress->row(256), ['256', 'Value 256'], 'large indexed file backward seek');
expectTable($stress->cachedRowCount() <= TableData::CHUNK_SIZE * 2, true, 'large indexed file bounded cache');
fclose($temporary);
$largeTable = new Table();
$largeTable->setTsvFile(APP_DIR . '/SPTK/Demo/Layout/large.tsv');
$largeTable->paint(new GridWriter(new Grid(80, 8), new Tile(0, 0, 80, 8)));
expectTable(max($largeTable->columnWidths()) <= 40, true, 'wide fields capped to half viewport');
expectTable($largeTable->columnWidths()[15], 40, 'long description field sizing');
$largeTable->setCursor(2047, 15);
expectTable($largeTable->activeRowValues()[0], '2048', 'last indexed row');
$parser = new SPTK\XmlParser\XmlParser();
$screens = $parser->windows[0]['screens'];
expectTable(count($screens), 5, 'demo screen count');
$tables = [];
foreach ($screens[4]->layout->leaves() as $leaf) {
  if ($leaf->instance() instanceof Table) {
    $tables[] = $leaf->instance();
  }
}
expectTable(count($tables), 3, 'inline and file Table XML sources');
expectTable($tables[0]->rowValues(2), ['Archive', 'History', null], 'XML null field');
expectTable($tables[1]->rowCount(), 4, 'XML TSV file');
expectTable($tables[2]->rowCount(), 2048, 'large demo uses indexed TSV');
$tables[2]->setCursor(2047, 15);
$gutterGrid = new Grid(80, 8);
$tables[2]->paint(new GridWriter($gutterGrid, new Tile(0, 0, 80, 8)));
expectTable($gutterGrid->cell(4, 0)->glyph, '#', 'row number header remains fixed');
expectTable($gutterGrid->cell(5, 1)->glyph, '│', 'row number separator remains fixed');
$tables[2]->setCursor(0, 15);
$tables[2]->paint(new GridWriter($gutterGrid, new Tile(0, 0, 80, 8)));
$visibleRow = '';
for ($x = 0; $x < 80; $x++) {
  $visibleRow .= $gutterGrid->cell($x, 1)->glyph;
}
expectTable(str_contains($visibleRow, '~'), true, 'long field truncation marker');
$tables[2]->setCursor(0, 11);
$tables[2]->paint(new GridWriter($gutterGrid, new Tile(0, 0, 80, 8)));
$visibleRow = '';
for ($x = 0; $x < 80; $x++) {
  $visibleRow .= $gutterGrid->cell($x, 1)->glyph;
}
expectTable(str_contains($visibleRow, 'v'), true, 'multiline field marker');
echo "Table checks passed\n";
