<?php

namespace SPTK\Widgets\List;

use SPTK\Core\{ItemViewport, Style, WidgetTitle};
use SPTK\Rendering\GridWriter;

/** Paints a list's fixed title and full or partial item rows with shared viewport bookkeeping. */
final class View {

  private Painter $painter;
  private WidgetTitle $title;
  private int $paintedWidth = -1;
  private int $paintedHeight = -1;

  /** Share the list's viewport, redraw state, and inherited title and item colors. */
  public function __construct(Style $style, private ItemViewport $viewport, private Redraw $redraw, ?string $title) {
    $this->painter = new Painter($style);
    $this->title = new WidgetTitle($title, $style);
  }

  /** Return the preferred width of the fixed title. */
  public function titleWidth(): int {
    return $this->title->width();
  }

  /** Return the number of rows reserved above the list's items. */
  public function titleHeight(): int {
    return $this->title->height();
  }

  /** Paint the title and item viewport, remembering the tile size for partial updates. */
  public function paint(GridWriter $writer, array $items, array $visible, string $query, bool $active, bool $multiple): void {
    $this->paintedWidth = $writer->width();
    $this->paintedHeight = $writer->height();
    $body = $this->title->body($writer);
    $this->viewport->setHeight($body->height());
    $this->painter->paint($body, $items, $visible, $this->viewport, $query, $active, $multiple);
    $this->redraw->painted();
  }

  /** Paint only changed item rows when filtering, scrolling, and tile dimensions permit it. */
  public function paintUpdate(GridWriter $writer, array $items, array $visible, string $query, bool $active, bool $multiple): bool {
    $rows = $this->redraw->rows();
    if ($rows === null || $writer->width() !== $this->paintedWidth || $writer->height() !== $this->paintedHeight) {
      return false;
    }
    $body = $this->title->body($writer, false);
    if ($body->width() > 0 && $body->height() > 0) {
      $this->painter->paintRows($body, $items, $visible, $this->viewport, $query, $active, $multiple, $rows);
    }
    $this->redraw->painted();
    return true;
  }

}
