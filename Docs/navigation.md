
# Navigation


## Layout mode

This is the default mode after initialization. User can move between the tiles with the
arrow keys. The selected widget is highlighted (actually the other widgets are darkened).
Return activates the selected widgets and that's the "input mode".
The first widget leaf in XML order is selected initially.


## Input mode

The activated widgets get all input (keyboard and text events). If an input not handled by
the widget it bubbles up to the app screen input handler. Return or Escape returns to "layout
mode".


## Screen selection

Only the first screen in each Window is visible initially. Apps can select another
screen with `Window::setCurrentScreen()`.
