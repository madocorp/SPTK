<?php

namespace SPTK\Widgets;

/**
 * Defines one display row for ListView.
 */
class ListItem {

  protected string|false $name = false;
  protected mixed $value = null;
  protected string $text = '';
  protected string $left = '';
  protected int $leftReserve = 0;
  protected string $prefix = '';
  protected string $prefixSeparator = ' ';
  protected string $right = '';
  protected int $rightReserve = 0;
  protected string $rightAlign = 'right';
  protected string $truncateMarker = '';
  protected bool|string $selectable = false;
  protected bool $selected = false;
  protected bool $filterable = false;
  protected bool $enabled = true;
  protected string $selectedMarker = '';

  public function __construct(array|string $config = []) {
    if (is_string($config)) {
      $config = ['value' => $config];
    }
    $this->apply($config);
  }

  public function apply(array $config): void {
    foreach ($config as $key => $value) {
      if ($key === 'name') {
        $this->setName($value);
      } else if ($key === 'value') {
        $this->setValue($value);
      } else if ($key === 'text' || $key === 'label') {
        $this->setText($value);
      } else if ($key === 'left') {
        $this->setLeft($value);
      } else if ($key === 'leftReserve') {
        $this->setLeftReserve($value);
      } else if ($key === 'prefix') {
        $this->setPrefix($value);
      } else if ($key === 'prefixSeparator') {
        $this->setPrefixSeparator($value);
      } else if ($key === 'right') {
        $this->setRight($value);
      } else if ($key === 'rightReserve') {
        $this->setRightReserve($value);
      } else if ($key === 'rightAlign') {
        $this->setRightAlign($value);
      } else if ($key === 'truncateMarker') {
        $this->setTruncateMarker($value);
      } else if ($key === 'selectable') {
        $this->setSelectable($value);
      } else if ($key === 'selected') {
        $this->setSelected($value);
      } else if ($key === 'filterable') {
        $this->setFilterable($value);
      } else if ($key === 'enabled') {
        $this->setEnabled($value);
      }
    }
  }

  public function name(): string|false {
    return $this->name ?? false;
  }

  public function setName(mixed $name): void {
    $this->name = $name === false || $name === null ? false : (string)$name;
  }

  public function value(): mixed {
    return $this->value === null || $this->value === '' ? $this->text : $this->value;
  }

  public function setValue(mixed $value): void {
    $this->value = $value;
    if ($this->text === '') {
      $this->text = (string)$value;
    }
  }

  public function text(): string {
    return $this->text;
  }

  public function setText(mixed $text): void {
    $this->text = (string)$text;
  }

  public function left(): string {
    return $this->selectedMarker !== '' ? $this->selectedMarker : $this->left;
  }

  public function setLeft(mixed $left): void {
    $this->left = $left === false || $left === null ? '' : (string)$left;
  }

  public function leftReserve(): int {
    return $this->leftReserve;
  }

  public function setLeftReserve(mixed $leftReserve): void {
    $this->leftReserve = max(0, (int)$leftReserve);
  }

  public function prefix(): string {
    return $this->prefix;
  }

  public function setPrefix(mixed $prefix): void {
    $this->prefix = $prefix === false || $prefix === null ? '' : (string)$prefix;
  }

  public function prefixSeparator(): string {
    return $this->prefixSeparator;
  }

  public function setPrefixSeparator(mixed $prefixSeparator): void {
    $this->prefixSeparator = $prefixSeparator === false || $prefixSeparator === null ? '' : (string)$prefixSeparator;
  }

  public function right(): string {
    return $this->right;
  }

  public function setRight(mixed $right): void {
    $this->right = $right === false || $right === null ? '' : (string)$right;
  }

  public function rightReserve(): int {
    return $this->rightReserve;
  }

  public function setRightReserve(mixed $rightReserve): void {
    $this->rightReserve = max(0, (int)$rightReserve);
  }

  public function rightAlign(): string {
    return $this->rightAlign;
  }

  public function setRightAlign(mixed $rightAlign): void {
    $this->rightAlign = $rightAlign === 'left' || $rightAlign === false || $rightAlign === 'false' ? 'left' : 'right';
  }

  public function truncateMarker(): string {
    return $this->truncateMarker;
  }

  public function setTruncateMarker(mixed $truncateMarker): void {
    $this->truncateMarker = $truncateMarker === false || $truncateMarker === null ? '' : (string)$truncateMarker;
  }

  public function selectable(): bool|string {
    return $this->selectable;
  }

  public function setSelectable(mixed $selectable): void {
    if ($selectable === true || $selectable === 'true') {
      $this->selectable = true;
    } else if ($selectable === false || $selectable === 'false' || $selectable === null) {
      $this->selectable = false;
    } else {
      $this->selectable = (string)$selectable;
    }
  }

  public function selected(): bool {
    return $this->selected;
  }

  public function setSelected(mixed $selected): void {
    if ($selected === true || $selected === 'true') {
      $this->select();
    } else if ($selected === false || $selected === 'false') {
      $this->deselect();
    }
  }

  public function select(string|int|false $marker = false): void {
    $this->selected = true;
    if ($marker !== false) {
      $this->selectedMarker = (string)$marker;
    } else if ($this->selectable === true) {
      $this->selectedMarker = 'X';
    } else {
      $this->selectedMarker = '*';
    }
  }

  public function deselect(): void {
    $this->selected = false;
    $this->selectedMarker = '';
  }

  public function markSelected(string|int|false $marker = false): void {
    if ($this->selected) {
      $this->selectedMarker = $marker === false ? '' : (string)$marker;
    }
  }

  public function filterable(): bool {
    return $this->filterable;
  }

  public function setFilterable(mixed $filterable): void {
    $this->filterable = $filterable === true || $filterable === 'true';
  }

  public function enabled(): bool {
    return $this->enabled;
  }

  public function setEnabled(mixed $enabled): void {
    $this->enabled = !($enabled === false || $enabled === 'false');
  }

}
