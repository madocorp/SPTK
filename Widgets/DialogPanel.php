<?php

namespace SPTK\Widgets;

use SPTK\Core\Color;
use SPTK\Core\Clipboard;
use SPTK\Core\Element;
use SPTK\Core\InputAction;
use SPTK\Core\InputEvent;
use SPTK\Core\Place;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;
use SPTK\Core\Theme;

/**
 * Centered modal panel with title, flow content, and optional button row.
 */
class DialogPanel extends Element {

  protected const COLORS = [
    'normal' => [
      'bg' => '#cccccc',
      'inactiveBg' => '#999999',
      'fg' => '#000000',
      'border' => '#226622',
      'activeBorder' => '#338833',
      'titleFg' => '#ffffff',
      'activeTitleFg' => '#ffffff',
    ],
    'warning' => [
      'bg' => '#ffff00',
      'inactiveBg' => '#b3b300',
      'fg' => '#000000',
      'border' => '#aa5500',
      'activeBorder' => '#cc7700',
      'titleFg' => '#ffffff',
      'activeTitleFg' => '#ffffff',
    ],
    'error' => [
      'bg' => '#ffaaaa',
      'inactiveBg' => '#b37777',
      'fg' => '#000000',
      'border' => '#990000',
      'activeBorder' => '#cc0000',
      'titleFg' => '#ffffff',
      'activeTitleFg' => '#ffffff',
    ],
  ];

  protected string $title = '';
  protected string $variant = 'normal';
  protected string $size = 'normal';
  protected ?int $contentColumns = null;
  protected ?int $windowMarginCells = null;
  protected ?int $windowMarginColumns = null;
  protected ?int $windowMarginRows = null;
  protected bool $active = false;
  protected bool $closeable = true;
  protected Flow $content;
  protected ?FlowRow $buttons = null;
  protected $onClose = null;

  public function __construct(string $name = '', array $options = []) {
    parent::__construct($name);
    $this->title = (string)($options['title'] ?? '');
    $this->variant = $this->normalizeVariant((string)($options['variant'] ?? 'normal'));
    $this->size = $this->normalizeSize((string)($options['size'] ?? 'normal'));
    if (array_key_exists('contentColumns', $options)) {
      $this->contentColumns = max(1, (int)$options['contentColumns']);
    }
    if (array_key_exists('windowMarginCells', $options)) {
      $this->windowMarginCells = max(0, (int)$options['windowMarginCells']);
    }
    if (array_key_exists('windowMarginColumns', $options)) {
      $this->windowMarginColumns = max(0, (int)$options['windowMarginColumns']);
    }
    if (array_key_exists('windowMarginRows', $options)) {
      $this->windowMarginRows = max(0, (int)$options['windowMarginRows']);
    }
    $this->closeable = (bool)($options['closeable'] ?? true);
    if (array_key_exists('onClose', $options) && is_callable($options['onClose'])) {
      $this->onClose = $options['onClose'];
    }
    $this->content = new Flow($name === '' ? 'dialog-content' : $name . '-content');
    parent::add($this->content);
    $this->syncChildTheme();
  }

  public function setTheme(Theme $theme): static {
    parent::setTheme($theme);
    $this->syncChildTheme();
    return $this;
  }

  public function focusScope(): string {
    return 'dialog';
  }

  public function setActive(bool $active): static {
    if ($this->active !== $active) {
      $this->invalidateRender();
    }
    $this->active = $active;
    $this->syncChildTheme();
    return $this;
  }

  public function isActive(): bool {
    return $this->active;
  }

  public function setTitle(string $title): static {
    if ($this->title !== $title) {
      $this->invalidateLayout();
    }
    $this->title = $title;
    return $this;
  }

  public function setVariant(string $variant): static {
    $variant = $this->normalizeVariant($variant);
    if ($this->variant !== $variant) {
      $this->invalidateRender();
    }
    $this->variant = $variant;
    $this->syncChildTheme();
    return $this;
  }

  public function setSize(string $size): static {
    $size = $this->normalizeSize($size);
    if ($this->size !== $size) {
      $this->invalidateLayout();
    }
    $this->size = $size;
    return $this;
  }

  public function size(): string {
    return $this->size;
  }

  public function widthRatio(): float {
    return match ($this->size) {
      'big' => 0.9,
      'small' => 0.45,
      'extra-small' => 0.3,
      default => 0.6,
    };
  }

  public function contentColumns(): ?int {
    return $this->contentColumns;
  }

  public function windowMarginCells(): ?int {
    return $this->windowMarginCells;
  }

  public function windowMarginColumns(): ?int {
    return $this->windowMarginColumns ?? $this->windowMarginCells;
  }

  public function windowMarginRows(): ?int {
    return $this->windowMarginRows ?? $this->windowMarginCells;
  }

  public function backgroundColor(): Color {
    return $this->colors()['bg'];
  }

  public function foregroundColor(): Color {
    return $this->colors()['fg'];
  }

  public function borderColor(): Color {
    return $this->colors()['border'];
  }

  public function titleForegroundColor(): Color {
    return $this->colors()['titleFg'];
  }

  public function title(): string {
    return $this->title;
  }

  public function setCloseable(bool $closeable): static {
    $this->closeable = $closeable;
    return $this;
  }

  public function isCloseable(): bool {
    return $this->closeable;
  }

  public function setOnClose(?callable $onClose): static {
    $this->onClose = $onClose;
    return $this;
  }

  public function notifyClosed(): void {
    if ($this->onClose !== null) {
      ($this->onClose)($this);
    }
  }

  public function interiorRows(): int {
    return max(1, $this->preferredRows() - $this->borderRows());
  }

  public function content(): Flow {
    return $this->content;
  }

  public function buttons(): FlowRow {
    if ($this->buttons === null) {
      $this->buttons = new FlowRow($this->name === '' ? 'dialog-buttons' : $this->name . '-buttons', 'justify');
      parent::add($this->buttons);
      $this->buttons->setTheme($this->childTheme());
      $this->invalidateLayout();
    }
    return $this->buttons;
  }

  public function addContent(Element $child, int|Place|null $rows = null): static {
    $this->content->place($child, $rows);
    $this->syncChildFieldColors($child);
    $this->invalidateLayout();
    return $this;
  }

  public function addButton(Button $button): static {
    $this->buttons()->place($button);
    $this->syncButtonAlignment();
    $this->invalidateLayout();
    return $this;
  }

  public function preferredRows(): int {
    return $this->preferredRowsForColumns($this->frame->width);
  }

  public function preferredRowsForColumns(int $columns): int {
    $contentRows = $columns > 0 ? $this->content->preferredRowsForColumns(max(1, $columns - 4)) : $this->content->preferredRows();
    return max(3, $this->borderRows() + $this->titleRows() + $this->paddingRows() * 2 + $contentRows + $this->contentButtonGapRows() + $this->buttonRows());
  }

  public function layout(): void {
    $inner = $this->frame->inset(1, 1);
    $y = $inner->y;
    if ($this->titleRows() > 0) {
      $y++;
    }
    $y += $this->paddingRows();
    $buttonRows = $this->buttonRows();
    $contentHeight = max(0, $inner->bottom() - $y - $this->contentButtonGapRows() - $buttonRows - $this->paddingRows());
    $this->content->setFrame(new Rect($inner->x + 1, $y, max(0, $inner->width - 2), $contentHeight));
    if ($this->buttons !== null) {
      $buttonY = max($inner->y, $inner->bottom() - $this->paddingRows() - 1);
      $this->buttons->setFrame(new Rect($inner->x + 1, $buttonY, max(0, $inner->width - 2), 1));
    }
  }

  public function layoutInterior(): void {
    $y = $this->frame->y;
    if ($this->titleRows() > 0) {
      $y++;
    }
    $y += $this->paddingRows();
    $buttonRows = $this->buttonRows();
    $bottomPadding = $this->interiorBottomPaddingRows();
    $contentHeight = max(0, $this->frame->bottom() - $y - $this->contentButtonGapRows() - $buttonRows - $bottomPadding);
    $this->content->setFrame(new Rect($this->frame->x + 1, $y, max(0, $this->frame->width - 2), $contentHeight));
    if ($this->buttons !== null) {
      $buttonY = max($this->frame->y, $this->frame->bottom() - $bottomPadding - 1);
      $this->buttons->setFrame(new Rect($this->frame->x + 1, $buttonY, max(0, $this->frame->width - 2), 1));
    }
  }

  public function renderInterior(RenderTarget $target): void {
    $target->pushClip($this->frame);
    $this->paintInterior($target);
    foreach ($this->children as $child) {
      $child->render($target);
    }
    $target->popClip();
  }

  protected function paint(RenderTarget $target): void {
    $colors = $this->colors();
    $target->fill($this->frame, ' ', $colors['fg'], $colors['bg']);
    if ($this->frame->width <= 0 || $this->frame->height <= 0) {
      return;
    }
    if ($this->titleRows() > 0 && $this->frame->height > 2) {
      $target->fill(new Rect($this->frame->x + 1, $this->frame->y + 1, max(0, $this->frame->width - 2), 1), ' ', $colors['titleFg'], $colors['border']);
      $title = mb_substr($this->title, 0, max(0, $this->frame->width - 2));
      $x = $this->frame->x + max(1, intdiv($this->frame->width - mb_strlen($title), 2));
      $target->write($x, $this->frame->y + 1, $title, $colors['titleFg'], $colors['border']);
    }
    $target->drawRect($this->frame, $colors['border'], 2);
  }

  protected function paintInterior(RenderTarget $target): void {
    $colors = $this->colors();
    $target->fill($this->frame, ' ', $colors['fg'], $colors['bg']);
    if ($this->titleRows() === 0 || $this->frame->height <= 0) {
      return;
    }
    $target->fillToWindowEdge(new Rect($this->frame->x, $this->frame->y, $this->frame->width, 1), ' ', $colors['titleFg'], $colors['border']);
    $title = mb_substr($this->title, 0, max(0, $this->frame->width));
    $x = $this->frame->x + max(0, intdiv($this->frame->width - mb_strlen($title), 2));
    $target->write($x, $this->frame->y, $title, $colors['titleFg'], $colors['border']);
  }

  protected function handleShortcut(InputEvent $event): bool {
    if ($event->type !== 'key') {
      return false;
    }
    if (InputAction::cancel($event, 'dialog') && $this->closeable) {
      return $this->close();
    }
    if (InputAction::confirm($event, 'dialog')) {
      return $this->pressDefaultButton();
    }
    if (InputAction::copy($event, 'dialog')) {
      return $this->copyTextBlocks();
    }
    if (preg_match('/^F([1-9]|1[0-2])$/', $event->key)) {
      return $this->pressHotKeyButton($event->key);
    }
    return false;
  }

  protected function pressDefaultButton(): bool {
    foreach ($this->buttons?->children() ?? [] as $child) {
      if ($child instanceof Button) {
        return $child->press();
      }
    }
    return false;
  }

  protected function pressHotKeyButton(string $key): bool {
    foreach ($this->buttons?->children() ?? [] as $child) {
      if ($child instanceof Button && $child->matchesHotKey($key)) {
        return $child->press();
      }
    }
    return false;
  }

  protected function copyTextBlocks(): bool {
    $blocks = [];
    $this->collectTextBlocks($this, $blocks);
    if ($blocks === []) {
      return false;
    }
    Clipboard::set(implode("\n\n", array_map(fn(TextBlock $block): string => $block->text(), $blocks)));
    return true;
  }

  protected function collectTextBlocks(Element $element, array &$blocks): void {
    if ($element instanceof TextBlock) {
      $blocks[] = $element;
    }
    foreach ($element->children() as $child) {
      $this->collectTextBlocks($child, $blocks);
    }
  }

  protected function close(): bool {
    $parent = $this->parent();
    if (!$parent instanceof DialogLayer) {
      return false;
    }
    return $parent->pop($this) !== null;
  }

  protected function borderRows(): int {
    return 2;
  }

  protected function titleRows(): int {
    return $this->title === '' ? 0 : 1;
  }

  protected function buttonRows(): int {
    return $this->buttons === null ? 0 : 1;
  }

  protected function contentButtonGapRows(): int {
    return $this->buttons === null ? 0 : 2;
  }

  protected function paddingRows(): int {
    return 1;
  }

  protected function interiorBottomPaddingRows(): int {
    return $this->windowMarginColumns() !== null || $this->windowMarginRows() !== null ? 0 : $this->paddingRows();
  }

  protected function colors(): array {
    $colors = self::COLORS[$this->variant] ?? self::COLORS['normal'];
    $bg = $this->active ? $colors['bg'] : ($colors['inactiveBg'] ?? $colors['bg']);
    $border = $this->active ? $colors['activeBorder'] : $colors['border'];
    return [
      'bg' => Color::from($bg),
      'fg' => Color::from($colors['fg']),
      'border' => Color::from($border),
      'titleFg' => Color::from($this->active ? $colors['activeTitleFg'] : $colors['titleFg']),
    ];
  }

  protected function normalizeVariant(string $variant): string {
    return array_key_exists($variant, self::COLORS) ? $variant : 'normal';
  }

  protected function normalizeSize(string $size): string {
    return in_array($size, ['normal', 'big', 'small', 'extra-small'], true) ? $size : 'normal';
  }

  protected function syncChildTheme(): void {
    $theme = $this->childTheme();
    $this->content->setTheme($theme);
    $this->buttons?->setTheme($theme);
    $this->syncChildFieldColors($this->content);
  }

  protected function syncChildFieldColors(Element $element): void {
    $colors = $this->colors();
    if ($element instanceof TextEditor || $element instanceof ListView || $element instanceof TableView || $element instanceof TabView) {
      $element->setFieldColors($colors['fg'], $colors['border']);
    }
    foreach ($element->children() as $child) {
      $this->syncChildFieldColors($child);
    }
  }

  protected function syncButtonAlignment(): void {
    if ($this->buttons === null) {
      return;
    }
    $this->buttons->setAlign(count($this->buttons->children()) === 1 ? 'center' : 'justify');
  }

  protected function childTheme(): Theme {
    $colors = $this->colors();
    return new Theme(
      fg: $colors['fg'],
      bg: $colors['bg'],
      accent: $colors['border'],
      muted: $colors['border'],
      inverseFg: $colors['fg'],
      inverseBg: $colors['border'],
      border: $colors['border'],
      hotkey: $this->theme->hotkey,
      markerFg: '#ffffff',
      menuActiveBg: $this->theme->menuActiveBg,
      menuPopupFg: $this->theme->menuPopupFg,
      menuPopupBg: $this->theme->menuPopupBg,
      menuPopupActiveFg: $this->theme->menuPopupActiveFg,
      menuPopupActiveBg: $this->theme->menuPopupActiveBg,
      cursorFg: $this->theme->cursorFg,
      cursorBg: $this->theme->cursorBg,
      inactiveCursorFg: $this->theme->inactiveCursorFg,
      inactiveCursorBg: $this->theme->inactiveCursorBg,
      rowHeight: $this->theme->rowHeight
    );
  }

}
