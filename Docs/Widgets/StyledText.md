# StyledText

`StyledText` renders read-only proportional text inside one native layout tile. It supports
mixed font sizes, families, bold/italic faces, colors, inline backgrounds, wrapping, explicit
line breaks, alignment, margins, padding, and borders. PHP GD with FreeType is required.

```xml
<StyledText height="1*" fontSize="28" textAlign="center" verticalAlign="center">
  Hello <Run bold="true" color="#ffd180">world</Run><Br />
  <Run fontFamily="monospace" fontSize="20">some code</Run>
</StyledText>
```

Body text, CDATA, and spaces between runs are preserved. Use compact XML or CDATA when
formatting whitespace should not appear in the rendered text. `<Br />` starts a new line.
`Run` contains only text and accepts the same typography attributes as the parent.
The widget also accepts the usual inherited `<Style>` and `<Event>` elements.

| Attribute | Default | Behavior |
| --- | --- | --- |
| `fontFamily` | `sans-serif` | Font family or explicit font file path, resolved by FontFinder. |
| `fontSize` | `24` | CSS pixel font size; also accepts `px`, `vh`, and `vw`. |
| `fontWeight` / `bold` | `normal` / `false` | Select a bold font face. |
| `fontStyle` / `italic` | `normal` / `false` | Select an italic font face. |
| `color` | inherited foreground | Text color. |
| `background` | `transparent` | Raster background; the tile still has its inherited opaque background. |
| `textAlign` | `left` | `left`, `center`, or `right`. |
| `verticalAlign` | `top` | `top`, `center`, or `bottom`. |
| `wrap` | `true` | Wrap at words and split oversized words at grapheme boundaries. |
| `lineGap` | `4` | Pixels between lines, including viewport-unit support. |
| `margin` | `0` | Transparent space outside the box background and border. |
| `padding` | `0` | Pixel inset around text. |
| `borderWidth` | `0` | Pixel border thickness. |
| `borderColor` | `#ffffff` | Border color. |
| `dimmed` | `true` | Apply the normal pixel-widget shading when its tile is unselected. |

Colors accept `#RGB`, `#RRGGBB`, `#RRGGBBAA`, or `transparent`. Viewport units resolve
against this widget's pixel tile. Runs share a baseline even when font sizes differ.
Explicit newlines and code indentation survive wrapping; tabs expand to four spaces.
Margins stay inside the allocated tile; ink, backgrounds, and borders clip to the tile.
These widget options remain available in cell layouts. In an opt-in pixel layout, the
layout item owns margin, border, and padding through its `<Style>` elements, and the
widget options are ignored so the inset is measured only once. See
[`layout.md`](../layout.md#opt-in-pixel-layouts).
StyledText never enters input mode.

The PHP API accepts a string or an array of runs:

```php
$text = new \SPTK\Widgets\StyledText\StyledText([
  ['text' => 'Hello '],
  ['text' => 'world', 'bold' => true, 'color' => '#ffd180'],
  ['type' => 'br'],
  ['text' => 'Code', 'fontFamily' => 'monospace'],
], ['fontSize' => 28]);
$text->setContent('Replacement', ['fontSize' => 24]);
```

`runs()` and `options()` expose normalized data. `setContent()` replaces both atomically
and emits `change` only when content differs. Invalid formatting leaves the old content
intact. PHP `margin`, `padding`, and `borderWidth` can also use named `top`, `right`, `bottom`, and
`left` edges; `fontFamily` can be an ordered array of fallback families.
`contentHeight($width, $referenceHeight = 600)` measures pixel height.
`raster($width, $height)` caches the immutable raster until text, style, or dimensions change.

Apps can subclass StyledText and override `content()` to resolve semantic styles against
an enclosing layout's measured grid. MaDemonstrator uses this for slide-relative font sizes
in both its presentation and editor preview; their slide subtree uses pixel tiles.

Run `php Tests/StyledText.php` for typography, wrapping, clipping, XML, color, and cache checks.
