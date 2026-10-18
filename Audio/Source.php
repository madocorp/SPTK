<?php

namespace SPTK\Audio;

/** Supplies one playback cursor of PCM frames to the audio player. */
interface Source {

  /** Return the SDL sample format, channel count, and sample rate. */
  public function spec(): array;

  /** Return up to the requested number of frames, or an empty string at end. */
  public function read(int $frames): string;

  /** Return this playback cursor to its beginning for looping. */
  public function rewind(): void;

}
