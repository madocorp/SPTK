<?php

namespace SPTK\Tests;

use SPTK\Core\ElementContext;
use SPTK\Core\FocusManager;
use SPTK\Core\GridBuffer;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Widgets\DateCalendarGrid;
use SPTK\Widgets\DatePicker;
use SPTK\Widgets\DateSelector;
use SPTK\Widgets\DateSelectorPickerPanel;
use SPTK\Widgets\DialogLayer;
use SPTK\Widgets\Dock;

return [
  'date picker renders monday first calendar' => function(): void {
    $picker = new DatePicker('date', '2026-08-21');
    $picker->setFrame(new Rect(0, 0, 28, 8));
    $buffer = new GridBuffer(28, 8);
    $picker->render($buffer);

    assertSame('August     ...', substr($buffer->line(0), 0, 14), 'DatePicker should render the selected month control across half the panel.');
    assertSame('2026       ...', substr($buffer->line(0), 14, 14), 'DatePicker should render the selected year control across half the panel.');
    assertSame(' Mo  Tu  We  Th  Fr  Sa  Su', rtrim($buffer->line(1)), 'DatePicker should render Monday as the first weekday with two spaces between columns.');
    assertSame('2', $buffer->cell(17, 5)?->glyph, 'Selected Friday should render in the Friday column.');
    assertSame('#555555', $buffer->cell(16, 5)?->bg->hex(), 'Unfocused selected date should highlight the left padding cell.');
    assertSame('#555555', $buffer->cell(19, 5)?->bg->hex(), 'Unfocused selected date should highlight the right padding cell.');
  },

  'date picker keyboard navigation updates value' => function(): void {
    $picker = new DatePicker('date', '2026-08-21');
    $root = new Dock('root');
    $root->fill($picker);
    $root->setFrame(new Rect(0, 0, 28, 8));
    $focus = new FocusManager($root);
    assertInstanceOf(DateCalendarGrid::class, $focus->current(), 'DatePicker should focus the calendar grid.');

    $focus->dispatch(InputEvent::key('Right'));
    assertSame('2026-08-22', $picker->getValue(), 'Right should move to the next day.');
    $focus->dispatch(InputEvent::key('Down'));
    assertSame('2026-08-29', $picker->getValue(), 'Down should move one week forward.');
    $focus->dispatch(InputEvent::key('PageUp'));
    assertSame('2026-07-29', $picker->getValue(), 'PageUp should move one month back.');
    $focus->dispatch(InputEvent::key('PageDown', ['ctrl' => true]));
    assertSame('2027-07-29', $picker->getValue(), 'Ctrl+PageDown should move one year forward.');
  },

  'date picker arrows stay within visible month' => function(): void {
    $picker = new DatePicker('date', '2026-08-31');
    $grid = $picker->grid();
    $grid->handle(InputEvent::key('Right'));
    assertSame('2026-08-31', $picker->getValue(), 'Right should not move from the last day into the next month.');
    $grid->handle(InputEvent::key('PageDown'));
    assertSame('2026-09-30', $picker->getValue(), 'PageDown should move to the next month.');
    $grid->handle(InputEvent::key('Left'));
    assertSame('2026-09-29', $picker->getValue(), 'Left should still move within the visible month.');

    $picker->setValue('2026-09-01');
    $grid->handle(InputEvent::key('Left'));
    assertSame('2026-09-01', $picker->getValue(), 'Left should not move from the first day into the previous month.');
  },

  'date picker month and year selectors update value' => function(): void {
    $picker = new DatePicker('date', '2026-01-31');
    $picker->monthSelector()->setValue(2);
    assertSame('2026-02-28', $picker->getValue(), 'Changing month should clamp the selected day to the target month.');
    $picker->yearSelector()->setValue(2028);
    assertSame('2028-02-28', $picker->getValue(), 'Changing year should keep the selected month and day when valid.');
  },

  'date picker month selector items use numeric values' => function(): void {
    $picker = new DatePicker('date', '2026-08-21');
    $picker->monthSelector()->setValue(2);

    assertSame(2, $picker->monthSelector()->getValue(), 'Month selector should store the numeric month value.');
    assertSame('2026-02-21', $picker->getValue(), 'Selecting February should not cast the month label to zero.');
  },

  'date picker rejects invalid date values' => function(): void {
    $picker = new DatePicker('date', '2026-08-21');
    $thrown = false;
    try {
      $picker->setValue('2026-02-30');
    } catch (\InvalidArgumentException) {
      $thrown = true;
    }

    assertSame(true, $thrown, 'DatePicker should reject impossible ISO dates.');
    assertSame('2026-08-21', $picker->getValue(), 'Rejecting an invalid date should leave the previous value intact.');
  },

  'date selector input ignores invalid dates until valid' => function(): void {
    $body = new DateSelectorPickerPanel('date-body', '2026-08-21');
    $body->setFrame(new Rect(0, 0, 28, 9));
    $input = $body->input();
    $input->setCursorPosition(10);
    foreach (array_fill(0, 10, InputEvent::key('Backspace')) as $event) {
      $input->handle($event);
    }
    foreach (str_split('2026-02-30') as $char) {
      $input->handle(InputEvent::text($char));
    }
    assertSame(28, $input->frame()->width, 'Date selector free input should fill the panel width.');
    assertSame('2026-08-21', $body->getValue(), 'Invalid free input should not update the date value.');
    assertSame('2026-02-30', $input->text(), 'Invalid free input should remain visible while editing.');

    $input->setCursorPosition(10);
    foreach (array_fill(0, 10, InputEvent::key('Backspace')) as $event) {
      $input->handle($event);
    }
    foreach (str_split('2026-02-28') as $char) {
      $input->handle(InputEvent::text($char));
    }
    assertSame('2026-02-28', $body->getValue(), 'Valid free input should update the date value.');
    assertSame('2026-02-28', $body->picker()->getValue(), 'Valid free input should update the picker.');
  },

  'date selector commits picker value with ok' => function(): void {
    $context = new ElementContext();
    $root = new Dock('root');
    $selector = new DateSelector('date', '2026-08-21');
    $dialogs = new DialogLayer('dialogs');
    $root->fill($selector);
    $root->add($dialogs);
    $root->setContext($context);
    $root->setFrame(new Rect(0, 0, 40, 16));
    $focus = new FocusManager($root);

    $focus->dispatch(InputEvent::key('Enter'));
    assertInstanceOf(DateCalendarGrid::class, $focus->current(), 'DateSelector should open with the calendar focused.');
    $focus->dispatch(InputEvent::key('Right'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Enter'));

    assertSame('2026-08-22', $selector->getValue(), 'OK should commit the picker date value.');
    assertSame(null, $dialogs->top(), 'Date selector panel should close on OK.');
    assertSame($selector, $focus->current(), 'Focus should return to the date selector.');
  },

  'date selector today button updates without closing' => function(): void {
    $context = new ElementContext();
    $root = new Dock('root');
    $selector = new DateSelector('date', '2000-01-01');
    $dialogs = new DialogLayer('dialogs');
    $root->fill($selector);
    $root->add($dialogs);
    $root->setContext($context);
    $root->setFrame(new Rect(0, 0, 40, 16));
    $focus = new FocusManager($root);

    $focus->dispatch(InputEvent::key('Enter'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Enter'));
    $body = $dialogs->top()?->content()->children()[0] ?? null;

    assertInstanceOf(DateSelectorPickerPanel::class, $body, 'Date selector panel should stay open after Today.');
    assertSame(date('Y-m-d'), $body->getValue(), 'Today should update the pending picker value.');
    assertSame('2000-01-01', $selector->getValue(), 'Today should not commit until OK.');
  },

  'date selector cancel keeps current value' => function(): void {
    $context = new ElementContext();
    $root = new Dock('root');
    $selector = new DateSelector('date', '2026-08-21');
    $dialogs = new DialogLayer('dialogs');
    $root->fill($selector);
    $root->add($dialogs);
    $root->setContext($context);
    $root->setFrame(new Rect(0, 0, 40, 16));
    $focus = new FocusManager($root);

    $focus->dispatch(InputEvent::key('Enter'));
    $focus->dispatch(InputEvent::key('Right'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Tab'));
    $focus->dispatch(InputEvent::key('Enter'));

    assertSame('2026-08-21', $selector->getValue(), 'Cancel should leave the date selector value unchanged.');
    assertSame(null, $dialogs->top(), 'Date selector panel should close on Cancel.');
  },
];
