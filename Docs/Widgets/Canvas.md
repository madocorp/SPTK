# Canvas

Canvas gives an app a writable pixel surface and a texture factory. Use it for sprite
atlases, tile maps, games, or other custom graphics. The API supports the atlas workflow
used by `mad/madventure`, reimplemented for mad4's one-widget-per-tile rendering.

```xml
<Canvas id="game" width="1*" painter="GameController::paint">
  <Style><Background>#102030</Background></Style>
  <Event type="keyDown" key="Space" action="GameController::move" />
</Canvas>
```

`painter` names a static method. It is resolved at the first displayed frame, so the
app can finish its setup after parsing XML. Canvas also accepts ordinary widget
`Event` and `Style` children. Its preferred size is 40 columns by 12 rows.

The callback receives `Core\Texture $surface`, `Core\TextureContext $textures`, and
`Widgets\Canvas\Canvas $canvas`. Drawing coordinates are local pixels; `(0, 0)` is
the tile's top-left. The surface's `width()` and `height()` give its current pixel size.

```php
class GameController {
  private static ?\SPTK\Core\Texture $atlas = null;

  public static function paint(
    \SPTK\Core\Texture $surface,
    \SPTK\Core\TextureContext $textures,
    \SPTK\Widgets\Canvas\Canvas $canvas
  ): void {
    if (self::$atlas === null || self::$atlas->destroyed()) {
      self::$atlas = $textures->textureFromImage(APP_DIR . '/Assets/tiles.png');
    }
    // Copy a sprite-sheet region at double size.
    self::$atlas->copy($surface, 32, 16, 40, 60, 16, 16, 32, 32);
  }
}
```

From PHP, construct `new Canvas(painter: $callback)` or call `setPainter($callback)`.
Named functions, static methods, callable arrays, and closures are supported.
`setPainter(null)` displays the background. A game-view subclass can override
`draw(Texture $surface, TextureContext $textures)` and `handleInput(mixed $event)`.
Canvas receives input only while activated; Return activates it and Escape or Return
releases it. Unhandled input reaches the configured XML actions.

The painter runs on first display, after `invalidate()` (also called `refresh()`),
after `setPainter()`, and when the surface size changes or it is recreated. Focus
changes and other widgets' redraws reuse the painted surface. Empty and unopened
Canvases allocate no textures and do not call their painters. Invalidation queues a
window redraw, so a timer can update game state and call `invalidate()` without waiting
for keyboard input. Update state outside the painter, then redraw the complete scene.

Before each callback, the surface is cleared to the widget background. Keep persistent
layers and sprites in separate textures. Do not retain or destroy the supplied surface;
resizing and screen switching can replace it. The completed surface is confined to its
tile and receives the same selection dimming as Image. Cached sprite assets keep their
original colors.

The texture factory provides:

- `createTexture($width, $height, $background = 'transparent')`: a writable layer.
- `textureFromImage($source)`: upload a file path, caller-owned GD image, or `RasterImage`.
  File decoding uses the shared lazy image source. Retain the returned texture for reuse;
  each factory call creates a separately owned native texture.
- `withTarget(Texture $target, callable $draw, array $arguments = [])`: batch multiple
  operations into one target scope when preparing an atlas or layer. The Canvas painter
  is already batched automatically. Nested operations on other textures restore the
  previous target, including exceptions.

Textures provide:

- `width()`, `height()`: pixel dimensions.
- `clear($color = 'transparent')`: replace every pixel, including alpha.
- `fillRect($x, $y, $width, $height, $color)`: filled rectangle.
- `drawRect($x, $y, $width, $height, $color)`: one-pixel outline.
- `drawLine($x1, $y1, $x2, $y2, $color)`: one-pixel line including endpoints.
- `copyTo(Texture $target, $x, $y)`: copy the whole texture at native size.
- `copy(Texture $target, $sourceX, $sourceY, $targetX, $targetY, $width, $height,
  $targetWidth = null, $targetHeight = null)`: crop and optionally scale a region.
- `destroy()`, `destroyed()`: release a texture early or check its lifetime.

Colors accept `Core\Color`, `#RRGGBB`, `#RRGGBBAA`, or `transparent`. Drawing and copying
alpha-blend, including copies through transparent intermediate layers. Copies use
nearest-neighbor filtering for crisp sprites. Source regions and destination drawing
clip to texture extents, preserving scaled alignment. Imported textures are read-only;
copy them to a created layer to edit them.
Copies require different textures from the same texture context. All Canvases in one
window share that context, so they can share atlases and layers.

PHP destruction releases owned textures. Window shutdown releases all associated
textures before SDL's renderer closes; retained references then report `destroyed()`.
Drawing with destroyed textures or allocating from a closed context throws. Texture
operations restore the renderer's target, viewport, clipping, draw color, and blend
mode, including exceptions. Apps can use the texture API without managing SDL state.

The visualizations demo contains a tiled background and a moving sprite composed from
an atlas. Activate its Canvas and press Space to advance the sprite.
Run `php Tests/Canvas.php` for pixel composition, clipping, caching, resize, XML, and
resource lifetime checks.
