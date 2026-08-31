<?php

namespace SPTK\Core;

/**
 * Produces styled text ranges for editor rendering.
 */
interface SyntaxHighlighter {

  public function highlight(array $lines): array;

  public function styleColors(): array;

}
