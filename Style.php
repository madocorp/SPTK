<?php

namespace SPTK;

class Style {

  const F_TYPE = 0;
  const F_NEGATIVE = 1;
  const F_VALUE = 2;
  const F_ORIGINAL = 3;

  const T_STRING = 0;
  const T_COLOR = 1;
  const T_PERCENT = 2;
  const T_PIXEL = 3;
  const T_BOOLEAN = 4;

  public $rules = [];

  public function __construct($init = false) {
    if (is_array($init)) {
      foreach ($init as $name => $value) {
        $this->rules[$name] = $this->parseValue($value);
      }
    } else if ($init !== false) {
      foreach ($init->rules as $name => $value) {
        $this->rules[$name] = $init->rules[$name];
      }
    }
  }

  public function merge($style) {
    foreach ($style->rules as $name => $value) {
      $this->rules[$name] = $value;
    }
  }

  public function inherit($ancestor) {
    foreach ($this->rules as $name => $value) {
      if ($value[self::F_VALUE] == 'inherit') {
        $this->rules[$name] = $ancestor->rules[$name];
      }
    }
  }

  protected function parseValue($value) {
    $original = $value;
    if (substr($value, 0, 1) === '#') {
      $type = self::T_COLOR;
      $negative = null;
      $value = substr($value, 1);
      if (strlen($value) == 8) {
        $value = hexdec($value);
        $value = [$value >> 24, ($value >> 16) & 0xff, ($value >> 8) & 0xff, $value & 0xff];
      } else if (strlen($value) == 6) {
        $value = hexdec($value);
        $value = [$value >> 16, ($value >> 8) & 0xff, $value & 0xff, 0xff];
      } else {
        throw new \Exception("Illegal color string: {$original}");
      }
    } else if (substr($value, -1) === '%') {
      $type = self::T_PERCENT;
      $negative = false;
      if (substr($value, 0, 1) === '-') {
        $negative = true;
        $value = substr($value, 1);
      }
      $value = (float)str_replace('%', '', $value);
    } else if (substr($value, -2) === 'px') {
      $type = self::T_PIXEL;
      $negative = false;
      if (substr($value, 0, 1) === '-') {
        $negative = true;
        $value = substr($value, 1);
      }
      $value = (int)substr($value, 0, -2);
    } else if ($value == 'true' || $value == 'false') {
      $negative = null;
      $type = self::T_BOOLEAN;
      $value = ($value == 'true');
    } else {
      $negative = null;
      $type = self::T_STRING;
    }
    return [
      self::F_TYPE => $type,
      self::F_NEGATIVE => $negative,
      self::F_VALUE => $value,
      self::F_ORIGINAL => $original
    ];
  }

  public function debug() {
    foreach ($this->rules as $name => $value) {
      $original = $value[self::F_ORIGINAL];
      echo "  {$name}: {$original}\n";
    }
  }

  public function get($name, $reference = false, &$negative = null) {
    if (!isset($this->rules[$name])) {
      throw new \Exception("Unknown style rule: {$name}");
    }
    $value = $this->rules[$name][self::F_VALUE];
    $type = $this->rules[$name][self::F_TYPE];
    $negative = $this->rules[$name][self::F_NEGATIVE];
    if ($type == self::T_PERCENT) {
      if ($reference == 'content') {
        throw new \Excepetion('A percentage value cannot be specified if the reference value depends on the content!');
      }
      return (int)round($reference * $value / 100) * ($negative ? -1 : 1);
    }
    if ($type == self::T_PIXEL) {
      return $value * ($negative ? -1 : 1);
    }
    return $value;
  }

  public function set($name, $value) {
    $this->rules[$name] = $this->parseValue($value);
  }

}
