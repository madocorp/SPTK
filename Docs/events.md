# Events

Events connect XML declarations to static PHP methods. A method receives an `SPTK\Core\EventContext` with the
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

Each declaration names a static method using `Class::method`. The class must be loaded by the application. A
method can be public static, for example `public static function selectAll(EventContext $event): bool`.

## Raw input events

Raw input event types are `keyDown`, `keyUp`, and `textInput`. A `key` attribute is allowed only with `keyDown`
and `keyUp`. Keys can be letters, digits, `f1` through `f12`, or named keys such as `enter`, `escape`, `left`,
`home`, and `backspace`. The optional modifiers are `ctrl`, `shift`, and `alt`, joined with `+`, as in
`key="ctrl+shift+a"`. Modifier and key names are case-insensitive.

For raw input, a widget's optional `InputHandler` runs first when that widget is activated. If it does not
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
