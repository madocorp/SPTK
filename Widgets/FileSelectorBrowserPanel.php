<?php

namespace SPTK\Widgets;

use SPTK\Core\Element;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;

/**
 * File selector dialog body: path header, directory pane, and file pane.
 */
class FileSelectorBrowserPanel extends Element {

  public const DEFAULT_PANEL_COLUMNS = 72;
  public const DEFAULT_PANEL_ROWS = 21;

  protected Label $pathLabel;
  protected TextBlock $pathText;
  protected DirectoryBrowser $directories;
  protected FileBrowser $files;

  public function __construct(string $name = '', string $path = '.', array $value = [], array $options = []) {
    parent::__construct($name);
    $path = $this->normalizePath($path);
    $this->setPreferredRows(self::DEFAULT_PANEL_ROWS);
    $this->pathLabel = new Label($name === '' ? 'file-selector-path-label' : $name . '-path-label', 'Path:');
    $this->pathText = new TextBlock($name === '' ? 'file-selector-path-text' : $name . '-path-text', $this->pathText($path, $value));
    $this->directories = new DirectoryBrowser($name === '' ? 'file-selector-directories' : $name . '-directories', $path, [
      'showHidden' => (bool)($options['showHidden'] ?? false),
      'onPathChange' => function(DirectoryBrowser $browser): void {
        $this->setPathFromSource($browser->path(), 'directories');
      },
    ]);
    $this->files = new FileBrowser($name === '' ? 'file-selector-files' : $name . '-files', $path, [
      'extensions' => $options['extensions'] ?? [],
      'multiple' => (bool)($options['multiple'] ?? false),
      'showHidden' => (bool)($options['showHidden'] ?? false),
      'onSelect' => function(): void {
        $this->refreshPathText();
      },
      'onPathChange' => function(FileBrowser $browser): void {
        $this->setPathFromSource($browser->path(), 'files');
      },
    ]);
    $this->files->setValue($value);
    $this->add($this->pathLabel);
    $this->add($this->pathText);
    $this->add($this->directories);
    $this->add($this->files);
  }

  public function getValue(): array {
    return $this->files->getValue();
  }

  public function path(): string {
    return $this->files->path();
  }

  public function setPath(string $path): static {
    $this->setPathFromSource($path, 'external');
    return $this;
  }

  public function refresh(): static {
    $this->directories->refresh();
    $this->files->refresh();
    $this->refreshPathText();
    return $this;
  }

  public function selectFiles(array $files): static {
    $this->files->setValue($files);
    $this->refreshPathText();
    return $this;
  }

  public function fileBrowser(): FileBrowser {
    return $this->files;
  }

  public function setFrame(Rect $frame): void {
    $this->frame = $frame;
    $this->layout();
  }

  public function preferredRowsForColumns(int $columns): int {
    return $this->preferredRows();
  }

  public function layout(): void {
    $headerHeight = min(1, $this->frame->height);
    $labelWidth = min(6, $this->frame->width);
    $this->pathLabel->setFrame(new Rect($this->frame->x, $this->frame->y, $labelWidth, $headerHeight));
    $this->pathText->setFrame(new Rect($this->frame->x + $labelWidth, $this->frame->y, max(0, $this->frame->width - $labelWidth), $headerHeight));
    $bodyY = $this->frame->y + $headerHeight;
    $bodyHeight = max(0, $this->frame->height - $headerHeight);
    $gap = $this->frame->width >= 3 ? 1 : 0;
    $leftWidth = intdiv(max(0, $this->frame->width - $gap), 2);
    $rightWidth = max(0, $this->frame->width - $leftWidth - $gap);
    $this->directories->setFrame(new Rect($this->frame->x, $bodyY, $leftWidth, $bodyHeight));
    $this->files->setFrame(new Rect($this->frame->x + $leftWidth + $gap, $bodyY, $rightWidth, $bodyHeight));
  }

  protected function setPathFromSource(string $path, string $source): void {
    $path = $this->normalizePath($path);
    if ($source !== 'directories') {
      $this->directories->setPath($path);
    }
    if ($source !== 'files') {
      $this->files->setPath($path);
    }
    $this->refreshPathText();
    $this->invalidateRender();
  }

  protected function refreshPathText(): void {
    $this->pathText->setText($this->pathText($this->files->path(), $this->files->getValue()));
  }

  protected function paint(RenderTarget $target): void {
    $target->fill($this->frame, ' ', $this->theme->fg, $this->theme->bg);
  }

  protected function pathText(string $path, array $selected): string {
    $selected = array_values(array_filter(array_map('strval', $selected), fn(string $file): bool => $file !== ''));
    $count = count($selected);
    if ($count === 0) {
      return $path;
    }
    if ($count === 1) {
      return $selected[0];
    }
    return $path . DIRECTORY_SEPARATOR . '[' . $count . ' files]';
  }

  protected function normalizePath(string $path): string {
    $real = realpath($path);
    return $real !== false && is_dir($real) ? $real : getcwd();
  }

}
