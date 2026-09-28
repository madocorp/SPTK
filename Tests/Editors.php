<?php

define('APP_DIR', __DIR__);

require_once APP_DIR . '/SPTK/App.php';

spl_autoload_register(['SPTK\\App', 'load']);

use SPTK\Core\{Clipboard, Color, Screen, ScrollIndicator, Style, TextEdit};
use SPTK\Layout\{LayoutLeaf, LayoutNode, Tile};
use SPTK\Rendering\{Grid, GridWriter};
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\Input\Input;
use SPTK\Widgets\Text\Text;
use SPTK\Widgets\TextEditor\TextEditor;

/** Counts editor acceptance notifications during screen tests. */
final class EditorTestListener {

  public static int $accepted = 0;

  /** Count one accepted editor release. */
  public static function accept(): void {
    self::$accepted++;
  }

}

/** Fail with a named assertion when expected and actual values differ. */
function expectEditor(mixed $actual, mixed $expected, string $name): void {
  if ($actual !== $expected) {
    throw new RuntimeException($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
  }
}

/** Find a scroll arrow and verify widget background ink over the highlight color. */
function expectInvertedIndicator(Grid $grid, string $name, Color $ink): void {
  for ($y = 0; $y < $grid->height(); $y++) {
    for ($x = 0; $x < $grid->width(); $x++) {
      $cell = $grid->cell($x, $y);
      if (!in_array($cell->glyph, ['▲', '▼', '◀', '▶'], true)) {
        continue;
      }
      expectEditor([$cell->fg->r, $cell->fg->g, $cell->fg->b], [$ink->r, $ink->g, $ink->b], $name . ' foreground');
      expectEditor([$cell->bg->r, $cell->bg->g, $cell->bg->b], [0, 255, 255], $name . ' background');
      return;
    }
  }
  throw new RuntimeException($name . ': no scroll indicator was drawn');
}

/** Check an arrow's edge position without depending on its page count. */
function expectArrowAt(Grid $grid, string $glyph, int $x, int $y, string $name): void {
  expectEditor($grid->cell($x, $y)->glyph, $glyph, $name);
}

/** Create a keyboard event without opening an SDL window. */
function keyEvent(int $key, int $mod = 0): object {
  return (object)['type' => SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => $key, 'mod' => $mod]];
}

/** Create an SDL-shaped UTF-8 text event for headless editing checks. */
function textEvent(string $text): object {
  $buffer = FFI::new('char[64]');
  FFI::memcpy($buffer, $text, strlen($text));
  return (object)['type' => SDL::SDL_EVENT_TEXT_INPUT, 'text' => (object)['text' => $buffer]];
}

/** Build a one-widget screen for release-notification checks. */
function editorScreen(Input|TextEditor $widget): Screen {
  $layout = new LayoutNode('vertical', '1*', '1*');
  $layout->addLeaf(new LayoutLeaf('Editor', '', '', $widget));
  $screen = new Screen($layout);
  $screen->measureGrid(new Tile(0, 0, 20, 4));
  return $screen;
}

$parser = new SPTK\XmlParser\XmlParser();
expectEditor(count($parser->windows[0]['screens']) >= 2, true, 'demo XML screens');
foreach ($parser->windows[0]['screens'] as $demoScreen) {
  $demoScreen->measureGrid(new Tile(0, 0, 100, 30));
  $demoScreen->paint(new Grid(100, 30));
}
$editorLeaves = $parser->windows[0]['screens'][1]->layout->leaves();
$editorWidgets = [];
foreach ($editorLeaves as $leaf) {
  if ($leaf->instance() instanceof Input || $leaf->instance() instanceof TextEditor) {
    $editorWidgets[] = $leaf->instance();
  }
}
expectEditor(count($editorWidgets), 2, 'Editors screen widget count');
expectEditor($editorWidgets[0] instanceof Input, true, 'Input XML parser');
expectEditor($editorWidgets[1] instanceof TextEditor, true, 'TextEditor XML parser');
$labeledInput = new Input('value', style: new Style(foreground: new Color(255, 255, 255), highlight: new Color(30, 180, 220)), label: 'Name');
expectEditor($labeledInput->preferredHeight(), 2, 'labeled Input preferred height');
$grid = new Grid(10, 2);
$labeledInput->paint(new GridWriter($grid, new Tile(0, 0, 10, 2)));
expectEditor($grid->cell(0, 0)->glyph, 'N', 'Input label row');
expectEditor([$grid->cell(0, 0)->fg->r, $grid->cell(0, 0)->fg->g, $grid->cell(0, 0)->fg->b], [30, 180, 220], 'Input label highlight');
expectEditor($grid->cell(0, 1)->glyph, 'v', 'Input value below label');
expectEditor($grid->cell(0, 1)->fg->r, 255, 'Input value foreground');
expectEditor($labeledInput->getValue(), 'value', 'Input label outside value');
$emptyLabel = new Input('value', label: '');
expectEditor($emptyLabel->preferredHeight(), 2, 'empty label reserves row');
$labeledEditor = new TextEditor('first', style: new Style(foreground: new Color(255, 255, 255), highlight: new Color(30, 180, 220)), label: 'Document');
expectEditor($labeledEditor->preferredHeight(), 17, 'labeled TextEditor preferred height');
$grid = new Grid(10, 2);
$labeledEditor->paint(new GridWriter($grid, new Tile(0, 0, 10, 2)));
expectEditor($grid->cell(0, 0)->glyph, 'D', 'TextEditor label row');
expectEditor([$grid->cell(0, 0)->fg->r, $grid->cell(0, 0)->fg->g, $grid->cell(0, 0)->fg->b], [30, 180, 220], 'TextEditor label highlight');
expectEditor($grid->cell(0, 1)->glyph, 'f', 'TextEditor text below label');
expectEditor($grid->cell(0, 1)->fg->r, 255, 'TextEditor text foreground');
expectEditor($labeledEditor->getValue(), 'first', 'TextEditor label outside value');
$grid = new Grid(10, 1);
$labeledEditor->paint(new GridWriter($grid, new Tile(0, 0, 10, 1)));
expectEditor($grid->cell(0, 0)->glyph, 'D', 'label in one-row tile');
$movingEditor = new TextEditor('abcd', label: 'Document');
$movingEditor->emit('activate');
$grid = new Grid(10, 3);
$writer = new GridWriter($grid, new Tile(0, 0, 10, 3));
$movingEditor->paint($writer);
$grid->beginUpdate();
$movingEditor->handleInput(keyEvent(SDL::KEY_RIGHT));
expectEditor($movingEditor->paintUpdate($writer), true, 'TextEditor cursor update');
expectEditor(count($grid->dirtyCells()), 2, 'TextEditor cursor dirties two cells');
$input = new Input('A🌿');
$screen = editorScreen($input);
$input->on('accept', [EditorTestListener::class, 'accept']);
$screen->handleEvent(keyEvent(SDL::KEY_RETURN));
$screen->handleEvent(keyEvent(SDL::KEY_END));
$screen->handleEvent(textEvent('é'));
expectEditor($input->getValue(), 'A🌿é', 'Unicode input');
$screen->handleEvent(keyEvent(ord('a'), SDL::MOD_CTRL));
$screen->handleEvent(keyEvent(ord('c'), SDL::MOD_CTRL));
expectEditor(Clipboard::get(), 'A🌿é', 'copy selection');
$screen->handleEvent(keyEvent(ord('x'), SDL::MOD_CTRL));
expectEditor($input->getValue(), '', 'cut selection');
$screen->handleEvent(keyEvent(ord('z'), SDL::MOD_CTRL));
expectEditor($input->getValue(), 'A🌿é', 'undo cut');
$screen->handleEvent(keyEvent(SDL::KEY_ESCAPE));
expectEditor(EditorTestListener::$accepted, 1, 'Input Escape accepts');
expectEditor($input->editing(), false, 'Input deactivates');
$input->setValue("one\ntwo");
expectEditor($input->getValue(), 'one two', 'Input one-line normalization');
$editor = new TextEditor("a\nb", wrap: true);
$screen = editorScreen($editor);
$editor->on('accept', [EditorTestListener::class, 'accept']);
$screen->handleEvent(keyEvent(SDL::KEY_RETURN));
$screen->handleEvent(keyEvent(SDL::KEY_RETURN));
expectEditor($editor->getValue(), "\na\nb", 'Return inserts newline');
$screen->handleEvent(keyEvent(SDL::KEY_RETURN, SDL::MOD_CTRL));
expectEditor(EditorTestListener::$accepted, 2, 'Ctrl+Return accepts editor');
expectEditor($editor->editing(), false, 'TextEditor deactivates');
$document = new TextEdit(true, "a\nb");
$revision = $document->revision();
$document->cursor()->setPosition(0, 1);
expectEditor($document->revision(), $revision, 'cursor movement keeps text layout revision');
expectEditor($document->cursor()->selectedText(), "\n", 'copy newline at caret');
$document->delete(false);
expectEditor($document->revision(), $revision + 1, 'text deletion changes layout revision');
expectEditor($document->text(), 'ab', 'delete newline');
$document->travel(false);
expectEditor($document->revision(), $revision + 2, 'undo changes layout revision');
expectEditor($document->text(), "a\nb", 'undo newline deletion');
$tabInput = new Input("a\tb", tabSize: 4);
$tabInput->emit('activate');
foreach ([1, 2, 3, 4, 8] as $width) {
  $grid = new Grid($width, 1);
  $tabInput->paint(new GridWriter($grid, new Tile(0, 0, $width, 1)));
}
expectEditor($tabInput->text(), "a\tb", 'tabs remain in Input value');
$marked = new TextEditor("a\nb");
$marked->emit('activate');
$marked->handleInput(keyEvent(SDL::KEY_END));
$marked->handleInput(keyEvent(SDL::KEY_RIGHT, SDL::MOD_SHIFT));
$grid = new Grid(10, 2);
$marked->paint(new GridWriter($grid, new Tile(0, 0, 10, 2)));
expectEditor($grid->cell(1, 0)->glyph, '¶', 'selected newline marker');
$text = new Text("one\ntwo");
$text->emit('activate');
$text->handleInput(keyEvent(ord('a'), SDL::MOD_CTRL));
$text->handleInput(keyEvent(ord('c'), SDL::MOD_CTRL));
expectEditor(Clipboard::get(), "one\ntwo", 'Text read-only copy');
$indicatorInk = new Color(12, 34, 56);
expectEditor(ScrollIndicator::label(10, 5, '▲'), '2▲', 'up arrow follows page count');
expectEditor(ScrollIndicator::bottomRight('3▶', '4▼'), '3▶ 4▼', 'shared lower-right indicator order');
expectEditor(ScrollIndicator::fit('3▶ 4▼', 2, true), '▶▼', 'shared narrow indicator keeps both arrows');
$grid = new Grid(4, 1);
(new Input('abcdefghijkl', style: new Style(background: $indicatorInk)))->paint(new GridWriter($grid, new Tile(0, 0, 4, 1)));
expectInvertedIndicator($grid, 'Input scroll indicator', $indicatorInk);
expectArrowAt($grid, '▶', 3, 0, 'Input right arrow at right edge');
$grid = new Grid(4, 1);
(new Text('abcdefghijkl', style: new Style(background: $indicatorInk), wrap: false))->paint(new GridWriter($grid, new Tile(0, 0, 4, 1)));
expectInvertedIndicator($grid, 'Text scroll indicator', $indicatorInk);
expectArrowAt($grid, '▶', 3, 0, 'Text right arrow at right edge');
$horizontal = new Text('abcdefghijkl', wrap: false);
$horizontal->emit('activate');
$horizontal->handleInput(keyEvent(SDL::KEY_END));
$grid = new Grid(5, 1);
$horizontal->paint(new GridWriter($grid, new Tile(0, 0, 5, 1)));
expectArrowAt($grid, '◀', 0, 0, 'Text left arrow at left edge');
$vertical = new Text("a\nb\nc\nd");
$vertical->emit('activate');
$vertical->handleInput(keyEvent(SDL::KEY_DOWN));
$vertical->handleInput(keyEvent(SDL::KEY_DOWN));
$grid = new Grid(10, 2);
$vertical->paint(new GridWriter($grid, new Tile(0, 0, 10, 2)));
expectArrowAt($grid, '▲', 9, 0, 'up arrow at right edge');
expectArrowAt($grid, '▼', 9, 1, 'down arrow at right edge');
$both = new Text("abcdefghijkl\nmnopqrstuvwx\nzyxwvutsrqpo\nlast", wrap: false);
$grid = new Grid(5, 3);
$both->paint(new GridWriter($grid, new Tile(0, 0, 5, 3)));
$bottom = '';
for ($x = 0; $x < 5; $x++) {
  $bottom .= $grid->cell($x, 2)->glyph;
}
expectEditor(str_ends_with($bottom, '▶ ▼'), true, 'Text right and down arrows share bottom row');
$grid = new Grid(6, 2);
(new TextEditor("one\ntwo\nthree", style: new Style(background: $indicatorInk)))->paint(new GridWriter($grid, new Tile(0, 0, 6, 2)));
expectInvertedIndicator($grid, 'TextEditor scroll indicator', $indicatorInk);
$grid = new Grid(10, 4);
$editor->emit('activate');
$editor->paint(new GridWriter($grid, new Tile(0, 0, 10, 4)));
echo "Editor checks passed\n";
