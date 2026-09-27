# ColorSelector

`<ColorSelector>` selects a six-digit RGB color. It shows a rainbow row, a row of dark-to-light tones for the current rainbow hue, and a row of exact-color shortcuts. The last shortcut keeps the most recently entered custom color. A hex field and a wide preview stripe occupy the bottom row.

```xml
<ColorSelector height="7" value="#12ab34" id="accent">
  <Event type="change" action="Controller::colorChanged" />
</ColorSelector>
```

`value` defaults to `#ff0000`. It accepts six hex digits with or without `#`, and the widget stores lowercase `#rrggbb`. Invalid values throw an exception. `getValue()` returns the complete color; `setValue(string)` changes it without emitting an event. `cursorPosition()` returns the selected swatch index from 0 to 47, `hexText()` includes any unfinished hex entry, and `active()` reports whether the widget is activated.

Return activates the selector. Arrow keys move among swatches, Home/End reach the first or last column, and Page Up/Down reach the top or bottom row. Moving to a swatch updates the color immediately. Type six hex digits to enter a custom color; the value changes only when all six digits are present. Backspace edits the field, Delete clears it, and new typing after a complete entry starts a fresh field. Return or Escape releases the selector and discards an incomplete entry while keeping the last complete color. User changes emit `change` once per changed color.

The full palette prefers 64 columns and 7 rows. Smaller tiles clip the drawing. The widget uses inherited `Foreground`, `Background`, `CursorBackground`, and `Selected` colors; nested `<Style>` and `<Event>` declarations are supported. The demo **Colors** screen shows the widget.
