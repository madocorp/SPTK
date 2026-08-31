<?php

namespace SPTK\Tests;

use SPTK\Core\Cell;
use SPTK\Core\Color;
use SPTK\Core\Theme;

return [
  'color parses hex strings' => function(): void {
    $color = Color::from('#0cf');
    assertSame(0, $color->r, 'Short hex should expand red channel.');
    assertSame(204, $color->g, 'Short hex should expand green channel.');
    assertSame(255, $color->b, 'Short hex should expand blue channel.');
    assertSame('#00ccff', $color->hex(), 'Hex output should be normalized.');
  },

  'color parses alpha formats' => function(): void {
    $color = Color::from('#11223344');
    assertSame(0x11223344, $color->packedRgba(), 'Packed RGBA should preserve channels.');
    assertSame('#11223344', $color->hex(), 'Hex output should include non-opaque alpha.');
  },

  'color parses packed integers' => function(): void {
    $rgb = Color::from(0x336699);
    $rgba = Color::from(0x33669980);
    assertSame('#336699', $rgb->hex(), 'RGB integers should default to opaque alpha.');
    assertSame('#33669980', $rgba->hex(), 'RGBA integers should preserve alpha.');
  },

  'color parses names' => function(): void {
    assertSame('#ffffff', Color::from('white')->hex(), 'Named colors should be accepted.');
    assertSame('#00000000', Color::from('transparent')->hex(), 'Transparent should include alpha.');
  },

  'cell normalizes colors' => function(): void {
    $cell = new Cell('x', '#123456', 0xabcdef80);
    assertInstanceOf(Color::class, $cell->fg, 'Cell foreground should be a Color.');
    assertSame('#123456', $cell->fg->hex(), 'Cell foreground should be normalized.');
    assertSame('#abcdef80', $cell->bg->hex(), 'Cell background should be normalized.');
  },

  'theme normalizes colors' => function(): void {
    $theme = new Theme(fg: '#111', bg: 'transparent', markerFg: '#0ff', cursorFg: '#222', inactiveCursorBg: '#333');
    assertInstanceOf(Color::class, $theme->fg, 'Theme foreground should be a Color.');
    assertSame('#111111', $theme->fg->hex(), 'Theme foreground should be normalized.');
    assertSame('#00000000', $theme->bg->hex(), 'Theme background should be normalized.');
    assertSame('#00ffff', $theme->markerFg->hex(), 'Theme marker foreground should be normalized.');
    assertSame('#222222', $theme->cursorFg->hex(), 'Theme cursor foreground should be normalized.');
    assertSame('#333333', $theme->inactiveCursorBg->hex(), 'Theme inactive cursor background should be normalized.');
  },
];
