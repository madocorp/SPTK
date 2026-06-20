<?php

namespace Examples\Tests;

use SPTK\Style;
use SPTK\Geometry;
use SPTK\StyleSheet;

return [
  'xss selectors merge by type, name, and classes' => function (): void {
    resetToolkit();
    $path = tempFile(<<<'XSS'
Box { color: #111111; width: 10px; }
#target { color: #222222; height: 20px; }
.large { width: 30px; }
.warning { color: #ff0000; }
Button:active { backgroundColor: #00ff00; }
XSS, 'xss');
    StyleSheet::load($path);

    $style = StyleSheet::get(null, null, 'Box', ['large', 'warning'], 'target');
    assertSame([255, 0, 0, 255], $style->get('color'), 'class rules override type and name rules in class order');
    assertSame(30, $style->get('width'), 'class width overrides type width');
    assertSame(20, $style->get('height'), 'name selector applies when no class overrides it');

    $active = StyleSheet::get(null, null, 'Button', ['Button:active']);
    assertSame([0, 255, 0, 255], $active->get('backgroundColor'), 'variant type classes are loaded as class selectors');
  },

  'xss shorthands expand like css around rules' => function (): void {
    resetToolkit();
    $path = tempFile(<<<'XSS'
Box {
  border: 1px,2px,3px,4px;
  borderColor: #111111,#222222,#333333,#444444;
  margin: 5px,6px,7px;
  padding: 8px,9px;
}
XSS, 'xss');
    StyleSheet::load($path);

    $style = StyleSheet::get(null, null, 'Box');
    assertSame(1, $style->get('borderTop'), 'four-value border top expands');
    assertSame(2, $style->get('borderRight'), 'four-value border right expands');
    assertSame(3, $style->get('borderBottom'), 'four-value border bottom expands');
    assertSame(4, $style->get('borderLeft'), 'four-value border left expands');
    assertSame([17, 17, 17, 255], $style->get('borderColorTop'), 'borderColor top expands');
    assertSame([68, 68, 68, 255], $style->get('borderColorLeft'), 'borderColor left expands');
    assertSame(5, $style->get('marginTop'), 'three-value margin top expands');
    assertSame(6, $style->get('marginRight'), 'three-value margin right expands');
    assertSame(7, $style->get('marginBottom'), 'three-value margin bottom expands');
    assertSame(6, $style->get('marginLeft'), 'three-value margin left expands');
    assertSame(8, $style->get('paddingTop'), 'two-value padding top expands');
    assertSame(9, $style->get('paddingRight'), 'two-value padding right expands');
    assertSame(8, $style->get('paddingBottom'), 'two-value padding bottom expands');
    assertSame(9, $style->get('paddingLeft'), 'two-value padding left expands');
  },

  'xss values parse colors units booleans and inheritance' => function (): void {
    resetToolkit();
    $path = tempFile(<<<'XSS'
Parent { color: #123456; }
Child {
  color: inherit;
  backgroundColor: #01020304;
  display: false;
  width: -25px;
  height: 50%;
  x: -10%w;
  y: 20%h;
}
XSS, 'xss');
    StyleSheet::load($path);

    $ancestor = StyleSheet::get(null, null, 'Parent');
    $style = StyleSheet::get(null, $ancestor, 'Child');
    $reference = new Geometry(null);
    $reference->innerWidth = 200;
    $reference->innerHeight = 100;
    $reference->windowWidth = 800;
    $reference->windowHeight = 600;

    assertSame([18, 52, 86, 255], $style->get('color'), 'inherit copies the ancestor parsed value');
    assertSame([1, 2, 3, 4], $style->get('backgroundColor'), '8-digit colors include alpha');
    assertFalse($style->get('display'), 'boolean false parses as false');
    assertSame(-25, $style->get('width', $reference), 'negative pixel values are preserved');
    assertSame(50, $style->get('height', $reference), 'percent values use geometry references');
    assertSame(-80, $style->get('x', $reference), 'negative window width percentage values resolve');
    assertSame(120, $style->get('y', $reference), 'window height percentage values resolve');
  },

  'xss overwrite and invalid colors are explicit' => function (): void {
    resetToolkit();
    $first = tempFile('Box { color: #111111; width: 10px; }', 'xss');
    $second = tempFile('Box { color: #222222; }', 'xss');
    StyleSheet::load($first);
    StyleSheet::load($second);
    $merged = StyleSheet::get(null, null, 'Box');
    assertSame(10, $merged->get('width'), 'loading without overwrite merges rules');
    assertSame([34, 34, 34, 255], $merged->get('color'), 'later merged rules override same properties');

    StyleSheet::load($second, true);
    StyleSheet::clearCache();
    $overwritten = StyleSheet::get(null, null, 'Box');
    assertThrows(fn() => $overwritten->get('width'), 'overwrite replaces the previous selector entirely');
    assertThrows(fn() => new Style(['color' => '#12345']), 'invalid color strings throw');
  },
];
