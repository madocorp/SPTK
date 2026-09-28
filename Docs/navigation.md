# Navigation

Each screen selects one widget tile at a time. The first widget in layout order is selected when the screen
starts. The initial `select` notification is sent when the screen first handles an input event.

## Layout mode

Layout mode is the default. Arrow keys prefer the nearest widget in the same row (Left/Right) or column
(Up/Down). Tiles share a row or column when their ranges overlap on the axis perpendicular to movement.
Only when no aligned widget remains in that direction does focus move to the geometrically nearest widget,
using edge-to-edge distance. If no widget lies in that direction, focus stays where it is. Movement follows
tile geometry rather than cycling through widgets in XML order. The selected tile is drawn at full brightness;
other widget tiles are dimmed.

Press Return or keypad Enter to activate the selected widget and enter input mode. A Button runs its action
immediately and stays in layout mode.

## Input mode

The activated widget receives input first. By default, Return or keypad Enter accepts and deactivates it, while
Escape cancels and deactivates it. If widget handling and event actions do not consume those keys, the screen applies
the widget's release rule. Arrow keys do not move selection while input mode is active.

Widget-specific input behavior is documented on each widget's page.
Input and TextEditor keep edits and emit `accept` on Escape. TextEditor inserts a newline on Return and releases on Ctrl+Return. Switching screens releases the active widget with `accept`.

## Screens

The first screen listed in a window is shown initially. Applications can switch screens with
`Window::setCurrentScreenId()` or the zero-based `Window::setCurrentScreen()`.
The demo's selector uses F1 for Main, F2 for Editors, F3 for Lists, F4 for Choices, and F5 for Images.
Each screen remembers its selected widget. When that widget is a screen selector button, returning to the
screen moves selection to its own button so focus agrees with the current screen.
Pressing the current screen's hotkey focuses its selector button, releasing an active widget if needed.
