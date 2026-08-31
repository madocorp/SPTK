<?php

namespace SPTK\Examples\Widgets;

use SPTK\Core\Len;
use SPTK\Core\Place;
use SPTK\Widgets\Box;
use SPTK\Widgets\Button;
use SPTK\Widgets\Checkbox;
use SPTK\Widgets\DialogPanel;
use SPTK\Widgets\Dock;
use SPTK\Widgets\Flow;
use SPTK\Widgets\FlowRow;
use SPTK\Widgets\ImageView;
use SPTK\Widgets\Input;
use SPTK\Widgets\Label;
use SPTK\Widgets\ListView;
use SPTK\Widgets\MenuItem;
use SPTK\Widgets\TabView;
use SPTK\Widgets\StyledTextBox;
use SPTK\Widgets\TextBlock;
use SPTK\Widgets\TextEditor;
use SPTK\Widgets\WorkspaceBox;

class LayoutExamples {

  public static function menu(ExampleHost $host): MenuItem {
    $menu = new MenuItem('Layout');
    $menu->addItem([
      'label' => 'Dock',
      'action' => fn() => $host->showMain('Layout / Dock', self::dockDemo()),
    ]);
    $menu->addItem([
      'label' => 'Flow',
      'action' => function() use ($host): void {
        $panel = $host->panel('flow-example', 'Flow', 'normal');
        $panel->addContent(new Label('flow-label', 'Stacked controls'));
        $panel->addContent(new Input('flow-input', 'value'));
        $panel->addContent(new TextBlock('flow-text', 'Flow places each child below the previous child.'));
        $host->showPanel('Layout / Flow', $panel);
      },
    ]);
    $menu->addItem([
      'label' => 'FlowRow',
      'action' => function() use ($host): void {
        $panel = $host->panel('flowrow-example', 'FlowRow', 'small');
        $row = new FlowRow('flowrow-buttons', 'justify');
        $row->place(new Button('flowrow-one', 'One'));
        $row->place(new Button('flowrow-two', 'Two'));
        $row->place(new Button('flowrow-three', 'Three'));
        $panel->addContent($row);
        $host->showPanel('Layout / FlowRow', $panel);
      },
    ]);
    $menu->addItem([
      'label' => 'TabView',
      'action' => function() use ($host): void {
        $panel = $host->panel('tabview-example', 'TabView', 'normal');
        $tabs = new TabView('tabview-tabs');

        $account = new Flow('tabview-account');
        $account->place(new Label('tabview-name-label', 'Name'));
        $account->place(new Input('tabview-name', 'Ada Lovelace'));
        $account->place(new Label('tabview-email-label', 'Email'));
        $account->place(new Input('tabview-email', 'ada@example.test'));

        $flags = new Flow('tabview-flags');
        $flags->place(new Checkbox('tabview-enabled', 'Enabled'));
        $flags->place(new Checkbox('tabview-admin', 'Administrator'));
        $flags->place(new Checkbox('tabview-notify', 'Send notifications'));

        $notes = new TextEditor('tabview-notes', "Notes live on their own pane.\nUse F1, F2, F3 to switch.");

        $tabs->addTab('Account', $account);
        $tabs->addTab('Flags', $flags);
        $tabs->addTab('Notes', $notes);
        $panel->addContent($tabs, 9);
        $host->showPanel('Layout / TabView', $panel);
      },
    ]);
    $menu->addItem([
      'label' => 'ImageView',
      'action' => fn() => $host->showMain('Layout / ImageView', self::imageDemo()),
    ]);
    $menu->addItem([
      'label' => 'StyledTextBox',
      'action' => fn() => $host->showMain('Layout / StyledTextBox', self::styledTextDemo()),
    ]);
    $menu->addItem([
      'label' => 'Box',
      'action' => fn() => $host->showMain('Layout / Box', self::boxDemo()),
    ]);
    $dialog = new MenuItem('DialogPanel');
    $dialog->addItem([
      'label' => 'Normal',
      'action' => fn() => self::showDialogVariant($host, 'normal'),
    ]);
    $dialog->addItem([
      'label' => 'Warning',
      'action' => fn() => self::showDialogVariant($host, 'warning'),
    ]);
    $dialog->addItem([
      'label' => 'Error',
      'action' => fn() => self::showDialogVariant($host, 'error'),
    ]);
    $menu->addItem($dialog);
    $menu->addItem([
      'label' => 'WorkspaceBox',
      'action' => fn() => $host->showMain('Layout / WorkspaceBox', self::workspaceDemo()),
    ]);
    return $menu;
  }

  protected static function dockDemo(): Dock {
    $dock = new Dock('dock-demo');
    $dock->place(new Label('dock-top', 'Top docked row'), Place::dock('top', Len::cell(1), Len::px(2)));
    $dock->place(new ListView('dock-side', ['left', 'middle', 'right']), Place::dock('right', Len::cell(22), Len::px(2)));
    $dock->place(new TextEditor('dock-fill', "Fill area\nresizes with the window."), Place::fill());
    return $dock;
  }

  protected static function workspaceDemo(): Dock {
    $dock = new Dock('workspace-demo');
    $left = new WorkspaceBox('workspace-left');
    $left->add(new ListView('workspace-list', ['alpha', 'beta', 'gamma']));
    $right = new WorkspaceBox('workspace-right');
    $right->add(new TextEditor('workspace-editor', "Workspace focus remembers\nits active child."));
    $dock->place($left, Place::dock('left', Len::percent(35), Len::px(2)));
    $dock->place($right, Place::fill());
    return $dock;
  }

  protected static function imageDemo(): Dock {
    $dock = new Dock('image-demo');
    $dock->place(new Label('image-title', 'ImageView'), Place::dock('top', Len::cell(1), Len::px(2)));

    $side = new Dock('image-side');
    $side->place(self::sampleImage('image-contained')->setCellSize(24), Place::dock('top', Len::cell(10)));
    $side->place(new TextBlock('image-notes', "Contain keeps the source ratio.\nA second image is positioned by pixels over the workspace."), Place::fill());
    $dock->place($side, Place::dock('left', Len::cell(30), Len::px(2)));

    $workspace = new Dock('image-workspace');
    $workspace->place(new TextEditor('image-background', "Pixel-positioned ImageView\n\nResize the window; the floating image keeps its pixel box."), Place::fill());
    $workspace->place(self::sampleImage('image-floating')->setPixelSize(120, 90), Place::at(Len::px(96), Len::px(64), Len::px(120), Len::px(90)));
    $dock->place($workspace, Place::fill());
    return $dock;
  }

  protected static function boxDemo(): Dock {
    $dock = new Dock('box-demo');
    $dock->place(new Box('box-left', new TextBlock('box-left-text', "Inside border\ninsets child content."), [
      'background' => '#101820',
      'border' => 'inside',
      'borderColor' => '#00ffff',
    ]), Place::dock('left', Len::percent(50), Len::px(2)));
    $dock->place(new Box('box-right', new TextBlock('box-right-text', "Transparent background\noutside border."), [
      'background' => 'transparent',
      'border' => 'outside',
      'borderColor' => '#ffff00',
    ]), Place::fill());
    return $dock;
  }

  protected static function sampleImage(string $name): ImageView {
    $image = new ImageView($name);
    $image->setSource(dirname(__DIR__) . '/Accessories/demo.png');
    return $image;
  }

  protected static function styledTextDemo(): StyledTextBox {
    return new StyledTextBox('styled-text-demo', [
      ['text' => 'StyledTextBox', 'bold' => true, 'fontSize' => 34, 'color' => '#64ffda'],
      ['type' => 'br'],
      ['text' => 'A reusable styled text widget without HTML or CSS compatibility.'],
      ['type' => 'br'],
      ['text' => 'Inline runs can be '],
      ['text' => 'bold', 'bold' => true, 'color' => '#ffcc66'],
      ['text' => ', '],
      ['text' => 'italic', 'italic' => true, 'color' => '#ff9ab3'],
      ['text' => ', or '],
      ['text' => 'monospace', 'fontFamily' => 'monospace', 'color' => '#b7f7ff'],
      ['text' => '.'],
      ['type' => 'br'],
      ['text' => 'Font sizes share one baseline: '],
      ['text' => 'small', 'fontSize' => 16, 'color' => '#8fd6ff'],
      ['text' => ' medium', 'fontSize' => 24, 'color' => '#d8f7ff'],
      ['text' => ' large', 'fontSize' => 38, 'bold' => true, 'color' => '#f7d774'],
      ['text' => '.'],
    ], [
      'background' => '#081217',
      'borderColor' => '#12404c',
      'borderWidth' => 2,
      'padding' => 24,
      'fontFamily' => 'sans-serif',
      'fontSize' => 24,
      'color' => '#d8f7ff',
      'lineGap' => 8,
    ]);
  }

  protected static function showDialogVariant(ExampleHost $host, string $variant): void {
    $title = 'DialogPanel ' . ucfirst($variant);
    $panel = new DialogPanel('dialogpanel-' . $variant . '-example', [
      'title' => $title,
      'size' => 'small',
      'variant' => $variant,
    ]);
    $panel->addContent(new TextBlock('dialogpanel-' . $variant . '-text', self::dialogVariantText($variant)));
    $host->showPanel('Layout / DialogPanel / ' . ucfirst($variant), $panel);
  }

  protected static function dialogVariantText(string $variant): string {
    return match ($variant) {
      'warning' => 'Warning dialogs use the warning palette for recoverable problems.',
      'error' => 'Error dialogs use the error palette for blocking failures.',
      default => 'Normal dialogs demonstrate title, border, flow content, and buttons.',
    };
  }

}
