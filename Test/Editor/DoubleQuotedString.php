<?php

class DoubleQuotedString extends \SPTK\Tokenizer {

  protected $styleMap = [
    'STRING' => 'darkgreen',
    'ERROR' => 'error'
  ];
  protected $regexpRules = [
    ['type' => 'STRING',  'regexp' => '/^[^\\\\"]+/'],
    ['type' => 'STRING',  'regexp' => '/^\\\\\\\\/'],
    ['type' => 'STRING',  'regexp' => '/^\\\\"/'],
  ];

}
