<?php

namespace SPTK\Tests;

use SPTK\Core\Cell;
use SPTK\Core\Color;
use SPTK\Core\PixelTextRenderTarget;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;
use SPTK\Core\SurfaceRenderTarget;
use SPTK\Widgets\StyledTextBox;

class StyledTextBoxProbeTarget implements SurfaceRenderTarget, PixelTextRenderTarget {

  public array $fills = [];
  public array $texts = [];
  protected array $stack = [];

  public function __construct(
    protected Rect $surface = new Rect(0, 0, 200, 120),
    protected int $cellWidth = 10,
    protected int $cellHeight = 20
  ) {
  }

  public function put(int $x, int $y, string|Cell $value, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
  }

  public function write(int $x, int $y, string $text, string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
    $this->texts[] = ['text' => $text, 'rect' => [$x, $y, mb_strlen($text), 1], 'color' => Color::from($fg ?? '#ffffff')->hex(), 'font' => []];
  }

  public function fill(Rect $rect, string|Cell $value = ' ', string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
  }

  public function fillToWindowEdge(Rect $rect, string|Cell $value = ' ', string|int|Color|null $fg = null, string|int|Color|null $bg = null, array $flags = []): void {
  }

  public function drawLine(float $x1, float $y1, float $x2, float $y2, string|int|Color $color, int $thickness = 1): void {
  }

  public function drawRect(Rect $rect, string|int|Color $color, int $thickness = 1): void {
  }

  public function drawOuterRect(Rect $rect, string|int|Color $color, int $thickness = 1): void {
  }

  public function pushClip(Rect $rect): void {
  }

  public function popClip(): void {
  }

  public function columnsForWidth(int $pixelWidth): int {
    return max(1, intdiv($pixelWidth, $this->cellWidth));
  }

  public function rowsForHeight(int $pixelHeight): int {
    return max(1, intdiv($pixelHeight, $this->cellHeight));
  }

  public function cellWidth(): int {
    return $this->cellWidth;
  }

  public function cellHeight(): int {
    return $this->cellHeight;
  }

  public function currentSurfacePixelRect(): Rect {
    return $this->surface;
  }

  public function pushSurface(Rect $pixelRect, int $columns, int $rows, Color|string|int|null $background = null, string $gridAlignment = 'center'): void {
    $this->stack[] = $this->surface;
    $this->surface = $pixelRect;
  }

  public function popSurface(): void {
    $this->surface = array_pop($this->stack);
  }

  public function fillPixels(Rect $pixelRect, Color|string|int $color): void {
    $this->fills[] = ['rect' => [$pixelRect->x, $pixelRect->y, $pixelRect->width, $pixelRect->height], 'color' => Color::from($color)->hex()];
  }

  public function drawPixelLine(float $x1, float $y1, float $x2, float $y2, Color|string|int $color, int $thickness = 1): void {
  }

  public function measureTextPixels(string $text, array $font = []): array {
    return [mb_strlen($text) * 5, (int)($font['size'] ?? 10)];
  }

  public function fontMetricsPixels(array $font = []): array {
    $height = (int)($font['size'] ?? 10);
    $ascent = (int)round($height * 0.8);
    return ['ascent' => $ascent, 'descent' => max(0, $height - $ascent), 'height' => $height];
  }

  public function drawTextPixels(string $text, Rect $targetRect, Color|string|int $color, array $font = []): void {
    $this->texts[] = ['text' => $text, 'rect' => [$targetRect->x, $targetRect->y, $targetRect->width, $targetRect->height], 'color' => Color::from($color)->hex(), 'font' => $font];
  }

}

return [
  'styled text box paints background border and padding' => function(): void {
    $box = new StyledTextBox('box', 'Hello', [
      'background' => '#101010',
      'borderColor' => '#ff0000',
      'borderWidth' => ['top' => 1, 'right' => 2, 'bottom' => 3, 'left' => 4],
      'padding' => 5,
      'fontSize' => 12,
    ]);
    $target = new StyledTextBoxProbeTarget();
    $box->paintPixels($target, new Rect(10, 20, 100, 80));
    assertSame(['rect' => [10, 20, 100, 80], 'color' => '#101010'], $target->fills[0], 'Background should fill the whole box.');
    assertSame(['rect' => [10, 20, 100, 1], 'color' => '#ff0000'], $target->fills[1], 'Top border should use configured width and color.');
    assertSame([19, 26, 25, 12], $target->texts[0]['rect'], 'Text should start after border and padding.');
  },

  'styled text box wraps and reports calculated content height' => function(): void {
    $box = new StyledTextBox('box', 'Alpha beta gamma', ['fontSize' => 10, 'padding' => 2, 'lineGap' => 1]);
    $target = new StyledTextBoxProbeTarget();
    $height = $box->contentHeight($target, 38);
    $box->paintPixels($target, new Rect(0, 0, 38, $height));
    assertTrue(count($target->texts) >= 2, 'Text should wrap when measured width exceeds content width.');
    assertSame(36, $height, 'Calculated height should include wrapped lines, padding, and line gap.');
  },

  'styled text box supports center and right alignment' => function(): void {
    $target = new StyledTextBoxProbeTarget();
    $center = new StyledTextBox('center', 'Hi', ['fontSize' => 10, 'textAlign' => 'center']);
    $center->paintPixels($target, new Rect(0, 0, 100, 30));
    $right = new StyledTextBox('right', 'Hi', ['fontSize' => 10, 'textAlign' => 'right']);
    $right->paintPixels($target, new Rect(0, 30, 100, 30));
    assertSame(45, $target->texts[0]['rect'][0], 'Centered text should be horizontally centered.');
    assertSame(90, $target->texts[1]['rect'][0], 'Right-aligned text should end at the content edge.');
  },

  'styled text box applies inline run styles' => function(): void {
    $box = new StyledTextBox('box', [
      ['text' => 'Alpha '],
      ['text' => 'bold', 'bold' => true, 'color' => '#ffff00'],
      ['type' => 'br'],
      ['text' => 'code', 'fontFamily' => 'monospace'],
    ], ['fontSize' => 14, 'fontFamily' => 'sans-serif']);
    $target = new StyledTextBoxProbeTarget();
    $box->paintPixels($target, new Rect(0, 0, 140, 60));
    $bold = null;
    $code = null;
    foreach ($target->texts as $text) {
      if ($text['text'] === 'bold') {
        $bold = $text;
      }
      if ($text['text'] === 'code') {
        $code = $text;
      }
    }
    assertSame('bold', $bold['font']['weight'] ?? null, 'Bold run should use bold font weight.');
    assertSame('#ffff00', $bold['color'] ?? null, 'Inline color should override base color.');
    assertSame(['monospace'], $code['font']['families'] ?? null, 'Inline font family should override base family.');
  },

  'styled text box aligns mixed font sizes to a common baseline' => function(): void {
    $box = new StyledTextBox('box', [
      ['text' => 'small', 'fontSize' => 10],
      ['text' => 'BIG', 'fontSize' => 20],
    ], ['fontSize' => 10]);
    $target = new StyledTextBoxProbeTarget();
    $box->paintPixels($target, new Rect(0, 0, 100, 40));
    assertSame([0, 8, 25, 10], $target->texts[0]['rect'], 'Smaller text should be lowered to the larger run baseline.');
    assertSame([25, 0, 15, 20], $target->texts[1]['rect'], 'Larger text should define the line baseline.');
  },
];
