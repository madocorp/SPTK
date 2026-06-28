<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\Font;
use \SPTK\StyleSheet;
use \SPTK\SDLWrapper\Action;
use \SPTK\SDLWrapper\KeyCombo;
use \SPTK\SDLWrapper\TTF;

class Table extends Element {

  protected string|false $file = false;
  protected int $chunkSize = 10000;
  protected int $chunkStart = 0;
  protected array $chunk = [];
  protected array $header = [];
  protected int $lineCount = 0;
  protected int $rowCount = 0;
  protected array $lineOffsets = [];
  protected array $rawColumnWidths = [];
  protected array $columnWidths = [];
  protected int $lineHeight = 18;
  protected int $rowHeight = 18;
  protected int $letterWidth = 8;
  protected int $cellHorizontalChrome = 0;
  protected int $cellVerticalChrome = 0;
  protected int $minFieldWidth = 40;
  protected bool $widthsMeasured = false;
  protected int $cursorRow = 0;
  protected int $cursorColumn = 0;
  protected int $poolStart = -1;
  protected int $poolSize = 0;

  protected function init(): void {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    new TableHeader($this);
    new TableContent($this);
  }

  public function getAttributeList(): array {
    return ['file', 'chunkSize', 'minFieldWidth'];
  }

  public function setFile($file): void {
    if ($file === false) {
      return;
    }
    if (strpos($file, '/') !== 0) {
      if (defined('APP_PATH')) {
        $file = dirname(APP_PATH) . "/{$file}";
      } else {
        $file = getcwd() . "/{$file}";
      }
    }
    if (!file_exists($file)) {
      return;
    }
    $this->file = $file;
    $this->scrollX = 0;
    $this->scrollY = 0;
    $this->cursorRow = 0;
    $this->cursorColumn = 0;
    $this->contentElement()->setScroll(0, 0);
    $this->chunk = [];
    $this->chunkStart = 0;
    $this->poolStart = -1;
    $this->poolSize = 0;
    $this->rawColumnWidths = [];
    $this->columnWidths = [];
    $this->widthsMeasured = false;
    $this->scanFile();
    $this->loadChunk(0);
    $this->measureColumnWidths();
    if ($this->renderer !== false) {
      $this->recalculateGeometry();
    }
  }

  public function setChunkSize($value): void {
    $value = (int)$value;
    if ($value > 0) {
      $this->chunkSize = $value;
    }
  }

  public function setMinFieldWidth($value): void {
    $value = (int)$value;
    if ($value >= 0) {
      $this->minFieldWidth = $value;
      $this->widthsMeasured = false;
      $this->measureColumnWidths();
    }
  }

  public function getHeader(): array {
    return $this->header;
  }

  public function getLineCount(): int {
    return $this->lineCount;
  }

  public function getRowCount(): int {
    return $this->rowCount;
  }

  public function getChunkStart(): int {
    return $this->chunkStart;
  }

  public function getChunk(): array {
    return $this->chunk;
  }

  public function getColumnWidths(): array {
    return $this->columnWidths;
  }

  public function getCursor(): array {
    return [$this->cursorRow, $this->cursorColumn];
  }

  public function scrollToRow(int $row): void {
    $row = max(0, min($row, max(0, $this->rowCount - 1)));
    $this->syncInternalElements();
    $this->contentElement()->setScrollY($row * $this->rowHeight);
    $this->reloadVisibleChunk();
    if ($this->renderer !== false) {
      $this->recalculateGeometry();
    }
  }

  protected function scanFile(): void {
    $this->header = [];
    $this->lineCount = 0;
    $this->rowCount = 0;
    $this->lineOffsets = [];

    $handle = fopen($this->file, 'rb');
    if ($handle === false) {
      return;
    }
    while (($offset = ftell($handle)) !== false && ($line = fgets($handle)) !== false) {
      if ($this->lineCount % $this->chunkSize === 0) {
        $this->lineOffsets[$this->lineCount] = $offset;
      }
      if ($this->lineCount === 0) {
        $this->header = $this->parseLine($line);
      }
      $this->lineCount++;
    }
    fclose($handle);
    $this->rowCount = max(0, $this->lineCount - 1);
  }

  protected function loadChunk(int $row): void {
    if ($this->file === false) {
      return;
    }
    $row = max(0, min($row, max(0, $this->rowCount - 1)));
    $this->chunkStart = (int)(floor($row / $this->chunkSize) * $this->chunkSize);
    $targetLine = $this->chunkStart + 1;
    $seekLine = $this->nearestIndexedLine($targetLine);

    $handle = fopen($this->file, 'rb');
    if ($handle === false) {
      return;
    }
    fseek($handle, $this->lineOffsets[$seekLine] ?? 0);
    for ($line = $seekLine; $line < $targetLine; $line++) {
      if (fgets($handle) === false) {
        break;
      }
    }
    $this->chunk = [];
    for ($i = 0; $i < $this->chunkSize && ($line = fgets($handle)) !== false; $i++) {
      $this->chunk[] = $this->parseLine($line);
    }
    fclose($handle);
  }

  protected function nearestIndexedLine(int $targetLine): int {
    $nearest = 0;
    foreach ($this->lineOffsets as $line => $offset) {
      if ($line > $targetLine) {
        break;
      }
      $nearest = $line;
    }
    return $nearest;
  }

  protected function parseLine(string $line): array {
    $line = rtrim($line, "\r\n");
    $fields = [];
    $field = '';
    $escaping = false;
    $length = strlen($line);
    for ($i = 0; $i < $length; $i++) {
      $char = $line[$i];
      if ($escaping) {
        $field .= match ($char) {
          't' => "\t",
          'n' => "\n",
          'r' => "\r",
          '\\' => '\\',
          default => "\\{$char}",
        };
        $escaping = false;
        continue;
      }
      if ($char === "\\") {
        $escaping = true;
      } else if ($char === "\t") {
        $fields[] = ($field === '\N' ? null : $field);
        $field = '';
      } else {
        $field .= $char;
      }
    }
    if ($escaping) {
      $field .= '\\';
    }
    $fields[] = ($field === '\N' ? null : $field);
    return $fields;
  }

  protected function measureColumnWidths(): void {
    $this->syncTextMetrics();
    if (!$this->widthsMeasured) {
      $widths = [];
      foreach (array_merge([$this->header], $this->chunk) as $row) {
        foreach ($row as $i => $field) {
          $text = $field === null ? '' : (string)$field;
          $width = mb_strlen($text) * $this->letterWidth + $this->cellHorizontalChrome;
          $widths[$i] = max($widths[$i] ?? $this->minFieldWidth, $width, $this->minFieldWidth);
        }
      }
      $columns = max(count($this->header), count($widths));
      for ($i = 0; $i < $columns; $i++) {
        $widths[$i] = $widths[$i] ?? $this->minFieldWidth;
      }
      ksort($widths);
      $this->rawColumnWidths = array_values($widths);
      $this->widthsMeasured = true;
    }
    $this->columnWidths = $this->rawColumnWidths;
    $this->limitColumnWidthsToBox();
  }

  protected function limitColumnWidthsToBox(): void {
    if ($this->geometry->innerWidth <= 0) {
      return;
    }
    if (array_sum($this->rawColumnWidths) <= $this->geometry->innerWidth) {
      return;
    }
    $maxFieldWidth = max($this->minFieldWidth, (int)floor($this->geometry->innerWidth * 0.33));
    foreach ($this->columnWidths as $i => $width) {
      $this->columnWidths[$i] = min($width, $maxFieldWidth);
    }
  }

  protected function syncTextMetrics(): void {
    $fontSize = (int)$this->style->get('fontSize', $this->geometry);
    $this->lineHeight = max(1, (int)$this->style->get('lineHeight', $this->geometry));
    $this->letterWidth = max(1, (int)round($fontSize * 0.6));
    if (TTF::$instance === null || $fontSize <= 0) {
      $this->syncCellMetrics();
      return;
    }
    $font = new Font($this->style->get('font'), $fontSize);
    $this->lineHeight = max(1, $font->height);
    $this->letterWidth = max(1, $font->letterWidth);
    $this->syncCellMetrics();
  }

  protected function syncCellMetrics(): void {
    $cellStyle = StyleSheet::get($this->style, $this->style, 'TableCell');
    $this->cellHorizontalChrome =
      $cellStyle->get('paddingLeft', $this->geometry) +
      $cellStyle->get('paddingRight', $this->geometry) +
      $cellStyle->get('borderLeft', $this->geometry) +
      $cellStyle->get('borderRight', $this->geometry);
    $this->cellVerticalChrome =
      $cellStyle->get('paddingTop', $this->geometry) +
      $cellStyle->get('paddingBottom', $this->geometry) +
      $cellStyle->get('borderTop', $this->geometry) +
      $cellStyle->get('borderBottom', $this->geometry);
    $this->rowHeight = $this->lineHeight + $this->cellVerticalChrome;
  }

  protected function measure(): void {
    $this->geometry->setValues($this->ancestor->geometry, $this->style);
    $this->syncTextMetrics();
    $this->measureColumnWidths();
    $this->syncInternalElements();
    foreach ($this->descendants as $descendant) {
      $descendant->measure();
    }
  }

  protected function calculateHeights(): void {
    if ($this->display === false) {
      return;
    }
    $this->geometry->contentHeight = $this->geometry->paddingTop + $this->rowHeight + $this->geometry->paddingBottom;
    if ($this->geometry->height === 'content') {
      $this->geometry->height =
        $this->geometry->borderTop +
        $this->geometry->contentHeight +
        $this->geometry->borderBottom;
      $this->geometry->limitateHeight();
      $this->geometry->setDerivedHeights();
    }
    $this->geometry->ascent = $this->geometry->fullHeight - $this->geometry->marginBottom;
    $this->geometry->descent = $this->geometry->marginBottom;
  }

  protected function layout(): void {
    if ($this->display === false) {
      return;
    }
    if ($this->geometry->position === 'absolute') {
      $this->geometry->setAbsolutePosition($this->ancestor->geometry, $this->style);
    }
    $this->columnWidths = $this->rawColumnWidths;
    $this->limitColumnWidthsToBox();
    $this->syncInternalElements();
    $this->reloadVisibleChunk();
    $this->buildTree();
    foreach ($this->descendants as $descendant) {
      $descendant->measure();
      $descendant->calculateWidths();
      $descendant->calculateHeights();
      $descendant->layout();
    }
    $this->geometry->contentWidth =
      $this->geometry->paddingLeft +
      array_sum($this->columnWidths) +
      $this->geometry->paddingRight;
  }

  protected function syncInternalElements(): void {
    $contentWidth = array_sum($this->columnWidths);
    $this->headerElement()->setTableGeometry($this->rowHeight, $contentWidth, $this->contentElement()->getScrollX());
    $this->contentElement()->setTableGeometry($this->rowHeight, $contentWidth, $this->rowCount);
  }

  protected function headerElement(): TableHeader {
    return $this->descendants[0];
  }

  protected function contentElement(): TableContent {
    return $this->descendants[1];
  }

  protected function reloadVisibleChunk(): void {
    if ($this->file === false || $this->rowCount === 0) {
      return;
    }
    $first = $this->firstVisibleRow();
    if ($first < $this->chunkStart || $first >= $this->chunkStart + count($this->chunk)) {
      $this->loadChunk($first);
    }
  }

  protected function firstVisibleRow(): int {
    return max(0, (int)floor($this->contentElement()->getScrollY() / $this->rowHeight));
  }

  protected function lastVisibleRow(): int {
    $content = $this->contentElement();
    if ($content->getGeometry()->height === 0 || $content->getGeometry()->height === 'content') {
      return min($this->rowCount - 1, $this->firstVisibleRow() + 300);
    }
    $visible = (int)ceil($content->getGeometry()->height / $this->rowHeight) + 2;
    return min($this->rowCount - 1, $this->firstVisibleRow() + $visible);
  }

  protected function visibleRowCount(): int {
    $content = $this->contentElement();
    if ($content->getGeometry()->height === 0 || $content->getGeometry()->height === 'content') {
      return min(300, max(1, $this->rowCount));
    }
    return max(1, (int)ceil($content->getGeometry()->height / $this->rowHeight) + 1);
  }

  protected function buildTree(): void {
    $header = $this->headerElement();
    $content = $this->contentElement();
    $this->syncRowPool($header, 1, 'TableHeaderRow');
    $this->updateRow($header->nthChild(0), -1, $this->header, 0);
    $this->syncContentRows();
  }

  protected function bufferFirstRow(): int {
    return $this->firstVisibleRow();
  }

  protected function syncRowPool(Element $container, int $size, string $type): void {
    while ($container->countDescendants() < $size) {
      new TableRow($container, null, null, $type);
    }
    foreach ($container->getDescendants() as $i => $row) {
      if ($i < $size) {
        $row->show();
      } else {
        $row->hide();
      }
    }
  }

  protected function syncContentRows(): void {
    $content = $this->contentElement();
    $first = $this->firstVisibleRow();
    $size = min($this->visibleRowCount(), max(0, $this->rowCount - $first));
    $last = $first + $size - 1;
    $this->poolStart = $first;
    $this->poolSize = $size;

    $rowsByDataRow = [];
    $available = [];
    foreach ($content->getDescendants() as $row) {
      if (!$row instanceof TableRow) {
        continue;
      }
      $dataRow = $row->getDataRow();
      if ($row->isDisplayed() && $dataRow >= $first && $dataRow <= $last && !isset($rowsByDataRow[$dataRow])) {
        $rowsByDataRow[$dataRow] = $row;
      } else {
        $available[] = $row;
      }
    }

    for ($dataRow = $first; $dataRow <= $last; $dataRow++) {
      $row = $rowsByDataRow[$dataRow] ?? array_shift($available);
      if (!$row instanceof TableRow) {
        $row = new TableRow($content, null, null, 'TableRow');
      }
      $row->show();
      $chunkIndex = $dataRow - $this->chunkStart;
      $this->updateRow($row, $dataRow, $this->chunk[$chunkIndex] ?? [], $dataRow);
    }

    foreach ($available as $row) {
      $row->hide();
    }
  }

  protected function updateRow(TableRow $row, int $dataRow, array $values, int $visualRow): void {
    $row->setTablePosition($visualRow, $this->rowHeight);
    $x = 0;
    $columns = max(count($this->columnWidths), count($this->header), count($values));
    for ($i = 0; $i < $columns; $i++) {
      $cell = $row->nthChild($i);
      if (!$cell instanceof TableCell) {
        $cell = new TableCell($row);
      }
      $cell->setCellGeometry($x, $this->columnWidths[$i] ?? $this->minFieldWidth, $this->rowHeight);
      $cell->setCellValue($values[$i] ?? null);
      if ($dataRow === $this->cursorRow && $i === $this->cursorColumn) {
        $cell->addVariant('cursor');
      } else {
        $cell->removeVariant('cursor');
      }
      $x += $this->columnWidths[$i] ?? $this->minFieldWidth;
    }
    foreach ($row->getDescendants() as $i => $cell) {
      if ($i < $columns) {
        $cell->show();
      } else {
        $cell->hide();
      }
    }
  }

  protected function clampScroll(): void {
    $this->contentElement()->clampScroll();
    $this->headerElement()->setScrollX($this->contentElement()->getScrollX());
  }

  protected function columnCount(): int {
    return max(1, count($this->columnWidths), count($this->header));
  }

  protected function clampCursor(): void {
    $this->cursorRow = max(0, min($this->cursorRow, max(0, $this->rowCount - 1)));
    $this->cursorColumn = max(0, min($this->cursorColumn, $this->columnCount() - 1));
  }

  protected function cursorCellX(): int {
    $x = 0;
    for ($i = 0; $i < $this->cursorColumn; $i++) {
      $x += $this->columnWidths[$i] ?? $this->minFieldWidth;
    }
    return $x;
  }

  protected function keepCursorOnScreen(): void {
    $this->clampCursor();
    $content = $this->contentElement();
    $cellTop = $this->cursorRow * $this->rowHeight;
    $cellBottom = $cellTop + $this->rowHeight;
    $scrollY = $content->getScrollY();
    $visibleTop = $scrollY;
    $visibleBottom = $scrollY + $content->getGeometry()->innerHeight;
    if ($cellTop < $visibleTop) {
      $content->setScrollY($cellTop);
    } else if ($cellBottom > $visibleBottom) {
      $content->setScrollY($cellBottom - $content->getGeometry()->innerHeight);
    }

    $cellLeft = $this->cursorCellX();
    $cellWidth = $this->columnWidths[$this->cursorColumn] ?? $this->minFieldWidth;
    $cellRight = $cellLeft + $cellWidth;
    $scrollX = $content->getScrollX();
    if ($cellLeft < $scrollX) {
      $content->setScrollX($cellLeft);
    } else if ($cellRight > $scrollX + $content->getGeometry()->innerWidth) {
      $content->setScrollX($cellRight - $content->getGeometry()->innerWidth);
    }
    $this->clampScroll();
  }

  protected function cursorCellElement(int $row, int $column): TableCell|false {
    foreach ($this->contentElement()->getDescendants() as $rowElement) {
      if ($rowElement instanceof TableRow && $rowElement->getDataRow() === $row) {
        $cell = $rowElement->nthChild($column);
        return $cell instanceof TableCell ? $cell : false;
      }
    }
    return false;
  }

  protected function refreshCursorCells(int $oldRow, int $oldColumn, bool $render = true): bool {
    $oldCell = $this->cursorCellElement($oldRow, $oldColumn);
    $newCell = $this->cursorCellElement($this->cursorRow, $this->cursorColumn);
    if ($oldCell === false || $newCell === false) {
      return false;
    }
    $redrawScrollbar = !$this->cellCanBeRefreshedDirectly($oldCell) || !$this->cellCanBeRefreshedDirectly($newCell);
    if ($oldCell->getId() !== $newCell->getId()) {
      $oldCell->removeVariant('cursor');
      $newCell->addVariant('cursor');
      if ($render && $this->renderer !== false) {
        Element::immediateRefresh($oldCell);
        Element::immediateRefresh($newCell);
        if ($redrawScrollbar && $this->contentElement()->hasScrollbarOverlap()) {
          $this->contentElement()->redrawScrollbar();
          Element::immediateCopy($this->contentElement());
        }
      }
    }
    return true;
  }

  protected function cellCanBeRefreshedDirectly(TableCell $cell): bool {
    $row = $cell->getAncestor();
    if (!$row instanceof TableRow) {
      return false;
    }
    $content = $this->contentElement();
    $x1 = $row->getGeometry()->x + $cell->getGeometry()->x - $content->getScrollX();
    $y1 = $row->getGeometry()->y + $cell->getGeometry()->y - $content->getScrollY();
    $x2 = $x1 + $cell->getGeometry()->width;
    $y2 = $y1 + $cell->getGeometry()->height;
    $rightLimit = $content->getGeometry()->width;
    $bottomLimit = $content->getGeometry()->height;
    $scrollbarSize = $content->getStyle()->get('scrollbarSize', $content->getGeometry());
    if ($content->getGeometry()->contentHeight > $content->getGeometry()->height) {
      $rightLimit -= $scrollbarSize;
    }
    if ($content->getGeometry()->contentWidth > $content->getGeometry()->width) {
      $bottomLimit -= $scrollbarSize;
    }
    return $x1 >= 0 && $y1 >= 0 && $x2 <= $rightLimit && $y2 <= $bottomLimit;
  }

  protected function refreshVisiblePool(): void {
    $this->buildTree();
    foreach ($this->descendants as $descendant) {
      $descendant->measure();
      $descendant->calculateWidths();
      $descendant->calculateHeights();
      $descendant->layout();
    }
  }

  public function keyPressHandler($element, $event): bool {
    $action = KeyCombo::resolve($event['mod'], $event['scancode'], $event['key']);
    $page = max(1, (int)floor($this->contentElement()->getGeometry()->innerHeight / $this->rowHeight));
    $oldRow = $this->cursorRow;
    $oldColumn = $this->cursorColumn;
    $oldScrollX = $this->contentElement()->getScrollX();
    $oldScrollY = $this->contentElement()->getScrollY();
    $oldChunkStart = $this->chunkStart;
    switch ($action) {
      case Action::MOVE_UP:
      case Action::SELECT_UP:
        $this->cursorRow--;
        break;
      case Action::MOVE_DOWN:
      case Action::SELECT_DOWN:
        $this->cursorRow++;
        break;
      case Action::PAGE_UP:
      case Action::SELECT_PAGE_UP:
        $this->cursorRow -= $page;
        break;
      case Action::PAGE_DOWN:
      case Action::SELECT_PAGE_DOWN:
        $this->cursorRow += $page;
        break;
      case Action::MOVE_FIRST:
      case Action::SELECT_FIRST:
        $this->cursorColumn = 0;
        break;
      case Action::MOVE_LAST:
      case Action::SELECT_LAST:
        $this->cursorColumn = $this->columnCount() - 1;
        break;
      case Action::MOVE_LEFT:
      case Action::SELECT_LEFT:
        $this->cursorColumn--;
        break;
      case Action::MOVE_RIGHT:
      case Action::SELECT_RIGHT:
        $this->cursorColumn++;
        break;
      case Action::MOVE_START:
      case Action::SELECT_START:
        $this->cursorRow = 0;
        $this->cursorColumn = 0;
        break;
      case Action::MOVE_END:
      case Action::SELECT_END:
        $this->cursorRow = $this->rowCount - 1;
        $this->cursorColumn = $this->columnCount() - 1;
        break;
      default:
        return false;
    }
    $this->keepCursorOnScreen();
    $this->reloadVisibleChunk();
    $viewportChanged =
      $oldScrollX !== $this->contentElement()->getScrollX() ||
      $oldScrollY !== $this->contentElement()->getScrollY();
    $chunkChanged = $oldChunkStart !== $this->chunkStart;
    if (!$viewportChanged && $this->refreshCursorCells($oldRow, $oldColumn)) {
      return true;
    }
    if ($viewportChanged && !$chunkChanged) {
      $this->refreshVisiblePool();
      if ($this->renderer !== false) {
        if ($oldScrollX !== $this->contentElement()->getScrollX()) {
          Element::immediateRefresh($this->headerElement(), false);
        }
        Element::immediateRefresh($this->contentElement(), false);
      }
      return true;
    }
    if ($this->renderer !== false) {
      $this->recalculateGeometry();
      Element::immediateRender($this, false);
    }
    return true;
  }

}
