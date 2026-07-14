<?php

namespace SPTK;

class StyleSheet {

  const ANY = 0;

  protected static $styles = [];
  protected static $cache = [];
  protected static $around = [
    'border', 'borderColor', 'margin', 'padding'
  ];

  public static function load(string $path, bool $overwrite = false): void {
    if (!file_exists($path)) {
      throw new \Exception("File not found: {$path}");
    }
    $file = file_get_contents($path);
    if ($file === false) {
      throw new \Exception("Couldn't read file: {$path}");
    }
    if (empty($file)) {
      return;
    }
    $file = preg_replace("/\/\/[^\n]\n/", "", $file);
    $file = str_replace(["\n", " "], '', $file);
    $file = trim($file, "}");
    $styles = explode("}", $file);
    foreach ($styles as $style) {
      list($selector, $rules) = explode("{", $style, 2);
      $rules = trim($rules, ";");
      $rules = explode(";", $rules);
      $processedRules = [];
      foreach ($rules as $rule) {
        if (strpos($rule, ':') === false) {
          continue;
        }
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
      foreach (self::expandSelectors($selector) as $selector) {
        if ($overwrite === false && isset(self::$styles[$selector])) {
          self::$styles[$selector]->merge($style);
        } else {
          self::$styles[$selector] = clone $style;
        }
      }
    }
    self::clearCache();
    //  DEBUG:style foreach (self::$styles as $selector => $style) {
    //  DEBUG:style   echo "----------------\n";
    //  DEBUG:style   echo $selector, "\n";
    //  DEBUG:style   echo "----------------\n";
    //  DEBUG:style   $style->debug();
    //  DEBUG:style   echo "\n";
    //  DEBUG:style }
  }

  protected static function expandSelectors(string $selector): array {
    $selectors = [];
    foreach (explode(',', $selector) as $selector) {
      if ($selector === '') {
        continue;
      }
      if (str_contains($selector, '/') && !str_starts_with($selector, '#') && !str_starts_with($selector, '.')) {
        $selector = '#' . $selector;
      }
      if (
        strpos($selector, ':') !== false &&
        !preg_match('/^[^#.:]+#[^#.:]+:[^#.:]+$/', $selector) &&
        !preg_match('/^#[^#.:]+:[^#.:]+$/', $selector)
      ) {
        $selector = '.' . $selector;
      }
      $selectors[] = $selector;
    }
    return $selectors;
  }

  public static function get(Style|array|null $defaultStyle, Style|array|null $ancestorStyle, string $type, string|array|int $class = self::ANY, string|int $name = self::ANY): Style {
    if (is_array($class)) {
      $classStr = implode('.', $class);
    } else if ($class !== self::ANY) {
      $classStr = $class;
      $class = [$class];
    } else {
      $classStr = self::ANY;
    }
    $defaultKey = self::styleCacheKey($defaultStyle);
    if (!isset(self::$cache[$type][$name][$classStr][$defaultKey])) {
      $style = new Style($defaultStyle);
      if (isset(self::$styles[$type])) {
        $style->merge(self::$styles[$type]);
      }
      if ($name !== self::ANY && isset(self::$styles["#{$name}"])) {
        $style->merge(self::$styles["#{$name}"]);
      }
      if ($class !== self::ANY) {
        foreach ($class as $classi) {
          if (isset(self::$styles[".{$classi}"])) {
            $style->merge(self::$styles[".{$classi}"]);
          }
          if ($name !== self::ANY && str_starts_with($classi, "{$type}:")) {
            $variant = substr($classi, strlen($type) + 1);
            if (isset(self::$styles["#{$name}:{$variant}"])) {
              $style->merge(self::$styles["#{$name}:{$variant}"]);
            }
            if (isset(self::$styles["{$type}#{$name}:{$variant}"])) {
              $style->merge(self::$styles["{$type}#{$name}:{$variant}"]);
            }
          } else if ($name !== self::ANY && strpos($classi, ':') !== false) {
            $variant = substr($classi, strrpos($classi, ':') + 1);
            if (isset(self::$styles["#{$name}:{$variant}"])) {
              $style->merge(self::$styles["#{$name}:{$variant}"]);
            }
            $classType = substr($classi, 0, strpos($classi, ':'));
            if (isset(self::$styles["{$classType}#{$name}:{$variant}"])) {
              $style->merge(self::$styles["{$classType}#{$name}:{$variant}"]);
            }
          }
        }
      }
      if (is_string($name) && str_contains($name, '/') && isset(self::$styles["#{$name}"])) {
        $style->merge(self::$styles["#{$name}"]);
      }
      self::$cache[$type][$name][$classStr][$defaultKey] = $style;
    } else {
      $style = self::$cache[$type][$name][$classStr][$defaultKey];
    }
    $inheritedStyle = clone $style;
    if ($ancestorStyle !== null) {
      $inheritedStyle->inherit($ancestorStyle);
    }
    return $inheritedStyle;
  }

  public static function clearCache(): void {
    self::$cache = [];
  }

  private static function styleCacheKey(Style|array|null $style): string {
    if ($style instanceof Style) {
      return sha1(serialize($style->rules));
    }
    if (is_array($style)) {
      return sha1(serialize($style));
    }
    return 'null';
  }

}
