<?php

namespace SPTK\Tests;

use SPTK\Core\InputAction;
use SPTK\Core\InputEvent;

return [
  'input action normalizes key aliases' => function(): void {
    assertSame('Enter', InputAction::normalizedKey('Return'), 'Return should normalize to Enter.');
    assertSame('Enter', InputAction::normalizedKey('KeypadEnter'), 'Keypad Enter should normalize to Enter.');
    assertSame('Down', InputAction::normalizedKey('KeypadDown'), 'Keypad Down should normalize to Down.');
    assertSame('PageDown', InputAction::normalizedKey('PgDn'), 'PgDn should normalize to PageDown.');
    assertSame('A', InputAction::normalizedKey('a'), 'Single-character keys should normalize to uppercase.');
  },

  'input action matches editing shortcuts' => function(): void {
    assertSame(true, InputAction::copy(InputEvent::key('c', ['ctrl' => true])), 'Ctrl+C should match copy.');
    assertSame(true, InputAction::copy(InputEvent::key('Insert', ['ctrl' => true])), 'Ctrl+Insert should match copy.');
    assertSame(true, InputAction::paste(InputEvent::key('Insert', ['shift' => true])), 'Shift+Insert should match paste.');
    assertSame(true, InputAction::selectAll(InputEvent::key('a', ['ctrl' => true])), 'Ctrl+A should match select all.');
    assertSame(false, InputAction::copy(InputEvent::key('c')), 'C without Ctrl should not match copy.');
  },

  'input action matches contextual activation shortcuts' => function(): void {
    assertSame(true, InputAction::activate(InputEvent::key('Return'), 'button'), 'Return should activate button-like widgets.');
    assertSame(true, InputAction::select(InputEvent::key('Space'), 'list'), 'Space should select list-like widgets.');
    assertSame(true, InputAction::newline(InputEvent::key('KeypadEnter'), 'editor'), 'Keypad Enter should match editor newline.');
    assertSame(true, InputAction::cancel(InputEvent::key('Esc'), 'dialog'), 'Esc should match cancel.');
    assertSame(true, InputAction::confirm(InputEvent::key('Return', ['ctrl' => true]), 'dialog'), 'Ctrl+Return should match dialog confirm.');
  },

  'input action matches table selection shortcuts' => function(): void {
    assertSame(true, InputAction::selectRow(InputEvent::key('Space', ['shift' => true]), 'table'), 'Shift+Space should select a table row.');
    assertSame(true, InputAction::selectColumn(InputEvent::key('Space', ['ctrl' => true]), 'table'), 'Ctrl+Space should select a table column.');
    assertSame(false, InputAction::selectColumn(InputEvent::key('Space'), 'table'), 'Space without Ctrl should not select a table column.');
  },
];
