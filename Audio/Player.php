<?php

namespace SPTK\Audio;

use SPTK\Events\EventLoop;
use SPTK\SDLWrapper\SDL;

/** Plays one music source and overlapping effects through one SDL3 device. */
final class Player {

  private const TICK_MS = 25;
  private const QUEUE_MS = 350;
  private SDL $sdl;
  private EventLoop $loop;
  private int $device;
  private int $timer;
  private ?array $music = null;
  private array $sounds = [];
  private int $nextSound = 1;
  private bool $closed = false;

  public function __construct(SDL $sdl, EventLoop $loop) {
    $this->sdl = $sdl;
    $this->loop = $loop;
    $sdl->checkReturnValue($sdl->ffi->SDL_InitSubSystem(SDL::SDL_INIT_AUDIO), 'SDL_InitSubSystem(audio)');
    $this->device = (int)$sdl->ffi->SDL_OpenAudioDevice(SDL::SDL_AUDIO_DEVICE_DEFAULT_PLAYBACK, null);
    if ($this->device === 0) {
      $sdl->ffi->SDL_QuitSubSystem(SDL::SDL_INIT_AUDIO);
      throw new \RuntimeException('SDL_OpenAudioDevice failed: ' . $sdl->error());
    }
    $this->timer = $loop->after(self::TICK_MS, $this->onTimer(...));
  }

  /** Start a WAV or PCM music source, replacing the previous track. */
  public function playMusic(Source|string $source, bool $loop = false, int $fadeInMs = 0): void {
    $this->validateFade($fadeInMs);
    $source = $this->source($source);
    $track = $this->createTrack($source, $loop, $fadeInMs);
    $this->stopMusic();
    $this->music = $track;
    try {
      $this->fill($this->music);
    } catch (\Throwable $error) {
      $this->stopMusic();
      throw $error;
    }
  }

  /** Stop music now or lower its gain over the requested duration. */
  public function stopMusic(int $fadeOutMs = 0): void {
    $this->validateFade($fadeOutMs);
    if ($this->music === null) {
      return;
    }
    if ($fadeOutMs === 0) {
      $this->destroy($this->music);
      $this->music = null;
      return;
    }
    $now = hrtime(true);
    $this->music['fadeFrom'] = $this->music['gain'];
    $this->music['fadeStart'] = $now;
    $this->music['fadeEnd'] = $now + $fadeOutMs * 1000000;
    $this->music['stopping'] = true;
  }

  /** Report whether the single music slot contains a track. */
  public function playingMusic(): bool {
    return $this->music !== null;
  }

  /** Play an effect independently and return its stop handle. */
  public function playSound(Source|string $source, float $gain = 1.0): int {
    if (!is_finite($gain) || $gain < 0) {
      throw new \InvalidArgumentException('Sound gain must be finite and nonnegative.');
    }
    $source = $this->source($source);
    $track = $this->createTrack($source, false, 0);
    try {
      $this->setGain($track, $gain);
      $this->fill($track);
    } catch (\Throwable $error) {
      $this->destroy($track);
      throw $error;
    }
    $id = $this->nextSound++;
    $this->sounds[$id] = $track;
    return $id;
  }

  /** Stop one active effect by its handle. */
  public function stopSound(int $id): void {
    if (!isset($this->sounds[$id])) {
      return;
    }
    $this->destroy($this->sounds[$id]);
    unset($this->sounds[$id]);
  }

  /** Report whether an effect handle still has an active stream. */
  public function playingSound(int $id): bool {
    return isset($this->sounds[$id]);
  }

  /** Fill active streams and advance their fades on the main event loop. */
  public function tick(): void {
    if ($this->music !== null) {
      $this->advanceMusic();
    }
    foreach (array_keys($this->sounds) as $id) {
      if ($this->advance($this->sounds[$id])) {
        $this->stopSound($id);
      }
    }
  }

  /** Reschedule one audio pump after the current event-loop callback. */
  private function onTimer(): void {
    try {
      $this->tick();
    } finally {
      if (!$this->closed) {
        $this->timer = $this->loop->after(self::TICK_MS, $this->onTimer(...));
      }
    }
  }

  /** Release streams, the timer, and the audio subsystem before SDL quits. */
  public function close(): void {
    if ($this->closed) {
      return;
    }
    $this->stopMusic();
    foreach (array_keys($this->sounds) as $id) {
      $this->stopSound($id);
    }
    $this->loop->removeTimer($this->timer);
    $this->sdl->ffi->SDL_CloseAudioDevice($this->device);
    $this->sdl->ffi->SDL_QuitSubSystem(SDL::SDL_INIT_AUDIO);
    $this->closed = true;
  }

  /** Turn a WAV filename into a separate playback cursor. */
  private function source(Source|string $source): Source {
    if ($this->closed) {
      throw new \LogicException('Audio player is closed.');
    }
    return is_string($source) ? new WavSource($this->sdl, $source) : $source;
  }

  /** Create one bound stream using the source's PCM format. */
  private function createTrack(Source $source, bool $loop, int $fadeInMs): array {
    $spec = $source->spec();
    $format = $spec['format'] ?? null;
    $channels = $spec['channels'] ?? null;
    $freq = $spec['freq'] ?? null;
    if (!is_int($format) || !is_int($channels) || $channels < 1 || !is_int($freq) || $freq < 1) {
      throw new \InvalidArgumentException('Invalid PCM source format.');
    }
    $frameBytes = intdiv($format & 0xff, 8) * $channels;
    if ($frameBytes < 1) {
      throw new \InvalidArgumentException('Invalid PCM sample size.');
    }
    $native = $this->sdl->ffi->new('SDL_AudioSpec');
    $native->format = $format;
    $native->channels = $channels;
    $native->freq = $freq;
    $stream = $this->sdl->ffi->SDL_CreateAudioStream(\FFI::addr($native), null);
    if (\FFI::isNull($stream)) {
      throw new \RuntimeException('SDL_CreateAudioStream failed: ' . $this->sdl->error());
    }
    try {
      $this->sdl->checkReturnValue($this->sdl->ffi->SDL_BindAudioStream($this->device, $stream), 'SDL_BindAudioStream');
      $gain = $fadeInMs > 0 ? 0.0 : 1.0;
      $this->sdl->checkReturnValue($this->sdl->ffi->SDL_SetAudioStreamGain($stream, $gain), 'SDL_SetAudioStreamGain');
    } catch (\Throwable $error) {
      $this->sdl->ffi->SDL_DestroyAudioStream($stream);
      throw $error;
    }
    $now = hrtime(true);
    return ['stream' => $stream, 'source' => $source, 'loop' => $loop, 'frameBytes' => $frameBytes,
      'freq' => $freq, 'eof' => false, 'drainedAt' => null, 'gain' => $gain,
      'fadeFrom' => $gain, 'fadeStart' => $now, 'fadeEnd' => $now + $fadeInMs * 1000000,
      'stopping' => false];
  }

  /** Update the music gain and dispose of it once its fade or data ends. */
  private function advanceMusic(): void {
    $track =& $this->music;
    $now = hrtime(true);
    if ($track['fadeEnd'] > $track['fadeStart']) {
      $progress = min(1.0, ($now - $track['fadeStart']) / ($track['fadeEnd'] - $track['fadeStart']));
      $target = $track['stopping'] ? 0.0 : 1.0;
      $this->setGain($track, $track['fadeFrom'] + ($target - $track['fadeFrom']) * $progress);
      if ($track['stopping'] && $progress >= 1.0) {
        $this->stopMusic();
        return;
      }
    }
    if ($this->advance($track)) {
      $this->stopMusic();
    }
  }

  /** Keep a bounded amount of PCM ahead of the playback device. */
  private function fill(array &$track): void {
    $target = (int)ceil($track['freq'] * self::QUEUE_MS / 1000) * $track['frameBytes'];
    $queued = $this->sdl->ffi->SDL_GetAudioStreamQueued($track['stream']);
    if ($queued < 0) {
      throw new \RuntimeException('SDL_GetAudioStreamQueued failed: ' . $this->sdl->error());
    }
    while (!$track['eof'] && $queued < $target) {
      $frames = min(4096, (int)ceil(($target - $queued) / $track['frameBytes']));
      $data = $track['source']->read($frames);
      if ($data === '') {
        if (!$track['loop']) {
          $track['eof'] = true;
          $this->sdl->checkReturnValue($this->sdl->ffi->SDL_FlushAudioStream($track['stream']), 'SDL_FlushAudioStream');
          break;
        }
        $track['source']->rewind();
        $data = $track['source']->read($frames);
        if ($data === '') {
          $track['eof'] = true;
          $this->sdl->checkReturnValue($this->sdl->ffi->SDL_FlushAudioStream($track['stream']), 'SDL_FlushAudioStream');
          break;
        }
      }
      if (strlen($data) > $frames * $track['frameBytes'] || strlen($data) % $track['frameBytes'] !== 0) {
        throw new \RuntimeException('PCM source returned incomplete or excessive frames.');
      }
      $this->sdl->checkReturnValue($this->sdl->ffi->SDL_PutAudioStreamData($track['stream'], $data, strlen($data)), 'SDL_PutAudioStreamData');
      $queued += strlen($data);
    }
  }

  /** Return true when a finished stream has had time to reach the device. */
  private function advance(array &$track): bool {
    $this->fill($track);
    if (!$track['eof']) {
      return false;
    }
    if ($this->sdl->ffi->SDL_GetAudioStreamQueued($track['stream']) > 0) {
      $track['drainedAt'] = null;
      return false;
    }
    $track['drainedAt'] ??= hrtime(true);
    return hrtime(true) - $track['drainedAt'] > 150000000;
  }

  /** Set a stream's current gain and remember it for later fades. */
  private function setGain(array &$track, float $gain): void {
    $this->sdl->checkReturnValue($this->sdl->ffi->SDL_SetAudioStreamGain($track['stream'], $gain), 'SDL_SetAudioStreamGain');
    $track['gain'] = $gain;
  }

  /** Destroy a stream after it is no longer used. */
  private function destroy(array $track): void {
    $this->sdl->ffi->SDL_DestroyAudioStream($track['stream']);
  }

  /** Reject fade durations that cannot be represented safely. */
  private function validateFade(int $milliseconds): void {
    if ($milliseconds < 0 || $milliseconds > 3600000) {
      throw new \InvalidArgumentException('Fade duration must be between 0 and 3600000 milliseconds.');
    }
  }

}
