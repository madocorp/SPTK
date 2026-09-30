# Layout

A screen's `<Layout>` elements form a tree of horizontal and vertical splits. Each widget element occupies one
tile at a leaf of that tree. Nested layouts split their assigned tile again.

Window creates one readonly `Layout\WindowGeometry` on each resize. Its public fields hold `cellWidth`,
`cellHeight`, `windowWidth`, `windowHeight`, `offsetX`, and `offsetY`, all measured in pixels. Screen and layout
elements receive this object through `measureArea(Tile $windowGrid, WindowGeometry $geometry)`.
Each element keeps its own grid tile; the shared geometry converts it to cell-content pixels, padded
background bounds, and two-pixel separator rectangles. Backgrounds and separators clip to the window;
fixed tiles beyond its edges produce empty decoration areas when the window becomes too small.
Interior backgrounds extend by one cell horizontally
and half a cell vertically, with odd cell heights rounded down above and up below. Backgrounds touching
the outer grid edges extend to the corresponding window edges.

`Layout\Tile` normalizes negative widths and heights to zero for every layout and rendering path.
Negative positions remain valid for content that extends beyond an edge. Empty rectangles skip drawing,
so a window that becomes too small can collapse its content without a size exception.

Pixel widgets can override `Widget::pixelPadding()` to return `false`, drawing across their full
measured background area instead of only their character-cell rectangle. Image exposes this as
`padding="false"`. Separators draw after pixel content so they remain visible.

## Direction and structure

Every screen file contains one `<Layout>` inside its `<Screen>`. A layout's `direction` controls how its
children are arranged:

- `vertical` stacks children from top to bottom. This is the default when `direction` is omitted.
- `horizontal` places children from left to right.

Children can be widgets, nested `<Layout>` elements, or `<Separator>` elements. A separator must appear between
two layout items; it cannot be first or last, and two separators cannot be adjacent. Separators are visual
boundaries and do not take a size attribute. See the [widget documentation](Widgets/Widgets.md) for widget-specific
attributes and behavior.

## Navigation

Set `navigateChildren="false"` to keep the layout itself as one selectable tile while skipping its
individual descendants. An optional layout `id` identifies this focus tile. Selecting it highlights
its rendered descendants together. With the default `navigate="true"`, the layout region remains
reachable while its descendants are skipped. `navigateChildren` defaults to `true`.

Add `enterChildren="true"` to an explicitly grouped layout to let Return enter its own navigation
scope. Arrows then move among its child focus tiles, and Escape returns to the grouped layout tile.
Nested grouped layouts can be entered in the same way. Returning to a layout restores its last
selected child. `enterChildren` defaults to `false` and requires `navigateChildren="false"`.
At the outer level a selected group keeps all of its children bright; after entry, only the
selected child stays bright. A group dims as one region when another outer tile is selected.

Set `navigate="false"` on a widget or layout to omit it from arrow-key destinations. On a layout,
this omits the whole subtree, whether or not `navigateChildren` is enabled. It remains visible,
available by ID, and usable through actions such as button hotkeys. Initial or explicit selection
can still focus it; arrows then move to the next eligible tile. `navigate` defaults to `true`.

## Sizing

The parent layout's direction determines the size attribute for each child. A vertical parent reads each
child's `height`; a horizontal parent reads each child's `width`. The other dimension fills the available tile.
The root layout fills the screen and does not accept `width` or `height`.

Sizes are expressed in grid cells. A whole number is a fixed size. A value ending in `*` is a weight for sharing
the remaining space. For example, `1*` and `2*` divide the remaining space in a 1:2 ratio. If a size is
omitted on a widget, its `Widget` base class can provide a preferred fixed size for that axis; otherwise it
defaults to `1*`. An explicit size always takes precedence. Give a nested layout its size on the axis
of its parent, just like a widget; omitted nested-layout sizes default to `1*`.

## Example

This layout has a one-row header, a two-column middle section with a 1:2 split, and a two-row footer:

```xml
<Screen>
  <Layout direction="vertical">
    <Text height="1">Header</Text>
    <Separator />
    <Layout direction="horizontal" height="1*">
      <Text width="1*">Left</Text>
      <Separator />
      <Text width="2*">Right</Text>
    </Layout>
    <Separator />
    <Text height="2">Footer</Text>
  </Layout>
</Screen>
```

The vertical root assigns one row to the header and two rows to the footer; its nested layout receives the
remaining height. The nested horizontal layout divides its width between the two widgets according to their
weights.

Navigation uses tile positions to prefer the nearest widget in the same row or column, then falls back to
the geometrically nearest widget in the arrow-key direction. See
[`navigation.md`](navigation.md) for focus and activation behavior.

## Screen selector

Put `<ScreenSelector screens="main,editors,lists" />` inside a window in `app.xml`. Each name refers to a
`<Screen id="..." title="..." file="..." />` in that window. The selector creates a one-row horizontal
layout above each screen's content, with a separator between them. Its buttons follow the `screens` order,
display screen titles, and use F1 through F12 in that order. A selector accepts 1 to 12 unique screen IDs. The screen's own layout still
comes from its separate XML file. Allow enough window columns for all button labels and hotkeys.

Every widget may have an `id` attribute unique within its screen. In code,
`$window->screen('editors')?->widget('save')` returns its widget instance. Use
`$window->setCurrentScreenId('editors')` to select a screen by ID.

Applications can build `LayoutNode` and `LayoutLeaf` objects directly for generated content.
`LayoutNode::replaceChild($current, $replacement)` replaces a nested leaf or layout by identity
and returns whether it was found. Call `Screen::setLayout($screen->layout)` to reindex a changed
tree; focus and input mode stay with the selected widget if that instance survives. A removed
active widget is released. Call `setWindow($window)` if newly added buttons need window binding,
then `$window->resize()` to measure and render the changed tree. MaDemonstrator uses this to
replace its slide preview while retaining the Markdown editor's buffer, cursor, and history.

Window modes and pixel sizes can also be changed at runtime with `Core\WindowPlacement::apply($window,
['mode' => 'normal', 'width' => 1280, 'height' => 720])`. Supported modes are `normal`, `maximized`, and
`fullscreen`. `capture($window)` returns the current mode and size for later restoration.

Add one `<StatusBar height="1" />` to any screen to display the currently selected widget's
one-line tip automatically. It updates when focus or activation changes. The status bar uses the app's
regular text font. Widgets supply default tips based on their behavior and attributes. Any widget may
override its tip with `tip="..."` and optionally use `activeTip="..."` while activated. If only `tip`
is given, it applies in both states. An empty tip deliberately clears the bar. A layout with
`navigateChildren="false"` may also have `tip`, which belongs to its one focus tile. Applications
may call `StatusBar::notice($message)`, `warning($message)`, or `error($message)` for
color-coded updates. `notify($message)` remains an alias for `notice()`. The selected
widget's tip returns at the next focus or activation change. `confirm($message, $yes,
$no, $cancel)` shows a warning and handles Y, N, and Esc before widget input. The
screen keeps its current focus while the confirmation is pending.
