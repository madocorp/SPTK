# ProgressBar

`<ProgressBar>` shows determinate progress. An optional `title` occupies a row above the bar. The centered label inside the bar can show a percentage, a current / total value, or custom text.

```xml
<ProgressBar title="Build assets" value="65" />
<ProgressBar title="Rows fetched" value="42" max="120" display="fraction" />
<ProgressBar title="Current phase" value="2" max="4" text="Writing files" />
<ProgressBar title="Memory" value="6" max="16" barWidth="20"
             detail="6 GiB used / 16 GiB total" detailPlacement="after" />
```

`value` defaults to 0 and `max` to 100. Both accept finite integers or decimals. The value is clamped to 0…max; a negative maximum is invalid. A zero maximum leaves the bar empty. The fill occupies whole character cells.

`display` accepts `percent` (default), `fraction`, or `text`. Supplying `text` selects text mode unless `display` is explicit. Text can also be written in the element body. Line breaks and tabs in the title or custom text become spaces. The title and label are clipped to the tile width; wide glyphs stay intact. The bar occupies one row, plus one title row when titled. Extra allocated rows keep the normal background.

`detail` adds text outside the bar. `detailPlacement` accepts `before`, `after`
(default), `above`, or `below`. Before and after share the bar row with one
separator cell; above and below reserve another row. `barWidth` optionally
sets the bar's width in cells. Without it, a side detail takes its natural
width and the bar uses the remaining space. An above or below detail leaves
the bar at full tile width unless `barWidth` is set. The detail is clipped
to the available space when the tile is narrow.

The title uses the inherited `Highlight` color. `Selected` fills completed bar cells; unfilled cells use a slightly darker shade of `Background` so the bar's full length stays visible. Label colors switch across the fill boundary so the text remains readable. Nested `Style` and `Event` declarations work as for other widgets. The bar is read-only and does not enter input mode.

Update it from PHP with `setProgress($value, $maximum)`, `setValue($value)`, `setMaximum($maximum)`, `setDisplay($mode)`, `setText($text)`, `setTitle($title)`, `setDetail($text)`, `setDetailPlacement($placement)`, or `setBarWidth($cells)`. `setText()` selects text mode. Getters include `getValue()` (also `value()`), `maximum()`, `ratio()`, `display()`, `text()`, `title()`, `detail()`, `detailPlacement()`, `barWidth()`, and `label()`.

The demo's **Progress** screen shows all three label modes.
