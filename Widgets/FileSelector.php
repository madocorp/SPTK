<?php

namespace SPTK\Widgets;

/**
 * Read-only file path field that opens a FileBrowser panel.
 */
class FileSelector extends Selector {

  protected string $path;
  protected bool $multiple = false;
  protected bool $showHidden = false;
  protected bool $createDirectory = false;
  protected bool $createFile = false;
  protected array $extensions = [];

  public function __construct(string $name = '', string $path = '.', mixed $value = null, array $options = []) {
    $this->path = $this->normalizePath($path);
    parent::__construct($name, [], null, $options + [
      'title' => 'Select File',
      'panelSize' => 'big',
      'panelRows' => FileSelectorBrowserPanel::DEFAULT_PANEL_ROWS - 1,
      'panelColumns' => FileSelectorBrowserPanel::DEFAULT_PANEL_COLUMNS,
    ]);
    $this->setValue($value ?? ($this->multiple ? [] : ''));
  }

  public function setOptions(array $options): static {
    parent::setOptions($options);
    if (array_key_exists('path', $options)) {
      $this->path = $this->normalizePath((string)$options['path']);
    }
    if (array_key_exists('multiple', $options)) {
      $this->multiple = (bool)$options['multiple'];
    }
    if (array_key_exists('showHidden', $options)) {
      $this->showHidden = (bool)$options['showHidden'];
    }
    if (array_key_exists('createDirectory', $options)) {
      $this->createDirectory = (bool)$options['createDirectory'];
    } else if (array_key_exists('allowCreateDirectory', $options)) {
      $this->createDirectory = (bool)$options['allowCreateDirectory'];
    }
    if (array_key_exists('createFile', $options)) {
      $this->createFile = (bool)$options['createFile'];
    } else if (array_key_exists('allowCreateFile', $options)) {
      $this->createFile = (bool)$options['allowCreateFile'];
    }
    if (array_key_exists('extensions', $options)) {
      $this->extensions = $this->normalizeExtensions((array)$options['extensions']);
    }
    return $this;
  }

  public function setValue(mixed $value): static {
    if ($this->multiple) {
      $value = is_array($value) ? array_values(array_map('strval', $value)) : ((string)$value === '' ? [] : [(string)$value]);
    } else {
      $value = is_array($value) ? (string)($value[0] ?? '') : (string)$value;
    }
    return parent::setValue($value);
  }

  public function getValue(): mixed {
    return parent::getValue();
  }

  public function text(): string {
    $value = $this->getValue();
    if (is_array($value)) {
      if ($value === []) {
        return $this->placeholder;
      }
      return $this->formatSelectedPaths($value);
    }
    return $value === '' ? $this->placeholder : (string)$value;
  }

  protected function openPanel(): bool {
    $layer = $this->findDialogLayer($this->root());
    if ($layer === null) {
      return false;
    }
    $panelOptions = [
      'title' => $this->title,
      'size' => $this->panelSize,
    ];
    if ($this->panelColumns !== null) {
      $panelOptions['contentColumns'] = $this->panelColumns;
    }
    $panel = new DialogPanel($this->name === '' ? 'file-selector-panel' : $this->name . '-file-selector-panel', $panelOptions);
    $value = $this->browserValue();
    $browser = new FileSelectorBrowserPanel($this->name === '' ? 'file-selector-browser' : $this->name . '-file-selector-browser', $this->browserPath($value), $value, [
      'extensions' => $this->extensions,
      'multiple' => $this->multiple,
      'showHidden' => $this->showHidden,
    ]);
    $panel->addContent($browser, $this->panelRows() + 1);
    $ok = new Button($panel->name() . '-ok', 'OK');
    $ok->setOnPress(function() use ($layer, $panel, $browser): void {
      $selected = $browser->getValue();
      $this->setValue($this->multiple ? $selected : ($selected[0] ?? ''));
      $layer->pop($panel);
      $this->requestFocus();
    });
    $cancel = new Button($panel->name() . '-cancel', 'Cancel');
    $cancel->setOnPress(function() use ($layer, $panel): void {
      $layer->pop($panel);
      $this->requestFocus();
    });
    $panel->addButton($ok);
    if ($this->createDirectory) {
      $createDirectory = new Button($panel->name() . '-create-directory', 'Create Dir');
      $createDirectory->setOnPress(function() use ($layer, $browser): void {
        $this->openCreatePanel($layer, $browser, 'directory');
      });
      $panel->addButton($createDirectory);
    }
    if ($this->createFile) {
      $createFile = new Button($panel->name() . '-create-file', 'Create File');
      $createFile->setOnPress(function() use ($layer, $browser): void {
        $this->openCreatePanel($layer, $browser, 'file');
      });
      $panel->addButton($createFile);
    }
    $panel->addButton($cancel);
    $layer->push($panel);
    return true;
  }

  protected function openCreatePanel(DialogLayer $layer, FileSelectorBrowserPanel $browser, string $type): void {
    $title = $type === 'directory' ? 'Create Directory' : 'Create File';
    $panel = new DialogPanel(($this->name === '' ? 'file-selector' : $this->name) . '-create-' . $type, [
      'title' => $title,
      'size' => 'small',
    ]);
    $input = new Input($panel->name() . '-name');
    $panel->addContent(new Label($panel->name() . '-label', 'Name:'));
    $panel->addContent($input);
    $create = new Button($panel->name() . '-create', 'Create');
    $create->setOnPress(function() use ($layer, $panel, $browser, $input, $type): void {
      $target = $this->createTarget($browser->path(), $input->getValue());
      if ($target === null) {
        return;
      }
      if ($type === 'directory') {
        if (!is_dir($target)) {
          @mkdir($target);
        }
        if (is_dir($target)) {
          $browser->setPath($target);
        }
      } else {
        if (!file_exists($target)) {
          @touch($target);
        }
        $browser->refresh();
        if (is_file($target)) {
          $browser->selectFiles([$target]);
        }
      }
      $layer->pop($panel);
      $browser->fileBrowser()->requestFocus();
    });
    $cancel = new Button($panel->name() . '-cancel', 'Cancel');
    $cancel->setOnPress(function() use ($layer, $panel, $browser): void {
      $layer->pop($panel);
      $browser->fileBrowser()->requestFocus();
    });
    $panel->addButton($create)->addButton($cancel);
    $layer->push($panel);
  }

  protected function createTarget(string $path, string $name): ?string {
    $name = trim($name);
    if ($name === '' || $name === '.' || $name === '..' || str_contains($name, '/') || str_contains($name, '\\')) {
      return null;
    }
    return rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
  }

  protected function browserValue(): array {
    $value = $this->getValue();
    return is_array($value) ? $value : ((string)$value === '' ? [] : [(string)$value]);
  }

  protected function browserPath(array $value): string {
    foreach ($value as $path) {
      $path = (string)$path;
      if ($path === '') {
        continue;
      }
      if (is_dir($path)) {
        return $this->normalizePath($path);
      }
      $directory = dirname($path);
      if ($directory !== '' && is_dir($directory)) {
        return $this->normalizePath($directory);
      }
    }
    return $this->path;
  }

  protected function syncPreferredColumns(): void {
    $this->setPreferredColumns(max(12, mb_strlen($this->text()) + 3));
  }

  protected function formatSelectedPaths(array $paths): string {
    $paths = array_values(array_filter(array_map('strval', $paths), fn(string $path): bool => $path !== ''));
    $count = count($paths);
    if ($count === 0) {
      return $this->placeholder;
    }
    if ($count === 1) {
      return $paths[0];
    }
    $directory = dirname($paths[0]);
    foreach ($paths as $path) {
      if (dirname($path) !== $directory) {
        return '...' . DIRECTORY_SEPARATOR . '[' . $count . ' files]';
      }
    }
    return $directory . DIRECTORY_SEPARATOR . '[' . $count . ' files]';
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

  protected function normalizePath(string $path): string {
    $real = realpath($path);
    return $real !== false && is_dir($real) ? $real : getcwd();
  }

}
