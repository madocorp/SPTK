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

  public function setStack($stack) {
    $this->stack = $stack;
    $contextId = end($this->stack);
    $this->context = self::$contexts[$contextId];
    $tokenizer = $this->context['tokenizer'];
    $tokenizerId = self::$initializedTokenizers[$tokenizer];
    $this->setSwitcherIds($tokenizerId);
  }

  public function initialize() {
    $tokenizer = '\\' . get_class($this);
    $id = count(self::$contexts);
    if (isset(self::$initializedTokenizers[$tokenizer])) {
      throw new \Exception("Tokenizer is already initialized ({$tokenizer})");
    }
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
    $className = $context['tokenizer'];
    $stack[] = $context['id'];
    self::$tokenizer = new $className;
    self::$tokenizer->setStack($stack);
  }

  protected static function restorePreviousContext() {
    $stack = self::$tokenizer->stack;
    array_pop($stack);
    $prevContextId = end($stack);
    $className = self::$contexts[$prevContextId]['tokenizer'];
    self::$tokenizer = new $className;
    self::$tokenizer->setStack($stack);
  }

  protected static function contextEnd($str, $first, $tokenizer) {
    if (isset($tokenizer->context['endFirst']) && $tokenizer->context['endFirst'] !== false && $first === false) {
      return false;
    }
    if (isset($tokenizer->context['endRegexp'])) {
      if (preg_match($tokenizer->context['endRegexp'], $str, $matches)) {
        return $match[0];
      }
    } else if (isset($tokenizer->context['end'])) {
      if (str_starts_with($str, $tokenizer->context['end'])) {
        return $tokenizer->context['end'];
      }
    }
    return false;
  }

  protected static function contextStart($str, $first, $tokenizer, &$newContext) {
    foreach ($tokenizer->contextSwitchers as $context) {
      if (isset($context['startFirst']) && $context['startFirst'] !== false && $first === false) {
        continue;
      }
      if (isset($context['startRegexp'])) {
        if (preg_match($context['startRegexp'], $str, $matches)) {
          $newContext = $context;
          return $matches[0];
        }
      } else if (isset($context['start'])) {
        if (str_starts_with($str, $context['start'])) {
          $newContext = $context;
          return $context['start'];
        }
      }
    }
    return false;
  }

  protected static function getNextToken($str, $first) {
    $tokenizer = self::$tokenizer;
    $contextEnd = self::contextEnd($str, $first, $tokenizer);
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
    $contextStart = self::contextStart($str, $first, $tokenizer, $newContext);
    if ($contextStart !== false) {
      self::setContext($newContext);
      return [
        'type' => $newContext['type'],
        'style' => $tokenizer->getStyle($newContext['type']),
        'value' => $contextStart,
        'length' => mb_strlen($contextStart),
      ];
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
      if (isset($rule['first']) && $rule['first'] === true && $first === false) {
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
      if ($length === 0 && isset(self::$tokenizer->context['end']) && self::$tokenizer->context['end'] === 'empty') {
        self::restorePreviousContext();
      }
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
      if (!isset(self::$initializedTokenizers[$className])) {
        Autoload::autoload($className);
      }
      if (!isset(self::$initializedTokenizers[$className])) {
        throw new \Exception("Uninitialized tokenizer: {$className}");
      }
      $contextId = self::$initializedTokenizers[$className];
      $stack = [$contextId];
    } else {
      $stack = $context;
      $contextId = end($stack);
      if (!isset(self::$contexts[$contextId])) {
        throw new \Exception("Tokenizer not found!");
      }
      $className = self::$contexts[$contextId]['tokenizer'];
    }
    self::$tokenizer = new $className;
    self::$tokenizer->setStack($stack);
    self::tokenize();
    $tokens = self::$tokens;
    self::$tokens = [];
    return $tokens;
  }

}

(new Tokenizer)->initialize();
