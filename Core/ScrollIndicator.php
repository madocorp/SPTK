<?php

namespace SPTK\Core;

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

}
