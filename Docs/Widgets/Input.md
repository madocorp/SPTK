# Input

`<Input>` edits one line in a tile. It accepts a `value` attribute or text content; `value` wins when both are present. Newlines become spaces. It also accepts nested `<Style>` and `<Event>` elements and the layout size for its parent direction. Without an explicit height, it prefers one cell, or two when labeled.

```xml
<Input height="2" label="Name" value="Alice">
  <Event type="accept" action="Controller::saveName" />
</Input>
```

| Attribute | Default | Behavior |
| --- | --- | --- |
| `value` | text content | Initial value. |
| `label` | absent | Printable single-line text in a separate top row. It is not part of the value or caret navigation. Even an empty label reserves the row. |
| `tabSize` | `8` | Positive number of display cells between tab stops. |

The label uses the widget's highlight and background colors and is clipped to the tile width. The editable line starts on the next row. A tile only one row high shows the label while retaining the editable value.
Horizontal scroll arrows appear at the left and right edges according to the hidden content.

Return activates Input. Return or Escape releases it, keeps edits, and emits `accept`. Arrow keys move within the line while active; Shift extends the inclusive selection. Home/End reach line edges, and Ctrl+Home/End reach visible edges then page horizontally. Ctrl+Left/Right reach line edges. Tab inserts a literal tab. Hidden text is marked with horizontal page indicators drawn in the background color on the highlight color.

Ctrl+A/C/X/V select all, copy, cut, and paste. Ctrl+Insert copies, Shift+Insert pastes, and Ctrl+Delete cuts. Ctrl+Z undoes; Ctrl+Y and Ctrl+Shift+Z redo. Backspace and Delete remove one grapheme or the selection. Copy without an extended selection copies the character under the caret.

`getValue()` and `text()` return the current text, including ongoing edits. `setValue(string)` replaces it and resets the caret, scroll, and history. `cursorPosition()` returns a zero-based grapheme index; `editing()` reports activation. Screen switching or window focus loss releases an active Input with `accept`.
