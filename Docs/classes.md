
# Classes


## App level: one instance per app

Never pass these as function arguments because it's accessible globally through the App's static helpers.

- Rendering\Font: app font, can be used rendering other fonts, but there is one common app font
- SDLWrapper\SDL: the sdl ffi instance
- SDLWrapper\TTF: the sdl-ttf ffi instance


## Window level: one instance per window

Pass further as function arguments.

- Core\Window: represents the window
- Rendering\Grid: the common center aligned character grid
- Rendering\GridRenderer: renders the grid to the window (ffi renderer)
- Rendering\GlyphAtlas: glyph atlas for the window, available through GridRenderer. Its texture belongs to the window.
- Rendering\PixelRenderer: draws tile backgrounds, paddings, separators, images

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
- Widgets\Table\TableData: stores inline rows or indexes and caches escaped TSV chunks
- Widgets\Table\Painter: measures and paints table columns and scroll indicators
- Widgets\Table\Navigator: moves the table cursor to visible row and field edges, then pages the viewport
- Widgets\Table\Redraw: tracks rows changed by cursor movement for partial painting
- Widgets\Table\Selection: tracks rectangular selection and copies escaped TSV
- Core\RasterImage: stores GD-decoded pixels for SDL texture upload
- Core\WidgetDefinition: bundles a widget with its event subscriptions
- Rendering\FontFinder: search for a font based it's name
- Rendering\GridWriter: clips the writings to a tile
- Rendering\TextMetrics: to get glyph attribites
- Core\Widget: abstract base class for widget behavior and preferred layout sizing
- Core\WidgetSelection: tracks focus and chooses the next widget from tile geometry
- Core\Color: rgb color data
- Core\Style: inherited application colors with defaults and local overrides
- Core\Cell: a character grid cell; glyph, fg, bg, width

## Events

- Events\EventLoop: stores windows and timers and dispatches SDL and timer events
- Events\KeyNormalizer: canonicalizes SDL key names and modifier masks, including keypad navigation keys
- Events\EventDefinition: stores one parsed event subscription
- Events\EventContext: provides event type, source widget, and native input to actions
- Events\EventDispatcher: invokes matching static actions and handles input consumption
- Events\KeyboardEvent: converts SDL keyboard fields to PHP scalars before dispatch
- Events\WidgetEventEmitter: lets widgets subscribe named handlers to their lifecycle events

## Widgets

Widget behavior and XML attributes are documented by widget in the [widget documentation](Widgets/Widgets.md).
`Widgets\Button\Button` runs actions and paints the selected screen state; its parser handles standalone buttons.
