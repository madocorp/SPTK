# Text

`<Text>` displays read-only text inside its layout tile. It accepts text content and nested `<Event>` and `<Style>`
elements. Layout sizing attributes such as `width` and `height` are documented in [Layout](../layout.md).

## Attributes

| Attribute | Default | Behavior |
| --- | --- | --- |
| `wrap` | `true` | Wrap long lines at word boundaries. When `false`, each source line stays on one row and can scroll horizontally. |
| `tabSize` | `8` | Positive number of display cells between tab stops. |

```xml
<Text height="1*">A paragraph that wraps to fit its tile.</Text>
<Text height="1*" wrap="false">A line that can scroll horizontally.</Text>
```

## Input behavior

Activate the widget to show its cursor. Arrow keys move the cursor; hold Shift to extend the selection. Home and End
move to the logical line edges. Ctrl+Home and Ctrl+End move to the horizontal viewport edges; pressing either again
scrolls horizontally by one viewport width. Page Up and Page Down move to the vertical viewport edge; pressing
again scrolls by one page. Ctrl+Page Up and Ctrl+Page Down move to the start and end of the document. Shift extends
the selection during these movements.

The widget is read-only; it does not insert or delete text.
Ctrl+A selects all text. Ctrl+C or Ctrl+Insert copies the selection, or the grapheme under the cursor when the selection is empty. Text and TextEditor share visual-row mapping, cursor behavior, and painting.
Scroll indicators are drawn in the widget background color on the highlight color.
Vertical arrows sit at the right edge; horizontal arrows sit at the left or right edge according to scroll direction.
