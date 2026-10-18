<?php

namespace SPTK\Audio;

use SPTK\SDLWrapper\SDL;

/** Loads a WAV file into memory and supplies its frames in bounded chunks. */
final class WavSource implements Source {

  private string $samples;
  private array $spec;
  private int $frameBytes;
  private int $offset = 0;

  public function __construct(SDL $sdl, string $path) {
    $spec = $sdl->ffi->new('SDL_AudioSpec');
    $buffer = $sdl->ffi->new('Uint8 *');
    $length = $sdl->ffi->new('Uint32');
    $sdl->checkReturnValue($sdl->ffi->SDL_LoadWAV($path, \FFI::addr($spec), \FFI::addr($buffer), \FFI::addr($length)), 'SDL_LoadWAV');
    try {
      $this->samples = \FFI::string($buffer, (int)$length->cdata);
    } finally {
      $sdl->ffi->SDL_free($buffer);
    }
    $this->spec = ['format' => (int)$spec->format, 'channels' => (int)$spec->channels, 'freq' => (int)$spec->freq];
    $this->frameBytes = intdiv((int)$spec->format & 0xff, 8) * (int)$spec->channels;
    if ($this->frameBytes < 1 || strlen($this->samples) % $this->frameBytes !== 0) {
      throw new \RuntimeException('WAV has an invalid PCM frame size.');
    }
  }

  public function spec(): array {
    return $this->spec;
  }

  public function read(int $frames): string {
    if ($frames < 1) {
      throw new \InvalidArgumentException('Frame count must be positive.');
    }
    $bytes = substr($this->samples, $this->offset, $frames * $this->frameBytes);
    $this->offset += strlen($bytes);
    return $bytes;
  }

  public function rewind(): void {
    $this->offset = 0;
  }

}
