<?php

namespace SPTK\Runtime;

use SPTK\SDLWrapper\TTF;

/**
 * Opens one UI font and exposes fixed grid cell metrics for SDL rendering.
 */
class SdlFont {

  protected $handle;
  public int $ascent;
  public int $descent;
  public int $height;
  public int $rowHeight;
  public int $letterWidth;
  public int $letterHeight;

  public function __construct(
    protected TTF $ttf,
    protected string $path,
    protected int $size,
    ?int $rowHeight = null
  ) {
    $this->handle = $this->ttf->ffi->TTF_OpenFont($path, $size);
    if ($this->handle === null) {
      throw new \RuntimeException("Unable to open font: {$path}");
    }
    $this->ttf->ffi->TTF_SetFontHinting($this->handle, TTF::TTF_HINTING_LIGHT_SUBPIXEL);
    $this->ascent = $this->ttf->ffi->TTF_GetFontAscent($this->handle);
    $this->descent = $this->ttf->ffi->TTF_GetFontDescent($this->handle);
    $this->height = max(1, $this->ttf->ffi->TTF_GetFontHeight($this->handle));
    $this->rowHeight = max($this->height, $rowHeight ?? $this->height);
    $this->letterWidth = max(1, $this->glyphAdvance('M'));
    $metrics = $this->glyphMetrics('|');
    $this->letterHeight = max(1, $metrics[3] - $metrics[2]);
  }

  public function handle(): mixed {
    return $this->handle;
  }

  public function glyphMetrics(string $glyph): array {
    $minx = \FFI::new('int');
    $maxx = \FFI::new('int');
    $miny = \FFI::new('int');
    $maxy = \FFI::new('int');
    $advance = \FFI::new('int');
    $this->ttf->ffi->TTF_GetGlyphMetrics(
      $this->handle,
      mb_ord($glyph),
      \FFI::addr($minx),
      \FFI::addr($maxx),
      \FFI::addr($miny),
      \FFI::addr($maxy),
      \FFI::addr($advance)
    );
    return [$minx->cdata, $maxx->cdata, $miny->cdata, $maxy->cdata, $advance->cdata];
  }

  protected function glyphAdvance(string $glyph): int {
    return $this->glyphMetrics($glyph)[4];
  }

  public function close(): void {
    if ($this->handle !== null) {
      $this->ttf->ffi->TTF_CloseFont($this->handle);
      $this->handle = null;
    }
  }

  public function __destruct() {
    $this->close();
  }

  public static function findPath(string $name = 'NimbusMonoPS-Regular', bool $fontConfig = false): string {
    if (file_exists($name)) {
      return $name;
    }
    $clearName = strtolower(preg_replace('/[^a-z0-9]/i', '', $name));
    $dirs = ['/usr/share/fonts', '/usr/local/share/fonts', getenv('HOME') . '/.local/share/fonts', getenv('HOME') . '/.fonts'];
    $best = false;
    $bestDistance = PHP_INT_MAX;
    foreach ($dirs as $dir) {
      if (!is_string($dir) || !file_exists($dir)) {
        continue;
      }
      $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
      foreach ($files as $file) {
        if (!$file->isFile() || !preg_match('/\.(ttf|otf|ttc|otc)$/i', $file->getFilename())) {
          continue;
        }
        $candidate = strtolower(preg_replace('/[^a-z0-9]/i', '', pathinfo($file->getFilename(), PATHINFO_FILENAME)));
        if ($candidate === $clearName) {
          return $file->getPathname();
        }
        $distance = levenshtein($clearName, $candidate);
        if ($distance < $bestDistance) {
          $best = $file->getPathname();
          $bestDistance = $distance;
        }
      }
    }
    if ($fontConfig) {
      $fontConfigPath = self::fontConfigPath($name);
      if ($fontConfigPath !== null) {
        return $fontConfigPath;
      }
    }
    if ($best === false) {
      throw new \RuntimeException("Unable to find a usable font for: {$name}");
    }
    return $best;
  }

  protected static function fontConfigPath(string $name): ?string {
    if (trim($name) === '' || str_contains($name, '/')) {
      return null;
    }
    $cmd = 'fc-match -f %{file} -- ' . escapeshellarg($name);
    $path = trim((string)@shell_exec($cmd));
    if ($path !== '' && is_file($path)) {
      return $path;
    }
    return null;
  }

}
