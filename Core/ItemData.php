<?php

namespace SPTK\Core;

/** Normalizes unique value and label records used by choice and list widgets. */
final class ItemData {

  /** Validate item records and keep their selection flag in input order. */
  public static function normalize(array $items, string $stateName): array {
    $normalized = [];
    $seen = [];
    foreach ($items as $item) {
      if (is_string($item)) {
        $item = ['value' => $item, 'label' => $item];
      }
      if (!is_array($item) || !isset($item['value']) || !is_string($item['value'])) {
        throw new \InvalidArgumentException('Items need a string value.');
      }
      $value = $item['value'];
      $label = $item['label'] ?? $value;
      if (!is_string($label) || !mb_check_encoding($value, 'UTF-8') || !mb_check_encoding($label, 'UTF-8')) {
        throw new \InvalidArgumentException('Item values and labels must be valid UTF-8 strings.');
      }
      if (preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $label)) {
        throw new \InvalidArgumentException('Item labels must be printable text.');
      }
      if (isset($seen[$value])) {
        throw new \InvalidArgumentException("Duplicate item value: {$value}");
      }
      $searchOffset = $item['searchOffset'] ?? 0;
      if (!is_int($searchOffset) || $searchOffset < 0 || $searchOffset > mb_strlen($label)) {
        throw new \InvalidArgumentException('Item search offset must fit its label.');
      }
      $seen[$value] = true;
      $record = ['value' => $value, 'label' => preg_replace('/[\r\n\t]+/u', ' ', $label), $stateName => (bool)($item[$stateName] ?? false)];
      if (isset($item['searchOffset'])) {
        $record['searchOffset'] = $searchOffset;
      }
      $normalized[] = $record;
    }
    return $normalized;
  }

}
