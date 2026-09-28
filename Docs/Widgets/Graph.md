# Graph

`SPTK\Widgets\Graph\Graph` displays numeric XY series as a cached GD image through
mad4's `RasterImage` and `PixelRenderer`. It supports lines with dot markers,
points, and grouped vertical bars sharing linear axes. It is read-only and does
not enter input mode. Normal tile selection and declared events still apply.

```xml
<Graph id="load" height="1*" title="Server load" xLabel="Time" xUnit="s"
       yLabel="Load" yUnit="%" yMin="0" yMax="100" grid="true">
  <Series name="Server A" type="line" color="#00ffff">
    <Point x="0" y="25" />
    <Point x="10" y="60" />
  </Series>
  <Series name="Observed" type="point" color="#ffff00">
    <Point x="5" y="35" />
  </Series>
</Graph>
```

Series and points are internal data, never child widgets. Graph also accepts
`Style` and `Event` children. Series accept `name`, `type` (`line`, `point`, or
`bar`), and a mad4 RGB `color` (`#RRGGBB`). The default type is `line`; omitted
names become `Series 1`, etc. Colors cycle through cyan, yellow, magenta, green,
red, and white. Points require finite numeric `x` and `y` attributes.

```php
use SPTK\Widgets\Graph\Graph;

$graph = new Graph([
  ['name' => 'Server A', 'points' => [[0, 25], [10, 60]]],
], ['title' => 'Server load', 'grid' => true]);
$graph->addSeries(['name' => 'Samples', 'type' => 'point', 'points' => [[5, 35]]]);
$graph->setSeries([
  ['name' => 'Updated', 'points' => [[0, 30], [10, 55], [20, 45]]],
]);
$graph->setOptions(['yMin' => 0, 'yMax' => 100, 'legend' => true]);
$graph->setOptions(['yMin' => null, 'yMax' => null]); // Restore automatic bounds.
```

The constructor accepts series, options, and an inherited `Core\Style`.
`series()` and `options()` return normalized copies. Coordinates become floats
and colors become six-digit RGB strings. Invalid updates leave previous state
intact; unchanged setters retain the cached image. Programmatic updates do not
emit user events. `raster(width, height, rowHeight)` exposes the generated
`RasterImage`; rendering regenerates it on size, data, or option changes.

Both axes automatically cover all points. Empty data uses 0–1 and constant
values receive a nonzero range. Explicit `xMin`/`xMax` and `yMin`/`yMax` must be
supplied as increasing pairs. PHP null pairs restore autoscaling. Line points
connect in supplied order. Points, bars, and crossing line segments are clipped
to the plot rectangle.

Bars include zero on the automatic Y axis, extend downward for negative values,
and pad the automatic X range. Bars at the same X value appear side by side in
series order; missing values reserve their slot. A bar series cannot repeat an
X value. Group width is 80% of the smallest positive bar X spacing, or 0.8 data
units for a single X value. Zero-height bars have no filled area.

Options include `title`, `xLabel`, `yLabel`, `xUnit`, `yUnit`, `tickCount`,
`legend`, `grid`, and the four axis bounds. Text defaults empty. `tickCount`
defaults to 5 and accepts integers 2–12; crowding reduces ticks and then scale
text size. Units follow numeric tick labels. Grid lines default off. The legend
defaults on for multiple series; PHP `legend => null` restores this default.

Natural size is 40 columns by 12 rows. Use `height` in a vertical layout or
`width` in a horizontal layout to allocate more space. The raster matches the
allocated pixel dimensions. Text uses Liberation Mono Bold, measured with
FreeType; long labels are clipped. Tiny tiles show only the background. The
inherited background, foreground, and separator colors control the graph's
background, text, and axes. Selection shading comes from the image renderer.

The Progress screen includes mixed-series and grouped-bar examples.
Run `php Tests/Graph.php` for data, XML, rendering, and cache checks.
