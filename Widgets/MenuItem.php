<?php

namespace SPTK\Widgets;

/**
 * Defines one menu row and recursively owns any submenu rows below it.
 */
class MenuItem {

  protected array $config;
  protected array $items = [];

  public function __construct(array|string $config = []) {
    if (is_string($config)) {
      $config = ['label' => $config];
    }
    $items = $config['items'] ?? [];
    unset($config['items']);
    $this->config = $config;
    foreach ($items as $item) {
      $this->addItem($item);
    }
  }

  public function addItem(MenuItem|array|string $item, ?int $index = null): static {
    $item = $item instanceof MenuItem ? $item : new MenuItem($item);
    if ($index === null || $index < 0 || $index >= count($this->items)) {
      $this->items[] = $item;
      return $this;
    }
    array_splice($this->items, $index, 0, [$item]);
    return $this;
  }

  public function item(int $index): ?MenuItem {
    return $this->items[$index] ?? null;
  }

  public function items(): array {
    return $this->items;
  }

  public function hasItems(): bool {
    return !empty($this->items);
  }

  public function label(): string {
    return (string)($this->config['label'] ?? '');
  }

  public function layout(): array {
    return $this->arrayValue('layout');
  }

  public function left(): string {
    if ($this->has('left')) {
      return (string)$this->get('left');
    }
    if (!$this->checked()) {
      return '';
    }
    return $this->selectable() === true ? 'X' : '*';
  }

  public function right(): string {
    if ($this->has('right')) {
      return (string)$this->get('right');
    }
    return $this->hasItems() ? '>' : '';
  }

  public function enabled(): bool {
    return !$this->has('enabled') || $this->get('enabled') !== false;
  }

  public function selectable(): bool|string {
    $selectable = $this->get('selectable', false);
    return is_string($selectable) ? $selectable : (bool)$selectable;
  }

  public function checked(): bool {
    return (bool)$this->get('checked', false);
  }

  public function separatorAfter(): bool {
    return (bool)$this->get('separatorAfter', false);
  }

  public function closeOnActivate(): ?bool {
    return $this->has('closeOnActivate') ? $this->get('closeOnActivate') !== false : null;
  }

  public function values(string $column): array {
    return $this->arrayValue("{$column}Values");
  }

  public function width(string $column): ?int {
    return $this->has("{$column}Width") ? (int)$this->get("{$column}Width") : null;
  }

  public function get(string $key, mixed $default = null): mixed {
    return $this->config[$key] ?? $default;
  }

  public function has(string $key): bool {
    return array_key_exists($key, $this->config);
  }

  public function update(array $changes): void {
    if (isset($changes['items']) && is_array($changes['items'])) {
      $this->items = [];
      foreach ($changes['items'] as $item) {
        $this->addItem($item);
      }
      unset($changes['items']);
    }
    $this->config = array_replace($this->config, $changes);
  }

  public function action(): ?callable {
    $action = $this->get('action');
    return is_callable($action) ? $action : null;
  }

  public function onOpen(): ?callable {
    $onOpen = $this->get('onOpen');
    return is_callable($onOpen) ? $onOpen : null;
  }

  protected function arrayValue(string $key): array {
    $value = $this->get($key, []);
    return is_array($value) ? $value : [];
  }

}
