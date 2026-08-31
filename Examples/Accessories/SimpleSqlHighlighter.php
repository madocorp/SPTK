<?php

namespace SPTK\Examples\Accessories;

use SPTK\Core\Tokenizer;

/**
 * Tiny SQL-ish highlighter example for TextEditor::setTokenizer().
 */
class SimpleSqlHighlighter extends Tokenizer {

  protected array $styleMap = [
    'TEXT' => 'plain',
    'KEYWORD' => 'keyword',
    'NUMBER' => 'number',
    'STRING' => 'string',
    'OPERATOR' => 'operator',
  ];

  protected array $styleColors = [
    'keyword' => ['fg' => '#cccccc'],
    'number' => ['fg' => '#cccc00'],
    'string' => ['fg' => '#00aaaa'],
    'operator' => ['fg' => '#cccccc'],
  ];

  protected array $contextSwitchers = [
    ['type' => 'STRING', 'start' => "'", 'end' => "'", 'tokenizer' => SimpleSqlStringHighlighter::class],
  ];

  protected array $charRules = [
    '*' => 'OPERATOR',
    ',' => 'OPERATOR',
    '=' => 'OPERATOR',
    '(' => 'OPERATOR',
    ')' => 'OPERATOR',
  ];

  protected array $regexpRules = [
    ['type' => 'KEYWORD', 'regexp' => '/^(select|from|where|and|or|limit)\b/i'],
    ['type' => 'NUMBER', 'regexp' => '/^\d+/'],
    ['type' => 'TEXT', 'regexp' => '/^\s+/'],
    ['type' => 'TEXT', 'regexp' => '/^[A-Za-z_][A-Za-z0-9_]*/'],
  ];

}
