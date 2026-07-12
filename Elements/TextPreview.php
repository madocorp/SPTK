<?php

namespace SPTK\Elements;

class TextPreview extends TextGrid {

  protected const WRAP_MARKER = '~';
  protected const CONTINUATION_MARKER = 'vvv';
  protected const MARKER_COLOR = [255, 255, 0, 255];

  protected string $text = '';

  public function setValue(mixed $value): void {
    $this->text = str_replace(["\r\n", "\r"], "\n", (string)$value);
    $this->value = $this->text;
    $this->scrollX = 0;
    $this->scrollY = 0;
    $this->changed = true;
  }

  protected function layout(): void {
    parent::layout();
    $this->setCells($this->previewCells());
  }

  protected function previewCells(): array {
    $columns = max(1, (int)floor($this->geometry->innerWidth / max(1, $this->letterWidth)));
    $rows = max(1, (int)floor($this->geometry->innerHeight / max(1, $this->lineHeight)));
    $lines = $this->wrappedLines($columns);
    $overflow = count($lines) > $rows;
    if ($overflow) {
      $lines = array_slice($lines, 0, max(0, $rows - 1));
      $lines[] = [
        'text' => mb_substr(self::CONTINUATION_MARKER, 0, $columns),
        'markerFrom' => 0
      ];
    } else {
      $lines = array_slice($lines, 0, $rows);
    }

    $fg = $this->colorValue('color', [255, 255, 255, 255]);
    $bg = $this->colorValue('backgroundColor', [0, 0, 0, 0]);
    $markerFg = self::MARKER_COLOR;
    $cells = [];
    foreach ($lines as $line) {
      $row = [];
      $glyphs = preg_split('//u', $line['text'], -1, PREG_SPLIT_NO_EMPTY);
      for ($column = 0; $column < $columns; $column++) {
        $marker = $column >= $line['markerFrom'];
        $row[] = [
          'glyph' => $glyphs[$column] ?? ' ',
          'fg' => $marker ? $markerFg : $fg,
          'bg' => $bg
        ];
      }
      $cells[] = $row;
    }
    return $cells;
  }

  protected function wrappedLines(int $columns): array {
    $lines = [];
    foreach (explode("\n", $this->text) as $line) {
      if ($line === '') {
        $lines[] = ['text' => '', 'markerFrom' => $columns];
        continue;
      }
      while (mb_strlen($line) > $columns) {
        if ($columns === 1) {
          $lines[] = ['text' => self::WRAP_MARKER, 'markerFrom' => 0];
          $line = mb_substr($line, 1);
          continue;
        }
        $take = $columns - 1;
        $lines[] = [
          'text' => mb_substr($line, 0, $take) . self::WRAP_MARKER,
          'markerFrom' => $take
        ];
        $line = mb_substr($line, $take);
      }
      $lines[] = ['text' => $line, 'markerFrom' => $columns];
    }
    return $lines;
  }

  protected function colorValue(string $name, array $fallback): array {
    try {
      $color = $this->style->get($name);
      return is_array($color) ? $color : $fallback;
    } catch (\Throwable $e) {
      return $fallback;
    }
  }

}
