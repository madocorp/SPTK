<?php

namespace SPTK\Runtime;

use SPTK\SDLWrapper\SDL;

/**
 * Bridges SPTK clipboard calls to SDL's OS clipboard integration.
 */
class SdlClipboard {

  public function __construct(protected SDL $sdl) {
  }

  public function set(string $text): void {
    $this->sdl->ffi->SDL_SetClipboardText($text);
  }

  public function setPrimary(string $text): void {
    $this->sdl->ffi->SDL_SetPrimarySelectionText($text);
  }

  public function get(): string {
    if (!$this->sdl->ffi->SDL_HasClipboardText()) {
      return '';
    }
    $ptr = $this->sdl->ffi->SDL_GetClipboardText();
    if ($ptr === null) {
      return '';
    }
    $text = \FFI::string($ptr);
    $this->sdl->ffi->SDL_free($ptr);
    return $text;
  }

}
