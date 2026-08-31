<?php

namespace SPTK\Examples\Widgets;

use SPTK\Examples\Accessories\SimpleSqlHighlighter;
use SPTK\Widgets\MenuItem;
use SPTK\Widgets\TextEditor;

class EditorExamples {

  public static function menu(ExampleHost $host): MenuItem {
    $menu = new MenuItem('Editor');
    $default = new MenuItem('Default');
    $default->addItem([
      'label' => 'Main',
      'action' => fn() => $host->showMain('Editor / Default / Main', new TextEditor('editor-main', "Edit this text\nwith arrow keys and typing.")),
    ]);
    $default->addItem([
      'label' => 'Panel',
      'action' => fn() => self::showPanel($host, 'Editor Default', new TextEditor('editor-panel', "Panel editor\ninside a dialog.")),
    ]);
    $menu->addItem($default);

    $syntax = new MenuItem('Syntax Highlight');
    $syntax->addItem([
      'label' => 'Main',
      'action' => function() use ($host): void {
        $host->showMain('Editor / Syntax Highlight / Main', self::sqlEditor('editor-syntax-main'));
      },
    ]);
    $syntax->addItem([
      'label' => 'Panel',
      'action' => fn() => self::showPanel($host, 'Editor Syntax Highlight', self::sqlEditor('editor-syntax-panel')),
    ]);
    $menu->addItem($syntax);
    $menu->addItem([
      'label' => 'Mixed Wrap',
      'action' => fn() => self::showPanel($host, 'Editor Mixed Wrap', self::mixedWrapEditor('editor-mixed-wrap'), 'big'),
    ]);
    $menu->addItem([
      'label' => 'Scroll',
      'action' => function() use ($host): void {
        $lines = [];
        for ($i = 1; $i <= 80; $i++) {
          $lines[] = "Line {$i}: scrollable editor content";
        }
        self::showPanel($host, 'Editor Scroll', new TextEditor('editor-scroll', implode("\n", $lines)), 'big');
      },
    ]);
    return $menu;
  }

  protected static function sqlEditor(string $name): TextEditor {
    $editor = new TextEditor($name, "select id, name\nfrom users\nwhere id = 42 and name = 'Ada'\nlimit 10");
    $editor->setTokenizer(SimpleSqlHighlighter::class);
    return $editor;
  }

  protected static function mixedWrapEditor(string $name): TextEditor {
    $editor = new TextEditor($name, implode("\n", [
      'Long prose wraps at word boundaries so normal descriptions remain readable inside a narrow editor panel.',
      '',
      '• A long list item uses hanging indentation on wrapped continuation rows while leaving the editor text unchanged.',
      '',
      'CODE const example = "this deliberately long code block line should stay on one row while the editor is in auto-wrap mode";',
      '',
      'After the code block, prose wraps again using the same editor surface.',
    ]));
    $editor->setHighlighter([
      ['regex' => '/^[-*+•] /u', 'style' => 'list'],
      ['regex' => '/^CODE .*/u', 'style' => 'code-block'],
      ['regex' => '/^.+/u', 'style' => 'plain'],
    ]);
    $editor->setAutoWrap();
    $editor->setWrapIndentStyles(['list']);
    $editor->setWrapExemptStyles(['code-block']);
    $editor->setLineFillStyles(['code-block']);
    $editor->setStyleColors([
      'list' => ['fg' => '#00ffff'],
      'code-block' => ['fg' => '#003f66', 'bg' => '#b7d7e8'],
    ]);
    return $editor;
  }

  protected static function showPanel(ExampleHost $host, string $title, TextEditor $editor, string $size = 'big'): void {
    $panel = $host->panel(strtolower(str_replace(' ', '-', $title)), $title, $size);
    $panel->addContent($editor, 12);
    $host->showPanel($title, $panel);
  }

}
