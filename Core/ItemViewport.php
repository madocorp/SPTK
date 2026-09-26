<?php

namespace SPTK\Core;

use SPTK\SDLWrapper\SDL;

/** Tracks one highlighted item and a vertically paged tile viewport. */
final class ItemViewport {

  private int $count = 0;
  private int $cursor = 0;
  private int $scroll = 0;
  private int $height = 1;

  /** Reset selection and scroll for replacement items. */
  public function reset(int $count): void {
    $this->count = max(0, $count);
    $this->cursor = 0;
    $this->scroll = 0;
  }

  /** Set the visible item count and clamp the current position. */
  public function setCount(int $count): void {
    $this->count = max(0, $count);
    $this->cursor = min($this->cursor, max(0, $this->count - 1));
    $this->sync();
  }

  /** Set the tile's available number of item rows. */
  public function setHeight(int $height): void {
    $this->height = max(1, $height);
    $this->sync();
  }

  /** Return the highlighted visible position. */
  public function position(): int {
    return $this->cursor;
  }

  /** Return the first visible item position. */
  public function scroll(): int {
    return $this->scroll;
  }

  /** Return how many items belong to this viewport. */
  public function count(): int {
    return $this->count;
  }

  /** Select a visible position and keep it inside the viewport. */
  public function setPosition(int $position): void {
    $this->cursor = max(0, min(max(0, $this->count - 1), $position));
    $this->sync();
  }

  /** Apply Up, Down, Home, End, and edge-then-page movement. */
  public function move(int $key): bool {
    $before = $this->cursor;
    $last = max(0, $this->count - 1);
    if ($key === SDL::KEY_UP) {
      $this->cursor = max(0, $this->cursor - 1);
    } else if ($key === SDL::KEY_DOWN) {
      $this->cursor = min($last, $this->cursor + 1);
    } else if ($key === SDL::KEY_HOME) {
      $this->cursor = 0;
    } else if ($key === SDL::KEY_END) {
      $this->cursor = $last;
    } else if ($key === SDL::KEY_PAGEUP || $key === SDL::KEY_PAGEDOWN) {
      $this->page($key === SDL::KEY_PAGEDOWN);
    } else {
      return false;
    }
    $this->sync();
    return $this->cursor !== $before;
  }

  /** Move to the current viewport edge, then one page on the next press. */
  private function page(bool $down): void {
    $edge = $down ? min($this->count - 1, $this->scroll + $this->height - 1) : $this->scroll;
    if ($this->cursor === $edge) {
      $this->scroll = max(0, min(max(0, $this->count - $this->height), $this->scroll + ($down ? 1 : -1) * $this->height));
      $edge = $down ? min($this->count - 1, $this->scroll + $this->height - 1) : $this->scroll;
    }
    $this->cursor = max(0, $edge);
  }

  /** Clamp scrolling around the highlighted item. */
  private function sync(): void {
    $this->scroll = max(0, min($this->scroll, $this->cursor, max(0, $this->count - $this->height)));
    $this->scroll = max($this->scroll, $this->cursor - $this->height + 1);
  }

}
