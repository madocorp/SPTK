# List

`<List>` creates a `ListView` with one item per row. Each `<Item>` accepts `value`, `label`, and boolean `selected` attributes. Text content can supply the label; the value defaults to that label. Values must be unique. With one selection, the first row is current by default. Multiple initially selected rows require `multiple="true"`.

```xml
<List title="Options" multiple="true" filterable="true" reorderable="true" height="1*">
  <Item value="one" selected="true">One</Item>
  <Item value="two">Two</Item>
  <Event type="change" action="Controller::listChanged" />
</List>
```

The optional `title` attribute displays a fixed top row in the inherited `Highlight`
color. Items, search results, and scroll indicators use the rows below it; the title
does not scroll or participate in selection or filtering. Titles are printable single-line
UTF-8 text, clipped to the tile width. Even `title=""` reserves a row; omitting it preserves
the original item-only layout. A one-row tile shows only the title and retains its items.
Preferred width includes the title, and preferred height adds one row when it is supplied.
PHP accepts the same option as `new ListView($items, title: 'Options')`.

Return activates the list. Up/Down, Home/End, and Page Up/Down move its cursor; page keys first reach the visible edge, then move one page. In a single-selection list, the cursor is the value. In a multiple-selection list, Space toggles the current row and key repeat is ignored. Return or Escape releases the list and clears its temporary query. Left/Right moves between tiles after release.

Typing builds a case-insensitive prefix query. Single-selection lists accept spaces in the query; multiple-selection lists keep Space for toggling rows. With `filterable="true"` (the default), only matching rows remain. With `filterable="false"` and `searchable="true"`, all rows remain and the first match becomes current. Set both false to disable typing and let screen hotkeys work while the list is active. Backspace removes the last query character; Delete clears the query. A query with no matches shows a message and gives a single-selection list a `null` value until the query is cleared. The matching prefix is highlighted. An item may set `searchOffset` in PHP to exclude a visible prefix from matching.
While a searchable list is active, printable keys are reserved for its text input and do not trigger button hotkeys. When both search options are disabled, printable keys can trigger screen hotkeys. Control and Alt shortcuts can still reach screen actions.

Set `reorderable="true"` to move the current row with Shift+Up/Down when no query is active. The initial order is preserved until the user reorders it. `values()` and `items()` expose the current order; `cursorPosition()` returns the underlying item index. `activeValue()` returns the value under the cursor. `getValue()` returns a string or `null` for single selection, and an ordered array for multiple selection. `setValue(string|array)`, `setItems(array)`, and `setFilter(string)` are programmatic and do not emit `change`.

`reorder` fires after a user changes the row order, including when the selected value stays the same. Boundary moves and moves blocked by a query emit nothing. Programmatic setters do not emit `reorder`.

`change` fires when a user action changes `getValue()`. Filtering or reordering without a value change does not fire it. Tile focus continues to use the separate `select` lifecycle event. The list uses the widget's selected color for selected item labels, and its highlight color for matching prefixes and the scroll indicator background. The widget background color is used as indicator text. Nested `<Style>` and `<Event>` elements and layout sizes work as for other widgets.
