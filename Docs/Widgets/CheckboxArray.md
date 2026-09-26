# CheckboxArray

`<CheckboxArray>` presents independent checkboxes, one item per row. Each `<Item>` accepts `value`, `label`, and boolean `checked` attributes. The label can also be text content, and `value` defaults to the label. Values must be unique.

```xml
<CheckboxArray height="1*">
  <Item value="tables" checked="true">Show tables</Item>
  <Item value="views">Show views</Item>
  <Event type="change" action="Controller::choiceChanged" />
</CheckboxArray>
```

Return activates the widget. Up/Down, Home/End, and Page Up/Down move its marker; paging first reaches the visible edge and then moves a page. Space toggles the highlighted checkbox. Holding Space does not repeatedly toggle it. Return or Escape releases the widget without undoing changes. Left/Right moves between tiles after release.

`getValue()` returns checked strings in item order. `setValue(array)` replaces the selection atomically and rejects unknown values. `setItems(array)` accepts strings or value/label/checked records and resets cursor and selection. `items()` returns value/label/checked records, `cursorPosition()` gives the zero-based highlighted index, and `active()` reports activation. Programmatic setters do not emit events; each user toggle emits `change`.

Markers use `[X]` and `[ ]`. Long groups scroll within their tile; indicators use the widget background color on its highlight color. Labels are clipped to the tile width. The layout's width and height attributes and nested `<Style>` and `<Event>` elements work as for other widgets.
