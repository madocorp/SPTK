# Navigation

Each screen selects one widget tile at a time. The first widget in layout order is selected when the screen
starts. The initial `select` notification is sent when the screen first handles an input event.

## Layout mode

Layout mode is the default. Use the arrow keys to move focus to a nearby widget in that direction. If no
widget lies in that direction, focus stays where it is. Movement follows tile geometry rather than cycling
through widgets in XML order. The selected tile is drawn at full brightness; other widget tiles are dimmed.

Press Return or keypad Enter to activate the selected widget and enter input mode.

## Input mode

The activated widget receives input first. By default, Return or keypad Enter accepts and deactivates it, while
Escape cancels and deactivates it. If widget handling and event actions do not consume those keys, the screen applies
the widget's release rule. Arrow keys do not move selection while input mode is active.

Widget-specific input behavior is documented on each widget's page.
Input and TextEditor keep edits and emit `accept` on Escape. TextEditor inserts a newline on Return and releases on Ctrl+Return. Switching screens releases the active widget with `accept`.

## Screens

The first screen listed in a window is shown initially. Applications can switch screens with
`Window::setCurrentScreen()`.
The demo uses F1 for its original screen, F2 for Editors, F3 for Choices, F4 for Lists, and F5 for Images.
