<?php

namespace SPTK;

class Tokenizer {

  protected static $lines = [];
  protected static $tokens = [];
  protected static $tokenizer;
  protected static $initializedTokenizers = [];
  protected static $contexts = [];

  protected $stack = [];
  protected $context;
  protected $stylePrefix = '';
  protected $styleMap = [];
  protected $contextSwitchers = [];
  protected $charRules = [];
  protected $regexpRules = [
    ['type' => 'TEXT', 'regexp' => '/^.*/']
  ];

  protected function __construct($context) {
    if (is_string($context)) {
      $tokenizer = $context;
      if (!isset(self::$initializedTokenizers[$tokenizer])) {
        $this->initialize($tokenizer);
      }
      $contextId = self::$initializedTokenizers[$tokenizer];
      $this->stack = [$contextId];
    } else {
      $this->stack = $context;
      $contextId = end($this->stack);
    }
    $this->context = self::$contexts[$contextId];
    $this->setSwitcherIds($contextId);
  }

  protected function initialize($tokenizer) {
    $id = count(self::$contexts);
    self::$initializedTokenizers[$tokenizer] = $id;
    self::$contexts[$id] = [
      'id' => $id,
      'tokenizer' => $tokenizer
    ];
    foreach ($this->contextSwitchers as &$context) {
      $id++;
      $context['id'] = $id;
      self::$contexts[$id] = $context;
    }
  }

  protected function setSwitcherIds($id) {
    foreach ($this->contextSwitchers as &$context) {
      $id++;
      $context['id'] = $id;
    }
  }

  protected function getStyle($type) {
    if (isset($this->styleMap[$type])) {
      return $this->stylePrefix . $this->styleMap[$type];
    }
    return false;
  }

  protected static function setContext($context) {
    $stack = self::$tokenizer->stack;
    $stack[] = $context['id'];
    $className = $context['tokenizer'];
    self::$tokenizer = new $className($stack);
  }

  protected static function restorePreviousContext() {
    $stack = self::$tokenizer->stack;
    array_pop($stack);
    $prevContextId = end($stack);
    $className = self::$contexts[$prevContextId]['tokenizer'];
    self::$tokenizer = new $className($stack);
  }

  protected static function getNextToken($str, $first) {
    $tokenizer = self::$tokenizer;
    $contextEnd = false;
    if (isset($tokenizer->context['endRegexp'])) {
      if (preg_match($tokenizer->context['endRegexp'], $str, $matches)) {
        $contextEnd = $match[0];
      }
    } else if (isset($tokenizer->context['end'])) {
      if (str_starts_with($str, $tokenizer->context['end'])) {
        $contextEnd = $tokenizer->context['end'];
      }
    }
    if ($contextEnd !== false) {
      self::restorePreviousContext();
      $type = $tokenizer->context['type'];
      return [
        'type' => $type,
        'style' => self::$tokenizer->getStyle($type),
        'value' => $contextEnd,
        'length' => mb_strlen($contextEnd),
      ];
    }
    $contextStart = false;
    foreach ($tokenizer->contextSwitchers as $context) {
      if (isset($context['startRegexp'])) {
        if (preg_match($context['startRegexp'], $str, $matches)) {
          $contextStart = $match[0];
        }
      } else if (isset($context['start'])) {
        if (str_starts_with($str, $context['start'])) {
          $contextStart = $context['start'];
        }
      }
      if ($contextStart !== false) {
        self::setContext($context);
        return [
          'type' => $context['type'],
          'style' => $tokenizer->getStyle($context['type']),
          'value' => $contextStart,
          'length' => mb_strlen($contextStart),
        ];
      }
    }
    $chr = $str[0];
    if (isset($tokenizer->charRules[$chr])) {
      $type = $tokenizer->charRules[$chr];
      return [
        'type' => $type,
        'style' => $tokenizer->getStyle($type),
        'value' => $chr,
        'length' => 1
      ];
    }
    foreach ($tokenizer->regexpRules as $rule) {
      if (isset($rule['first']) && $rule['first'] === true && $first !== true) {
        continue;
      }
      if (preg_match($rule['regexp'], $str, $matches)) {
        if (isset($matches[1])) {
          $value = $matches[1];
        } else {
          $value = $matches[0];
        }
        return [
          'type' => $rule['type'],
          'style' => $tokenizer->getStyle($rule['type']),
          'value' => $value,
          'length' => mb_strlen($value)
        ];
      }
    }
    return [
      'type' => 'ERROR',
      'style' => $tokenizer->getStyle('ERROR'),
      'value' => $str,
      'length' => mb_strlen($str)
    ];
  }

  protected static function tokenize() {
    while (($line = array_shift(self::$lines)) !== null) {
      $lineTokens = [];
      $length = mb_strlen($line);
      $token = false;
      $first = true;
      while ($length > 0) {
        $token = self::getNextToken($line, $first);
        $first = false;
        if (!isset($token['length']) || $token['length'] === 0) {
          throw new \Exception('Tokenizer infinite loop detected!');
        }
        $line = mb_substr($line, $token['length']);
        $lineTokens[] = $token;
        $length -= $token['length'];
      }
      self::$tokens[] = ['tokens' => $lineTokens, 'context' => self::$tokenizer->stack];
    }
  }

  public static function start($lines, $context) {
    self::$lines = $lines;
    self::$tokens = [];
    if (is_string($context)) {
      $className = $context;
    } else {
      $contextId = end($context);
      if (!isset(self::$contexts[$contextId])) {
        throw new \Exception("Tokenizer not found!");
      }
      $className = self::$contexts[$contextId]['tokenizer'];
    }
    self::$tokenizer = new $className($context);
    self::tokenize();
    $tokens = self::$tokens;
    self::$tokens = [];
    return $tokens;
  }

}
