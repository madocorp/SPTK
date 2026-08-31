# Widget Positioning And Sizing

SPTK uses one visible UI concept: an element. In application code these are the
widgets on screen.

A widget owns a pixel box. The box may be transparent, but by default it is
filled with the widget theme background. Inside the box, normal widgets expose a
character grid. The renderer fits as many full cells as possible into the pixel
box, then aligns that grid inside the leftover pixels.

When a non-transparent widget paints its full grid frame or full pixel surface,
the renderer treats that as the widget background and fills the whole pixel box
with the same color. The fitted grid and the extra pixels around it must not
show different background colors.

The window is only a pixel host. It does not own the application's character
grid.

## Box And Grid

- Layout is pixel-first.
- Every widget receives a pixel box from the window or parent layout.
- The widget grid is calculated from full cells that fit inside that box.
- Extra pixels that do not fit a full cell remain inside the widget box.
- Grid alignment defaults to `center`.
- Edge UI such as menu bars, status bars, and popups should use `top-left`.

```php
$editor->setGridAlignment('center');
$status->setGridAlignment('top-left');
```

Supported alignment names are intentionally string-based today. Use combinations
such as `center`, `top-left`, `top`, `left`, and `bottom-right` only where the
renderer understands them.

## Lengths

Use `SPTK\Core\Len` for explicit size and position values:

```php
Len::px(120);       // pixels
Len::cell(8);       // character cells
Len::percent(35);   // percent of the containing box
Len::content();     // preferred widget content size
```

`Len::cells()` and `Len::pixels()` are aliases for readability.

For free positions, negative values are measured from the far edge of the
containing box. A child placed at `Len::px(-20)` with width `100px` has its right
edge 20 pixels from the containing box right edge.

## Dock Layout

`SPTK\Widgets\Dock` divides its own remaining box among children. Use
`SPTK\Core\Place` to describe each child:

```php
use SPTK\Core\Len;
use SPTK\Core\Place;

$root = new Dock('root');
$root->place($menu, Place::dock('top', Len::cell(1)));
$root->place($status, Place::dock('bottom', Len::cell(1)));
$root->place($sidebar, Place::dock('left', Len::percent(30), Len::px(2)));
$root->place($content, Place::fill());
```

Docking is ordered. Every dock consumes from the current remaining rectangle:

- `top` and `bottom` occupy the full remaining width and calculate height.
- `left` and `right` occupy the full remaining height and calculate width.
- Multiple children docked to the same edge are arranged from the outside edge
  toward the center.
- A later left dock never overlaps an earlier top dock because it receives only
  the remaining height.
- `Place::fill()` receives the final remaining box and must be the last
  consuming placement.

Separators belong to the parent `Dock`, not to the child. They reserve space
between the docked child and the remaining area.

## Free Placement

Use `Place::at()` for widgets positioned freely inside the current remaining
box:

```php
$workspace->place(
  $preview,
  Place::at(Len::px(96), Len::px(64), Len::px(120), Len::px(90))
);

$workspace->place(
  $tools,
  Place::at(Len::px(-20), Len::px(20), Len::cell(24), Len::content())
);
```

Free placement does not consume the remaining box. This makes it useful for
overlays, floating previews, inspectors, and pixel-positioned media.

## Widget Cursor

Automatic placement is still part of the API. Use `Place::cursor()` when a
layout should place widgets at the current widget cursor and advance it.

```php
$panel->addContent(new Label('name-label', 'Name:'));
$panel->addContent($nameInput, Place::cursor(Len::cell(1)));
$panel->addContent($notes, Place::cursor(Len::content()));
```

`DialogPanel::addContent()`, `Flow::place()`, and `FlowRow::place()` remain the
normal dialog and form APIs. They position children automatically:

- `Flow` stacks children vertically and advances by the child's preferred rows
  or the explicit cursor height.
- `FlowRow` places children left to right and uses the explicit cursor width
  when supplied.
- `Len::content()` asks the child for its preferred size at the available width.

## Backgrounds And Borders

Use `SPTK\Widgets\Box` when a widget needs a general background or border.
This keeps decoration separate from the child widget's own behavior.

```php
$panel = new Box('panel', $content, [
  'background' => '#101820',
  'border' => 'inside',
  'borderColor' => '#00ffff',
]);

$overlay = new Box('overlay', $content, [
  'background' => 'transparent',
  'border' => 'outside',
]);
```

Border modes:

- `inside` draws inside the assigned box and insets child content.
- `outside` draws around the assigned box where clipping allows.
- `none` draws no border.

A transparent box does not request a pixel-surface background fill. Child
widgets may still paint their own backgrounds.

## Preferred Size

Widgets can expose preferred content size:

- `preferredRows()` defaults to `1`.
- `preferredRowsForColumns($columns)` supports wrapped content height.
- `preferredColumns()` supports row layout and content-width placement.

Use preferred size for content-driven placement, especially dialog content,
forms, and text blocks. Use explicit pixel or cell sizes for stable application
chrome, media previews, and workspace panels.

## API Discipline

New behavior starts local to the concrete widget. Do not add methods to
`Core\Element`, `Widgets\Dock`, `Core\RenderTarget`, `Core\Theme`, or
`Core\ElementContext` for a single widget need.

Promote behavior to shared API only when the same capability is needed by more
than one concrete widget with the same call shape, and update this document when
the rule changes.
