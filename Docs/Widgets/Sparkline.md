# Sparkline

`<Sparkline>` draws a one-row history using eight Unicode block heights. Each
sample maps to a block between `▁` and `█`. The chart occupies a fixed number
of cells: unfilled slots appear as `·` on the left at startup, and each new value enters
on the right. When samples exceed the available slots, the oldest values leave
on the left. The widget does not collect samples or retain past values.

```xml
<Sparkline id="loadHistory" height="1" max="100" samples="10,25,50,75"
           label="[12]" showValue="true" unit="%" />
```

`samples` is an optional comma-separated list of numbers and `max` is a
positive scale, defaulting to 100. `label` is optional text before the chart.
`showValue` defaults to `false`; when true, the latest number appears after the
chart with one separating space. `unit` is appended to that number, such as `%`
or `MiB/s`. Any padding needed for the numeric field follows the value. Applications
can update samples, scale, label, value visibility, and unit with `setSamples`,
`setMaximum`, `setLabel`, `setShowValue`, and `setUnit`.

The chart uses the inherited highlight and background colors. It can be
selected for navigation but cannot enter input mode. `Style` and `Event`
children are supported.

For charts embedded within another widget's text, use
`SPTK\Rendering\Sparkline::render($samples, $maximum, $width, $label, $showValue, $unit)`
to obtain the same fixed-width row.
