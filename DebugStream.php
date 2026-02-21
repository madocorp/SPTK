<?php 

namespace SPTK;

class DebugStream {

  public static string $appDir = '';

  private $handle;
  private string $data = '';
  private int $pos = 0;

  public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool {
    $rel = preg_replace('~^debug://~', '', $path);
    $appDir = dirname(APP_PATH);
    $real = $appDir . '/' . $rel;
    $php = @file_get_contents($real);
    if ($php === false) {
      return false;
    }
    $php = self::transform($php);
    $this->data = $php;
    $this->pos = 0;
    $opened_path = $path;
    return true;
  }

  public function stream_read(int $count): string {
    $chunk = substr($this->data, $this->pos, $count);
    $this->pos += strlen($chunk);
    return $chunk;
  }

  public function stream_eof(): bool {
    return $this->pos >= strlen($this->data);
  }

  public function stream_tell(): int {
    return $this->pos;
  }

  public function stream_seek(int $offset, int $whence = SEEK_SET): bool {
    $len = strlen($this->data);
    switch ($whence) {
      case SEEK_SET:
        $new = $offset;
        break;
      case SEEK_CUR:
        $new = $this->pos + $offset;
        break;
      case SEEK_END:
        $new = $len + $offset;
        break;
      default:
        return false;
    }
    if ($new < 0 || $new > $len) {
      return false;
    }
    $this->pos = $new;
    return true;
  }

  public function stream_stat(): array {
    return [];
  }

  public function stream_set_option(int $option, int $arg1, int $arg2): bool {
    return false;
  }

  public function url_stat(string $path, int $flags): array|false {
    $rel = preg_replace('~^debug://~', '', $path);
    $appDir = dirname(APP_PATH);
    $real = $appDir . '/' . $rel;
    if (!file_exists($real)) {
      return false;
    }
    return stat($real);
  }

  private static function transform(string $php): string {
    preg_match("~//\s*DEBUGLEVEL:(-?[0-9])~", $php, $m);
    if (DEBUG === true && !isset($m[1])) {
      return $php;
    }
    $level = (int)($m[1] ?? DEBUG);
    $only = false;
    if ($level < 0) {
      $level = -$level;
      $only = true;
    }
    if ($level > 9) {
      $level = 0;
    }
    if ($level == 0) {
      $level = '(:0|)';
    } else {
      $level = $only ? "(:{$level})" : "(:[0-{$level}]|)";
    }
    return preg_replace("~//\s*DEBUG{$level} ~", '', $php);
  }

}
