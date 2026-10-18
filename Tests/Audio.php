<?php

define('APP_DIR', __DIR__);
define('APP_NAMESPACE', 'SPTKTEST');
require_once APP_DIR . '/SPTK/App.php';

use SPTK\Audio\{Player, WavSource};
use SPTK\Events\EventLoop;
use SPTK\SDLWrapper\SDL;

/** Fail one audio expectation with its reason. */
function expectAudio(bool $condition, string $message): void {
  if (!$condition) {
    throw new RuntimeException($message);
  }
}

putenv('SDL_AUDIODRIVER=dummy');
$sdl = new SDL();
expectAudio($sdl->ffi->SDL_Init(0), 'SDL initializes for dummy audio.');
$path = tempnam(sys_get_temp_dir(), 'sptk-audio-');
$samples = pack('v*', 32768, 40000, 50000, 32768);
$wav = 'RIFF' . pack('V', 36 + strlen($samples)) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, 48000, 96000, 2, 16)
  . 'data' . pack('V', strlen($samples)) . $samples;
file_put_contents($path, $wav);
try {
  $source = new WavSource($sdl, $path);
  expectAudio($source->spec()['freq'] === 48000, 'WAV sample rate is preserved.');
  expectAudio(strlen($source->read(2)) === 4 && strlen($source->read(8)) === 4 && $source->read(1) === '', 'WAV reads bounded PCM frames.');
  $source->rewind();
  expectAudio(strlen($source->read(1)) === 2, 'WAV source rewinds for looping.');
  $player = new Player($sdl, new EventLoop());
  $player->playMusic($path, true, 30);
  expectAudio($player->playingMusic(), 'Music starts.');
  $first = $player->playSound($path);
  $second = $player->playSound($path);
  expectAudio($first !== $second && $player->playingSound($first) && $player->playingSound($second), 'Effects overlap independently.');
  $player->stopSound($first);
  expectAudio(!$player->playingSound($first) && $player->playingSound($second), 'Stopping one effect preserves another.');
  $player->playMusic($path);
  expectAudio($player->playingMusic(), 'Replacing music leaves one active music slot.');
  $player->stopMusic(30);
  usleep(40000);
  $player->tick();
  expectAudio(!$player->playingMusic(), 'Fade-out releases the music slot.');
  $player->playMusic($path);
  usleep(200000);
  $player->tick();
  usleep(160000);
  $player->tick();
  expectAudio(!$player->playingMusic(), 'A finished music source releases its slot.');
  $player->close();
  $player->close();
} finally {
  unlink($path);
  $sdl->close();
}
echo "Audio checks passed\n";
