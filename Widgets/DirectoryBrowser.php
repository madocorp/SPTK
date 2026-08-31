<?php

namespace SPTK\Widgets;

use SPTK\Core\InputAction;
use SPTK\Core\InputEvent;

/**
 * Directory-only searchable list that navigates through filesystem paths.
 */
class DirectoryBrowser extends ListView {

  protected string $path;
  protected bool $showHidden = false;
  protected $directoryFilter = null;
  protected $onPathChange = null;

  public function __construct(string $name = '', string $path = '.', array $options = []) {
    $this->path = $this->normalizePath($path);
    parent::__construct($name, [], $options + ['searchable' => true]);
    $this->refreshDirectoryItems();
  }

  public function setOptions(array $options): static {
    parent::setOptions($options);
    if (array_key_exists('showHidden', $options)) {
      $this->showHidden = (bool)$options['showHidden'];
    }
    if (array_key_exists('directoryFilter', $options) && is_callable($options['directoryFilter'])) {
      $this->directoryFilter = $options['directoryFilter'];
    }
    if (array_key_exists('onPathChange', $options) && is_callable($options['onPathChange'])) {
      $this->onPathChange = $options['onPathChange'];
    }
    return $this;
  }

  public function setPath(string $path): static {
    $path = $this->normalizePath($path);
    if ($this->path !== $path) {
      $this->path = $path;
      $this->refreshDirectoryItems();
      $this->notifyPathChange();
    }
    return $this;
  }

  public function refresh(): static {
    $this->refreshDirectoryItems();
    return $this;
  }

  public function path(): string {
    return $this->path;
  }

  public function value(): mixed {
    return $this->path;
  }

  public function getValue(): string {
    return $this->path;
  }

  public function handle(InputEvent $event): bool {
    if (InputAction::activate($event, 'browser')) {
      $item = $this->activeItem();
      if ($item === null) {
        return false;
      }
      return $this->setPath((string)$item->value()) !== null;
    }
    return parent::handle($event);
  }

  protected function refreshDirectoryItems(): void {
    $this->setItems($this->directoryItems());
    $this->setPreferredRows(count($this->items()));
    $this->invalidateRender();
  }

  protected function notifyPathChange(): void {
    if ($this->onPathChange !== null) {
      ($this->onPathChange)($this);
    }
  }

  protected function directoryItems(): array {
    $items = [];
    $parent = dirname($this->path);
    if ($parent !== $this->path) {
      $items[] = ['text' => '..', 'value' => $parent, 'filterable' => true];
    }
    foreach ($this->scanDirectoryNames($this->path) as $name) {
      $path = $this->path . DIRECTORY_SEPARATOR . $name;
      if (!$this->acceptDirectory($name, $path)) {
        continue;
      }
      $items[] = ['text' => $name, 'value' => $path, 'filterable' => true];
    }
    return $items;
  }

  protected function scanDirectoryNames(string $path): array {
    $names = @scandir($path);
    if ($names === false) {
      return [];
    }
    $dirs = [];
    foreach ($names as $name) {
      if ($name === '.' || $name === '..') {
        continue;
      }
      if (!$this->showHidden && str_starts_with($name, '.')) {
        continue;
      }
      if (is_dir($path . DIRECTORY_SEPARATOR . $name)) {
        $dirs[] = $name;
      }
    }
    natcasesort($dirs);
    return array_values($dirs);
  }

  protected function acceptDirectory(string $name, string $path): bool {
    return $this->directoryFilter === null || (bool)($this->directoryFilter)($name, $path);
  }

  protected function normalizePath(string $path): string {
    $real = realpath($path);
    return $real !== false && is_dir($real) ? $real : getcwd();
  }

}
