<?php

namespace SPTK\Widgets\List;

/** Finds list item labels that match a case-insensitive prefix, or any part after a leading `*`. */
final class ItemSearch {

  /** Return item indices whose labels match the query. */
  public static function matchingIndices(array $items, string $query): array {
    $matches = [];
    foreach ($items as $index => $item) {
      if (self::matchStart($item, $query) !== null) {
        $matches[] = $index;
      }
    }
    return $matches;
  }

  /** Return [start, length] of the match in the searchable label, or null without a match. */
  public static function matchStart(array $item, string $query): ?array {
    $label = mb_strtolower(mb_substr($item['label'], $item['searchOffset'] ?? 0));
    if (str_starts_with($query, '*')) {
      $needle = mb_strtolower(mb_substr($query, 1));
      $start = $needle === '' ? 0 : mb_strpos($label, $needle);
      return $start === false ? null : [$start, mb_strlen($needle)];
    }
    $query = mb_strtolower($query);
    return $query === '' || str_starts_with($label, $query) ? [0, mb_strlen($query)] : null;
  }

}
