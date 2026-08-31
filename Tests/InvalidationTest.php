<?php

namespace SPTK\Tests;

use SPTK\Core\FocusManager;
use SPTK\Core\InputEvent;
use SPTK\Core\ElementContext;
use SPTK\Widgets\Dock;
use SPTK\Widgets\Label;
use SPTK\Widgets\ListView;
use SPTK\Widgets\DialogBox;
use SPTK\Widgets\TextEditor;

return [
  'element add invalidates layout focus and render' => function(): void {
    $context = new ElementContext();
    $context->clearLayout();
    $context->clearFocus();
    $context->clearRender();
    $root = new Dock('root');
    $root->setContext($context);
    $root->add(new DialogBox('dialog'));
    assertTrue($context->layoutDirty(), 'Adding an element should invalidate layout.');
    assertTrue($context->focusDirty(), 'Adding an element should invalidate focus.');
    assertTrue($context->renderDirty(), 'Adding an element should invalidate render.');
  },

  'focus manager rebuild sees dynamic elements' => function(): void {
    $root = new Dock('root');
    $first = new ListView('first', ['a']);
    $root->add($first);
    $focus = new FocusManager($root);
    assertSame('first', $focus->current()?->name(), 'Initial focus should use first focusable element.');
    $root->add(new TextEditor('second'));
    $focus->rebuild();
    $focus->dispatch(InputEvent::key('Tab'));
    assertSame('second', $focus->current()?->name(), 'Rebuilt focus manager should include dynamic element.');
  },

  'state changes invalidate render' => function(): void {
    $context = new ElementContext();
    $context->clearRender();
    $label = new Label('label', 'old');
    $label->setContext($context);
    $label->setText('new');
    assertTrue($context->renderDirty(), 'Changing label text should invalidate render.');
  },
];
