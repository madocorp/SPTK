#!/usr/bin/env php
<?php

define('SPTK\DEBUG', false);
define('APP_PATH', __FILE__);
define('APP_NAMESPACE', 'EXAMPLES');
define('SPTK_PATH', realpath(__DIR__ . '/../../..'));

require_once SPTK_PATH . '/Autoload.php';

$progressValue = 0;
$jobs = [
  '/tmp/a.txt',
  '/tmp/b.txt',
  '/tmp/c.txt'
];

new SPTK\App(
  __DIR__ . "/layout.xml",
  __DIR__ . "/style.xss",
  function () use (&$jobs): void {
    SPTK\Element::byName('progress')->setJobName($jobs[0]);
    SPTK\Element::byName('panel')->show();
    SPTK\Element::refresh();
  },
  null,
  function () use (&$progressValue, &$jobs): void {
    $progressValue = ($progressValue + 7) % 101;
    $progress = SPTK\Element::byName('progress');
    $progress->setValue($progressValue);
    $progress->setJobName($jobs[(int)floor($progressValue / 25) % count($jobs)]);
  }
);
