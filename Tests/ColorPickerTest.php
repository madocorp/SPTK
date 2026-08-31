<?php

namespace SPTK\Tests;

use SPTK\Core\FocusManager;
use SPTK\Core\GridBuffer;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Widgets\ColorPicker;
use SPTK\Widgets\ColorSwatchGrid;
use SPTK\Widgets\Dock;

return [
  'color picker renders swatch grid' => function(): void {
    $picker = new ColorPicker('color', '#e61717');
    $picker->setFrame(new Rect(0, 0, 64, 6));
    $buffer = new GridBuffer(64, 6);
    $picker->render($buffer);

    assertSame('#000000', $buffer->cell(1, 0)?->bg->hex(), 'ColorPicker should render black as the first base color.');
    assertSame('#e61717', $buffer->cell(5, 0)?->bg->hex(), 'ColorPicker should render color bases after black.');
    assertSame('[', $buffer->cell(28, 2)?->glyph, 'ColorPicker should mark the active tone cursor with a left bracket.');
    assertSame('#555555', $buffer->cell(28, 2)?->bg->hex(), 'Unfocused active cursor marker should use the inactive cursor background.');
    assertSame(' ', $buffer->cell(28, 3)?->glyph, 'Selected swatch highlight should not fill the bottom padding row.');
    assertSame('#ff0000', $buffer->cell(1, 4)?->bg->hex(), 'ColorPicker should render exact color shortcuts in the third row.');
  },

  'color swatch grid keyboard navigation updates value' => function(): void {
    $picker = new ColorPicker('color', '#ff0000');
    $root = new Dock('root');
    $root->fill($picker);
    $root->setFrame(new Rect(0, 0, 64, 6));
    $focus = new FocusManager($root);
    $focus->next();
    assertInstanceOf(ColorSwatchGrid::class, $focus->current(), 'ColorPicker should focus the swatch grid.');
    $grid = $focus->current();
    $start = $grid->cursor();

    $focus->dispatch(InputEvent::key('Right'));
    assertSame($start + 1, $grid->cursor(), 'Right should move to the next color in the row.');
    assertSame($grid->getValue(), $picker->getValue(), 'Moving the swatch cursor should update the picker value.');
    $focus->dispatch(InputEvent::key('End'));
    assertSame(15, $grid->cursor() % 16, 'End should move to the last color in the current row.');
    $focus->dispatch(InputEvent::key('Home'));
    assertSame(0, $grid->cursor() % 16, 'Home should move to the first color in the current row.');
    $focus->dispatch(InputEvent::key('PageDown'));
    assertSame(2, intdiv($grid->cursor(), 16), 'PageDown should move to the exact color row in the current column.');
    $focus->dispatch(InputEvent::key('PageUp'));
    assertSame(0, intdiv($grid->cursor(), 16), 'PageUp should move to the base color row in the current column.');
  },

  'color swatch base selection stays visible while moving tones' => function(): void {
    $picker = new ColorPicker('color', '#e61717');
    $picker->setFrame(new Rect(0, 0, 64, 6));
    $grid = $picker->grid();

    assertSame(16 + 7, $grid->cursor(), 'Exact base color values should remain on the tone row when initialized.');
    $grid->handle(InputEvent::key('Right'));
    assertSame(16 + 8, $grid->cursor(), 'Moving right on the tone row should stay on the tone row.');
    $buffer = new GridBuffer(64, 6);
    $picker->render($buffer);

    assertSame('[', $buffer->cell(4, 0)?->glyph, 'Base selection should remain marked while choosing tones.');
    assertSame('#000000', $buffer->cell(4, 0)?->fg->hex(), 'Persistent base marker should use black text.');
    assertSame('[', $buffer->cell(32, 2)?->glyph, 'Tone cursor should highlight the selected tone side padding.');
  },

  'color swatch down from base moves to matching tone' => function(): void {
    $picker = new ColorPicker('color', '#000000');
    $grid = $picker->grid();
    $grid->handle(InputEvent::key('PageUp'));
    $grid->handle(InputEvent::key('Right'));
    assertSame(1, $grid->cursor(), 'Right should move to the first color base after black.');
    $grid->handle(InputEvent::key('Down'));

    assertSame(16 + 7, $grid->cursor(), 'Down from a base color should move to the matching middle tone.');
    assertSame('#e61717', $grid->getValue(), 'The matching tone should keep the same color value as the base.');
  },

  'color swatch up from tone returns to selected base column' => function(): void {
    $picker = new ColorPicker('color', '#000000');
    $grid = $picker->grid();
    $grid->handle(InputEvent::key('PageUp'));
    $grid->handle(InputEvent::key('Right'));
    $grid->handle(InputEvent::key('Down'));
    $grid->handle(InputEvent::key('Right'));
    assertSame(16 + 8, $grid->cursor(), 'Setup should move to a neighboring tone.');
    $grid->handle(InputEvent::key('Up'));

    assertSame(1, $grid->cursor(), 'Up from the tone row should return to the persistent base column.');
  },

  'color swatch down from black moves to black tone' => function(): void {
    $picker = new ColorPicker('color', '#000000');
    $grid = $picker->grid();
    $grid->handle(InputEvent::key('PageUp'));
    $grid->handle(InputEvent::key('Down'));

    assertSame(16, $grid->cursor(), 'Down from black should move to the black tone in the gray row.');
    assertSame('#000000', $grid->getValue(), 'The black tone should keep the same color value as the black base.');
  },

  'color swatch black base shows gray tones' => function(): void {
    $picker = new ColorPicker('color', '#000000');
    $picker->setFrame(new Rect(0, 0, 64, 6));
    $buffer = new GridBuffer(64, 6);
    $picker->render($buffer);

    assertSame('#000000', $buffer->cell(1, 2)?->bg->hex(), 'Black base should start the tone row with black.');
    assertSame('#ffffff', $buffer->cell(61, 2)?->bg->hex(), 'Black base should end the tone row with white.');
  },

  'color swatch color tones avoid full black and white endpoints' => function(): void {
    $picker = new ColorPicker('color', '#e61717');
    $picker->setFrame(new Rect(0, 0, 64, 6));
    $buffer = new GridBuffer(64, 6);
    $picker->render($buffer);

    assertSame('#510808', $buffer->cell(1, 2)?->bg->hex(), 'Color tone rows should start from a dark but not black tone.');
    assertSame('#f9c5c5', $buffer->cell(61, 2)?->bg->hex(), 'Color tone rows should end near white but not pure white.');
  },

  'color swatch exact row keeps free colors visible' => function(): void {
    $picker = new ColorPicker('color', '#123456');
    $picker->setFrame(new Rect(0, 0, 64, 6));
    $grid = $picker->grid();
    $buffer = new GridBuffer(64, 6);
    $picker->render($buffer);

    assertSame(47, $grid->cursor(), 'Free colors should put the cursor on the last exact color slot.');
    assertSame('#ff0000', $buffer->cell(1, 4)?->bg->hex(), 'The first exact color slot should keep the red shortcut.');
    assertSame('#123456', $buffer->cell(61, 4)?->bg->hex(), 'The last exact color slot should render the free color.');
    assertSame('#123456', $grid->getValue(), 'The grid value should keep the exact free color.');
  },

  'color swatch exact row keeps free color while selecting shortcuts' => function(): void {
    $picker = new ColorPicker('color', '#123456');
    $grid = $picker->grid();
    $grid->handle(InputEvent::key('Home'));
    assertSame('#ff0000', $grid->getValue(), 'Home on the exact row should select the first fixed shortcut.');
    assertSame(32, $grid->cursor(), 'Shortcut selection should stay in the fixed shortcut position.');
    $grid->handle(InputEvent::key('End'));

    assertSame(47, $grid->cursor(), 'End on the exact row should move to the free color slot.');
    assertSame('#123456', $grid->getValue(), 'The free slot should keep the previous free color after shortcut selection.');
  },

  'color swatch set value jumps to exact shortcut or free slot' => function(): void {
    $picker = new ColorPicker('color', '#123456');
    $grid = $picker->grid();
    $picker->setValue('#ff0000');
    $picker->setFrame(new Rect(0, 0, 64, 6));
    $buffer = new GridBuffer(64, 6);
    $picker->render($buffer);

    assertSame(32, $grid->cursor(), 'A predefined exact color should move the cursor to its fixed shortcut slot.');
    assertSame('#123456', $buffer->cell(61, 4)?->bg->hex(), 'Selecting a shortcut should keep the previous free color in the last slot.');

    $picker->setValue('#654321');
    assertSame(47, $grid->cursor(), 'A non-represented exact color should move the cursor to the free slot.');
    assertSame('#654321', $grid->getValue(), 'The free slot should keep the exact non-represented value.');
  },

  'color swatch reopens exact picker tones on the picker row' => function(): void {
    $picker = new ColorPicker('color', '#f9c5c5');
    $grid = $picker->grid();

    assertSame(31, $grid->cursor(), 'An exact generated picker tone should reopen on the matching tone cell.');
    assertSame('#f9c5c5', $grid->getValue(), 'Reopening an exact generated picker tone should not move it to the free slot.');
  },

  'color swatch clipped exact cursor keeps right bracket only' => function(): void {
    $picker = new ColorPicker('color', '#123456');
    $picker->setFrame(new Rect(0, 0, 63, 6));
    $buffer = new GridBuffer(63, 6);
    $picker->render($buffer);

    assertSame('[', $buffer->cell(60, 4)?->glyph, 'A clipped last-column exact cursor should show the left bracket.');
    assertSame(']', $buffer->cell(62, 4)?->glyph, 'A clipped last-column exact cursor should keep the right bracket visible.');
    for ($x = 0; $x < 63; $x++) {
      assertTrue(!in_array($buffer->cell($x, 0)?->glyph, ['[', ']'], true), 'Base marker should be hidden while the exact row cursor is active.');
    }
  },
];
