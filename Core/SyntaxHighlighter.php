<?php

namespace SPTK\Core;

/** Supplies grapheme-based token ranges line by line and style colors for a text widget. */
interface SyntaxHighlighter {

  /**
   * Return [tokens, state] for one line.
   * $state is what the previous line returned, or null for the first line.
   */
  public function highlightLine(string $line, mixed $state): array;

  public function styleColors(): array;

}
