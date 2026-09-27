<?php

/** Encode one value in the Table widget's escaped TSV format. */
function tableDemoField(?string $value): string {
  if ($value === null) {
    return '\N';
  }
  return str_replace(["\\", "\r", "\n", "\t"], ["\\\\", '\r', '\n', '\t'], $value);
}

$path = dirname(__DIR__) . '/Demo/Layout/large.tsv';
$handle = fopen($path, 'wb');
if ($handle === false) {
  throw new RuntimeException("Cannot create {$path}");
}
$header = ['ID', 'Customer', 'Region', 'Status', 'Created', 'Updated', 'Amount', 'Currency', 'Owner', 'Email', 'SQL Query', 'Notes', 'Path', 'Tags', 'Priority', 'Long Description'];
fwrite($handle, implode("\t", $header) . "\n");
$regions = ['Europe', 'North America', 'Asia Pacific', 'South America', 'Africa'];
$statuses = ['New', 'Queued', 'Running', 'Complete', 'Failed'];
$owners = ['Ada', 'Lin', 'Sam', 'Éva', 'Noor', 'Kai'];
for ($index = 0; $index < 2048; $index++) {
  $id = $index + 1;
  $notes = $index % 37 === 0 ? "First line for record {$id}\nSecond line with a tab\tand more detail" : "Review batch {$id}";
  $description = $index % 97 === 0
    ? "A deliberately long description for record {$id}: this value crosses the normal column width and tests horizontal scrolling and field truncation in the table widget."
    : "Routine result {$id} for the reporting dashboard";
  $query = $index % 113 === 0
    ? "SELECT customer_id, SUM(amount) FROM invoice_items WHERE customer_id = {$id} GROUP BY customer_id ORDER BY SUM(amount) DESC"
    : "SELECT * FROM results WHERE id = {$id}";
  $fields = [
    (string)$id,
    "Customer {$id}",
    $regions[$index % count($regions)],
    $statuses[$index % count($statuses)],
    '2026-09-' . sprintf('%02d', $index % 28 + 1),
    '2026-10-' . sprintf('%02d', $index % 28 + 1),
    number_format(($index * 173) % 100000 / 100, 2, '.', ''),
    'EUR',
    $owners[$index % count($owners)],
    "customer{$id}@example.test",
    $query,
    $notes,
    "/reports/2026/customer-{$id}/result.tsv",
    $index % 11 === 0 ? "sql\tpriority" : 'sql,report',
    $index % 41 === 0 ? null : (string)($index % 5 + 1),
    $description,
  ];
  fwrite($handle, implode("\t", array_map('tableDemoField', $fields)) . "\n");
}
fclose($handle);
