<?php

namespace SPTK\Elements;

use \SPTK\Element;

class ListBoxRow {

  private static int $nextId = 1;

  protected ListBox $list;
  protected int $id;
  protected string|false $name = false;
  protected mixed $value = false;
  protected string $text = '';
  protected string $left = '';
  protected int $leftReserve = 0;
  protected string $prefix = '';
  protected string $prefixSeparator = ' ';
  protected string $right = '';
  protected int $rightReserve = 0;
  protected string $truncateMarker = '';
  protected array|false $columns = false;
  protected array $classes = [];
  protected bool|string $selectable = false;
  protected bool $selected = false;
  protected bool $filterable = false;
  protected bool $display = true;
  protected bool $matched = false;
  protected int|false $matchLength = false;
  protected ?Element $source = null;

  public function __construct(ListBox $list, array|string $data = [], ?Element $source = null) {
    $this->list = $list;
    $this->id = self::$nextId++;
    $this->source = $source;
    if (is_string($data)) {
      $this->setValue($data);
    } else {
      $this->apply($data);
    }
  }

  public function apply(array $data): void {
    foreach ($data as $key => $value) {
      switch ($key) {
        case 'name': $this->setName($value); break;
        case 'value': $this->setValue($value); break;
        case 'text': $this->setText($value); break;
        case 'left': $this->setLeft($value); break;
        case 'leftReserve': $this->setLeftReserve($value); break;
        case 'prefix': $this->setPrefix($value); break;
        case 'prefixSeparator': $this->setPrefixSeparator($value); break;
        case 'right': $this->setRight($value); break;
        case 'rightReserve': $this->setRightReserve($value); break;
        case 'truncateMarker': $this->setTruncateMarker($value); break;
        case 'columns': $this->setColumns($value); break;
        case 'class':
        case 'classes':
          foreach (is_array($value) ? $value : preg_split('/ +/', (string)$value) as $class) {
            if ($class !== '') {
              $this->addClass($class);
            }
          }
          break;
        case 'selectable': $this->setSelectable($value); break;
        case 'selected': $this->setSelected($value); break;
        case 'filterable': $this->setFilterable($value); break;
      }
    }
  }

  public function getId(): int {
    return $this->id;
  }

  public function getName(): string|false {
    if ($this->name !== false) {
      return $this->name;
    }
    return $this->source === null ? false : $this->source->getName();
  }

  public function setName($name): void {
    $this->name = $name === false ? false : (string)$name;
  }

  public function getValue(): mixed {
    if ($this->value === false || $this->value === '') {
      return $this->text;
    }
    return $this->value;
  }

  public function setValue($value): void {
    $this->value = $value;
    $this->text = (string)$value;
    $this->list->rowChanged();
  }

  public function getText(?array &$text = null): string {
    return $this->text;
  }

  public function setText($text): void {
    $this->text = (string)$text;
    $this->list->rowChanged();
  }

  public function setLeft($value): void {
    $this->left = $value === false ? '' : (string)$value;
    $this->list->rowChanged();
  }

  public function getLeft(): string {
    return $this->left;
  }

  public function setLeftReserve($value): void {
    $this->leftReserve = max(0, (int)$value);
    $this->list->rowChanged();
  }

  public function getLeftReserve(): int {
    return $this->leftReserve;
  }

  public function setPrefix($value): void {
    $this->prefix = $value === false ? '' : (string)$value;
    $this->list->rowChanged();
  }

  public function getPrefix(): string {
    return $this->prefix;
  }

  public function setPrefixSeparator($value): void {
    $this->prefixSeparator = $value === false ? '' : (string)$value;
    $this->list->rowChanged();
  }

  public function getPrefixSeparator(): string {
    return $this->prefixSeparator;
  }

  public function setRight($value): void {
    $this->right = $value === false ? '' : (string)$value;
    $this->list->rowChanged();
  }

  public function getRight(): string {
    return $this->right;
  }

  public function setRightReserve($value): void {
    $this->rightReserve = max(0, (int)$value);
    $this->list->rowChanged();
  }

  public function getRightReserve(): int {
    return $this->rightReserve;
  }

  public function setTruncateMarker($value): void {
    $this->truncateMarker = $value === false ? '' : (string)$value;
    $this->list->rowChanged();
  }

  public function getTruncateMarker(): string {
    return $this->truncateMarker;
  }

  public function setColumns($value): void {
    if ($value === false || $value === null) {
      $this->columns = false;
    } else {
      $this->columns = array_map('strval', is_array($value) ? $value : preg_split('/\s*,\s*/', (string)$value));
      if ($this->text === '') {
        $this->text = implode(' ', $this->columns);
      }
    }
    $this->list->rowChanged();
  }

  public function getColumns(): array|false {
    return $this->columns;
  }

  public function addClass(string $class): void {
    if (!in_array($class, $this->classes, true)) {
      $this->classes[] = $class;
      $this->list->rowChanged();
    }
  }

  public function removeClass(string $class): void {
    $key = array_search($class, $this->classes, true);
    if ($key !== false) {
      unset($this->classes[$key]);
      $this->classes = array_values($this->classes);
      $this->list->rowChanged();
    }
  }

  public function getClass(): array {
    return $this->classes;
  }

  public function addVariant(string $class): void {
    $this->addClass($this->getType() . ':' . $class);
  }

  public function removeVariant(string $class): void {
    $this->removeClass($this->getType() . ':' . $class);
  }

  public function hasVariant(string $class): bool {
    return in_array($this->getType() . ':' . $class, $this->classes, true);
  }

  public function setSelectable($value): void {
    if ($value === true || $value === 'true') {
      $this->selectable = true;
    } else if ($value === false || $value === 'false') {
      $this->selectable = false;
    } else {
      $this->selectable = (string)$value;
    }
  }

  public function isSelectable(): bool|string {
    return $this->selectable;
  }

  public function setSelected($value): void {
    if ($value === true || $value === 'true') {
      $this->select(false);
    }
  }

  public function isSelected(): bool {
    return $this->selected;
  }

  public function select($marker = false): void {
    if ($this->selected && $this->selectable === true) {
      $this->deselect();
      return;
    }
    $this->selected = true;
    $this->addVariant('selected');
    if ($marker !== false) {
      $this->left = (string)$marker;
    } else if ($this->selectable === true) {
      $this->left = 'X';
    } else {
      $this->left = '*';
    }
    $this->list->rowChanged();
  }

  public function deselect(): void {
    $this->selected = false;
    $this->left = '';
    $this->removeVariant('selected');
    $this->list->rowChanged();
  }

  public function markSelected($marker = false): void {
    if ($this->selected) {
      $this->left = (string)$marker;
      $this->list->rowChanged();
    }
  }

  public function setFilterable($value): void {
    $this->filterable = ($value === true || $value === 'true');
  }

  public function isFilterable(): bool {
    return $this->filterable;
  }

  public function match($search): bool {
    if ($this->filterable === false) {
      return false;
    }
    if ($this->text !== '' && $search !== false) {
      $pos = strpos($this->text, (string)$search);
      if ($pos === 0) {
        $this->matched = true;
        $this->matchLength = mb_strlen((string)$search);
        $this->list->rowChanged();
        return true;
      }
    }
    if ($this->matched) {
      $this->matched = false;
      $this->matchLength = false;
      $this->list->rowChanged();
    }
    return false;
  }

  public function isMatched(): bool {
    return $this->matched;
  }

  public function getMatchLength(): int|false {
    return $this->matchLength;
  }

  public function show(): void {
    $this->display = true;
    $this->list->rowChanged();
  }

  public function hide(): void {
    $this->display = false;
    $this->list->rowChanged();
  }

  public function isDisplayed(): bool {
    return $this->display;
  }

  public function getType(): string {
    return 'ListItem';
  }

  public function remove(): void {
    $this->list->removeItem($this);
  }

  public function moveAfter(ListBoxRow $row): void {
    $this->list->moveItemAfter($this, $row);
  }

  public function findAncestorByType(string $type): Element|false {
    return $this->list->findAncestorByType($type);
  }

}
