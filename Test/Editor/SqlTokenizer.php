<?php

require_once 'SingleQuotedString.php';
require_once 'DoubleQuotedString.php';
require_once 'BlockComment.php';

class SqlTokenizer extends \SPTK\Tokenizer {

  protected $stylePrefix = '';
  protected $styleMap = [
    'ERROR' => 'red',
    'KEYWORD' => 'cyan',
    'FUNCTION' => 'yellow',
    'BRACKET' => 'white',
    'SEPARATOR' => 'white',
    'OPERATOR' => 'white',
    'IDENTIFIER' => 'brightblue',
    'NUMBER' => 'green',
    'STRING' => 'darkgreen',
    'COMMENT' => 'gray'
  ];
  protected $contextSwitchers = [
    [
      'start' => '"',
      'end' => '"',
      'escape' => '\\',
      'escapeItself' => true,
      'tokenizer' => '\DoubleQuotedString',
      'type' => 'STRING'
    ],
    [
      'start' => "'",
      'end' => "'",
      'escape' => '\\',
      'escapeItself' => true,
      'tokenizer' => '\SingleQuotedString',
      'type' => 'STRING'
    ],
    [
      'start' => '/' . '*',
      'end' => '*' . '/',
      'escape' => '\\',
      'escapeItself' => true,
      'tokenizer' => '\BlockComment',
      'type' => 'COMMENT'
    ]
  ];
  protected $charRules = [
    '(' => 'BRACKET',
    ')' => 'BRACKET',
    '.' => 'SEPARATOR',
    ',' => 'SEPARATOR',
  ];
  protected $regexpRules = [
    ['type' => 'COMMENT', 'regexp' => '/^--\s.*/'],
    ['type' => 'KEYWORD', 'regexp' => '/^(SELECT|FROM|WHERE|LEFT JOIN|RIGHT JOIN|INNER JOIN|JOIN|LIMIT|ORDER BY|AS|ON|ASC|DESC)(\s|$)/'],
    ['type' => 'FUNCTION', 'regexp' => '/^(COALESCE)(\s*\()/'],
    ['type' => 'IDENTIFIER', 'regexp' => '/^`[^`]+`/'],
    ['type' => 'OPERATOR', 'regexp' => '/^(<=|>=|<|>|=|AND|OR|\+|-|\/|\*)/'],
    ['type' => 'NUMBER', 'regexp' => '/^(0x[0-9a-fA-F]+|0b[01]+|[0-9]+(\.[0-9]+)?)/'],
    ['type' => 'WHITESPACE', 'regexp' => '/^\s+/'],
    ['type' => 'WORD', 'regexp' => '/^[^\s\(\)\.]+/']
  ];

}

(new SqlTokenizer)->initialize();
