<?php

namespace SPTK\Widgets\StatusBar;

use SPTK\Core\{Color, Style, Widget};
use SPTK\Events\{KeyNormalizer, WidgetEventEmitter};
use SPTK\Rendering\GridWriter;
use SPTK\SDLWrapper\SDL;

/** Displays explicit help, progress, alerts and decisions without following tile focus. */
final class StatusBar extends Widget {

  use WidgetEventEmitter;

  private string $shown = '';
  private string $kind = 'empty';
  private string $behavior = 'empty';
  private bool $locked = false;
  private ?array $continuous = null;
  private int $generation = 0;
  private ?array $confirmation = null;
  private bool $consumeTextInput = false;
  private $activate = null;
  private $dismiss = null;
  private $schedule = null;

  public function __construct(private readonly Style $style = new Style()) {
  }

  /** Let the owning Screen present and dismiss the status as a focus overlay. */
  public function setFocusHandlers(callable $activate, callable $dismiss): void {
    $this->activate = $activate;
    $this->dismiss = $dismiss;
  }

  /** Bind a one-shot timer supplied by the owning screen. */
  public function setScheduler(callable $schedule): void {
    $this->schedule = $schedule;
  }

  public function text(): string {
    return $this->shown;
  }

  public function kind(): string {
    return $this->kind;
  }

  public function behavior(): string {
    return $this->behavior;
  }

  public function locked(): bool {
    return $this->locked;
  }

  /** Show help for the selected tile until Return or Escape dismisses it. */
  public function hint(string $text, string $behavior = 'modal', int $durationMs = 3000): void {
    $this->display($text, 'hint', $behavior, $durationMs);
  }

  public function info(string $text, string $behavior = 'modal', int $durationMs = 3000, bool $lock = false): void {
    $this->display($text, 'info', $behavior, $durationMs, $lock);
  }

  public function notice(string $text, string $behavior = 'modal', int $durationMs = 3000): void {
    $this->display($text, 'notice', $behavior, $durationMs);
  }

  public function warning(string $text, string $behavior = 'modal', int $durationMs = 3000): void {
    $this->display($text, 'warning', $behavior, $durationMs);
  }

  public function error(string $text, string $behavior = 'modal', int $durationMs = 3000): void {
    $this->display($text, 'error', $behavior, $durationMs);
  }

  /** Y or Return accepts; N or Escape declines. A legacy fourth callback keeps Escape as cancel. */
  public function confirm(string $message, callable $yes, callable $no, ?callable $legacyCancel = null, string $style = 'warning'): void {
    $this->validateStyle($style);
    $this->generation++;
    $this->locked = false;
    $this->confirmation = [$yes, $no, $legacyCancel];
    $this->message($message . ($legacyCancel === null ? '  Y/Return: yes  N/Esc: no' : '  Y/Return: yes  N: no  Esc: cancel'), $style, 'confirmation');
    $this->requestFocus();
  }

  public function confirming(): bool {
    return $this->confirmation !== null;
  }

  /** Handle modal input before the screen sends it to the previous tile. */
  public function handleBlockingInput(mixed $event): bool {
    if ($this->consumeTextInput) {
      if ($event->type === SDL::SDL_EVENT_TEXT_INPUT) {
        $this->consumeTextInput = false;
        return true;
      }
      if ($event->type === SDL::SDL_EVENT_KEY_DOWN) {
        $this->consumeTextInput = false;
      }
    }
    if ($this->confirmation !== null) {
      if ($event->type !== SDL::SDL_EVENT_KEY_DOWN) {
        return true;
      }
      $key = KeyNormalizer::keyName((int)$event->key->key, (int)$event->key->mod);
      $choice = match ($key) {
        'y', 'enter' => 0,
        'n' => 1,
        'escape' => $this->confirmation[2] === null ? 1 : 2,
        default => null,
      };
      if ($choice !== null) {
        $action = $this->confirmation[$choice];
        $this->confirmation = null;
        $this->acknowledge();
        $this->consumeTextInput = $key === 'y' || $key === 'n';
        $action();
      }
      return true;
    }
    return $this->locked;
  }

  /** Leave the bar empty until another explicit message arrives. */
  public function clear(): void {
    $this->continuous = null;
    $this->restoreContinuous();
  }

  /** Dismiss a modal message and reveal ongoing guidance, if any. */
  public function acknowledge(): void {
    if ($this->locked) {
      return;
    }
    $this->restoreContinuous();
  }

  /** Apply a color independently of how the message handles input. */
  public function display(string $text, string $style = 'notice', string $behavior = 'modal', int $durationMs = 3000, bool $lock = false): void {
    $this->validateStyle($style);
    if (!mb_check_encoding($text, 'UTF-8')) {
      throw new \InvalidArgumentException('Status message must be UTF-8 text.');
    }
    if (!in_array($behavior, ['modal', 'continuous', 'background'], true)) {
      throw new \InvalidArgumentException('Unknown status behavior: ' . $behavior);
    }
    if ($lock && $behavior !== 'modal') {
      throw new \InvalidArgumentException('Only a modal status can lock input.');
    }
    if ($behavior === 'continuous') {
      $this->continuous = [$text, $style];
      if (in_array($this->behavior, ['modal', 'confirmation'], true)) {
        return;
      }
    }
    if ($behavior === 'background') {
      if ($durationMs < 1 || $this->schedule === null) {
        throw new \LogicException('Background status requires a positive duration and a scheduler.');
      }
      if (in_array($this->behavior, ['modal', 'confirmation'], true) && !$this->locked) {
        return;
      }
    }
    $this->generation++;
    $this->locked = $lock;
    $this->confirmation = null;
    $this->message($text, $style, $behavior);
    if ($behavior === 'modal') {
      $this->requestFocus();
    } else {
      $this->dismissFocus();
    }
    if ($behavior === 'background') {
      $generation = $this->generation;
      ($this->schedule)($durationMs, function() use ($generation): void {
        if ($this->generation === $generation && $this->behavior === 'background') {
          $this->restoreContinuous();
        }
      });
    }
  }

  private function restoreContinuous(): void {
    $this->generation++;
    $this->shown = '';
    $this->kind = 'empty';
    $this->behavior = 'empty';
    $this->locked = false;
    $this->confirmation = null;
    if ($this->continuous !== null) {
      [$text, $style] = $this->continuous;
      $this->message($text, $style, 'continuous');
    } else {
      $this->emit('change');
    }
    $this->dismissFocus();
  }

  private function message(string $message, string $kind, string $behavior): void {
    if (!mb_check_encoding($message, 'UTF-8')) {
      throw new \InvalidArgumentException('Status message must be UTF-8 text.');
    }
    $this->shown = str_replace(["\n", "\r"], ' ', $message);
    $this->kind = $kind;
    $this->behavior = $behavior;
    $this->emit('change');
  }

  private function validateStyle(string $style): void {
    if (!in_array($style, ['hint', 'notice', 'info', 'warning', 'error'], true)) {
      throw new \InvalidArgumentException('Unknown status style: ' . $style);
    }
  }

  private function requestFocus(): void {
    if ($this->activate !== null) {
      ($this->activate)();
    }
  }

  private function dismissFocus(): void {
    if ($this->dismiss !== null) {
      ($this->dismiss)();
    }
  }

  /** Colored messages use their accent as the background. */
  public function paint(GridWriter $writer): void {
    $background = $this->background();
    $foreground = in_array($this->kind, ['info', 'warning', 'error'], true)
      ? $this->style->background : $this->style->foreground;
    $writer->fill($foreground, $background);
    $writer->write(0, 0, $this->shown, $foreground, $background);
  }

  public function background(): Color {
    return match ($this->kind) {
      'info' => $this->style->highlight,
      'warning' => $this->style->selected,
      'error' => $this->style->error,
      default => $this->style->background,
    };
  }

  public function canActivate(): bool {
    return false;
  }

  public function handleInput(mixed $event): bool {
    return false;
  }

  protected function defaultTip(bool $active): string {
    return 'Press H on a tile for help.';
  }
}
