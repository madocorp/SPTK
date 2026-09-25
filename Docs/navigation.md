
# Navigation


## Layout mode

This is the default mode after initialization. User can move between the tiles with the
arrow keys. The selected widget is highlighted (actually the other widgets are darkened).
Return activates the selected widgets and that's the "input mode".
The first widget leaf in XML order is selected initially.


## Input mode

The activated widget receives keyboard and text events first. If its input handler does not
handle an event, widget event actions run, followed by screen event actions. An unhandled
Return accepts and deactivates the widget; an unhandled Escape cancels and deactivates it.
Handling a screen hotkey does not leave input mode.

## XML events

Each screen XML file starts with a `<Screen>` element containing event declarations and one
`<Layout>`. `<Event>` elements can also be placed inside widget elements. Event actions name
a loaded static PHP method as `Class::method` and receive an `SPTK\Core\EventContext`.
For example, `<Event type="keyDown" key="ctrl+a" action="AppController::selectAll" />`
binds a screen shortcut. Raw input action methods return `true` to consume the event or
`false` to let it continue.
Lifecycle and change events are notifications; their action return values are ignored.

The framework emits `select`, `unselect`, `activate`, `deactivate`, `accept`, and `cancel`.
Widgets implementing `ChangeAwareWidget` can report committed value changes as `change`.
Raw input types are `keyDown`, `keyUp`, and `textInput`; key chords accept `ctrl`, `shift`,
and `alt` modifiers, for example `key="ctrl+a"`.


## Screen selection

Only the first screen in each Window is visible initially. Apps can select another
screen with `Window::setCurrentScreen()`.
