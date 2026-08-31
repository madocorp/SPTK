<?php

namespace SPTK\Widgets;

use SPTK\Core\InputEvent;

/**
 * Searchable selectable file list for one directory.
 */
class FileBrowser extends ListView {

  protected string $path;
  protected bool $showHidden = false;
  protected bool $multiple = false;
  protected string $mode = 'files';
  protected array $extensions = [];
  protected $fileFilter = null;
  protected $directoryFilter = null;
  protected $onPathChange = null;

  public function __construct(string $name = '', string $path = '.', array $options = []) {
    $this->path = $this->normalizePath($path);
    parent::__construct($name, [], $options + ['searchable' => true]);
    $this->refreshFileItems();
  }

  public function setOptions(array $options): static {
    parent::setOptions($options);
    if (array_key_exists('showHidden', $options)) {
      $this->showHidden = (bool)$options['showHidden'];
    }
    if (array_key_exists('multiple', $options)) {
      $this->multiple = (bool)$options['multiple'];
    }
    if (array_key_exists('mode', $options)) {
      $this->mode = $this->normalizeMode((string)$options['mode']);
    }
    if (array_key_exists('directoriesOnly', $options)) {
      $this->mode = (bool)$options['directoriesOnly'] ? 'directories' : 'files';
    }
    if (array_key_exists('extensions', $options)) {
      $this->extensions = $this->normalizeExtensions((array)$options['extensions']);
    }
    if (array_key_exists('fileFilter', $options) && is_callable($options['fileFilter'])) {
      $this->fileFilter = $options['fileFilter'];
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
      $this->refreshFileItems();
      $this->notifyPathChange();
    }
    return $this;
  }

  public function refresh(): static {
    $this->refreshFileItems();
    return $this;
  }

  public function path(): string {
    return $this->path;
  }

  public function value(): mixed {
    return $this->getValue();
  }

  public function getValue(): array {
    $selected = [];
    foreach ($this->items() as $item) {
      if ($item->selected()) {
        $selected[] = (string)$item->value();
      }
    }
    return $selected;
  }

  public function setValue(mixed $value): static {
    $values = is_array($value) ? array_map('strval', $value) : [(string)$value];
    foreach ($this->items() as $item) {
      if ($item->selectable() !== false && in_array((string)$item->value(), $values, true)) {
        $item->select();
      } else {
        $item->deselect();
      }
    }
    $this->invalidateRender();
    return $this;
  }

  protected function refreshFileItems(): void {
    $this->setItems($this->fileItems());
    $this->setPreferredRows(count($this->items()));
    $this->invalidateRender();
  }

  protected function notifyPathChange(): void {
    if ($this->onPathChange !== null) {
      ($this->onPathChange)($this);
    }
  }

  protected function fileItems(): array {
    $items = [];
    if ($this->mode === 'directories') {
      foreach ($this->scanDirectoryNames($this->path) as $name) {
        $path = $this->path . DIRECTORY_SEPARATOR . $name;
        if (!$this->acceptDirectory($name, $path)) {
          continue;
        }
        $items[] = [
          'text' => $name,
          'value' => $path,
          'filterable' => true,
          'selectable' => $this->multiple ? true : 'file',
        ];
      }
      return $items;
    }
    foreach ($this->scanFileNames($this->path) as $name) {
      $path = $this->path . DIRECTORY_SEPARATOR . $name;
      if (!$this->acceptFile($name, $path)) {
        continue;
      }
      $items[] = [
        'text' => $name,
        'value' => $path,
        'filterable' => true,
        'selectable' => $this->multiple ? true : 'file',
      ];
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

  protected function scanFileNames(string $path): array {
    $names = @scandir($path);
    if ($names === false) {
      return [];
    }
    $files = [];
    foreach ($names as $name) {
      if ($name === '.' || $name === '..') {
        continue;
      }
      if (!$this->showHidden && str_starts_with($name, '.')) {
        continue;
      }
      if (is_file($path . DIRECTORY_SEPARATOR . $name)) {
        $files[] = $name;
      }
    }
    natcasesort($files);
    return array_values($files);
  }

  protected function acceptFile(string $name, string $path): bool {
    if ($this->extensions !== []) {
      $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
      if (!in_array($extension, $this->extensions, true)) {
        return false;
      }
    }
    return $this->fileFilter === null || (bool)($this->fileFilter)($name, $path);
  }

  protected function acceptDirectory(string $name, string $path): bool {
    return $this->directoryFilter === null || (bool)($this->directoryFilter)($name, $path);
  }

  protected function normalizeExtensions(array $extensions): array {
    $normalized = [];
    foreach ($extensions as $extension) {
      $extension = strtolower(ltrim((string)$extension, '.'));
      if ($extension !== '') {
        $normalized[] = $extension;
      }
    }
    return array_values(array_unique($normalized));
  }

  protected function normalizeMode(string $mode): string {
    return in_array($mode, ['files', 'directories'], true) ? $mode : 'files';
  }

  protected function normalizePath(string $path): string {
    $real = realpath($path);
    return $real !== false && is_dir($real) ? $real : getcwd();
  }

}
