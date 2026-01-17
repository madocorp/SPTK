<?php

class BlockComment extends \SPTK\Tokenizer {

  protected $styleMap = [
    'STRING' => 'gray',
    'ERROR' => 'error'
  ];
  protected $regexpRules = [
    ['type' => 'STRING',  'regexp' => "/^[^\*']+/"],
    ['type' => 'STRING',  'regexp' => "/^\*+/"],
  ];

}

(new BlockComment)->initialize();