# Layout

A screen's `<Layout>` elements form a tree of horizontal and vertical splits. Each widget element occupies one
tile at a leaf of that tree. Nested layouts split their assigned tile again.

## Direction and structure

Every screen file contains one `<Layout>` inside its `<Screen>`. A layout's `direction` controls how its
children are arranged:

- `vertical` stacks children from top to bottom. This is the default when `direction` is omitted.
- `horizontal` places children from left to right.

Children can be widgets, nested `<Layout>` elements, or `<Separator>` elements. A separator must appear between
two layout items; it cannot be first or last, and two separators cannot be adjacent. Separators are visual
boundaries and do not take a size attribute. See the [widget documentation](Widgets/Widgets.md) for widget-specific
attributes and behavior.

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

Navigation uses the resulting tile positions to choose the nearest widget in the arrow-key direction. See
[`navigation.md`](navigation.md) for focus and activation behavior.
