<?php

namespace SPTK\Layout;

use SPTK\Core\Style;
use SPTK\Rendering\GridWriter;

/** Paints a closed deck tile's summary: a title line, then text lines. */
final class DeckSummary {

  /** Fill the tile and write the first line highlighted and the others in the text color. */
  public static function paint(GridWriter $writer, string $summary, Style $style): void {
    $writer->fill($style->foreground, $style->background);
    $lines = explode("\n", $summary);
    for ($row = 0; $row < count($lines) && $row < $writer->height(); $row++) {
      $line = preg_replace('/[\x00-\x1f\x7f]/', ' ', $lines[$row]);
      $writer->write(0, $row, $line, $row === 0 ? $style->highlight : $style->foreground, $style->background);
    }
  }

}
