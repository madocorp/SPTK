<?php

namespace SPTK\Widgets;

use SPTK\Core\Cell;
use SPTK\Core\Color;
use SPTK\Core\Element;
use SPTK\Core\PixelSurfaceElement;
use SPTK\Core\PixelTextRenderTarget;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;
use SPTK\Core\SurfaceRenderTarget;

/**
 * Renders an explicitly styled text box without HTML or CSS compatibility.
 */
class StyledTextBox extends Element implements PixelSurfaceElement {

  protected array $runs = [];
  protected array $style = [];

  protected const DEFAULT_STYLE = [
    'color' => '#ffffff',
    'background' => 'transparent',
    'borderColor' => '#777777',
    'borderWidth' => 0,
    'padding' => 0,
    'fontFamily' => 'NimbusSans-Regular',
    'fontSize' => 24,
    'fontStyle' => 'normal',
    'fontWeight' => 'normal',
    'textAlign' => 'left',
    'lineGap' => 4,
  ];

  public function __construct(string $name = '', string|array $text = '', array $style = []) {
    parent::__construct($name);
    $this->setStyle($style);
    is_array($text) ? $this->setRuns($text) : $this->setText($text);
  }

  public function setText(string $text): static {
    return $this->setRuns([['text' => $text]]);
  }

  public function setRuns(array $runs): static {
    $this->runs = $this->normalizeRuns($runs);
    $this->invalidateLayout();
    $this->invalidateRender();
    return $this;
  }

  public function runs(): array {
    return $this->runs;
  }

  public function setStyle(array $style): static {
    $this->style = array_replace(self::DEFAULT_STYLE, $style);
    $this->invalidateLayout();
    $this->invalidateRender();
    return $this;
  }

  public function style(): array {
    return $this->style;
  }

  public function contentHeight(SurfaceRenderTarget&PixelTextRenderTarget $target, int $pixelWidth): int {
    $style = array_replace(self::DEFAULT_STYLE, $this->style);
    $edges = $this->edges($style['padding'] ?? 0);
    $border = $this->edges($style['borderWidth'] ?? 0);
    $contentWidth = max(0, $pixelWidth - $edges['left'] - $edges['right'] - $border['left'] - $border['right']);
    $lines = $this->layoutLines($target, $this->runs, $style, $contentWidth);
    $height = 0;
    foreach ($lines as $index => $line) {
      $height += $line['height'];
      if ($index < count($lines) - 1) {
        $height += (int)$style['lineGap'];
      }
    }
    return $border['top'] + $edges['top'] + max(0, $height) + $edges['bottom'] + $border['bottom'];
  }

  public function renderPixelSurface(SurfaceRenderTarget $target, Rect $pixelFrame): void {
    if (!$target instanceof PixelTextRenderTarget) {
      $columns = $target->columnsForWidth($pixelFrame->width);
      $rows = $target->rowsForHeight($pixelFrame->height);
      $this->paintGridFallback($target, new Rect(0, 0, $columns, $rows));
      return;
    }
    $this->paintPixels($target, $pixelFrame);
  }

  protected function paint(RenderTarget $target): void {
    if ($target instanceof SurfaceRenderTarget && $target instanceof PixelTextRenderTarget) {
      $this->paintPixels($target, $target instanceof \SPTK\Core\ImageRenderTarget ? $target->pixelRectForCells($this->frame) : $target->currentSurfacePixelRect());
      return;
    }
    $this->paintGridFallback($target, $this->frame);
  }

  public function paintPixels(SurfaceRenderTarget&PixelTextRenderTarget $target, Rect $box): void {
    if ($box->width <= 0 || $box->height <= 0) {
      return;
    }
    $style = array_replace(self::DEFAULT_STYLE, $this->style);
    $border = $this->edges($style['borderWidth'] ?? 0);
    $padding = $this->edges($style['padding'] ?? 0);
    $background = (string)($style['background'] ?? 'transparent');
    if ($background !== 'transparent') {
      $target->fillPixels($box, $background);
    }
    $this->paintBorder($target, $box, $border, (string)($style['borderColor'] ?? '#777777'));
    $content = $box->inset($border['left'] + $padding['left'], $border['top'] + $padding['top'], $border['right'] + $padding['right'], $border['bottom'] + $padding['bottom']);
    if ($content->width <= 0 || $content->height <= 0) {
      return;
    }
    $lines = $this->layoutLines($target, $this->runs, $style, $content->width);
    $lineGap = (int)$style['lineGap'];
    $y = $content->y;
    foreach ($lines as $line) {
      if ($y + $line['height'] > $content->bottom()) {
        break;
      }
      $x = match ($style['textAlign'] ?? 'left') {
        'center' => $content->x + max(0, intdiv($content->width - $line['width'], 2)),
        'right' => $content->right() - $line['width'],
        default => $content->x,
      };
      foreach ($line['segments'] as $segment) {
        $font = $this->fontOptions($style, $segment['style']);
        if (($segment['style']['background'] ?? 'transparent') !== 'transparent') {
          $target->fillPixels(new Rect($x, $y, $segment['width'], $line['height']), (string)$segment['style']['background']);
        }
        $segmentY = $y + max(0, (int)$line['ascent'] - (int)$segment['ascent']);
        $target->drawTextPixels($segment['text'], new Rect($x, $segmentY, $segment['width'], $segment['height']), $segment['style']['color'] ?? $style['color'], $font);
        $x += $segment['width'];
      }
      $y += $line['height'] + $lineGap;
    }
  }

  protected function paintGridFallback(RenderTarget $target, Rect $frame): void {
    $style = array_replace(self::DEFAULT_STYLE, $this->style);
    $target->fill($frame, ' ', $style['color'], $style['background'] === 'transparent' ? $this->theme->bg : $style['background']);
    $text = trim(implode('', array_map(fn(array $run): string => $run['text'] ?? "\n", $this->runs)));
    foreach (array_slice(explode("\n", wordwrap($text, max(1, $frame->width), "\n", true)), 0, $frame->height) as $row => $line) {
      $target->write($frame->x, $frame->y + $row, mb_substr($line, 0, $frame->width), $style['color'], null);
    }
  }

  protected function layoutLines(PixelTextRenderTarget $target, array $runs, array $baseStyle, int $maxWidth): array {
    $lines = [];
    $line = $this->emptyLine($target, $baseStyle);
    foreach ($runs as $run) {
      if (($run['type'] ?? 'text') === 'br') {
        $lines[] = $line;
        $line = $this->emptyLine($target, $baseStyle);
        continue;
      }
      $style = array_replace($baseStyle, $run);
      foreach ($this->textPieces((string)($run['text'] ?? '')) as $piece) {
        if ($piece === "\n") {
          $lines[] = $line;
          $line = $this->emptyLine($target, $baseStyle);
          continue;
        }
        $font = $this->fontOptions($baseStyle, $style);
        [$width, $height] = $target->measureTextPixels($piece, $font);
        $metrics = $target->fontMetricsPixels($font);
        if ($line['segments'] !== [] && $line['width'] + $width > $maxWidth) {
          $lines[] = $line;
          $line = $this->emptyLine($target, $baseStyle);
          $piece = ltrim($piece);
          [$width, $height] = $target->measureTextPixels($piece, $font);
        }
        if ($piece === '') {
          continue;
        }
        $ascent = (int)($metrics['ascent'] ?? $height);
        $descent = (int)($metrics['descent'] ?? max(0, $height - $ascent));
        $line['segments'][] = ['text' => $piece, 'width' => $width, 'height' => $height, 'ascent' => $ascent, 'descent' => $descent, 'style' => $style];
        $line['width'] += $width;
        $line['ascent'] = max($line['ascent'], $ascent);
        $line['descent'] = max($line['descent'], $descent);
        $line['height'] = max($line['height'], $line['ascent'] + $line['descent'], $height);
      }
    }
    if ($line['segments'] !== [] || $lines === []) {
      $lines[] = $line;
    }
    return $lines;
  }

  protected function textPieces(string $text): array {
    $pieces = [];
    foreach (preg_split('/(\R)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $part) {
      if ($part === '') {
        continue;
      }
      if (preg_match('/^\R$/u', $part)) {
        $pieces[] = "\n";
        continue;
      }
      foreach (preg_split('/(\s+)/u', $part, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $piece) {
        if ($piece !== '') {
          $pieces[] = $piece;
        }
      }
    }
    return $pieces;
  }

  protected function emptyLine(PixelTextRenderTarget $target, array $style): array {
    $metrics = $target->fontMetricsPixels($this->fontOptions($style, []));
    $ascent = (int)($metrics['ascent'] ?? 0);
    $descent = (int)($metrics['descent'] ?? 0);
    $height = max(1, (int)($metrics['height'] ?? ($ascent + $descent)));
    return ['segments' => [], 'width' => 0, 'height' => $height, 'ascent' => $ascent, 'descent' => $descent];
  }

  protected function fontOptions(array $baseStyle, array $runStyle): array {
    $family = $runStyle['fontFamily'] ?? $baseStyle['fontFamily'] ?? self::DEFAULT_STYLE['fontFamily'];
    return [
      'family' => $family,
      'families' => is_array($family) ? $family : [$family],
      'size' => (int)($runStyle['fontSize'] ?? $baseStyle['fontSize'] ?? self::DEFAULT_STYLE['fontSize']),
      'style' => (($runStyle['italic'] ?? false) || ($runStyle['fontStyle'] ?? $baseStyle['fontStyle'] ?? 'normal') === 'italic') ? 'italic' : 'normal',
      'weight' => (($runStyle['bold'] ?? false) || ($runStyle['fontWeight'] ?? $baseStyle['fontWeight'] ?? 'normal') === 'bold') ? 'bold' : 'normal',
    ];
  }

  protected function normalizeRuns(array $runs): array {
    $normalized = [];
    foreach ($runs as $run) {
      if (is_string($run)) {
        $run = ['text' => $run];
      }
      if (!is_array($run)) {
        continue;
      }
      if (($run['type'] ?? 'text') === 'br') {
        $normalized[] = ['type' => 'br'];
        continue;
      }
      $run['type'] = 'text';
      $run['text'] = (string)($run['text'] ?? '');
      $normalized[] = $run;
    }
    return $normalized;
  }

  protected function edges(int|array $value): array {
    if (!is_array($value)) {
      return ['top' => $value, 'right' => $value, 'bottom' => $value, 'left' => $value];
    }
    return [
      'top' => (int)($value['top'] ?? 0),
      'right' => (int)($value['right'] ?? 0),
      'bottom' => (int)($value['bottom'] ?? 0),
      'left' => (int)($value['left'] ?? 0),
    ];
  }

  protected function paintBorder(SurfaceRenderTarget $target, Rect $box, array $border, string $color): void {
    if ($color === 'transparent') {
      return;
    }
    if ($border['top'] > 0) {
      $target->fillPixels(new Rect($box->x, $box->y, $box->width, $border['top']), $color);
    }
    if ($border['bottom'] > 0) {
      $target->fillPixels(new Rect($box->x, $box->bottom() - $border['bottom'], $box->width, $border['bottom']), $color);
    }
    if ($border['left'] > 0) {
      $target->fillPixels(new Rect($box->x, $box->y, $border['left'], $box->height), $color);
    }
    if ($border['right'] > 0) {
      $target->fillPixels(new Rect($box->right() - $border['right'], $box->y, $border['right'], $box->height), $color);
    }
  }

}
