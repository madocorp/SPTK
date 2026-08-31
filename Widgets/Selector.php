<?php

namespace SPTK\Widgets;

use SPTK\Core\Element;
use SPTK\Core\InputAction;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;

/**
 * Read-only input-like selector that opens a list dialog for choosing a value.
 */
class Selector extends Element {

  protected array $items = [];
  protected mixed $value = null;
  protected string $placeholder = '';
  protected string $title = 'Select Value';
  protected string $panelSize = 'small';
  protected int $panelRows = 0;
  protected ?int $panelColumns = null;
  protected $onChange = null;

  public function __construct(string $name = '', array $items = [], mixed $value = null, array $options = []) {
    parent::__construct($name);
    $this->focusable = true;
    $this->setPreferredRows(1);
    $this->setOptions($options);
    $this->setItems($items);
    if ($value !== null) {
      $this->setValue($value);
    }
  }

  public function setOptions(array $options): static {
    if (array_key_exists('placeholder', $options)) {
      $this->placeholder = (string)$options['placeholder'];
    }
    if (array_key_exists('title', $options)) {
      $this->title = (string)$options['title'];
    }
    if (array_key_exists('panelSize', $options)) {
      $this->panelSize = (string)$options['panelSize'];
    }
    if (array_key_exists('panelRows', $options)) {
      $this->panelRows = max(0, (int)$options['panelRows']);
    }
    if (array_key_exists('panelColumns', $options)) {
      $this->panelColumns = max(1, (int)$options['panelColumns']);
    } else if (array_key_exists('contentColumns', $options)) {
      $this->panelColumns = max(1, (int)$options['contentColumns']);
    }
    if (array_key_exists('onChange', $options) && is_callable($options['onChange'])) {
      $this->onChange = $options['onChange'];
    }
    return $this;
  }

  public function setItems(array $items): static {
    $this->items = [];
    foreach ($items as $key => $item) {
      $this->items[] = $this->normalizeItem($key, $item);
    }
    if ($this->items !== [] && $this->selectedIndex() === null) {
      $this->value = $this->items[0]['value'];
    }
    $this->syncPreferredColumns();
    $this->invalidateRender();
    return $this;
  }

  public function setValue(mixed $value): static {
    $old = $this->value;
    $this->value = $value;
    $this->syncPreferredColumns();
    if ($old !== $this->value) {
      $this->invalidateRender();
      if ($this->onChange !== null) {
        ($this->onChange)($this);
      }
    }
    return $this;
  }

  public function getValue(): mixed {
    return $this->value;
  }

  public function text(): string {
    $index = $this->selectedIndex();
    return $index === null ? $this->placeholder : $this->items[$index]['label'];
  }

  public function handle(InputEvent $event): bool {
    if (!InputAction::activate($event, 'selector')) {
      return false;
    }
    return $this->openPanel();
  }

  protected function paint(RenderTarget $target): void {
    $target->fill($this->frame, ' ', $this->theme->inverseFg, $this->theme->inverseBg);
    if ($this->frame->width <= 0 || $this->frame->height <= 0) {
      return;
    }
    $markerWidth = min(3, $this->frame->width);
    $text = mb_substr($this->text(), 0, max(0, $this->frame->width - $markerWidth));
    if ($text !== '') {
      $target->write($this->frame->x, $this->frame->y, $text, $this->theme->inverseFg, $this->theme->inverseBg);
    }
    if ($markerWidth > 0) {
      $marker = mb_substr('...', 0, $markerWidth);
      $target->write(
        $this->frame->right() - $markerWidth,
        $this->frame->y,
        $marker,
        $this->focused ? $this->theme->cursorFg : $this->theme->markerFg,
        $this->focused ? $this->theme->cursorBg : $this->theme->inverseBg
      );
    }
  }

  protected function openPanel(): bool {
    $layer = $this->findDialogLayer($this->root());
    if ($layer === null || $this->items === []) {
      return false;
    }
    $panelOptions = [
      'title' => $this->title,
      'size' => $this->panelSize,
    ];
    if ($this->panelColumns !== null) {
      $panelOptions['contentColumns'] = $this->panelColumns;
    }
    $panel = new DialogPanel($this->name === '' ? 'selector-panel' : $this->name . '-selector-panel', $panelOptions);
    $list = new ListView($this->name === '' ? 'selector-list' : $this->name . '-selector-list', $this->listItems(), [
      'paddingX' => 1,
      'onSelect' => function(ListItem $item) use ($layer, $panel): void {
        $index = (int)$item->value();
        if (isset($this->items[$index])) {
          $this->setValue($this->items[$index]['value']);
        }
        $layer->pop($panel);
        $this->requestFocus();
      },
    ]);
    $selected = $this->selectedIndex();
    if ($selected !== null) {
      $list->setCursor($selected);
    }
    $panel->addContent($list, $this->panelRows());
    $layer->push($panel);
    return true;
  }

  protected function listItems(): array {
    $rows = [];
    foreach ($this->items as $index => $item) {
      $rows[] = [
        'value' => $index,
        'text' => $item['label'],
        'selectable' => 'selector',
        'selected' => $this->valuesEqual($item['value'], $this->value),
      ];
    }
    return $rows;
  }

  protected function panelRows(): int {
    if ($this->panelRows > 0) {
      return $this->panelRows;
    }
    return max(1, min(8, count($this->items)));
  }

  protected function normalizeItem(int|string $key, mixed $item): array {
    if (is_array($item)) {
      $value = $item['value'] ?? $key;
      $label = (string)($item['text'] ?? $item['label'] ?? $value);
      return ['value' => $value, 'label' => $label];
    }
    if (is_int($key)) {
      return ['value' => $item, 'label' => (string)$item];
    }
    return ['value' => $key, 'label' => (string)$item];
  }

  protected function selectedIndex(): ?int {
    foreach ($this->items as $index => $item) {
      if ($this->valuesEqual($item['value'], $this->value)) {
        return $index;
      }
    }
    return null;
  }

  protected function valuesEqual(mixed $a, mixed $b): bool {
    return $a === $b || (is_scalar($a) && is_scalar($b) && (string)$a === (string)$b);
  }

  protected function syncPreferredColumns(): void {
    $columns = max(8, mb_strlen($this->placeholder));
    foreach ($this->items as $item) {
      $columns = max($columns, mb_strlen($item['label']));
    }
    $this->setPreferredColumns($columns + 3);
  }

  protected function findDialogLayer(Element $element): ?DialogLayer {
    if ($element instanceof DialogLayer) {
      return $element;
    }
    foreach ($element->children() as $child) {
      $layer = $this->findDialogLayer($child);
      if ($layer !== null) {
        return $layer;
      }
    }
    return null;
  }

}
