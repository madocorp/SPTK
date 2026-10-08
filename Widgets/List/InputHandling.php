<?php

namespace SPTK\Widgets\List;

use SPTK\Events\KeyNormalizer;
use SPTK\SDLWrapper\SDL;

/** Routes active-list typing and navigation keys before screen hotkeys. */
trait InputHandling {

  /** Handle query typing, movement, selection, and optional item ordering. */
  public function handleInput(mixed $event): bool {
    if ($event->type === SDL::SDL_EVENT_TEXT_INPUT) {
      $text = \FFI::string($event->text->text);
      if (($this->filterable || $this->searchable) && ($text !== ' ' || !$this->multiple) && ($this->query === '' || ItemSearch::matchingIndices($this->items, $this->query) !== [])) {
        $this->changeValue($this->appendQuery(...), $text);
      }
      return true;
    }
    if ($event->type !== SDL::SDL_EVENT_KEY_DOWN) {
      return false;
    }
    $mod = (int)$event->key->mod;
    $key = KeyNormalizer::normalize((int)$event->key->key, $mod);
    if ($key === SDL::KEY_LEFT || $key === SDL::KEY_RIGHT) {
      return false;
    }
    if ($key === SDL::KEY_BACKSPACE || $key === SDL::KEY_DELETE) {
      $query = $key === SDL::KEY_DELETE ? '' : mb_substr($this->query, 0, max(0, mb_strlen($this->query) - 1));
      $this->changeValue($this->setFilter(...), $query);
      return true;
    }
    if ($key === SDL::KEY_SPACE) {
      if (!$event->key->repeat && $this->multiple && $this->activeValue() !== null) {
        $this->changeValue($this->toggleCurrent(...));
      }
      return true;
    }
    if (in_array($key, [SDL::KEY_UP, SDL::KEY_DOWN, SDL::KEY_HOME, SDL::KEY_END, SDL::KEY_PAGEUP, SDL::KEY_PAGEDOWN], true)) {
      $this->changeValue($this->move(...), $key, $mod);
      return true;
    }
    // SDL sends keydown before text input; keep printable keys inside the active list.
    return $this->acceptsTextInput() && ($mod & (SDL::MOD_CTRL | SDL::MOD_ALT)) === 0
      && $key >= 32 && $key <= 126;
  }

  /** Accept the list value when Escape releases the widget. */
  public function releaseNotification(int $key, int $modifiers): ?string {
    return $key === SDL::KEY_ESCAPE ? 'accept' : parent::releaseNotification($key, $modifiers);
  }

}
