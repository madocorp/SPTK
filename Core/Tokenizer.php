<?php

namespace SPTK\Core;

/**
 * Stateful line tokenizer with regex, character, and nested context rules.
 */
class Tokenizer implements SyntaxHighlighter {

  protected static array $lines = [];
  protected static array $tokens = [];
  protected static ?Tokenizer $tokenizer = null;
  protected static array $initializedTokenizers = [];
  protected static array $contexts = [];

  protected array $stack = [];
  protected array $context = [];
  protected string $stylePrefix = '';
  protected array $styleMap = [
    'TEXT' => 'plain',
    'ERROR' => 'plain',
  ];
  protected array $styleColors = [];
  protected array $contextSwitchers = [];
  protected array $charRules = [];
  protected array $regexpRules = [
    ['type' => 'TEXT', 'regexp' => '/^.*/u'],
  ];

  public function highlight(array $lines): array {
    $result = [];
    foreach (self::start($lines, static::class) as $line) {
      $offset = 0;
      $tokens = [];
      foreach ($line['tokens'] as $token) {
        $length = (int)($token['length'] ?? mb_strlen((string)($token['value'] ?? '')));
        $tokens[] = [
          'start' => $offset,
          'length' => $length,
          'style' => (string)($token['style'] ?? 'plain'),
          'type' => (string)($token['type'] ?? 'TEXT'),
          'value' => (string)($token['value'] ?? ''),
        ];
        $offset += $length;
      }
      $result[] = $tokens;
    }
    return $result;
  }

  public function styleColors(): array {
    return $this->styleColors;
  }

  public function setStack(array $stack): void {
    $this->stack = $stack;
    $contextId = end($this->stack);
    $this->context = self::$contexts[$contextId] ?? [];
    $tokenizer = $this->context['tokenizer'] ?? static::class;
    $tokenizerId = self::$initializedTokenizers[$tokenizer] ?? null;
    if ($tokenizerId !== null) {
      $this->setSwitcherIds($tokenizerId);
    }
  }

  public function initialize(): void {
    $tokenizer = static::class;
    if (isset(self::$initializedTokenizers[$tokenizer])) {
      return;
    }
    $id = count(self::$contexts);
    self::$initializedTokenizers[$tokenizer] = $id;
    self::$contexts[$id] = [
      'id' => $id,
      'tokenizer' => $tokenizer,
    ];
    foreach ($this->contextSwitchers as $context) {
      $id++;
      $context['id'] = $id;
      $context['tokenizer'] = $this->normalizeTokenizerClass($context['tokenizer'] ?? $tokenizer);
      self::$contexts[$id] = $context;
    }
  }

  public static function start(array $lines, string|array $context): array {
    self::$lines = $lines;
    self::$tokens = [];
    if (is_string($context)) {
      $className = self::normalizeTokenizerName($context);
      self::ensureInitialized($className);
      $contextId = self::$initializedTokenizers[$className] ?? null;
      if ($contextId === null) {
        throw new \RuntimeException("Uninitialized tokenizer: {$className}");
      }
      $stack = [$contextId];
    } else {
      $stack = $context;
      $contextId = end($stack);
      if (!isset(self::$contexts[$contextId])) {
        throw new \RuntimeException('Tokenizer not found.');
      }
      $className = self::$contexts[$contextId]['tokenizer'];
    }
    self::$tokenizer = new $className();
    self::$tokenizer->setStack($stack);
    self::tokenize();
    $tokens = self::$tokens;
    self::$tokens = [];
    return $tokens;
  }

  protected function setSwitcherIds(int $id): void {
    foreach ($this->contextSwitchers as &$context) {
      $id++;
      $context['id'] = $id;
      $context['tokenizer'] = $this->normalizeTokenizerClass($context['tokenizer'] ?? static::class);
    }
  }

  protected function getStyle(string $type): string {
    if (isset($this->styleMap[$type])) {
      return $this->stylePrefix . $this->styleMap[$type];
    }
    return strtolower($type);
  }

  protected static function setContext(array $context): void {
    if (self::$tokenizer === null) {
      return;
    }
    $stack = self::$tokenizer->stack;
    $className = $context['tokenizer'];
    $stack[] = $context['id'];
    self::$tokenizer = new $className();
    self::$tokenizer->setStack($stack);
  }

  protected static function restorePreviousContext(): void {
    if (self::$tokenizer === null) {
      return;
    }
    $stack = self::$tokenizer->stack;
    array_pop($stack);
    $prevContextId = end($stack);
    $className = self::$contexts[$prevContextId]['tokenizer'];
    self::$tokenizer = new $className();
    self::$tokenizer->setStack($stack);
  }

  protected static function contextEnd(string $str, bool $first, Tokenizer $tokenizer): ?string {
    if (($tokenizer->context['endFirst'] ?? false) !== false && $first === false) {
      return null;
    }
    if (isset($tokenizer->context['endRegexp'])) {
      if (preg_match($tokenizer->context['endRegexp'], $str, $matches) === 1) {
        return $matches[0];
      }
    } else if (isset($tokenizer->context['end'])) {
      if ($tokenizer->context['end'] === 'empty' && $str === '') {
        return '';
      }
      if (str_starts_with($str, $tokenizer->context['end'])) {
        return $tokenizer->context['end'];
      }
    }
    return null;
  }

  protected static function contextStart(string $str, bool $first, Tokenizer $tokenizer, ?array &$newContext): ?string {
    foreach ($tokenizer->contextSwitchers as $context) {
      if (($context['startFirst'] ?? false) !== false && $first === false) {
        continue;
      }
      if (isset($context['startRegexp'])) {
        if (preg_match($context['startRegexp'], $str, $matches) === 1) {
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
    return null;
  }

  protected static function getNextToken(string $str, bool $first): array {
    if (self::$tokenizer === null) {
      throw new \RuntimeException('Tokenizer is not active.');
    }
    $tokenizer = self::$tokenizer;
    $contextEnd = self::contextEnd($str, $first, $tokenizer);
    if ($contextEnd !== null) {
      self::restorePreviousContext();
      $type = (string)($tokenizer->context['type'] ?? 'TEXT');
      return [
        'type' => $type,
        'style' => self::$tokenizer?->getStyle($type) ?? 'plain',
        'value' => $contextEnd,
        'length' => mb_strlen($contextEnd),
      ];
    }
    $contextStart = self::contextStart($str, $first, $tokenizer, $newContext);
    if ($contextStart !== null && $newContext !== null) {
      self::setContext($newContext);
      $type = (string)$newContext['type'];
      return [
        'type' => $type,
        'style' => $tokenizer->getStyle($type),
        'value' => $contextStart,
        'length' => mb_strlen($contextStart),
      ];
    }
    $chr = mb_substr($str, 0, 1);
    if (isset($tokenizer->charRules[$chr])) {
      $type = (string)$tokenizer->charRules[$chr];
      return [
        'type' => $type,
        'style' => $tokenizer->getStyle($type),
        'value' => $chr,
        'length' => 1,
      ];
    }
    foreach ($tokenizer->regexpRules as $rule) {
      if (($rule['first'] ?? false) === true && $first === false) {
        continue;
      }
      if (preg_match($rule['regexp'], $str, $matches) === 1) {
        $value = $matches[1] ?? $matches[0];
        return [
          'type' => (string)$rule['type'],
          'style' => $tokenizer->getStyle((string)$rule['type']),
          'value' => $value,
          'length' => mb_strlen($value),
        ];
      }
    }
    return [
      'type' => 'ERROR',
      'style' => $tokenizer->getStyle('ERROR'),
      'value' => $str,
      'length' => mb_strlen($str),
    ];
  }

  protected static function tokenize(): void {
    while (($line = array_shift(self::$lines)) !== null) {
      $lineTokens = [];
      $length = mb_strlen($line);
      $first = true;
      if ($length === 0 && (self::$tokenizer?->context['end'] ?? null) === 'empty') {
        self::restorePreviousContext();
      }
      while ($length > 0) {
        $token = self::getNextToken($line, $first);
        $first = false;
        if (!isset($token['length']) || $token['length'] <= 0) {
          throw new \RuntimeException('Tokenizer infinite loop detected.');
        }
        $line = mb_substr($line, (int)$token['length']);
        $lineTokens[] = $token;
        $length -= (int)$token['length'];
      }
      $lineStyle = self::$tokenizer === null ? null : self::$tokenizer->lineStyleForContext(self::$tokenizer->stack);
      if ($lineTokens === [] && $lineStyle !== null) {
        $lineTokens[] = [
          'type' => 'LINE',
          'style' => $lineStyle,
          'value' => '',
          'length' => 0,
        ];
      }
      self::$tokens[] = ['tokens' => $lineTokens, 'context' => self::$tokenizer?->stack ?? []];
    }
  }

  protected function lineStyleForContext(array $stack): ?string {
    $contextId = end($stack);
    if ($contextId === false) {
      return null;
    }
    $context = self::$contexts[$contextId] ?? null;
    $type = is_array($context) ? (string)($context['type'] ?? '') : '';
    return $type === '' ? null : $this->getStyle($type);
  }

  protected static function ensureInitialized(string $className): void {
    if (!is_subclass_of($className, self::class) && $className !== self::class) {
      throw new \RuntimeException("Tokenizer class expected: {$className}");
    }
    if (!isset(self::$initializedTokenizers[$className])) {
      (new $className())->initialize();
    }
  }

  protected static function normalizeTokenizerName(string $className): string {
    $className = ltrim($className, '\\');
    if (!str_contains($className, '\\')) {
      $className = __NAMESPACE__ . '\\' . $className;
    }
    return $className;
  }

  protected function normalizeTokenizerClass(string $className): string {
    return self::normalizeTokenizerName($className);
  }

}
