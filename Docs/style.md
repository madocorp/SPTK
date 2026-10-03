# Style

SPTK reads colors from `<Style>` elements. Each element can set one or more palette colors; omitted colors keep
their inherited values. Color values use `#RRGGBB` notation.

## Default palette

When no `<Style>` sets a color, `Core\Style` supplies these defaults:

| Color | Default | Purpose |
| --- | --- | --- |
| `Background` | `#202630` | Widget background |
| `Foreground` | `#edf1f5` | Widget text |
| `Separator` | `#475568` | Layout separators |
| `BorderColor` | `#475568` | Pixel layout borders |
| `BorderWidth` | `0` | Default pixel layout border width; accepts one to four sizes |
| `Margin` | `0` | Space outside a pixel item's border |
| `Padding` | `0` | Space between a pixel item's border and inner area |
| `Highlight` | `#80cbc4` | Highlight palette color |
| `Selected` | `#ffd180` | Selected palette color |
| `CursorBackground` | `#526579` | Cursor palette background |
| `CursorForeground` | `#ffffff` | Cursor palette foreground |
| `Error` | `#ff6e6e` | Status bar error text |

The demo sets its own palette explicitly in `Demo/Layout/app.xml`.

## PHP palettes

Text, Input, TextEditor, ListView, RadioButton, CheckboxArray, and Button accept one `Core\Style` instead of
separate foreground, background, cursor, and highlight arguments. Their parsers pass the inherited style to
the widget, and the widget passes it to its painter.

```php
$style = new SPTK\Core\Style(
  background: new SPTK\Core\Color(24, 28, 36),
  foreground: new SPTK\Core\Color(230, 235, 245),
);
$text = new SPTK\Widgets\Text\Text('Hello', style: $style);
$input = new SPTK\Widgets\Input\Input('Name', style: $style, label: 'Name');
```

Replace old named color arguments such as `bg` and `indicatorFg` with `style`, using `background` and
`highlight` entries respectively. Omitting `style` uses the shared default palette. Input and
TextEditor continue to use `foreground` for their caret and selection text; Text uses `cursorForeground`.

## Inheritance and overrides

Styles inherit down the XML tree. A style can be declared inside `App`, `Window`, a screen file's `Screen`,
`Layout`, or a widget element. A child starts with the parent's effective colors, then overrides only the colors
declared in its own `<Style>`.

Place a `<Style>` before the windows, layouts, or widgets that should inherit it. For example, an app-level
style sets defaults for its windows, while a window-level style can override them for that window:

```xml
<App>
  <Style>
    <Background>#101820</Background>
    <Foreground>#f0f0f0</Foreground>
  </Style>
  <Window title="Main">
    <Style>
      <Separator>#607080</Separator>
    </Style>
    <Screen file="main.xml" />
  </Window>
</App>
```

A local layout or widget can override a subset without resetting the other colors:

```xml
<Layout direction="vertical">
  <Style>
    <Background>#202830</Background>
  </Style>
  <Text height="1">Uses the layout background and inherited foreground</Text>
  <Text height="1">
    <Style>
      <Foreground>#ffcc00</Foreground>
    </Style>
    Uses a local foreground override
  </Text>
</Layout>
```

Widget color attributes such as `fg` and `bg` are not supported. Set colors and pixel box edges with child
elements inside `<Style>`. Colors inherit; `Margin`, `BorderWidth`, and `Padding` apply to one layout item
and reset for its children.

## Palette entries

`Background`, `Foreground`, and `Separator` drive widget backgrounds, text, and grid separators.
`BorderColor` colors pixel layout borders. `BorderWidth`, `Margin`, and `Padding` size the current pixel
layout item; they do not change cell tile geometry.
`Highlight`, `Selected`, `CursorBackground`, and `CursorForeground` are shared palette entries for selection and
cursor styling; current widgets may not use each entry yet. Widget selection currently dims unselected cells.
