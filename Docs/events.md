# Events

Events connect XML declarations to static PHP methods. A method receives an `SPTK\Events\EventContext` with the
event `type`, its source `widget` (if any), and native SDL `input` (for raw input events).

## Declaring events

Put screen-level `<Event>` declarations inside the screen's `<Screen>` element. Put widget-level declarations
inside that widget's element. For example:

```xml
<Screen>
  <Event type="keyDown" key="ctrl+a" action="Controller::selectAll" />
  <Layout direction="vertical">
    <Text height="1">
      Search
      <Event type="activate" action="Controller::startSearch" />
    </Text>
  </Layout>
</Screen>
```

Put app lifecycle declarations directly inside the `<App>` element in `Layout/app.xml`:

```xml
<App>
  <Event type="init" action="Controller::initialize" />
  <Window title="Example">
    <Screen file="screen.xml" />
  </Window>
  <Event type="close" action="Controller::shutdown" />
</App>
```

The `init` action runs after SDL, fonts, windows, and screens have been initialized, immediately before the event
loop starts. The `close` action runs after the event loop ends and before SDL resources are released. Both receive
an `EventContext` whose `type` is `init` or `close`; `widget` and `input` are `null`.

Timer declarations also go directly inside `<App>`. Their callbacks run on the event loop thread and receive a
timer `EventContext` with `widget` and `input` set to `null`:

```xml
<Event type="timer" period="100" action="Controller::tick" />
```

Periods are positive milliseconds. Timers repeat from their original cadence; if the process is delayed across
multiple periods, the callback runs once and the next deadline advances to the next cadence point. The event loop
waits up to one second when no timer is active, or until the nearest timer deadline when timers are active.

Code can create and manage timers through `SPTK\App::eventLoop()`:

```php
$timerId = \SPTK\App::eventLoop()->addTimer('Controller::tick', 100);
\SPTK\App::eventLoop()->setTimerPeriod($timerId, 250);
\SPTK\App::eventLoop()->removeTimer($timerId);
```

For timers declared in XML, pass the action string to `setTimerPeriod` or `removeTimer`; this affects every timer
using that action. `addTimer` returns an integer handle for managing a code-created timer.

Each declaration names a static method using `Class::method`. The class must be loaded by the application. A
method can be public static, for example `public static function selectAll(EventContext $event): bool`.

## Raw input events

Raw input event types are `keyDown`, `keyUp`, and `textInput`. A `key` attribute is allowed only with `keyDown`
and `keyUp`. Keys can be letters, digits, `f1` through `f12`, or named keys such as `enter`, `escape`, `left`,
`home`, and `backspace`. The optional modifiers are `ctrl`, `shift`, and `alt`, joined with `+`, as in
`key="ctrl+shift+a"`. Modifier and key names are case-insensitive.

Modifiers match by category: either physical Control key matches `ctrl`, and likewise for Shift and Alt. Extra
lock modifiers do not affect chord matching.

Keypad navigation keys follow Num Lock: with Num Lock off, keypad positions such as KP 7 and KP 1 match
`home` and `end`; with Num Lock on, they remain keypad digits. Keypad Enter matches `enter` in either mode.

For raw input, the activated widget's `handleInput` method runs first. If it does not
consume the event, matching widget-level actions run, then matching screen-level actions. A handler or action
consumes input by returning `true`; `false` lets it continue. A screen action can run in either layout mode or
input mode. Returning `true` from a screen action prevents the screen from applying its navigation or
activation controls for that event.

## Notifications

The framework emits these notifications:

- `select` and `unselect` when focus changes
- `activate` when the selected widget enters input mode
- `accept` or `cancel` when input mode ends with Return or Escape
- `deactivate` after either accept or cancel
- `change` when a widget implementing `ChangeAwareWidget` reports a committed value change

Matching widget-level notification actions run before screen-level notification actions. Notification return
values are ignored. The first selected widget receives its initial `select` notification on the screen's first
input event.

Only `keyDown` and `keyUp` declarations can specify a key. Other declarations match by event type alone.
