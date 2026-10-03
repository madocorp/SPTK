# RadioButton

`<RadioButton>` presents one selectable item per row. Each `<Item>` accepts `value`, `label`, and boolean `checked` attributes. The label can also be text content, and `value` defaults to the label. Values must be unique. A nonempty group selects its first item when none is checked; more than one initially checked item is invalid. An empty group has a `null` value.

```xml
<RadioButton title="Database" height="1*">
  <Item value="local" checked="true">Local database</Item>
  <Item value="remote" label="Remote database" />
  <Event type="change" action="Controller::choiceChanged" />
</RadioButton>
```

The optional `title` attribute displays a fixed top row in the inherited `Highlight`
color. Items and scroll indicators use the rows below it; the title does not scroll or
participate in selection. Titles are printable single-line UTF-8 text, clipped to the tile
width. Even `title=""` reserves a row; omitting it preserves the original item-only layout.
A one-row tile shows only the title and retains its choices. Preferred width includes the
title, and preferred height adds one row when it is supplied.
PHP accepts `new RadioButton($items, title: 'Database')`.

Return activates the widget. Up/Down, Home/End, and Page Up/Down move its marker; paging first reaches the visible edge and then moves a page. Space selects the highlighted item. Holding Space does not repeat a selection. Return or Escape releases the widget without undoing changes. Left/Right moves between tiles after release.

`getValue()` returns the selected string or `null`. `setValue(string)` selects a known value; unknown values throw without altering the selection. `setItems(array)` accepts strings or value/label/checked records and resets cursor and selection. `items()` returns value/label/checked records, `cursorPosition()` gives the zero-based highlighted index, and `active()` reports activation. Programmatic setters do not emit events; a user selection that changes the value emits `change`.

Markers use `(O)` and `( )`. Long groups scroll within their tile; indicators use the widget background color on its highlight color. Labels are clipped to the tile width. The layout's width and height attributes and nested `<Style>` and `<Event>` elements work as for other widgets.
