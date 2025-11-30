<?php

namespace SPTK;

class StyleSheet {

  const ANY = 0;

  protected static $styles = [];
  protected static $cache = [];
  protected static $around = [
    'border', 'borderColor', 'margin', 'padding'
  ];

  public static function load($path) {
    if (!file_exists($path)) {
      throw new \Exception("File not found: {$path}");
    }
    $file = file_get_contents($path);
    if ($file === false) {
      throw new \Exception("Couldn't read file: {$path}");
    }
    $file = str_replace(["\n", " "], '', $file);
    $file = trim($file, "}");
    $styles = explode("}", $file);
    foreach ($styles as $style) {
      list($selector, $rules) = explode("{", $style, 2);
      $rules = trim($rules, ";");
      $rules = explode(";", $rules);
      $processedRules = [];
      foreach ($rules as $rule) {
        list($name, $value) = explode(':', $rule);
        if (in_array($name, self::$around)) {
          $values = explode(',', $value);
          switch (count($values)) {
            case 1:
              $processedRules["{$name}Top"] = $value;
              $processedRules["{$name}Right"] = $value;
              $processedRules["{$name}Bottom"] = $value;
              $processedRules["{$name}Left"] = $value;
              break;
            case 2:
              $processedRules["{$name}Top"] = $values[0];
              $processedRules["{$name}Right"] = $values[1];
              $processedRules["{$name}Bottom"] = $values[0];
              $processedRules["{$name}Left"] = $values[1];
              break;
            case 3:
              $processedRules["{$name}Top"] = $values[0];
              $processedRules["{$name}Right"] = $values[1];
              $processedRules["{$name}Bottom"] = $values[2];
              $processedRules["{$name}Left"] = $values[1];
              break;
            default:
              $processedRules["{$name}Top"] = $values[0];
              $processedRules["{$name}Right"] = $values[1];
              $processedRules["{$name}Bottom"] = $values[2];
              $processedRules["{$name}Left"] = $values[3];
              break;
          }
        } else {
          $processedRules[$name] = $value;
        }
      }
      $style = new Style($processedRules);
      if (isset(self::$styles[$selector])) {
        self::$styles[$selector]->merge($style);
      } else {
        self::$styles[$selector] = $style;
      }
    }
    if (DEBUG) {
      foreach (self::$styles as $selector => $style) {
        echo "----------------\n";
        echo $selector, "\n";
        echo "----------------\n";
        $style->debug();
        echo "\n";
      }
    }
  }

  public static function get($defaultStyle, $ancestorStyle, $type, $class = self::ANY, $id = self::ANY) {
    if (is_array($class)) {
      $classStr = implode('.', $class);
    } else if ($class !== self::ANY) {
      $classStr = $class;
      $class = [$class];
    } else {
      $classStr = self::ANY;
    }
    if (!isset(self::$cache[$type][$classStr][$id])) {
      $style = new Style($defaultStyle);
      if (isset(self::$styles[$type])) {
        $style->merge(self::$styles[$type]);
      }
      if ($class !== self::ANY) {
        foreach ($class as $classi) {
          if (isset(self::$styles[".{$classi}"])) {
            $style->merge(self::$styles[".{$classi}"]);
          }
        }
      }
      if ($id !== self::ANY && isset(self::$styles["#{$id}"])) {
        $style->merge(self::$styles["#{$id}"]);
      }
      self::$cache[$type][$classStr][$id] = $style;
    } else {
      $style = self::$cache[$type][$classStr][$id];
    }
    $inheritedStyle = clone $style;
    if ($ancestorStyle !== false) {
      $inheritedStyle->inherit($ancestorStyle);
    }
    return $inheritedStyle;
  }

}
