<?php

namespace SPTK\XmlParser;

use SPTK\Core\SyntaxHighlighter;

/** Create an app-owned syntax highlighter named by a text widget's XML. */
final class HighlighterParser {

  public static function parse(?string $class): ?SyntaxHighlighter {
    if ($class === null) {
      return null;
    }
    if ($class === '' || !class_exists($class) || !is_subclass_of($class, SyntaxHighlighter::class)) {
      throw new \RuntimeException("Invalid syntax highlighter class: {$class}");
    }
    try {
      return new $class();
    } catch (\Throwable $error) {
      throw new \RuntimeException("Could not create syntax highlighter {$class}: {$error->getMessage()}", 0, $error);
    }
  }

}
