<?php

namespace SPTK\Widgets;

use SPTK\Core\Color;
use SPTK\Core\Element;
use SPTK\Core\InputAction;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;

/**
 * Base colors and tone swatches.
 */
class ColorPicker extends Element {

  protected ColorSwatchGrid $grid;
  protected string $value = '#ff0000';
  protected $onChange = null;

  public function __construct(string $name = '', string $value = '#ff0000', array $options = []) {
    parent::__construct($name);
    $this->setOptions($options);
    $this->value = $this->normalizeColor($value);
    $this->grid = new ColorSwatchGrid($name === '' ? 'color-picker-grid' : $name . '-grid', $this->value);
    $this->grid->setOnChange(function(ColorSwatchGrid $grid): void {
      $this->gridChanged($grid->getValue());
    });
    $this->add($this->grid);
    $this->setPreferredRows(6);
    $this->setPreferredColumns(64);
  }

  public function setOptions(array $options): static {
    if (array_key_exists('onChange', $options) && is_callable($options['onChange'])) {
      $this->onChange = $options['onChange'];
    }
    return $this;
  }

  public function setValue(mixed $value): static {
    $value = $this->normalizeColor((string)$value);
    if ($this->value === $value) {
      return $this;
    }
    $this->value = $value;
    $this->grid->setValue($value);
    $this->invalidateRender();
    if ($this->onChange !== null) {
      ($this->onChange)($this);
    }
    return $this;
  }

  public function getValue(): string {
    return $this->value;
  }

  public function grid(): ColorSwatchGrid {
    return $this->grid;
  }

  public function setFrame(Rect $frame): void {
    $this->frame = $frame;
    $this->layout();
  }

  public function layout(): void {
    $this->grid->setFrame($this->frame);
  }

  protected function paint(RenderTarget $target): void {
    $target->fill($this->frame, ' ', $this->theme->fg, $this->theme->bg);
  }

  protected function gridChanged(string $color): void {
    $color = $this->normalizeColor($color);
    if ($this->value === $color) {
      return;
    }
    $this->value = $color;
    $this->invalidateRender();
    if ($this->onChange !== null) {
      ($this->onChange)($this);
    }
  }

  protected function normalizeColor(string $value): string {
    return Color::from($value)->hex();
  }

}

/**
 * Focusable swatch grid for choosing a base, tone, or exact custom color.
 */
class ColorSwatchGrid extends Element {

  protected const COLUMNS = 16;
  protected const ROWS = 3;
  protected const SWATCH_WIDTH = 2;
  protected const SLOT_WIDTH = 4;
  protected const SLOT_HEIGHT = 2;

  protected array $baseColors = [];
  protected array $tones = [];
  protected array $exactColors = [];
  protected string $freeColor = '#000000';
  protected int $baseColumn = 0;
  protected int $cursorRow = 0;
  protected int $cursorColumn = 0;
  protected string $value;
  protected $onChange = null;

  public function __construct(string $name = '', string $value = '#ff0000') {
    parent::__construct($name);
    $this->focusable = true;
    $this->baseColors = $this->buildBaseColors();
    $this->value = $this->normalizeColor($value);
    $this->syncCursorToValue();
    $this->setPreferredRows(self::ROWS * self::SLOT_HEIGHT);
    $this->setPreferredColumns(self::COLUMNS * self::SLOT_WIDTH);
  }

  public function setOnChange(callable $onChange): static {
    $this->onChange = $onChange;
    return $this;
  }

  public function setValue(string $value): static {
    $this->value = $this->normalizeColor($value);
    $this->syncCursorToValue();
    $this->invalidateRender();
    return $this;
  }

  public function getValue(): string {
    return $this->value;
  }

  public function cursor(): int {
    return $this->cursorRow * self::COLUMNS + $this->cursorColumn;
  }

  public function handle(InputEvent $event): bool {
    if ($event->type !== 'key') {
      return false;
    }
    $old = $this->cursor();
    $row = $this->cursorRow;
    $column = $this->cursorColumn;
    if (InputAction::left($event, 'color')) {
      $column = max(0, $column - 1);
    } else if (InputAction::right($event, 'color')) {
      $column = min(self::COLUMNS - 1, $column + 1);
    } else if (InputAction::up($event, 'color')) {
      if ($row === 1) {
        $row = 0;
        $column = $this->baseColumn;
      } else if ($row === 2) {
        $row = 1;
      } else {
        $row = max(0, $row - 1);
      }
    } else if (InputAction::down($event, 'color')) {
      if ($row === 0) {
        $row = 1;
        $column = $this->matchingToneColumn($this->baseColors[$column]);
      } else {
        $row = min(self::ROWS - 1, $row + 1);
      }
    } else if (InputAction::home($event, 'color')) {
      $column = 0;
    } else if (InputAction::end($event, 'color')) {
      $column = self::COLUMNS - 1;
    } else if (InputAction::pageUp($event, 'color')) {
      $row = 0;
    } else if (InputAction::pageDown($event, 'color')) {
      $row = self::ROWS - 1;
    } else if (InputAction::activate($event, 'color')) {
      $this->commitCursor();
      return true;
    } else {
      return false;
    }
    $this->cursorRow = $row;
    $this->cursorColumn = $column;
    if ($this->cursor() !== $old) {
      $this->commitCursor();
    }
    return true;
  }

  protected function paint(RenderTarget $target): void {
    $target->fill($this->frame, ' ', $this->theme->fg, $this->theme->bg);
    for ($row = 0; $row < self::ROWS; $row++) {
      for ($column = 0; $column < self::COLUMNS; $column++) {
        $this->paintSwatch($target, $row, $column);
      }
    }
  }

  protected function paintSwatch(RenderTarget $target, int $row, int $column): void {
    $slot = new Rect(
      $this->frame->x + $column * self::SLOT_WIDTH,
      $this->frame->y + $row * self::SLOT_HEIGHT,
      min(self::SLOT_WIDTH, max(0, $this->frame->right() - ($this->frame->x + $column * self::SLOT_WIDTH))),
      min(self::SLOT_HEIGHT, max(0, $this->frame->bottom() - ($this->frame->y + $row * self::SLOT_HEIGHT)))
    );
    if ($slot->width <= 0 || $slot->height <= 0) {
      return;
    }
    $target->fill($slot, ' ', $this->theme->fg, $this->theme->bg);
    $this->paintMarker($target, $slot, $row, $column);
    $swatch = new Rect($slot->x + 1, $slot->y, min(self::SWATCH_WIDTH, max(0, $slot->right() - $slot->x - 2)), 1);
    if ($swatch->width > 0) {
      $target->fill($swatch, ' ', '#000000', $this->colorAt($row, $column));
    }
  }

  protected function paintMarker(RenderTarget $target, Rect $slot, int $row, int $column): void {
    $rightX = min($slot->x + self::SWATCH_WIDTH + 1, $slot->right() - 1);
    if ($this->isActiveCursor($row, $column)) {
      $fg = $this->focused ? $this->theme->cursorFg : $this->theme->inactiveCursorFg;
      $bg = $this->focused ? $this->theme->cursorBg : $this->theme->inactiveCursorBg;
      $target->put($slot->x, $slot->y, '[', $fg, $bg);
      if ($rightX > $slot->x) {
        $target->put($rightX, $slot->y, ']', $fg, $bg);
      }
      return;
    }
    if ($this->isPersistentBaseMarker($row, $column)) {
      $target->put($slot->x, $slot->y, '[', '#000000', $this->theme->bg);
      if ($rightX > $slot->x) {
        $target->put($rightX, $slot->y, ']', '#000000', $this->theme->bg);
      }
    }
  }

  protected function commitCursor(): void {
    if ($this->cursorRow === 0) {
      $this->baseColumn = $this->cursorColumn;
      $this->tones = $this->buildTones($this->baseColors[$this->baseColumn]);
    }
    $this->value = $this->colorAt($this->cursorRow, $this->cursorColumn);
    $this->exactColors = $this->buildExactColors($this->value);
    $this->invalidateRender();
    if ($this->onChange !== null) {
      ($this->onChange)($this);
    }
  }

  protected function isHighlighted(int $row, int $column): bool {
    if ($row === 0) {
      return $column === $this->baseColumn;
    }
    return $this->cursorRow === 1 && $column === $this->cursorColumn;
  }

  protected function isActiveCursor(int $row, int $column): bool {
    return $row === $this->cursorRow && $column === $this->cursorColumn;
  }

  protected function isPersistentBaseMarker(int $row, int $column): bool {
    return $this->cursorRow !== 2 && $row === 0 && $column === $this->baseColumn;
  }

  protected function colorAt(int $row, int $column): string {
    if ($row === 0) {
      return $this->baseColors[$column];
    }
    if ($row === 1) {
      return $this->tones[$column] ?? $this->baseColors[$this->baseColumn];
    }
    return $this->exactColors[$column] ?? '#000000';
  }

  protected function syncCursorToValue(): void {
    if (($shortcut = array_search($this->value, $this->defaultExactColors(), true)) !== false) {
      $this->cursorRow = 2;
      $this->cursorColumn = (int)$shortcut;
      $this->tones = $this->buildTones($this->baseColors[$this->baseColumn]);
      $this->exactColors = $this->buildExactColors($this->value);
      return;
    }
    $palette = $this->palettePosition($this->value);
    if ($palette !== null) {
      $this->baseColumn = $palette['base'];
      $this->tones = $this->buildTones($this->baseColors[$this->baseColumn]);
      $this->exactColors = $this->buildExactColors($this->value);
      $this->cursorRow = $palette['row'];
      $this->cursorColumn = $palette['column'];
      return;
    }
    $this->baseColumn = $this->nearestColorIndex($this->value, $this->baseColors);
    $this->tones = $this->buildTones($this->baseColors[$this->baseColumn]);
    $this->freeColor = $this->value;
    $this->exactColors = $this->buildExactColors($this->value);
    $this->cursorRow = 2;
    $this->cursorColumn = self::COLUMNS - 1;
  }

  protected function palettePosition(string $value): ?array {
    foreach ($this->baseColors as $baseColumn => $base) {
      $tones = $this->buildTones($base);
      if (($toneColumn = array_search($value, $tones, true)) !== false) {
        return ['row' => 1, 'column' => (int)$toneColumn, 'base' => (int)$baseColumn];
      }
    }
    if (($baseColumn = array_search($value, $this->baseColors, true)) !== false) {
      return ['row' => 0, 'column' => (int)$baseColumn, 'base' => (int)$baseColumn];
    }
    return null;
  }

  protected function buildBaseColors(): array {
    $colors = ['#000000'];
    for ($column = 1; $column < self::COLUMNS; $column++) {
      $colors[] = $this->hsvToHex(($column - 1) / (self::COLUMNS - 1), 0.9, 0.9);
    }
    return $colors;
  }

  protected function buildTones(string $base): array {
    $color = Color::from($base);
    if ($color->r === 0 && $color->g === 0 && $color->b === 0) {
      return $this->buildGrayTones();
    }
    $tones = [];
    for ($i = 0; $i < self::COLUMNS; $i++) {
      if ($i < 8) {
        $factor = 0.35 + ($i / 7) * 0.65;
        $r = (int)round($color->r * $factor);
        $g = (int)round($color->g * $factor);
        $b = (int)round($color->b * $factor);
      } else {
        $factor = (($i - 7) / 8) * 0.75;
        $r = (int)round($color->r + (255 - $color->r) * $factor);
        $g = (int)round($color->g + (255 - $color->g) * $factor);
        $b = (int)round($color->b + (255 - $color->b) * $factor);
      }
      $tones[] = sprintf('#%02x%02x%02x', $r, $g, $b);
    }
    return $tones;
  }

  protected function buildGrayTones(): array {
    $tones = [];
    for ($i = 0; $i < self::COLUMNS; $i++) {
      $value = (int)round(($i / (self::COLUMNS - 1)) * 255);
      $tones[] = sprintf('#%02x%02x%02x', $value, $value, $value);
    }
    return $tones;
  }

  protected function buildExactColors(string $current): array {
    $current = $this->normalizeColor($current);
    $shortcuts = $this->defaultExactColors();
    if (!in_array($current, $this->baseColors, true) && !in_array($current, $this->tones, true) && !in_array($current, $shortcuts, true)) {
      $this->freeColor = $current;
    }
    return [...$shortcuts, $this->freeColor];
  }

  protected function defaultExactColors(): array {
    return [
      '#ff0000', '#ffff00', '#00ff00', '#00ffff',
      '#0000ff', '#ff00ff', '#ffffff', '#808080',
      '#800000', '#808000', '#008000', '#008080',
      '#000080', '#800080', '#c0c0c0',
    ];
  }

  protected function matchingToneColumn(string $base): int {
    $tones = $this->buildTones($base);
    if (($index = array_search($base, $tones, true)) !== false) {
      return (int)$index;
    }
    return $this->nearestColorIndex($base, $tones);
  }

  protected function nearestColorIndex(string $value, array $colors): int {
    if (($index = array_search($value, $colors, true)) !== false) {
      return (int)$index;
    }
    $target = Color::from($value);
    $best = 0;
    $bestDistance = PHP_INT_MAX;
    foreach ($colors as $index => $color) {
      $candidate = Color::from($color);
      $distance = ($target->r - $candidate->r) ** 2 + ($target->g - $candidate->g) ** 2 + ($target->b - $candidate->b) ** 2;
      if ($distance < $bestDistance) {
        $best = $index;
        $bestDistance = $distance;
      }
    }
    return $best;
  }

  protected function hsvToHex(float $h, float $s, float $v): string {
    $h *= 6;
    $i = (int)floor($h);
    $f = $h - $i;
    $p = $v * (1 - $s);
    $q = $v * (1 - $f * $s);
    $t = $v * (1 - (1 - $f) * $s);
    [$r, $g, $b] = match ($i % 6) {
      0 => [$v, $t, $p],
      1 => [$q, $v, $p],
      2 => [$p, $v, $t],
      3 => [$p, $q, $v],
      4 => [$t, $p, $v],
      default => [$v, $p, $q],
    };
    return sprintf('#%02x%02x%02x', (int)round($r * 255), (int)round($g * 255), (int)round($b * 255));
  }

  protected function normalizeColor(string $value): string {
    return Color::from($value)->hex();
  }

}
