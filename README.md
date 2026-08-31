# SPTK

SPTK is an experimental grid-first successor project for SPTK.

The current SPTK remains the stable toolkit for existing applications. SPTK is
a separate proving ground for a smaller, designed API where windows are pixel
hosts and elements expose character grids inside their own pixel boxes. SDL
rendering should become a backend over this model, not the model itself.
Element APIs are local-first: shared core API is promoted only after repeated
concrete use proves the same capability and call shape.

## Current Scope

- Headless grid buffer with cells, colors, flags, clipping, blitting, and dirty
  regions.
- Immutable RGBA colors and a configurable theme with MADB/ncurses-like default
  colors.
- Element tree that renders into a grid buffer.
- Ordered `RenderTarget` drawing commands for cells, text, clipping, and
  border/line decorations.
- Passive element-tree invalidation for layout, focus, and render refreshes.
- Pixel-first `Dock` layout with typed `Len`/`Place` positioning, dock, fill,
  free placement, cursor placement, and parent-owned separators.
- Basic widgets: dialog box, label, status bar, menu bar, list, table, text
  editor, scrollbar.
- Interactive menu bar navigation with item activation callbacks.
- Popup submenus with aligned left/label/right columns, adornments, width hints,
  and separator line decorations.
- Explicit special-surface placeholders: graphics, image, and HTML views.
- Experimental SDL backend with multi-window app runtime, one grid UI font per
  window, a glyph atlas, fullscreen/maximized startup options, and SDL input
  mapped to SPTK input events.
- Headless examples and tests.

XML/XSS compatibility is intentionally out of scope for the first experiment.

See `Docs/elements.md` for the pixel-first element layout rule and API
discipline.

## Run Tests

```sh
php Tests/run.php
```

## Run Examples

```sh
php Examples/demo.php
```
