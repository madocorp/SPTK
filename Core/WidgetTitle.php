<?php

namespace SPTK\Core;

use SPTK\Rendering\{GridWriter, TextMetrics};

/** Reserves and paints a fixed title row above a widget's scrollable content. */
final class WidgetTitle {

  /** Validate an optional printable title and retain its inherited colors. */
  public function __construct(private readonly ?string $title, private readonly Style $style) {
    if ($title !== null && (!mb_check_encoding($title, 'UTF-8') || preg_match('/[\x00-\x1f\x7f]/', $title))) {
      throw new \InvalidArgumentException('Widget title must be printable single-line UTF-8 text.');
    }
  }

  /** Return the title's preferred width in display cells. */
  public function width(): int {
    return $this->title === null ? 0 : TextMetrics::width($this->title);
  }

  /** Reserve one row whenever a title was supplied, including an empty string. */
  public function height(): int {
    return $this->title === null ? 0 : 1;
  }

  /** Return the item area, optionally painting its fixed title during a full redraw. */
  public function body(GridWriter $writer, bool $paint = true, ?Color $color = null): GridWriter {
    if ($this->title === null || $writer->height() < 1) {
      return $writer;
    }
    if ($paint) {
      $ink = $color ?? $this->style->highlight;
      $writer->fillRow(0, $ink, $this->style->background);
      $writer->write(0, 0, $this->title, $ink, $this->style->background);
    }
    return $writer->below(1);
  }

}
