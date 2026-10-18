<?php

namespace SPTK\Layout;

use SPTK\Core\Style;
use SPTK\Rendering\GridWriter;

/** Gives a layout or leaf an open size and an optional summary inside a deck. */
trait LayoutDeckTile {

  private ?string $deckSize = null;
  private string $deckAxis = 'height';
  private bool $deckOpen = false;
  private string|\Closure|null $deckSummary = null;
  private ?Style $deckSummaryStyle = null;

  /** Make this tile part of its parent's deck; the size applies while it is open. */
  public function setDeck(string $size, string $axis): void {
    if (!in_array($axis, ['width', 'height'], true)) {
      throw new \InvalidArgumentException('Deck axis must be width or height.');
    }
    $this->deckSize = $size;
    $this->deckAxis = $axis;
  }

  public function isDeckTile(): bool {
    return $this->deckSize !== null;
  }

  public function isDeckOpen(): bool {
    return $this->deckSize !== null && $this->deckOpen;
  }

  public function isDeckClosed(): bool {
    return $this->deckSize !== null && !$this->deckOpen;
  }

  /** Change the open state; the parent deck keeps only one tile open. */
  public function setDeckOpen(bool $open): void {
    $this->deckOpen = $open;
  }

  /** Set the closed text source; it gets the tile id and returns a title line and text lines. */
  public function setDeckSummary(string|\Closure|null $summary, Style $style = new Style()): void {
    $this->deckSummary = $summary;
    $this->deckSummaryStyle = $style;
  }

  /** Return the open deck size on its own axis, otherwise the normal size. */
  private function deckAdjustedSize(string $size, string $axis): string {
    return $this->isDeckOpen() && $this->deckAxis === $axis ? $this->deckSize : $size;
  }

  /** Paint the summary of a closed tile; false when it has no summary. */
  private function paintDeckSummary(GridWriter $writer, ?string $id): bool {
    if ($this->deckSummary === null) {
      return false;
    }
    if (!is_callable($this->deckSummary)) {
      throw new \RuntimeException('Deck summary is not callable: ' . $this->deckSummary);
    }
    DeckSummary::paint($writer, (string)call_user_func($this->deckSummary, $id), $this->deckSummaryStyle);
    return true;
  }

}
