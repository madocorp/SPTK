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
  public $height;
  public $letterWidth;


  public function __construct($name, $size) {
    if (!isset(self::$fonts[$name][$size])) {
      $this->open($name, $size);
    }
    $this->font = self::$fonts[$name][$size]['handle'];
    $this->ascent = self::$fonts[$name][$size]['ascent'];
    $this->descent = self::$fonts[$name][$size]['descent'];
    $this->height = self::$fonts[$name][$size]['height'];
    $this->letterWidth = self::$fonts[$name][$size]['letterWidth'];
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
    $height = $ttf->TTF_GetFontHeight($font);
    $minx = \FFI::new("int");
    $maxx = \FFI::new("int");
    $miny = \FFI::new("int");
    $maxy = \FFI::new("int");
    $advance = \FFI::new("int");
    $ttf->TTF_GetGlyphMetrics(
      $font,
      ord('M'),
      \FFI::addr($minx),
      \FFI::addr($maxx),
      \FFI::addr($miny),
      \FFI::addr($maxy),
      \FFI::addr($advance)
    );
    self::$fonts[$name][$size]['ascent'] = $ascent;
    self::$fonts[$name][$size]['descent'] = $descent;
    self::$fonts[$name][$size]['height'] = $height;
    self::$fonts[$name][$size]['letterWidth'] = $advance->cdata;
  }

  private function getPath($name, $exact) {
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
