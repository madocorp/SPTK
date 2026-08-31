<?php

namespace SPTK\Widgets;

use SPTK\Core\Element;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;

/**
 * Read-only directory path field that opens a DirectoryBrowser panel.
 */
class DirectorySelector extends Selector {

  protected string $path;
  protected bool $showHidden = false;

  public function __construct(string $name = '', string $path = '.', array $options = []) {
    $this->path = $this->normalizePath($path);
    parent::__construct($name, [], $this->path, $options + [
      'title' => 'Select Directory',
      'panelSize' => 'big',
      'panelRows' => 12,
    ]);
  }

  public function setValue(mixed $value): static {
    $path = $this->normalizePath((string)$value);
    $this->path = $path;
    return parent::setValue($path);
  }

  public function setOptions(array $options): static {
    parent::setOptions($options);
    if (array_key_exists('showHidden', $options)) {
      $this->showHidden = (bool)$options['showHidden'];
    }
    return $this;
  }

  public function getValue(): string {
    return (string)parent::getValue();
  }

  public function text(): string {
    return $this->getValue() === '' ? $this->placeholder : $this->getValue();
  }

  protected function openPanel(): bool {
    $layer = $this->findDialogLayer($this->root());
    if ($layer === null) {
      return false;
    }
    $panel = new DialogPanel($this->name === '' ? 'directory-selector-panel' : $this->name . '-directory-selector-panel', [
      'title' => $this->title,
      'size' => $this->panelSize,
    ]);
    $browser = new DirectorySelectorBrowserPanel($this->name === '' ? 'directory-selector-browser' : $this->name . '-directory-selector-browser', $this->getValue(), [
      'showHidden' => $this->showHidden(),
    ]);
    $panel->addContent($browser, $this->panelRows() + 1);
    $ok = new Button($panel->name() . '-ok', 'OK');
    $ok->setOnPress(function() use ($layer, $panel, $browser): void {
      $this->setValue($browser->getValue());
      $layer->pop($panel);
      $this->requestFocus();
    });
    $cancel = new Button($panel->name() . '-cancel', 'Cancel');
    $cancel->setOnPress(function() use ($layer, $panel): void {
      $layer->pop($panel);
      $this->requestFocus();
    });
    $panel->addButton($ok)->addButton($cancel);
    $layer->push($panel);
    return true;
  }

  protected function syncPreferredColumns(): void {
    $this->setPreferredColumns(max(12, mb_strlen($this->text()) + 3));
  }

  protected function showHidden(): bool {
    return $this->showHidden;
  }

  protected function normalizePath(string $path): string {
    $real = realpath($path);
    return $real !== false && is_dir($real) ? $real : getcwd();
  }

}

/**
 * Directory selector dialog body with a path header and browser list.
 */
class DirectorySelectorBrowserPanel extends Element {

  protected Label $pathLabel;
  protected TextBlock $pathText;
  protected DirectoryBrowser $browser;

  public function __construct(string $name = '', string $path = '.', array $options = []) {
    parent::__construct($name);
    $path = $this->normalizePath($path);
    $this->setPreferredRows(13);
    $this->pathLabel = new Label($name === '' ? 'directory-selector-path-label' : $name . '-path-label', 'Path:');
    $this->pathText = new TextBlock($name === '' ? 'directory-selector-path-text' : $name . '-path-text', $path);
    $this->browser = new DirectoryBrowser($name === '' ? 'directory-selector-directories' : $name . '-directories', $path, [
      'showHidden' => (bool)($options['showHidden'] ?? false),
      'onPathChange' => function(DirectoryBrowser $browser): void {
        $this->pathText->setText($browser->path());
        $this->invalidateRender();
      },
    ]);
    $this->add($this->pathLabel);
    $this->add($this->pathText);
    $this->add($this->browser);
  }

  public function getValue(): string {
    return $this->browser->getValue();
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
    $this->browser->setFrame(new Rect($this->frame->x, $this->frame->y + $headerHeight, $this->frame->width, max(0, $this->frame->height - $headerHeight)));
  }

  protected function paint(RenderTarget $target): void {
    $target->fill($this->frame, ' ', $this->theme->fg, $this->theme->bg);
  }

  protected function normalizePath(string $path): string {
    $real = realpath($path);
    return $real !== false && is_dir($real) ? $real : getcwd();
  }

}
