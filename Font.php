<?php

namespace SPTK;

class Font {

  private static $fonts = [];
  private static $fontDirs = [
    '/usr/share/fonts',
    '/usr/local/share/fonts',
    '~/.fonts'
  ];

  public $font;
  public $ascent;
  public $descent;

  public function __construct($name, $size) {
    if (!isset(self::$fonts[$name][$size])) {
      $this->open($name, $size);
    }
    $this->font = self::$fonts[$name][$size]['handle'];
    $this->ascent = self::$fonts[$name][$size]['ascent'];
    $this->descent = self::$fonts[$name][$size]['descent'];
  }

  private function open($name, $size) {
    $path = $this->getPath($name, true);
    if ($path === false) {
      echo "Searching font that similar to {$name}\n";
      $path = $this->getPath($name, false);
      if ($path === false) {
        echo "Using font DejaVu Sans as fallback\n";
        $path = $this->getPath('DejaVu Sans', false);
        if ($path === false) {
          throw new \Exception("Not found any font!");
        }
      }
    }
    $ttf = TTF::$instance->ttf;
    $font = $ttf->TTF_OpenFont($path, $size);
    self::$fonts[$name][$size]['handle'] = $font;
    $ascent = $ttf->TTF_GetFontAscent($font);
    $descent = $ttf->TTF_GetFontDescent($font);
    self::$fonts[$name][$size]['ascent'] = $ascent;
    self::$fonts[$name][$size]['descent'] = $descent;
  }

  private function getPath($name, $exact) {
echo $name, "\n";
    $clearName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
    $minL = strlen($clearName);
    $closestPath = false;
    foreach (self::$fontDirs as $dir) {
      $dir = str_replace('~', getenv('HOME'), $dir);
      if (!file_exists($dir)) {
        continue;
      }
      $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
      foreach ($files as $file) {
        if ($file->isFile() && preg_match('/(.*)\.(ttf|otf|ttc|otc)$/i', $file->getFilename(), $match)) {
          $clearFileName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $match[1]));
          if ($exact) {
            if ($clearName === $clearFileName) {
              return $file->getPathname();
            }
          } else {
            $l = levenshtein($clearName, $clearFileName);
            if ($l < $minL) {
              $closestPath = $file->getPathname();
              $minL = $l;
            }
          }
        }
      }
    }
    return $closestPath;
  }

  public static function closeAll() {
    $ttf = TTF::$instance->ttf;
    foreach (self::$fonts as $font) {
      foreach ($font as $size) {
        $ttf->TTF_CloseFont($size['handle']);
      }
    }
  }

}
