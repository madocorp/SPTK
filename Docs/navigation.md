# Navigation

Each screen selects one widget tile at a time. The first widget in layout order is selected when the screen
starts. The initial `select` notification is sent when the screen first handles an input event.

## Layout mode

Layout mode is the default. Use the arrow keys to move focus to a nearby widget in that direction. If no
widget lies in that direction, focus stays where it is. Movement follows tile geometry rather than cycling
through widgets in XML order. The selected tile is drawn at full brightness; other widget tiles are dimmed.

Press Return or keypad Enter to activate the selected widget and enter input mode.

## Input mode

The activated widget receives input first. Press Return or keypad Enter to accept and deactivate it, or Escape
to cancel and deactivate it. If widget handling and event actions do not consume those keys, the screen applies
these controls. Arrow keys do not move selection while input mode is active.

## Screens

The first screen listed in a window is shown initially. Applications can switch screens with
`Window::setCurrentScreen()`.
