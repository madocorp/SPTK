# TextEditor

`<TextEditor>` edits multiline text in a tile. XML text and CDATA preserve whitespace; a `value` attribute takes precedence. It accepts nested `<Style>` and `<Event>` elements and the layout size for its parent direction. Text stays left aligned. Without an explicit height, it prefers sixteen cells, plus one row each for a title and label when present.

```xml
<TextEditor height="1*" title="Document" textWrap="true"><![CDATA[First line
Second line]]></TextEditor>
```

| Attribute | Default | Behavior |
| --- | --- | --- |
| `value` | text content | Initial document text. |
| `title` | absent | Heading above the editor. It is not part of the document or caret navigation. |
| `label` | absent | Printable single-line text in a separate top row. It is not part of the document or caret navigation. Even an empty label reserves the row. |
| `textWrap` | `false` | Wrap by character when enabled. |
| `tabSize` | `8` | Positive number of display cells between tab stops. |

The title uses the shared widget title style. `setTitle(?string)` changes it without changing the document; `null` removes the title row. The label uses the widget's highlight and background colors and is clipped to the tile width. Editing, wrapping, and scrolling use the rows below the title and label. A tile only one row high shows the title, or the label when no title is present, while retaining the document.
Vertical scroll arrows sit at the right edge. Horizontal scroll arrows sit at the left or right edge; when a right and down arrow are both needed, the down arrow moves one row above the right arrow.

Return activates the editor. While active, Return inserts a newline and Tab inserts a literal tab. Ctrl+Return or Escape releases it, keeps edits, and emits `accept`. Arrows navigate text; Shift extends the inclusive selection. Home/End reach logical line edges, Ctrl+Left/Right do the same, and Ctrl+Home/End reach horizontal viewport edges then page. Page Up/Down first reach the visible edge, then page; Ctrl+Page Up/Down reach document ends. Wrapped text uses visual rows for vertical movement. Scroll indicators show hidden content in the background color on the highlight color. Selected newline characters appear as `¶` without changing the document.

Ctrl+A/C/X/V select all, copy, cut, and paste. Ctrl+Insert copies, Shift+Insert pastes, and Ctrl+Delete cuts. Ctrl+Z undoes; Ctrl+Y and Ctrl+Shift+Z redo. Backspace and Delete remove one grapheme or the selection. Copy without an extended selection copies the character under the caret, including a newline at a nonfinal line end.

`getValue()` and `text()` return the current document, including ongoing edits. `setValue(string)` replaces it and resets the caret, scroll, and history. `cursorPosition()` returns a zero-based `[row, graphemeIndex]`; `editing()` reports activation. Screen switching or window focus loss releases an active editor with `accept`.
