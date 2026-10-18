# ColorInput

`<ColorInput>` edits a six-digit RGB hex value. It looks like an Input: the label uses normal style colors, and only the value row has the selected color as its background. The value text switches between black and white for contrast. A predefined palette is optional.

```xml
<ColorInput id="accent" label="Color" value="#ff9000" height="auto">
  <Item value="#ff9000" />
  <Item value="#0050ff" />
  <Event type="accept" action="Controller::saveColor" />
</ColorInput>
```

`value` accepts `#rrggbb` or `rrggbb` and is stored as lowercase `#rrggbb`. `getValue()` returns the last complete color; `setValue(string)` changes it without a `change` event. `setPalette(array)` replaces the predefined colors. Palettes contain one to 36 unique hex colors. The smallest square from 2×2 to 6×6 that can hold them is used; unused cells remain blank. Each swatch is two colored character cells wide, with brackets around the selected swatch and an empty line between swatch rows.

Return activates the palette, hiding the hex row. Arrow keys move between swatches. Tab switches to hex input; there, type hex digits and edit with the usual Input keys. The value row background follows each complete value. Tab switches back to the palette. Return accepts the color from either mode and deactivates the widget. Escape cancels the edit and restores its starting color. Incomplete hex text is discarded on acceptance. Complete user changes emit `change`; acceptance emits `accept` through the screen.

The widget prefers two rows in hex input mode and grows to fit its square palette when activated. Use an `auto` height tile for this behavior. Like other widgets, its label, value row, and swatches darken when the tile is not selected.

The demo **Selectors** screen places ColorInput directly below ColorSelector.
