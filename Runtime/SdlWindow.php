<?php

namespace SPTK\Runtime;

use SPTK\Core\FocusManager;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Core\Element;
use SPTK\Core\ElementContext;
use SPTK\Core\Clipboard;
use SPTK\Renderer\SdlGridRenderer;
use SPTK\SDLWrapper\SDL;
use SPTK\SDLWrapper\TTF;

/**
 * Owns one SDL window, its renderer, root element, focus, and grid layout state.
 */
class SdlWindow {

  protected mixed $window = null;
  protected mixed $renderer = null;
  protected SdlFont $font;
  protected SdlGridRenderer $gridRenderer;
  protected FocusManager $focus;
  protected ?SDL $sdl = null;
  protected int $id = 0;
  protected int $columns = 1;
  protected int $rows = 1;
  protected $ffiWidth;
  protected $ffiHeight;
  protected bool $closed = false;
  protected ElementContext $context;

  public function __construct(
    protected Element $root,
    protected SdlWindowOptions $options
  ) {
  }

  public function open(SDL $sdl, TTF $ttf): void {
    $this->sdl = $sdl;
    $this->context = new ElementContext();
    $this->root->setContext($this->context);
    $rowHeight = $this->options->rowHeight ?? $this->root->theme()->rowHeight;
    $this->font = new SdlFont($ttf, SdlFont::findPath($this->options->fontName), $this->options->fontSize, $rowHeight);
    $flags = SDL::SDL_WINDOW_HIDDEN;
    if ($this->options->resizable) {
      $flags |= SDL::SDL_WINDOW_RESIZABLE;
    }
    if ($this->options->fullscreen) {
      $flags |= SDL::SDL_WINDOW_FULLSCREEN;
    } else if ($this->options->maximized) {
      $flags |= SDL::SDL_WINDOW_MAXIMIZED;
    }
    $width = $this->options->columns * $this->font->letterWidth;
    $height = $this->options->rows * $this->font->rowHeight;
    $this->window = $sdl->ffi->SDL_CreateWindow($this->options->title, $width, $height, $flags);
    if ($this->window === null) {
      throw new \RuntimeException('SDL_CreateWindow failed: ' . $sdl->error());
    }
    $this->id = (int)$sdl->ffi->SDL_GetWindowID($this->window);
    $this->renderer = $sdl->ffi->SDL_CreateRenderer($this->window, null);
    if ($this->renderer === null) {
      throw new \RuntimeException('SDL_CreateRenderer failed: ' . $sdl->error());
    }
    $sdl->ffi->SDL_StartTextInput($this->window);
    Clipboard::setProvider(new SdlClipboard($sdl));
    $this->ffiWidth = \FFI::new('int');
    $this->ffiHeight = \FFI::new('int');
    $this->gridRenderer = new SdlGridRenderer($sdl, $ttf, $this->renderer, $this->font);
    $this->focus = new FocusManager($this->root);
    $this->resizeFromWindow($sdl);
    $this->context->invalidateRender();
    $this->render();
    $sdl->ffi->SDL_ShowWindow($this->window);
    $sdl->ffi->SDL_SyncWindow($this->window);
    $this->resizeFromWindow($sdl);
    $this->context->invalidateRender();
    $this->render();
  }

  public function id(): int {
    return $this->id;
  }

  public function isClosed(): bool {
    return $this->closed;
  }

  public function close(SDL $sdl): void {
    if ($this->closed) {
      return;
    }
    $this->closed = true;
    if (isset($this->gridRenderer)) {
      $this->gridRenderer->destroy();
    }
    if (isset($this->font)) {
      $this->font->close();
    }
    if ($this->renderer !== null) {
      $sdl->ffi->SDL_DestroyRenderer($this->renderer);
      $this->renderer = null;
    }
    if ($this->window !== null) {
      $sdl->ffi->SDL_DestroyWindow($this->window);
      $this->window = null;
    }
    $this->sdl = null;
  }

  public function setFullscreen(bool $fullscreen = true): bool {
    if ($this->closed || $this->window === null || $this->sdl === null) {
      return false;
    }
    $ok = (bool)$this->sdl->ffi->SDL_SetWindowFullscreen($this->window, $fullscreen);
    $this->sdl->ffi->SDL_SyncWindow($this->window);
    $this->resizeFromWindow($this->sdl);
    return $ok;
  }

  public function maximize(): bool {
    if ($this->closed || $this->window === null || $this->sdl === null) {
      return false;
    }
    $ok = (bool)$this->sdl->ffi->SDL_MaximizeWindow($this->window);
    $this->sdl->ffi->SDL_SyncWindow($this->window);
    $this->resizeFromWindow($this->sdl);
    return $ok;
  }

  public function restore(): bool {
    if ($this->closed || $this->window === null || $this->sdl === null) {
      return false;
    }
    $ok = (bool)$this->sdl->ffi->SDL_RestoreWindow($this->window);
    $this->sdl->ffi->SDL_SyncWindow($this->window);
    $this->resizeFromWindow($this->sdl);
    return $ok;
  }

  public function render(): void {
    if ($this->closed) {
      return;
    }
    $this->flushInvalidation();
    if (!$this->context->renderDirty()) {
      return;
    }
    $this->gridRenderer->beginFrame($this->ffiWidth->cdata, $this->ffiHeight->cdata, $this->columns, $this->rows, $this->root->theme()->bg);
    $this->root->render($this->gridRenderer);
    $this->gridRenderer->present();
    $this->context->clearRender();
  }

  public function handleEvent(SDL $sdl, mixed $event): bool {
    if ($this->closed) {
      return false;
    }
    if ($event->type === SDL::SDL_EVENT_WINDOW_CLOSE_REQUESTED) {
      $this->close($sdl);
      return true;
    }
    if (
      $event->type === SDL::SDL_EVENT_WINDOW_RESIZED ||
      $event->type === SDL::SDL_EVENT_WINDOW_MAXIMIZED ||
      $event->type === SDL::SDL_EVENT_WINDOW_RESTORED ||
      $event->type === SDL::SDL_EVENT_WINDOW_EXPOSED
    ) {
      $this->resizeFromWindow($sdl);
      return true;
    }
    if ($event->type === SDL::SDL_EVENT_TEXT_INPUT) {
      $text = \FFI::string($event->text->text);
      if ($text !== '') {
        $this->focus->dispatch(InputEvent::text($text));
      }
      return true;
    }
    if ($event->type === SDL::SDL_EVENT_KEY_DOWN) {
      $key = $this->mapKey((int)$event->key->key);
      if ($key !== '') {
        $this->focus->dispatch(InputEvent::key($key, $this->modifiers((int)$event->key->mod)));
      }
      return true;
    }
    if ($event->type === SDL::SDL_EVENT_KEY_UP) {
      $key = $this->mapKey((int)$event->key->key);
      if ($key !== '') {
        $this->focus->dispatch(InputEvent::keyRelease($key, $this->modifiers((int)$event->key->mod)));
      }
      return true;
    }
    return false;
  }

  protected function resizeFromWindow(SDL $sdl): void {
    $sdl->ffi->SDL_GetWindowSize($this->window, \FFI::addr($this->ffiWidth), \FFI::addr($this->ffiHeight));
    $this->columns = max(1, intdiv($this->ffiWidth->cdata, $this->gridRenderer->cellWidth()));
    $this->rows = $this->gridRenderer->rowsForHeight($this->ffiHeight->cdata);
    $this->context->invalidateLayout();
  }

  protected function flushInvalidation(): void {
    if ($this->context->layoutDirty()) {
      $this->root->setFrame(new Rect(0, 0, $this->columns, $this->rows));
      $this->context->clearLayout();
    }
    if ($this->context->focusDirty()) {
      $this->focus->rebuild($this->context->takeRequestedFocus());
      $this->context->clearFocus();
    }
  }

  protected function mapKey(int $key): string {
    $mapped = match ($key) {
      SDL::KEY_RETURN => 'Enter',
      SDL::KEY_ESCAPE => 'Escape',
      SDL::KEY_BACKSPACE => 'Backspace',
      SDL::KEY_TAB => 'Tab',
      SDL::KEY_SPACE => 'Space',
      SDL::KEY_DELETE => 'Delete',
      SDL::KEY_INSERT => 'Insert',
      SDL::KEY_HOME => 'Home',
      SDL::KEY_PAGEUP => 'PageUp',
      SDL::KEY_END => 'End',
      SDL::KEY_PAGEDOWN => 'PageDown',
      SDL::KEY_KP_7 => 'Home',
      SDL::KEY_KP_9 => 'PageUp',
      SDL::KEY_KP_1 => 'End',
      SDL::KEY_KP_3 => 'PageDown',
      SDL::KEY_LEFT => 'Left',
      SDL::KEY_RIGHT => 'Right',
      SDL::KEY_UP => 'Up',
      SDL::KEY_DOWN => 'Down',
      SDL::KEY_F1 => 'F1',
      SDL::KEY_F2 => 'F2',
      SDL::KEY_F3 => 'F3',
      SDL::KEY_F4 => 'F4',
      SDL::KEY_F5 => 'F5',
      SDL::KEY_F6 => 'F6',
      SDL::KEY_F7 => 'F7',
      SDL::KEY_F8 => 'F8',
      SDL::KEY_F9 => 'F9',
      SDL::KEY_F10 => 'F10',
      SDL::KEY_F11 => 'F11',
      SDL::KEY_F12 => 'F12',
      default => '',
    };
    if ($mapped !== '') {
      return $mapped;
    }
    if ($key >= 97 && $key <= 122) {
      return chr($key);
    }
    if ($key >= 65 && $key <= 90) {
      return chr($key);
    }
    return '';
  }

  protected function modifiers(int $mod): array {
    return [
      'raw' => $mod,
      'shift' => (bool)($mod & SDL::MOD_SHIFT),
      'ctrl' => (bool)($mod & SDL::MOD_CTRL),
      'alt' => (bool)($mod & SDL::MOD_ALT),
    ];
  }

}
