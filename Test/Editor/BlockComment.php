<?php

class BlockComment extends \SPTK\Tokenizer {

  protected $styleMap = [
    'STRING' => 'blue',
    'ERROR' => 'error'
  ];
  protected $regexpRules = [
    ['type' => 'STRING',  'regexp' => "/^[^\*']+/"],
    ['type' => 'STRING',  'regexp' => "/^\*+/"],
  ];

}
