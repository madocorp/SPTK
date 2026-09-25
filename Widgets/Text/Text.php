<?php

namespace SPTK\Widgets\Text;

use SPTK\Rendering\GridWriter;
use SPTK\Core\{Color, Widget};
use SPTK\Rendering\TextMetrics;

/** Read-only colored text, clipped to its tile without wrapping. */
final class Text implements Widget {

  private array $lines;

  public function __construct(
    string $text,
    private readonly Color $fg = new Color(230, 235, 245),
    private readonly Color $bg = new Color(24, 28, 36),
  ) {
    if (!mb_check_encoding($text, 'UTF-8')) {
      throw new \InvalidArgumentException('Text must be valid UTF-8.');
    }
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    if (preg_match('/[\x00-\x09\x0b-\x1f\x7f]/', $text)) {
      throw new \InvalidArgumentException('Text supports printable characters and newlines.');
    }
    $this->lines = explode("\n", $text);
  }

  public function paint(GridWriter $writer): void {
    $writer->fill($this->fg, $this->bg);
    foreach ($this->lines as $y => $line) {
      if ($y >= $writer->height()) {
        break;
      }
      $writer->write(0, $y, $line, $this->fg, $this->bg);
    }
  }

}
