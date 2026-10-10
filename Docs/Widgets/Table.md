# Table

`<Table>` displays a fixed header and scrollable rows. Supply data with `file` pointing to a TSV file relative to the screen XML, or with `<Header>`, `<Row>`, and `<Field>` children. The first TSV line is the header. TSV fields support `\\t`, `\\n`, `\\r`, and `\\\\` escapes; `\\N` is a null field.

```xml
<Table rowNumbers="true">
  <Header><Field width="20">Name</Field><Field width="10">Count</Field></Header>
  <Row><Field>Customers</Field><Field>1284</Field></Row>
  <Row><Field>Orders</Field><Field null="true" /></Row>
  <Event type="change" action="Controller::tableChanged" />
</Table>
```

Use `<Table file="data.tsv" />` for a TSV source. `file` cannot be mixed with inline data. A width is a positive cell count; give every header field a width or omit all widths for automatic measurement. TSV loading scans once to count rows and index file offsets, then keeps at most two 256-row chunks in memory. Automatic widths are measured from the header and first chunk. When the table exceeds its tile, columns are capped at half the tile width so long values can be inspected by scrolling horizontally. Keep the source file unchanged while the table is open. `title` adds a fixed heading above the table header. It can also be changed with `setTitle($title)`. `rowNumbers` and `rowCursor` default to false. The widget accepts nested `Style` and `Event` declarations.

Cursor moves within the visible rows repaint only the affected rows. Scrolling repaints the tile.
Horizontal scroll markers use the bottom corners. Vertical markers use the right edge. When content overflows right and below, both markers share the bottom row as `▶ ▼`, with page counts when space permits.

The demo's **Table** screen reads [`large.tsv`](../../Demo/Layout/large.tsv) in its lower pane, with 2,048 rows and 16 fields. It includes nulls, escaped tabs and newlines, long SQL text, and long descriptions. Regenerate it with `php Tools/generate-table-demo.php` from the repository root.

Return activates the table. Arrows move the current cell. Page Up/Down move first to the top/bottom visible row, then scroll one body page on another press. Home/End move to the first/last field in the row; Ctrl+Home/End move first to the first/last visible field, then page horizontally. Ctrl+Page Up/Down jump to the first/last data cell. Shift extends a rectangular selection, Ctrl+A selects all cells, and Ctrl+C copies selected fields as escaped TSV. With `rowCursor="true"`, the entire current row is highlighted only while the table is active; Up/Down and Home/End move between rows, Shift extends whole-row selection, and Left/Right leave the row cursor unchanged. Return releases it with `accept`; Escape releases it with `cancel`. User cursor movement emits `change`. The header stays visible as rows scroll, and its background fills the full tile width. The current cell and selected cells use cursor foreground and background colors; column separators keep their normal colors in cell mode. Long fields end with `~`, multiline fields show `V`, and null fields show `NULL`. The markers and null values use the highlight color, including when a field is selected.

In PHP, `setRows($header, $rows, $widths = [])` and `setTsvFile($path, $widths = [])` replace the data. `setRowCursor(true)` enables row mode and `rowCursor()` reports it. `header()`, `rowCount()`, `rowValues($row)`, `columnWidths()`, `cursorRow()`, `cursorColumn()`, `activeCellValue()`, and `activeRowValues()` expose it. `setCursor($row, $column = 0)` moves the cursor without emitting `change`; `selectCells($firstRow, $firstColumn, $lastRow, $lastColumn)`, `selection()`, and `copySelection()` manage the selected rectangle.
