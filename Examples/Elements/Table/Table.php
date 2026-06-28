#!/usr/bin/env php
<?php

define('SPTK\DEBUG', false);
define('APP_PATH', __FILE__);
define('APP_NAMESPACE', 'EXAMPLES');
define('SPTK_PATH', realpath(__DIR__ . '/../../..'));

require_once SPTK_PATH . '/Autoload.php';

$sample = __DIR__ . '/sample.tsv';
$handle = fopen($sample, 'wb');
fwrite($handle, "id\tname\tstatus\tamount\thash\tnote\n");
for ($i = 1; $i <= 25000; $i++) {
  $status = ['new', 'active', 'paused', 'closed'][$i % 4];
  $amount = number_format($i * 3.14159, 2, '.', '');
  $note = $i % 17 === 0 ? '\N' : "row {$i} chunk demo";
  $hash = md5($note);
  fwrite($handle, "{$i}\tCustomer {$i}\t{$status}\t{$amount}\t{$hash}\t{$note}\n");
}
fclose($handle);

new SPTK\App(__DIR__ . "/layout.xml", __DIR__ . "/style.xss", function () {
  SPTK\Element::byName('panel')->show();
  SPTK\Element::refresh();
});
