# Image

`<Image>` displays a local image decoded by PHP GD. It supports PNG, JPEG, GIF, BMP, WebP, AVIF, and other formats available in the installed GD build. Animated formats show the frame GD decodes; they do not animate.

```xml
<Image src="../Assets/photo.png" width="1*" zoom="1.5" x="20" y="-10" />
<Image src="../Assets/photo.png" width="1*" fill="true" title="Whole image" />
<Image src="../Assets/photo.png" width="1*" fit="cover" padding="false" interactive="false">
  <Style><Background>#102030</Background></Style>
</Image>
```

Relative `src` paths are resolved against the XML screen file; absolute paths are accepted. Files are decoded lazily on first display or explicit `source()` access. Missing, unreadable, corrupt, or unsupported files raise an error with the path when first needed. `Image` requires PHP GD. Nested `<Event>` and `<Style>` elements work as for other widgets. It remains a selectable tile. By default it follows the usual Return and Escape activation rules;
`interactive="false"` prevents activation and disables all zoom and pan controls. Arrow keys continue
to navigate between tiles.

The optional `title` attribute reserves one grid row above the image, painted in the inherited `Highlight` color. Without a title there is no reserved row. Fitting and pan controls use only the image area below the title, including when `padding="false"`. An empty title still reserves the row. The PHP constructor accepts `title` and an optional inherited `style` after `padding`.

Fast pixel conversion uses GD's PNG support and the `libpng16.so.16` runtime library. GD exports an uncompressed PNG snapshot in memory; libpng reads its pixels and SDL converts their channel ordering. If the optional native libraries or APIs cannot load, conversion automatically falls back to GD's PHP pixel getters. The unavailable fast path is remembered so subsequent images do not retry loading it. Both paths preserve transparency and palette colors, and leave the original GD image and its save settings intact.

The image is centered in its tile at native pixel size by default. `fill="true"` shrinks an oversized image until the whole image fits inside the tile while keeping its aspect ratio; a smaller image stays at native size. With `fill="false"`, positive `zoom` scales the image from native size (`1` is unchanged), and signed integer `x` and `y` move it in pixels from the centered position. An axis on which the image fits stays centered, so its offset is ignored. On an oversized axis, the offset is limited so the image always covers that side of the tile, including after zooming or resizing the tile. The image is clipped to its tile. `zoom`, `x`, and `y` cannot be combined with `fill="true"`. Transparent pixels reveal the widget background color, and unselected tiles are dimmed. The layout's `width` or `height` attribute controls the tile size according to the parent layout direction.

`fit="cover"` scales uniformly, including enlarging a small source, until the entire tile is covered.
Excess image content is cropped at the tile edges. `fit="contain"` scales uniformly until the whole
image fits, including enlarging small sources. Choose either `fit` or the legacy `fill="true"`;
fitted modes cannot be combined with manual `zoom`, `x`, or `y`.

`padding="true"` is the default and leaves the layout's surrounding margins visible.
`padding="false"` lets image pixels extend across the full measured tile, including its margins
and adjoining window edges. The image stays clipped to that tile, and separators remain visible.
Combine `fit="cover"`, `padding="false"`, and `interactive="false"` for a static cover without
letterboxing or layout padding. Source transparency still reveals the background.

After Return activates the image, `+` or `*` zooms in by 25%; `-` or `/` zooms out by 20%. The keypad versions work too. Symbols are resolved through the current keyboard layout, including keys requiring Shift or AltGr. Arrow keys pan by half the tile width or height on axes where the image is larger than the tile, stopping at an image edge: Right shows more of the image's right side, and Down shows more of its bottom. Home and End align the image's left and right edges with the tile; Page Up and Page Down align its top and bottom edges. These keys leave a fitting axis centered. Space restores the widget's initial `fill`, `zoom`, `x`, and `y` settings, respecting the current tile size. `=` restores fitted sizing using the configured `fit` mode, or shrinks an oversized image with legacy fitting. Zooming or panning while fitted switches to manual mode at the current fitted scale. Return or Escape releases the widget.

Native layout sizing reads only image header dimensions when the format supports it; unusual formats may require decoding to determine their size. Widgets on unopened screens therefore normally require no pixel decoding. Zero-sized pixel tiles do not trigger decoding.

Widgets referring to the same canonical file path share one lazy `Core\ImageSource` and one immutable `RasterImage`, while keeping independent zoom, offsets, and background colors. The renderer consequently shares one SDL texture per window for that file. Decoded pixels remain available while their source is held by widgets; the shared-source cache uses weak references so it does not keep unused sources alive. Textures are reused while visible and released when absent from the next frame. Changes to a file after decoding do not reload the shared source.

`new Image(string|GdImage $src, bool $fill, float $zoom, int $x, int $y, Color $bg, ?string $fit, bool $interactive, bool $padding, ?string $title, ?Style $style)` also accepts a caller-owned GD image and immediately copies its pixels, so callers can safely modify or destroy it afterward. GD inputs are separate snapshots. `source()` forces any pending file decode and returns the shared immutable `RasterImage` with its dimensions and pixel data.

`setSource(string|\GdImage $source)` replaces the lazy image source, restores the
initial fitting, zoom, and offsets, and emits `change`.

For example, `new Image($path, fit: 'cover', interactive: false, padding: false)`
creates a static image that fills its tile. Existing constructor calls keep their behavior.
