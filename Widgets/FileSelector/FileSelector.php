<?php

namespace SPTK\Widgets\FileSelector;

use SPTK\Core\{Color, Style, Widget};
use SPTK\Events\{KeyNormalizer, WidgetEventEmitter};
use SPTK\Rendering\{GridWriter, TextMetrics};
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\List\ListView;

/** Browses a directory in a list with a fixed path header. */
final class FileSelector extends Widget {

  use WidgetEventEmitter;

  private ListView $list;
  private string $path = '';
  private ?string $error = null;
  private array $directories = [];

  /** Build a filesystem-backed list with inherited colors and search settings. */
  public function __construct(string $path = '.', private readonly bool $multiple = false, bool $filterable = true, bool $searchable = true, private readonly Style $style = new Style()) {
    $this->list = new ListView([], $multiple, $filterable, $searchable, false, $style);
    $this->list->on('change', $this->listChanged(...));
    $this->on('activate', $this->activate(...));
    $this->on('deactivate', $this->deactivate(...));
    $this->setPath($path);
  }

  /** Return the resolved directory currently displayed. */
  public function path(): string {
    return $this->path;
  }

  /** Return the last failed navigation message, if any. */
  public function error(): ?string {
    return $this->error;
  }

  /** Read a directory atomically and reset the list to its new entries. */
  public function setPath(string $path): void {
    if ($path !== '' && $path[0] !== '/' && $this->path !== '') {
      $path = $this->path . '/' . $path;
    }
    [$resolved, $items, $directories] = DirectoryListing::read($path);
    $previous = $this->path;
    $this->list->setItems($items);
    $this->directories = $directories;
    $this->path = $resolved;
    $this->error = null;
    if (!$this->multiple && $previous !== '' && dirname($previous) === $resolved && in_array($previous, $this->values(), true)) {
      $this->setValue($previous);
    }
  }

  /** Refresh the current directory listing. */
  public function reload(): void {
    $this->setPath($this->path);
  }

  /** Return current records with full filesystem paths as values. */
  public function items(): array {
    $items = $this->list->items();
    foreach ($items as &$item) {
      $item['value'] = hex2bin($item['value']);
      unset($item['searchOffset']);
    }
    unset($item);
    return $items;
  }

  /** Return full paths in display order. */
  public function values(): array {
    return array_map(hex2bin(...), $this->list->values());
  }

  /** Return the path under the cursor, if any. */
  public function activeValue(): ?string {
    $value = $this->list->activeValue();
    return $value === null ? null : hex2bin($value);
  }

  /** Return one active path or the selected paths in multiple mode. */
  public function getValue(): string|array|null {
    $value = $this->list->getValue();
    if ($value === null) {
      return null;
    }
    return is_array($value) ? array_map(hex2bin(...), $value) : hex2bin($value);
  }

  /** Select known full paths without emitting a user change event. */
  public function setValue(string|array $value): void {
    if (is_array($value)) {
      foreach ($value as $path) {
        if (!is_string($path)) {
          throw new \InvalidArgumentException('FileSelector values must be path strings.');
        }
      }
    }
    $ids = is_array($value) ? array_map(bin2hex(...), $value) : bin2hex($value);
    $this->list->setValue($ids);
  }

  /** Return the current list cursor index. */
  public function cursorPosition(): int {
    return $this->list->cursorPosition();
  }

  /** Report whether this selector receives keyboard input. */
  public function active(): bool {
    return $this->list->active();
  }

  /** Return the active prefix query. */
  public function filter(): string {
    return $this->list->filter();
  }

  /** Apply a prefix query without emitting a user event. */
  public function setFilter(string $query): void {
    $this->list->setFilter($query);
  }

  /** Use the inherited list background. */
  public function background(): Color {
    return $this->style->background;
  }

  /** Reserve enough width for the path header and item labels. */
  public function preferredWidth(): ?int {
    return max($this->list->preferredWidth(), TextMetrics::width($this->displayPath()));
  }

  /** Reserve a fixed path row above the list's natural height. */
  public function preferredHeight(): ?int {
    return $this->list->preferredHeight() + 1;
  }

  /** Paint the current path above a separately scrolling list body. */
  public function paint(GridWriter $writer): void {
    $writer->fill($this->style->foreground, $this->style->background);
    if ($writer->height() < 1) {
      return;
    }
    $writer->write(0, 0, $this->displayPath(), $this->style->highlight, $this->style->background);
    $this->list->paint($writer->below(1));
  }

  /** Open directories on Return and delegate other keys to the list. */
  public function handleInput(mixed $event): bool {
    if ($event->type === SDL::SDL_EVENT_KEY_DOWN) {
      $key = KeyNormalizer::normalize((int)$event->key->key, (int)$event->key->mod);
      if ($key === SDL::KEY_RETURN) {
        $value = $this->list->activeValue();
        if ($value !== null && hex2bin($value) !== $this->path && isset($this->directories[$value])) {
          try {
            $this->setPath(hex2bin($value));
          } catch (\RuntimeException $error) {
            $this->error = $error->getMessage();
            return true;
          }
          $this->emit('change');
          return true;
        }
        return false;
      }
    }
    return $this->list->handleInput($event);
  }

  /** Treat Escape as accepting the current file like the list widget. */
  public function releaseNotification(int $key, int $modifiers): ?string {
    return $key === SDL::KEY_ESCAPE ? 'accept' : parent::releaseNotification($key, $modifiers);
  }

  /** Return a printable path header with a short navigation error if needed. */
  private function displayPath(): string {
    $path = preg_replace('/[\p{Cc}]/u', ' ', mb_convert_encoding($this->path, 'UTF-8', 'UTF-8'));
    return $this->error === null ? $path : $path . ' — Cannot read directory';
  }

  /** Forward list value changes to FileSelector subscribers. */
  private function listChanged(): void {
    $this->emit('change');
  }

  /** Activate the composed list after tile activation. */
  private function activate(): void {
    $this->list->emit('activate');
  }

  /** Release the list and clear its transient search query. */
  private function deactivate(): void {
    $this->list->emit('deactivate');
  }

}
