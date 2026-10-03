<?php

namespace SPTK\Core;

use SPTK\Rendering\TextMetrics;

/** Formats textual marks that indicate hidden content beyond a viewport. */
final class ScrollIndicator {

  /** Return an arrow with a page count when at least one full page is hidden. */
  public static function label(int $hidden, int $page, string $arrow): string {
    if ($hidden <= 0) {
      return '';
    }
    $pages = intdiv($hidden, max(1, $page));
    $count = $pages > 0 ? (string)$pages : '';
    return $arrow === '◀' ? $arrow . $count : $count . $arrow;
  }

  /** Place horizontal and vertical lower-right marks on one line, horizontal first. */
  public static function bottomRight(string $right, string $below): string {
    return $right !== '' && $below !== '' ? $right . ' ' . $below : $right . $below;
  }

  /** Keep visible arrows when a scroll label is wider than its available columns. */
  public static function fit(string $label, int $width, bool $right): string {
    if ($label === '' || $width < 1) {
      return '';
    }
    if (TextMetrics::width($label) > $width) {
      $label = preg_replace('/[0-9]/', '', $label);
    }
    if (TextMetrics::width($label) > $width) {
      $label = str_replace(' ', '', $label);
    }
    $length = TextMetrics::width($label);
    return TextMetrics::slice($label, $right ? max(0, $length - $width) : 0, $width);
  }

}
