<?php

namespace SPTK\Tests;

use SPTK\Core\AppData;

return [
  'app data uses hidden home directory' => function(): void {
    $previousHome = getenv('HOME');
    $home = sys_get_temp_dir() . '/sptk-appdata-' . getmypid() . '-path';
    mkdir($home, 0700, true);
    try {
      putenv("HOME={$home}");

      $path = AppData::path('sample-app');

      assertSame($home . '/.sample-app', $path, 'App data should live in a hidden directory under HOME.');
      assertTrue(is_dir($path), 'App data path should be created on demand.');
    } finally {
      putenv($previousHome === false ? 'HOME' : "HOME={$previousHome}");
    }
  },

  'app data saves and loads json files' => function(): void {
    $previousHome = getenv('HOME');
    $home = sys_get_temp_dir() . '/sptk-appdata-' . getmypid() . '-json';
    mkdir($home, 0700, true);
    try {
      putenv("HOME={$home}");

      $saved = AppData::saveJson('settings.json', ['site' => 'https://jira.example', 'pageSize' => 50], 'majira2-test');
      $loaded = AppData::loadJson('settings.json', 'majira2-test');

      assertSame(true, $saved, 'Saving JSON should succeed.');
      assertSame(['site' => 'https://jira.example', 'pageSize' => 50], $loaded, 'Saved JSON should round-trip as an array.');
    } finally {
      putenv($previousHome === false ? 'HOME' : "HOME={$previousHome}");
    }
  },
];
