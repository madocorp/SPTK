# Image

`<Image>` displays a local image decoded by PHP GD. It supports PNG, JPEG, GIF, BMP, WebP, AVIF, and other formats available in the installed GD build. Animated formats show the frame GD decodes; they do not animate.

```xml
<Image src="../Assets/photo.png" width="1*" zoom="1.5" x="20" y="-10" />
<Image src="../Assets/photo.png" width="1*" fill="true">
  <Style><Background>#102030</Background></Style>
</Image>
```

Relative `src` paths are resolved against the XML screen file; absolute paths are accepted. Missing, unreadable, corrupt, or unsupported files raise an error with the path. `Image` requires PHP GD. Nested `<Event>` and `<Style>` elements work as for other widgets. It remains a selectable tile and follows the usual Return and Escape activation rules.

The image is centered in its tile at native pixel size by default. `fill="true"` shrinks an oversized image until the whole image fits inside the tile while keeping its aspect ratio; a smaller image stays at native size. With `fill="false"`, positive `zoom` scales the image from native size (`1` is unchanged), and signed integer `x` and `y` move it in pixels from the centered position. An axis on which the image fits stays centered, so its offset is ignored. On an oversized axis, the offset is limited so the image always covers that side of the tile, including after zooming or resizing the tile. The image is clipped to its tile. `zoom`, `x`, and `y` cannot be combined with `fill="true"`. Transparent pixels reveal the widget background color, and unselected tiles are dimmed. The layout's `width` or `height` attribute controls the tile size according to the parent layout direction.

After Return activates the image, `+` or `*` zooms in by 25%; `-` or `/` zooms out by 20%. The keypad versions work too. Arrow keys pan by half the tile width or height on axes where the image is larger than the tile, stopping at an image edge: Right shows more of the image's right side, and Down shows more of its bottom. Home and End align the image's left and right edges with the tile; Page Up and Page Down align its top and bottom edges. These keys leave a fitting axis centered. Space restores native size and centered position. `=` fits an oversized image to the tile. Zooming or panning while fitted switches to manual mode at the current fitted scale. Return or Escape releases the widget.

`new Image(string|GdImage $src, bool $fill, float $zoom, int $x, int $y, Color $bg)` also accepts a caller-owned GD image and copies its pixels. `source()` returns an immutable `RasterImage` with the decoded dimensions and pixel data. Image textures are reused while visible and released when absent from the next frame. Changing a source file does not reload an existing widget.
