# List

`<List>` creates a `ListView` with one item per row. Each `<Item>` accepts `value`, `label`, and boolean `selected` attributes. Text content can supply the label; the value defaults to that label. Values must be unique. With one selection, the first row is current by default. Multiple initially selected rows require `multiple="true"`.

```xml
<List multiple="true" filterable="true" reorderable="true" height="1*">
  <Item value="one" selected="true">One</Item>
  <Item value="two">Two</Item>
  <Event type="change" action="Controller::listChanged" />
</List>
```

Return activates the list. Up/Down, Home/End, and Page Up/Down move its cursor; page keys first reach the visible edge, then move one page. In a single-selection list, the cursor is the value. In a multiple-selection list, Space toggles the current row and key repeat is ignored. Return or Escape releases the list and clears its temporary query. Left/Right moves between tiles after release.

Typing builds a case-insensitive prefix query. With `filterable="true"` (the default), only matching rows remain. With `filterable="false"` and `searchable="true"`, all rows remain and the first match becomes current. Set both false to disable typing. Backspace removes the last query character; Delete clears the query. A query with no matches shows a message and gives a single-selection list a `null` value until the query is cleared. The matching prefix is highlighted.

Set `reorderable="true"` to move the current row with Shift+Up/Down when no query is active. The initial order is preserved until the user reorders it. `values()` and `items()` expose the current order; `cursorPosition()` returns the underlying item index. `activeValue()` returns the value under the cursor. `getValue()` returns a string or `null` for single selection, and an ordered array for multiple selection. `setValue(string|array)`, `setItems(array)`, and `setFilter(string)` are programmatic and do not emit `change`.

`change` fires when a user action changes `getValue()`. Filtering or reordering without a value change does not fire it. Tile focus continues to use the separate `select` lifecycle event. The list uses the widget's selected color for selected item labels, and its highlight color for matching prefixes and the scroll indicator background. The widget background color is used as indicator text. Nested `<Style>` and `<Event>` elements and layout sizes work as for other widgets.
