<?php

namespace SPTK\Core;

/**
 * Highlights text with ordered literal and regular-expression rules.
 */
class RuleSyntaxHighlighter implements SyntaxHighlighter {

  public function __construct(
    protected array $rules = [],
    protected array $styleColors = [
      'keyword' => ['fg' => '#ffffff', 'bg' => '#0000aa'],
      'string' => ['fg' => '#00ffff', 'bg' => '#0000aa'],
      'number' => ['fg' => '#ffff00', 'bg' => '#0000aa'],
      'comment' => ['fg' => '#777777', 'bg' => '#0000aa'],
      'operator' => ['fg' => '#ffffff', 'bg' => '#0000aa'],
    ]
  ) {
  }

  public static function fromRules(array $rules, ?array $styleColors = null): self {
    return $styleColors === null ? new self($rules) : new self($rules, $styleColors);
  }

  public function highlight(array $lines): array {
    $result = [];
    foreach ($lines as $line) {
      $tokens = [];
      $length = mb_strlen($line);
      $offset = 0;
      while ($offset < $length) {
        $matched = false;
        $tail = mb_substr($line, $offset);
        foreach ($this->rules as $rule) {
          $style = (string)($rule['style'] ?? $rule['type'] ?? 'plain');
          if (isset($rule['literal'])) {
            $literal = (string)$rule['literal'];
            if ($literal !== '' && str_starts_with($tail, $literal)) {
              $tokens[] = ['start' => $offset, 'length' => mb_strlen($literal), 'style' => $style];
              $offset += mb_strlen($literal);
              $matched = true;
              break;
            }
          }
          if (isset($rule['regex']) && preg_match($rule['regex'], $tail, $matches) === 1) {
            $value = $matches[1] ?? $matches[0];
            if ($value === '') {
              continue;
            }
            $tokens[] = ['start' => $offset, 'length' => mb_strlen($value), 'style' => $style];
            $offset += mb_strlen($value);
            $matched = true;
            break;
          }
        }
        if (!$matched) {
          $tokens[] = ['start' => $offset, 'length' => 1, 'style' => 'plain'];
          $offset++;
        }
      }
      $result[] = $tokens;
    }
    return $result;
  }

  public function styleColors(): array {
    return $this->styleColors;
  }

}
