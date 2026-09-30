
# Classes


## App level: one instance per app

Never pass these as function arguments because it's accessible globally through the App's static helpers.

- Rendering\Font: app font, can be used rendering other fonts, but there is one common app font
- SDLWrapper\SDL: the sdl ffi instance
- SDLWrapper\TTF: the sdl-ttf ffi instance


## Window level: one instance per window

Pass further as function arguments.

- Core\Window: represents the window
- Core\WindowPlacement: captures and applies native window modes and pixel sizes
- Layout\WindowGeometry: immutable cell size, window size, and grid offsets with pixel-area conversion
- Rendering\Grid: the common center aligned character grid
- Rendering\GridRenderer: renders the grid to the window (ffi renderer)
- Rendering\GlyphAtlas: glyph atlas for the window, available through GridRenderer. Its texture belongs to the window.
- Rendering\PixelRenderer: draws tile backgrounds, paddings, separators, images
- Rendering\CanvasRenderer: retains canvas surfaces and owns a window's application texture context

The full render path, partial updates, dirty cells, and caches are described in [rendering.md](rendering.md).


## Screen level:

Classes that belongs strictly to a screen

- Core\Screen: defines one visible screen and manages widget selection and activation
- Layout\LayoutNode: holds the layout structure, tree of vertical and horizontal subdivisions plus leafs
- Layout\LayoutLeaf: container for the widgets
- Layout\LayoutSeparator: marks a split boundary for screen-level separator drawing


## XML parsing

Classes in `XmlParser/` use the `SPTK\XmlParser` namespace and turn app, screen, widget, event, and style XML
into runtime objects.

- XmlParser\XmlParser: loads `Layout/app.xml` and builds window definitions
- XmlParser\ScreenParser: loads screen files and builds their layouts, widgets, and events
- XmlParser\WidgetParser: contract implemented by each widget's XML parser
- XmlParser\AttributeParser: shared XML attribute validation and conversion
- XmlParser\EventParser: parses XML event declarations and key chords
- XmlParser\StyleParser: parses `<Style>` color overrides and applies them to inherited styles
- XmlParser\ItemParser: reads item records shared by choice and list widgets
- XmlParser\ScreenSelector: expands a window-level selector into button layouts on its screens

The `Core\Style` defaults are passed down the XML tree. A `<Style>` element at app, window, screen, layout, or
widget level overrides only the colors it declares; descendants inherit the resulting style.

## Helpers

Static or reusable classes.

- Layout\Tile: defines a non overlapping rectangle inside the window; it can be measured in pixels or cells,
  depending on the caller
- Layout\Splitter: splits a Tile horizontally or vertically to other Tiles measured in cells
- Core\ScrollIndicator: formats textual marks for hidden content beyond a viewport
- Core\ItemData: validates unique item values and labels
- Core\ItemViewport: tracks item cursor and vertical scrolling
- Core\WidgetTitle: validates and paints a fixed title above list and choice item viewports
- Widgets\List\View: coordinates fixed titles, item viewport sizing, and partial row redraws
- Widgets\Table\TableData: stores inline rows or indexes and caches escaped TSV chunks
- Widgets\Table\Painter: measures columns and paints headers, row numbers, body cells, and scroll indicators
- Widgets\Table\Navigator: moves the table cursor to visible row and field edges, then pages the viewport
- Widgets\Table\Redraw: tracks rows changed by cursor movement for partial painting
- Widgets\Table\Selection: tracks rectangular selection and copies escaped TSV
- Widgets\Graph\Data: validates numeric series and graph options atomically
- Widgets\Graph\Axes: computes linear bounds, ticks, and grouped bar spacing
- Widgets\Graph\Plot: draws clipped lines, points, and grouped bars into GD images
- Widgets\Graph\Labels: measures and clips FreeType graph text
- Widgets\Graph\Raster: composes graph axes, labels, grid, legend, and series
- Widgets\StyledText\Format: validates rich text styles and resolves pixel and viewport dimensions
- Widgets\StyledText\Fonts: resolves font faces and measures FreeType baseline metrics
- Widgets\StyledText\Lines: wraps styled runs into lines sharing a baseline
- Widgets\StyledText\Raster: paints clipped rich text, padding, backgrounds, and borders
- Core\RasterImage: stores GD-decoded pixels for SDL texture upload
- Core\Texture: owns a reusable sprite or writable layer with pixel drawing and region copying
- Core\TextureContext: creates application textures and releases them before its window closes
- Rendering\RenderState: restores SDL targets, viewports, clipping, draw color, and blend modes
- Rendering\ImagePixels: transfers GD pixels in bulk and uses SDL for native pixel format conversion
- SDLWrapper\PNG: reads in-memory PNG pixels through libpng's public simplified API
- Core\ImageSource: shares file-backed sources, reads header dimensions, and decodes pixels on demand
- Core\WidgetDefinition: bundles a widget with its event subscriptions
- Rendering\FontFinder: search for a font based it's name
- Rendering\GridWriter: clips the writings to a tile
- Rendering\TextMetrics: to get glyph attribites
- Core\Widget: abstract base class for widget behavior and preferred layout sizing
- Core\WidgetSelection: tracks focus and chooses the next widget from tile geometry
- Core\FocusNavigation: keeps the current layout scope and restores child focus after exit
- Core\AppData: resolves private per-application files and loads or saves JSON [configuration](config.md)
- Core\Color: rgb color data
- Core\Style: inherited application colors with defaults and local overrides
- Core\Cell: a character grid cell; glyph, fg, bg, width

Pass `Core\Style` directly between XML parsers, widgets, and painters that share a palette, rather than
unpacking it into individual color arguments. Individual drawing operations can still take `Core\Color`.

## Events

- Events\EventLoop: stores windows and timers and dispatches SDL and timer events
- Events\KeyNormalizer: canonicalizes key names and modifiers, resolves layout symbols, and normalizes keypad navigation
- Events\EventDefinition: stores one parsed event subscription
- Events\EventContext: provides event type, source widget, and native input to actions
- Events\EventDispatcher: invokes matching static actions and handles input consumption
- Events\ScreenInput: dispatches screen input actions and button hotkeys
- Events\KeyboardEvent: converts SDL keyboard fields to PHP scalars before dispatch
- Events\WidgetEventEmitter: lets widgets subscribe named handlers to their lifecycle events

## Widgets

Widget behavior and XML attributes are documented by widget in the [widget documentation](Widgets/Widgets.md).
`Widgets\Button\Button` runs actions and paints the selected screen state; its parser handles standalone buttons.
