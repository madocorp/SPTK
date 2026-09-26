
# Classes


## App level: one instance per app

Never pass these as function arguments because it's accessible globally through the App's static helpers.

- Rendering\Font: app font, can be used rendering other fonts, but there is one common app font
- SDLWrapper\SDL: the sdl ffi instance
- SDLWrapper\TTF: the sdl-ttf ffi instance
- Core\EventLoop: stores the windows and hand out events to the them


## Window level: one instance per window

Pass further as function arguments.

- Core\Window: represents the window
- Rendering\Grid: the common center aligned character grid
- Rendering\GridRenderer: renders the grid to the window (ffi renderer)
- Rendering\GlyphAtlas: glyph atlas for the window, available through GridRenderer. Its texture belongs to the window.
- Rendering\PixelRenderer: draws tile backgrounds, paddings, separators, images


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

The `Core\Style` defaults are passed down the XML tree. A `<Style>` element at app, window, screen, layout, or
widget level overrides only the colors it declares; descendants inherit the resulting style.

## Helpers

Static or reusable classes.

- Layout\Tile: defines a non overlapping rectangle inside the window; it can be measured in pixels or cells,
  depending on the caller
- Layout\Splitter: splits a Tile horizontally or vertically to other Tiles measured in cells
- Core\EventDefinition: stores one parsed event subscription
- Core\EventContext: provides event type, source widget, and native input to actions
- Core\EventDispatcher: invokes matching static actions and handles input consumption
- Core\WidgetDefinition: bundles a widget with its event subscriptions
- Core\ChangeAwareWidget: optional widget contract for value change notifications
- Rendering\FontFinder: search for a font based it's name
- Rendering\GridWriter: clips the writings to a tile
- Rendering\TextMetrics: to get glyph attribites
- Core\Widget: common widget interface
- Core\InputHandler: optional input contract for an activated widget
- Core\Color: rgb color data
- Core\Style: inherited application colors with defaults and local overrides
- Core\Cell: a character grid cell; glyph, fg, bg, width

## Widgets

Classes under Widgets directory. Each widget should have an own namespace and directory with an XML parser (Parser.php),
and the widget definition. There can be other classes for complex widgets.

- Widgets\Text: shows a text, can be wrapped and scrollable
