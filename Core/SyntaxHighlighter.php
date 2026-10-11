<?php

namespace SPTK\Core;

/** Supplies grapheme-based token ranges and style colors for a text widget. */
interface SyntaxHighlighter {

  public function highlight(array $lines): array;

  public function styleColors(): array;

}
