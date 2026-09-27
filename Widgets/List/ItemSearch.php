<?php

namespace SPTK\Widgets\List;

/** Finds list item labels that match a Unicode-insensitive prefix. */
final class ItemSearch {

  /** Return item indices whose labels begin with the query. */
  public static function matchingIndices(array $items, string $query): array {
    $matches = [];
    foreach ($items as $index => $item) {
      if ($query === '' || str_starts_with(mb_strtolower($item['label']), mb_strtolower($query))) {
        $matches[] = $index;
      }
    }
    return $matches;
  }

}
