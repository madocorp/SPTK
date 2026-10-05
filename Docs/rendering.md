# Rendering

SPTK keeps two forms of the current window image: a character `Grid` in PHP and an SDL render target texture
(`Window::frameTexture`) containing the composed pixels. Each widget owns one layout tile. Widgets write cells
through a tile-clipped `GridWriter`; widgets such as `Image` can also draw pixels inside their tile.

## Full screen render

`Window::renderScreens()` runs during window initialization, after a resize or exposure, and when the active screen changes.
It clears the frame texture and the grid, then renders the current screen in this order:

1. `Screen::drawBackgrounds()` paints tile backgrounds; `drawSeparators()` paints layout boundaries.
2. `Screen::paint()` asks each widget to write its cells into the grid. Unselected tiles have their cell colors
   darkened by `LayoutLeaf`.
3. `GridRenderer::drawTile()` draws the backgrounds and glyphs inside each widget tile. Tile bounds leave separator gaps untouched, and black swatches render normally.
4. `Screen::paintPixels()` draws pixel widgets over the grid. `PixelRenderer` tracks the image textures used in
   this frame and releases textures no longer used. Unselected pixel content retains 75% brightness, matching
   character cells and tile backgrounds.
5. `Window::presentFrame()` copies the frame texture to the window backbuffer and calls `SDL_RenderPresent()`.

Resizing recreates the frame texture and grid, measures the layout again, and takes this full render path.
Window also replaces its readonly `Layout\WindowGeometry` on resize and passes the same object to every
screen's layout measurement. Full renders, active-widget updates, and focus changes use that geometry
for pixel widgets: `Screen::paintPixels($renderer, $geometry)` forwards it to each leaf, and
`LayoutLeaf::paintPixels($renderer, $geometry, $selected)` converts the leaf's grid tile into a pixel area.
Widgets continue to receive `paintPixels($renderer, $area, $selected)` with their concrete pixel rectangle.

## Input updates

`Window::handleEvent()` sends keyboard and text input to the current `Screen`. The resulting render path depends
on what changed:

| Change | Render work |
| --- | --- |
| Handled input in the same active widget | `renderLeaf()` updates that widget's tile; a widget may paint fewer cells. |
| Focus moves to another tile | `renderFocusChange()` repaints the old and new tiles, including their selection shading. |
| Screen switch, resize, exposure, or other event path | `renderScreens()` rebuilds the current screen. |

For an active widget update, `LayoutLeaf::paintUpdate()` calls the widget's `paintUpdate()`. Returning `true`
means the widget wrote everything needed for this update. Returning `false` makes the leaf call the widget's
full `paint()` for its tile. The base `Widget::paintUpdate()` returns `false`, so a widget with no partial paint
implementation still works.

The SDL frame texture retains pixels outside the repainted area. `renderLeaf()` updates that texture, then
`presentFrame()` copies the complete texture to the window backbuffer. The copy and present still cover the
window; the saved work is in widget painting and drawing into the frame texture.

## Dirty cells

Before a widget update, `Grid::beginUpdate()` starts collecting written cell positions. Every write marks its
position dirty, even if it writes the same value again. `Grid::dirtyCells()` returns the current cells at those
positions and clears the marks; it does not compare them with previous values. Multiple writes to one position
produce one dirty entry.

`GridRenderer::drawDirty()` redraws each dirty cell's background and glyph in the retained frame texture.
Wide glyphs occupy a leading cell and a continuation cell. The grid marks both columns when a wide glyph is
written or cleared; the renderer skips continuation entries because drawing the leading cell covers their
pixels. `GridWriter` clips writes to the widget's tile and substitutes a blank for a clipped wide glyph.

Dirty tracking limits SDL cell drawing to positions a widget wrote. It does not by itself reduce widget work:
a full tile `paint()` writes and dirties the whole tile. Widgets that support smaller updates choose what to
write in `paintUpdate()`.

## Scroll indicators

Widgets put horizontal indicators in the bottom corners and vertical indicators on the right edge. When both
rightward and downward overflow exist, `ScrollIndicator::bottomRight()` puts both labels on the bottom row,
horizontal first (`▶ ▼`). `ScrollIndicator::fit()` removes counts and spacing as needed in narrow tiles.

## Widget optimizations

- `Text` caches its wrapped visual rows by tile width. `TextEditor` caches visual rows by width and document
  revision; cursor movement does not change that revision. Both also cache the widest line width used for
  horizontal scrolling.
- For a cursor move with unchanged viewport, document, and selection, `Text` and `TextEditor` restore the cell
  beneath the old cursor and paint the new cursor cell. A scroll, selection change, edit, or size change uses a
  full tile paint. A wide glyph may affect an additional column.
- `ListView` tracks rows affected by cursor movement or selection. When the viewport stays fixed, its painter
  writes only those rows. Scrolling, filtering, and data changes require a full tile paint.
- `Table` repaints only the old and new cursor rows when its viewport stays fixed. Scrolling repaints the tile.
- Pixel widgets use a separate path: `renderLeaf()` clears that tile's pixel background, draws its grid cells,
  paints its pixel content, and restores separator lines. `Image` draws from a cached SDL texture for its
  decoded source rather than uploading it on every frame. File-backed images decode on first display;
  widgets with the same file share their source pixels and one texture per renderer. Layout normally reads
  only image header dimensions, so unopened screens avoid pixel decoding.
- `GlyphAtlas` caches rendered white glyph bitmaps in a texture owned by the window renderer. Cell colors are
  applied when the cached glyph is drawn. The atlas reuses slots when full and reuses FFI rectangle objects
  for glyph lookup and upload. Geometry glyph masks are rasterized into the atlas on first use, then use the
  same single texture draw as font glyphs instead of filling their pixel spans on every repaint.
- `PixelRenderer` keeps the current draw color for the shared SDL renderer; `GridRenderer` keeps the atlas
  texture tint. They set SDL colors only when the RGB value changes. Window invalidates the draw-color cache
  when switching render targets.

These caches have different lifetimes: visual rows belong to a text widget, image and glyph textures belong
to an SDL renderer, and the frame texture belongs to a window until resize or close.

## Canvas surfaces and application textures

`CanvasRenderer` owns one `TextureContext` per window. Canvas surfaces are allocated on first display
and repainted only after invalidation or recreation. Resizing replaces the surface; leaving the screen
releases unused surfaces. Application sprites and writable layers remain available until explicitly
destroyed, discarded by PHP, or released during window shutdown. Canvas invalidation queues a window
expose event to redraw after timer-driven updates as well as keyboard input.
Queued Canvas exposes carry an internal marker. If input has already repainted all changed Canvas
surfaces, the window consumes the notification without repainting or presenting again. Native expose
events continue to redraw the complete frame. Unconsumed input, including the extra text-input event
from holding Space, does not redraw an unchanged window.

`Texture` drawing and copy operations restore the shared renderer state. Writable textures accumulate
premultiplied pixels and use SDL's predefined premultiplied blend mode when copied, preserving alpha
through intermediate layers. Imported image textures use ordinary alpha blending. Nearest filtering
keeps scaled sprite pixels crisp. Selection shading affects the composed canvas rather than its assets.
The painter runs inside one `TextureContext::withTarget()` scope, so copies to its surface remain queued
under the same SDL target. Drawing to another layer creates a nested scope and restores the Canvas
target afterward. This avoids switching targets and flushing SDL's drawing queue for every sprite.
See [Canvas](Widgets/Canvas.md) for the app API and Madventure atlas workflow.

Run `php Tests/CanvasPerformance.php` for repeated-key rendering and timer-style invalidation checks,
plus frame timings with the dummy driver. `SDL_VIDEODRIVER=offscreen php Tests/CanvasPerformance.php 200 90`
measures a headless renderer at 200 columns by 90 rows. Timings include input handling and presentation;
no machine-dependent timing limit is asserted.

## Image pixel transfer

PNG files are decoded directly with libpng's simplified API. `SDL_ConvertPixels` converts the RGBA bytes to
native-endian `SDL_PIXELFORMAT_RGBA8888` words. This route requires `libpng16.so.16`, but does not require GD.
Other file formats and caller-owned GD images still use GD. GD exports an uncompressed PNG into memory for
the same libpng and SDL conversion, preserving alpha and transparent palette entries without changing the
caller image. If native conversion cannot load, GD inputs fall back to PHP pixel getters. That failure is
remembered so later GD inputs do not retry it. No temporary image files are created.

Run `php Tests/RasterImage.php` for RGB, alpha, palette, row order, and caller ownership checks.
Run `php Tests/RasterFallback.php` for missing-library fallback and retry suppression checks.
