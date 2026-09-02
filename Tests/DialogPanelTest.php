<?php

namespace SPTK\Tests;

use SPTK\Core\Clipboard;
use SPTK\Core\FocusManager;
use SPTK\Core\GridBuffer;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Widgets\Button;
use SPTK\Widgets\DialogLayer;
use SPTK\Widgets\DialogPanel;
use SPTK\Widgets\FlowRow;
use SPTK\Widgets\Label;
use SPTK\Widgets\ListView;
use SPTK\Widgets\MenuBar;
use SPTK\Widgets\TableView;
use SPTK\Widgets\TextBlock;
use SPTK\Widgets\TextEditor;

return [
  'dialog panel centers with preset width and measured height' => function(): void {
    $layer = new DialogLayer('dialogs');
    $panel = new DialogPanel('panel', ['title' => 'Settings']);
    $panel->addContent(new Label('message', 'Hello'));
    $layer->push($panel);
    $layer->setFrame(new Rect(0, 0, 40, 12));

    assertSame(8, $panel->frame()->x, 'Normal dialog should center at 60% width.');
    assertSame(3, $panel->frame()->y, 'Measured dialog should center vertically.');
    assertSame(24, $panel->frame()->width, 'Normal dialog should use 60% of the container width.');
    assertSame(6, $panel->frame()->height, 'Dialog height should include border, title, content, and padding rows.');

    $buffer = new GridBuffer(40, 12);
    $layer->render($buffer);
    assertSame('        +----------------------+        ', $buffer->line(3), 'Dialog border should render at the centered frame.');
    assertSame('        |       Settings       |        ', $buffer->line(4), 'Dialog title should be centered inside the title row.');
    assertSame('        |                      |        ', $buffer->line(5), 'A padding row should separate the title from content.');
    assertSame('        | Hello                |        ', $buffer->line(6), 'Content should be inset by one cell.');
  },

  'dialog panel can request exact content columns' => function(): void {
    $layer = new DialogLayer('dialogs');
    $panel = new DialogPanel('panel', ['title' => 'Exact', 'contentColumns' => 20]);
    $panel->addContent(new Label('message', 'Hello'));
    $layer->push($panel);
    $layer->setFrame(new Rect(0, 0, 60, 12));

    assertSame(18, $panel->frame()->x, 'Exact content dialogs should still be centered.');
    assertSame(24, $panel->frame()->width, 'Dialog width should include exact content columns plus panel padding and border.');
    assertSame(20, $panel->content()->frame()->width, 'Dialog content width should match requested exact content columns.');
  },

  'dialog panel can size to window minus margin cells' => function(): void {
    $layer = new DialogLayer('dialogs');
    $panel = new DialogPanel('panel', ['title' => 'Large', 'windowMarginCells' => 6]);
    $panel->addContent(new TextEditor('editor', 'Body'), 10);
    $layer->push($panel);
    $layer->setFrame(new Rect(0, 0, 80, 24));

    assertSame(3, $panel->frame()->x, 'Window-margin dialog should leave half the margin on the left.');
    assertSame(3, $panel->frame()->y, 'Window-margin dialog should leave half the margin above.');
    assertSame(74, $panel->frame()->width, 'Window-margin dialog should track the container width.');
    assertSame(18, $panel->frame()->height, 'Window-margin dialog should track the container height.');

    $layer->setFrame(new Rect(0, 0, 120, 40));

    assertSame(3, $panel->frame()->x, 'Window-margin dialog should remain centered after resize.');
    assertSame(3, $panel->frame()->y, 'Window-margin dialog should remain centered vertically after resize.');
    assertSame(114, $panel->frame()->width, 'Window-margin dialog should grow with the container width.');
    assertSame(34, $panel->frame()->height, 'Window-margin dialog should grow with the container height.');
  },

  'dialog panel reserves padded bottom button row' => function(): void {
    $panel = new DialogPanel('panel', ['title' => 'Confirm']);
    $panel->addContent(new Label('message', 'Continue?'));
    $ok = new Button('ok', 'OK');
    $cancel = new Button('cancel', 'Cancel');
    $panel->addButton($ok)->addButton($cancel);
    $panel->setFrame(new Rect(0, 0, 24, $panel->preferredRows()));

    assertSame(9, $panel->preferredRows(), 'Buttons should add a content gap and two-row button area plus panel padding.');
    assertSame(2, $ok->frame()->x, 'Buttons should be inset by the panel padding.');
    assertSame(20, $panel->content()->frame()->width, 'Content should wrap before the right padding cell.');
    assertSame(6, $ok->frame()->y, 'Buttons should render after the content gap and button spacer row.');
    assertSame(6, $cancel->frame()->y, 'All buttons should share the same row.');
    assertSame(1, $ok->frame()->height, 'Buttons should keep one row of height.');
  },

  'dialog panel keeps two rows between expanding content and buttons' => function(): void {
    $panel = new DialogPanel('panel', ['title' => 'Edit']);
    $panel->addContent(new TextEditor('editor', 'Body'), \SPTK\Core\Place::cursor(\SPTK\Core\Len::percent(100)));
    $ok = new Button('ok', 'OK');
    $panel->addButton($ok);
    $panel->setFrame(new Rect(0, 0, 40, 16));

    assertSame(2, $ok->frame()->y - $panel->content()->frame()->bottom(), 'Expanding content should leave two rows before the button row.');
    assertSame(1, $panel->frame()->bottom() - ($ok->frame()->y + $ok->frame()->height) - 1, 'Button row should leave one row before the bottom border.');
  },

  'dialog panel interior keeps compact dialogs padded below buttons' => function(): void {
    $panel = new DialogPanel('panel', ['title' => 'Edit']);
    $panel->addContent(new \SPTK\Widgets\Input('input', 'Body'));
    $ok = new Button('ok', 'OK');
    $panel->addButton($ok);
    $panel->setFrame(new Rect(0, 0, 40, $panel->preferredRowsForColumns(40) - 2));
    $panel->layoutInterior();

    assertSame(2, $ok->frame()->y - $panel->content()->frame()->bottom(), 'Interior compact content should leave two rows before the button row.');
    assertSame(1, $panel->frame()->bottom() - ($ok->frame()->y + $ok->frame()->height), 'Interior compact dialogs should leave one row below the button row.');
  },

  'dialog panel interior lets window margin dialogs use outer edge padding' => function(): void {
    $panel = new DialogPanel('panel', ['title' => 'Edit', 'windowMarginCells' => 6]);
    $panel->addContent(new TextEditor('editor', 'Body'), \SPTK\Core\Place::cursor(\SPTK\Core\Len::percent(100)));
    $ok = new Button('ok', 'OK');
    $panel->addButton($ok);
    $panel->setFrame(new Rect(0, 0, 40, 16));
    $panel->layoutInterior();

    assertSame(2, $ok->frame()->y - $panel->content()->frame()->bottom(), 'Window-margin interior content should leave two rows before the button row.');
    assertSame(0, $panel->frame()->bottom() - ($ok->frame()->y + $ok->frame()->height), 'Window-margin interior button row should rely on the outer pixel border for bottom edge padding.');
  },

  'single dialog button centers in the button row' => function(): void {
    $panel = new DialogPanel('panel', ['title' => 'Confirm']);
    $ok = new Button('ok', 'OK');
    $panel->addButton($ok);
    $panel->setFrame(new Rect(0, 0, 24, $panel->preferredRows()));

    assertSame(9, $ok->frame()->x, 'A single dialog button should be centered in the padded button row.');
  },

  'dialog text block wraps to padded content width' => function(): void {
    $panel = new DialogPanel('panel', ['title' => 'Message']);
    $panel->addContent(new TextBlock('message', 'alpha beta gamma delta'));
    $panel->setFrame(new Rect(0, 0, 16, $panel->preferredRowsForColumns(16)));
    $buffer = new GridBuffer(16, $panel->frame()->height);
    $panel->render($buffer);

    assertSame(7, $panel->frame()->height, 'Wrapped text should increase measured dialog height.');
    assertSame('| alpha beta   |', $buffer->line(3), 'First text row should wrap within the padded content width.');
    assertSame('| gamma delta  |', $buffer->line(4), 'Second text row should continue inside the padded content width.');
  },

  'text block marks clipped wrapped content' => function(): void {
    $text = new TextBlock('message', 'alpha beta gamma delta');
    $text->setFrame(new Rect(0, 0, 10, 2));
    $buffer = new GridBuffer(10, 2);
    $text->render($buffer);

    assertSame('alpha beta', $buffer->line(0), 'Clipped text block should leave the final row for the marker.');
    assertSame('--- vvv --', $buffer->line(1), 'Clipped text block should center the continuation marker on its own row.');
    assertSame('#00ffff', $buffer->cell(0, 1)?->fg->hex(), 'Text block continuation marker should use marker foreground.');
  },

  'dialog variants and active state choose border colors' => function(): void {
    $normal = new DialogPanel('normal', ['title' => 'Normal']);
    $error = new DialogPanel('error', ['title' => 'Error', 'variant' => 'error']);
    $normal->setFrame(new Rect(0, 0, 12, 4));
    $error->setFrame(new Rect(0, 0, 12, 4));
    $normalBuffer = new GridBuffer(12, 4);
    $errorBuffer = new GridBuffer(12, 4);

    $normal->render($normalBuffer);
    $error->setActive(true)->render($errorBuffer);

    assertSame('#226622', $normalBuffer->cell(0, 0)?->fg->hex(), 'Inactive normal panel should use dark green normal border.');
    assertSame('#999999', $normalBuffer->cell(1, 2)?->bg->hex(), 'Inactive normal panel should use dimmed content background.');
    assertSame('#cc0000', $errorBuffer->cell(0, 0)?->fg->hex(), 'Active error panel should use bright error border.');
    assertSame('#ffaaaa', $errorBuffer->cell(1, 2)?->bg->hex(), 'Error panel content background should use error color.');
  },

  'dialog text editor uses panel input field colors' => function(): void {
    $panel = new DialogPanel('panel');
    $panel->addContent(new TextEditor('editor', 'abc'), 2);
    $panel->setFrame(new Rect(0, 0, 12, $panel->preferredRowsForColumns(12)));
    $buffer = new GridBuffer(12, $panel->frame()->height);
    $panel->render($buffer);

    assertSame('#000000', $buffer->cell(3, 2)?->fg->hex(), 'Dialog editor text should use black foreground.');
    assertSame('#226622', $buffer->cell(3, 2)?->bg->hex(), 'Dialog editor text should use the panel input field background.');
    assertSame('#226622', $buffer->cell(2, 3)?->bg->hex(), 'Dialog editor empty space should use the panel input field background.');
  },

  'dialog list view uses panel input field background' => function(): void {
    $panel = new DialogPanel('panel');
    $panel->addContent(new ListView('list', [
      'main',
      ['text' => 'analytics', 'selectable' => 'database', 'selected' => true],
    ]), 3);
    $panel->setFrame(new Rect(0, 0, 12, $panel->preferredRowsForColumns(12)));
    $buffer = new GridBuffer(12, $panel->frame()->height);
    $panel->render($buffer);

    assertSame('#ffffff', $buffer->cell(3, 3)?->fg->hex(), 'Dialog list markers should use white foreground.');
    assertSame('#000000', $buffer->cell(5, 3)?->fg->hex(), 'Dialog list text should use black foreground.');
    assertSame('#226622', $buffer->cell(5, 3)?->bg->hex(), 'Dialog list rows should use the panel input field background.');
    assertSame('#226622', $buffer->cell(2, 4)?->bg->hex(), 'Dialog list empty space should use the panel input field background.');
  },

  'dialog table view uses panel input field background' => function(): void {
    $panel = new DialogPanel('panel');
    $panel->addContent(new TableView('table', ['id', 'name'], [
      [1, 'Ada'],
      [2, 'Grace'],
    ], [4, 8]), 4);
    $panel->setFrame(new Rect(0, 0, 18, $panel->preferredRowsForColumns(18)));
    $buffer = new GridBuffer(18, $panel->frame()->height);
    $panel->render($buffer);

    assertSame('#aaaaaa', $buffer->cell(2, 2)?->bg->hex(), 'Dialog table header should keep the table header background.');
    assertSame('#555555', $buffer->cell(2, 3)?->bg->hex(), 'Dialog table active cursor cell should keep the cursor background.');
    assertSame('#226622', $buffer->cell(2, 4)?->bg->hex(), 'Dialog table body cells should use the panel input field background.');
    assertSame('#226622', $buffer->cell(2, 5)?->bg->hex(), 'Dialog table empty body space should use the panel input field background.');
  },

  'dialog layer marks only the top panel active' => function(): void {
    $layer = new DialogLayer('dialogs');
    $first = new DialogPanel('first', ['title' => 'First']);
    $second = new DialogPanel('second', ['title' => 'Second']);
    $layer->push($first)->push($second);

    assertSame(false, $first->isActive(), 'Lower panel should be inactive.');
    assertSame(true, $second->isActive(), 'Top panel should be active.');
    $layer->pop();
    assertSame(true, $first->isActive(), 'Previous panel should become active after pop.');
  },

  'modal dialog focus is limited to the top panel' => function(): void {
    $root = new \SPTK\Widgets\Dock('root');
    $background = new TextEditor('background', '');
    $dialogs = new DialogLayer('dialogs');
    $first = new DialogPanel('first', ['title' => 'First']);
    $first->addContent(new ListView('first-list', ['one']));
    $second = new DialogPanel('second', ['title' => 'Second']);
    $second->addContent(new TextEditor('second-editor', ''));
    $root->fill($background);
    $root->fill($dialogs);
    $dialogs->push($first)->push($second);
    $root->setFrame(new Rect(0, 0, 60, 20));

    $focus = new FocusManager($root);
    assertSame('second-editor', $focus->current()?->name(), 'Only top dialog descendants should be focusable.');
    $focus->dispatch(InputEvent::key('Tab'));
    assertSame('second-editor', $focus->current()?->name(), 'Tab should stay inside the top dialog.');
  },

  'modal dialog blocks background menu shortcuts' => function(): void {
    $opened = false;
    $root = new \SPTK\Widgets\Dock('root');
    $menu = new MenuBar('menu', [
      [
        'label' => 'File',
        'action' => function() use (&$opened): void {
          $opened = true;
        },
      ],
    ]);
    $dialogs = new DialogLayer('dialogs');
    $panel = new DialogPanel('panel', ['title' => 'Modal']);
    $panel->addContent(new TextEditor('editor', ''));
    $dialogs->push($panel);
    $root->dock($menu, 'top');
    $root->fill(new TextEditor('background', ''));
    $root->add($dialogs);
    $root->setFrame(new Rect(0, 0, 60, 12));

    $focus = new FocusManager($root);
    assertSame('editor', $focus->current()?->name(), 'Modal editor should own focus.');
    assertSame(false, $focus->dispatch(InputEvent::key('F1')), 'F1 should not reach the background menu while a dialog is open.');
    assertSame(false, $opened, 'Background menu callback should not run while a dialog is open.');
  },

  'ctrl enter activates the first dialog button' => function(): void {
    $pressed = '';
    $root = new \SPTK\Widgets\Dock('root');
    $dialogs = new DialogLayer('dialogs');
    $panel = new DialogPanel('panel', ['title' => 'Confirm']);
    $panel->addContent(new TextEditor('editor', ''));
    $first = new Button('first', 'First');
    $first->setOnPress(function() use (&$pressed): void {
      $pressed = 'first';
    });
    $second = new Button('second', 'Second');
    $second->setOnPress(function() use (&$pressed): void {
      $pressed = 'second';
    });
    $panel->addButton($first)->addButton($second);
    $dialogs->push($panel);
    $root->add($dialogs);
    $root->setFrame(new Rect(0, 0, 60, 12));

    $focus = new FocusManager($root);
    assertSame(true, $focus->dispatch(InputEvent::key('Enter', ['ctrl' => true])), 'Ctrl+Enter should activate the default dialog button.');
    assertSame('first', $pressed, 'The first dialog button should be the default button.');
  },

  'ctrl c copies dialog text blocks' => function(): void {
    Clipboard::setProvider(null);
    Clipboard::set('');
    $root = new \SPTK\Widgets\Dock('root');
    $dialogs = new DialogLayer('dialogs');
    $panel = new DialogPanel('panel', ['title' => 'Warning', 'variant' => 'warning']);
    $panel->addContent(new TextBlock('first-message', "First line\nSecond line"));
    $panel->addContent(new Label('label', 'Not copied'));
    $panel->addContent(new TextBlock('second-message', 'More detail'));
    $panel->addButton(new Button('close', 'Close'));
    $dialogs->push($panel);
    $root->add($dialogs);
    $root->setFrame(new Rect(0, 0, 60, 12));

    $focus = new FocusManager($root);
    assertSame(true, $focus->dispatch(InputEvent::key('c', ['ctrl' => true])), 'Ctrl+C should be handled by dialog text copy.');
    assertSame("First line\nSecond line\n\nMore detail", Clipboard::get(), 'Dialog copy should concatenate TextBlocks with a blank line.');
  },

  'escape closes closeable dialog by default' => function(): void {
    $root = new \SPTK\Widgets\Dock('root');
    $dialogs = new DialogLayer('dialogs');
    $panel = new DialogPanel('panel', ['title' => 'Close']);
    $panel->addContent(new TextEditor('editor', ''));
    $dialogs->push($panel);
    $root->add($dialogs);
    $root->setFrame(new Rect(0, 0, 60, 12));

    $focus = new FocusManager($root);
    assertSame(true, $focus->dispatch(InputEvent::key('Escape')), 'Escape should close a closeable dialog.');
    assertSame(null, $dialogs->top(), 'Closed dialog should be removed from the dialog layer.');
  },

  'dialog close callback runs when closed with escape' => function(): void {
    $closed = false;
    $root = new \SPTK\Widgets\Dock('root');
    $dialogs = new DialogLayer('dialogs');
    $panel = new DialogPanel('panel', [
      'title' => 'Close',
      'onClose' => function(DialogPanel $panel) use (&$closed): void {
        $closed = $panel->name() === 'panel';
      },
    ]);
    $panel->addContent(new TextEditor('editor', ''));
    $dialogs->push($panel);
    $root->add($dialogs);
    $root->setFrame(new Rect(0, 0, 60, 12));

    $focus = new FocusManager($root);
    assertSame(true, $focus->dispatch(InputEvent::key('Escape')), 'Escape should close a closeable dialog.');
    assertSame(true, $closed, 'Dialog close callback should run after Escape closes the panel.');
  },

  'escape respects non closeable dialog option' => function(): void {
    $root = new \SPTK\Widgets\Dock('root');
    $dialogs = new DialogLayer('dialogs');
    $panel = new DialogPanel('panel', ['title' => 'Pinned', 'closeable' => false]);
    $panel->addContent(new TextEditor('editor', ''));
    $dialogs->push($panel);
    $root->add($dialogs);
    $root->setFrame(new Rect(0, 0, 60, 12));

    $focus = new FocusManager($root);
    assertSame(false, $focus->dispatch(InputEvent::key('Escape')), 'Escape should not close a non-closeable dialog.');
    assertSame($panel, $dialogs->top(), 'Non-closeable dialog should stay in the dialog layer.');
  },

  'button handles keyboard activation' => function(): void {
    $pressed = 0;
    $button = new Button('ok', 'OK');
    assertSame(null, $button->hotKey(), 'Buttons should not have a hotkey by default.');
    $button->setOnPress(function() use (&$pressed): void {
      $pressed++;
    });

    assertSame(true, $button->handle(InputEvent::key('Enter')), 'Enter should activate a button.');
    assertSame(true, $button->handle(InputEvent::key('Space')), 'Space should activate a button.');
    assertSame(true, $button->handle(InputEvent::key('Return')), 'Return should activate a button.');
    assertSame(3, $pressed, 'Button callback should run for each activation.');
  },

  'button hotkey renders only the function number in blue' => function(): void {
    $button = new Button('ok', 'OK');
    $button->setHotKey('F12');
    $button->setFrame(new Rect(0, 0, 10, 1));
    $buffer = new GridBuffer(10, 1);
    $button->render($buffer);

    assertSame('[ 12 OK ] ', $buffer->line(0), 'Button should render the function number inside the brackets.');
    assertSame('#000000', $buffer->cell(0, 0)?->fg->hex(), 'Opening bracket should use the normal button foreground.');
    assertSame('#0000ff', $buffer->cell(2, 0)?->fg->hex(), 'Button hotkey number should use the hotkey color.');
    assertSame('#0000ff', $buffer->cell(3, 0)?->fg->hex(), 'Both digits of a two-digit hotkey should use the hotkey color.');
    assertSame('#000000', $buffer->cell(4, 0)?->fg->hex(), 'Separator and label should use the normal button foreground.');
  },

  'dialog button hotkey activates matching button only' => function(): void {
    $pressed = '';
    $root = new \SPTK\Widgets\Dock('root');
    $dialogs = new DialogLayer('dialogs');
    $panel = new DialogPanel('panel', ['title' => 'Confirm']);
    $first = new Button('first', 'First');
    $first->setHotKey('F1')->setOnPress(function() use (&$pressed): void {
      $pressed = 'first';
    });
    $second = new Button('second', 'Second');
    $second->setHotKey('F2')->setOnPress(function() use (&$pressed): void {
      $pressed = 'second';
    });
    $panel->addButton($first)->addButton($second);
    $dialogs->push($panel);
    $root->add($dialogs);
    $root->setFrame(new Rect(0, 0, 60, 12));

    $focus = new FocusManager($root);
    assertSame(true, $focus->dispatch(InputEvent::key('F2')), 'Dialog should handle configured button hotkeys.');
    assertSame('second', $pressed, 'F2 should activate the second button.');
    assertSame(false, $focus->dispatch(InputEvent::key('F3')), 'Unconfigured F-key should not be handled by the dialog.');
    assertSame('second', $pressed, 'Unconfigured F-key should not activate any button.');
  },

  'flow row can justify buttons across a row' => function(): void {
    $row = new FlowRow('buttons', 'justify');
    $ok = new Button('ok', 'OK');
    $cancel = new Button('cancel', 'Cancel');
    $row->place($ok)->place($cancel);
    $row->setFrame(new Rect(0, 0, 30, 1));

    assertSame(0, $ok->frame()->x, 'First justified child should start at the row left edge.');
    assertSame(20, $cancel->frame()->x, 'Second justified child should be pushed to the far side.');
  },

  'dialog layer renders pixel-centered surfaces' => function(): void {
    $layer = new DialogLayer('dialogs');
    $panel = new DialogPanel('panel', ['title' => 'Pixels']);
    $panel->addContent(new Label('label', 'Body'));
    $layer->push($panel);
    $layer->setFrame(new Rect(0, 0, 80, 20));
    $target = new SurfaceProbeTarget(new Rect(0, 0, 800, 460), 10, 23);

    $layer->render($target);

    assertSame([163, 182, 474, 96, '#cccccc'], $target->pixelFills[0], 'Dialog background should fill the exact outer panel pixels.');
    assertSame([163, 182, 474, 2, '#338833'], $target->pixelFills[1], 'Dialog top border should start on the outer panel pixel.');
    assertSame([165, 184, 470, 92, 47, 4, '#cccccc', 'top-left'], $target->surfaces[0], 'Dialog content surface should be inset by the 2px border.');
  },

  'dialog layer dims background panels' => function(): void {
    $layer = new DialogLayer('dialogs');
    $first = new DialogPanel('first', ['title' => 'First']);
    $second = new DialogPanel('second', ['title' => 'Second']);
    $first->addContent(new Label('first-label', 'First'));
    $second->addContent(new Label('second-label', 'Second'));
    $layer->push($first)->push($second);
    $layer->setFrame(new Rect(0, 0, 80, 20));
    $target = new SurfaceProbeTarget(new Rect(0, 0, 800, 460), 10, 23);

    $layer->render($target);

    assertSame('#999999', $target->pixelFills[0][4], 'Lower dialog outer panel should use dimmed background.');
    assertSame('#999999', $target->surfaces[0][6], 'Lower dialog content surface should use dimmed background.');
    assertSame('#cccccc', $target->pixelFills[5][4], 'Top dialog outer panel should keep the active background.');
    assertSame('#cccccc', $target->surfaces[1][6], 'Top dialog content surface should keep the active background.');
  },

  'dialog layer renders window margin panels against surface size' => function(): void {
    $layer = new DialogLayer('dialogs');
    $panel = new DialogPanel('panel', ['title' => 'Large', 'windowMarginCells' => 6]);
    $panel->addContent(new TextEditor('editor', 'Body'), 10);
    $layer->push($panel);
    $layer->setFrame(new Rect(0, 0, 80, 20));
    $target = new SurfaceProbeTarget(new Rect(0, 0, 800, 460), 10, 23);

    $layer->render($target);

    assertSame([30, 69, 740, 322, '#cccccc'], $target->pixelFills[0], 'Window-margin pixel panel should leave three cells horizontally and vertically.');
    assertSame([32, 71, 736, 318, 73, 13, '#cccccc', 'top-left'], $target->surfaces[0], 'Window-margin content surface should grow from the current pixel surface.');
  },

  'absolute dialog layer overlays dock content without a full layer surface' => function(): void {
    $root = new \SPTK\Widgets\Dock('root');
    $root->fill(new Label('body', 'body'));
    $layer = new DialogLayer('dialogs');
    $panel = new DialogPanel('panel', ['title' => 'Overlay']);
    $panel->addContent(new Label('message', 'Message'));
    $layer->push($panel);
    $root->add($layer);
    $root->setFrame(new Rect(0, 0, 80, 20));
    $target = new SurfaceProbeTarget(new Rect(0, 0, 800, 460), 10, 23);

    assertSame([0, 0, 80, 20], [$layer->frame()->x, $layer->frame()->y, $layer->frame()->width, $layer->frame()->height], 'Absolute dialog layer should keep the root logical frame.');
    assertSame([16, 7, 48, 6], [$panel->frame()->x, $panel->frame()->y, $panel->frame()->width, $panel->frame()->height], 'Dialog children should be laid out before input dispatch.');

    $root->render($target);

    assertSame([0, 0, 800, 460, 80, 20, '#0000aa', 'center'], $target->surfaces[0], 'Dock content should render as the first full surface.');
    assertSame([165, 184, 470, 92, 47, 4, '#cccccc', 'top-left'], $target->surfaces[1], 'Dialog panel content should render directly over the content inside the pixel border.');
    assertSame(2, count($target->surfaces), 'Dialog layer itself should not clear a full overlay surface.');
  },
];
