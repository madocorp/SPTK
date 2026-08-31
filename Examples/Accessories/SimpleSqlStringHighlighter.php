<?php

namespace SPTK\Examples\Accessories;

use SPTK\Core\Tokenizer;

/**
 * String context for SimpleSqlHighlighter.
 */
class SimpleSqlStringHighlighter extends Tokenizer {

  protected array $styleMap = [
    'TEXT' => 'string',
    'STRING' => 'string',
  ];

  protected array $regexpRules = [
    ['type' => 'TEXT', 'regexp' => "/^[^']+/"],
  ];

}
