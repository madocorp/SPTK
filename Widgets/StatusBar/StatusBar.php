<?php

namespace SPTK\Widgets\StatusBar;

use SPTK\Core\{Color, Style, Widget};
use SPTK\Events\WidgetEventEmitter;
use SPTK\Rendering\GridWriter;
use SPTK\Events\KeyNormalizer;
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\Text\Text;

/** Displays the focused widget's tip and temporary application notifications. */
final class StatusBar extends Widget {

  use WidgetEventEmitter;

  private Text $text;
  private string $shown = '';
  private string $kind = 'notice';
  private ?array $confirmation = null;
  private string $tip = '';
  private bool $consumeTextInput = false;

  /** Retain app font and inherited colors through the regular Text renderer. */
  public function __construct(private readonly Style $style = new Style()) {
    $this->text = new Text('', $style, false);
  }

  /** Return the text currently displayed for tests and application inspection. */
  public function text(): string {
    return $this->shown;
  }

  /** Show a tip when screen focus or widget activation changes. */
  public function showTip(string $tip): void {
    $this->tip = $tip;
    if ($this->confirmation === null) {
      $this->notice($tip);
    }
  }

  /** Temporarily display a real application status notification. */
  public function notify(string $message): void {
    $this->notice($message);
  }

  public function notice(string $message): void {
    $this->message($message, 'notice');
  }

  public function warning(string $message): void {
    $this->message($message, 'warning');
  }

  public function error(string $message): void {
    $this->message($message, 'error');
  }

  /** Ask a global Y/N/Esc question without moving focus. */
  public function confirm(string $message, callable $yes, callable $no, callable $cancel): void {
    $this->confirmation = [$yes, $no, $cancel];
    $this->warning($message . '  Y: yes  N: no  Esc: cancel');
  }

  public function confirming(): bool {
    return $this->confirmation !== null;
  }

  /** Consume all keys while a confirmation is pending. */
  public function handleConfirmation(mixed $event): bool {
    if ($this->consumeTextInput) {
      $this->consumeTextInput = false;
      if ($event->type === SDL::SDL_EVENT_TEXT_INPUT) {
        return true;
      }
    }
    if ($this->confirmation === null) {
      return false;
    }
    if ($event->type !== SDL::SDL_EVENT_KEY_DOWN) {
      return true;
    }
    $key = KeyNormalizer::keyName((int)$event->key->key, (int)$event->key->mod);
    $choice = match ($key) { 'y' => 0, 'n' => 1, 'escape' => 2, default => null };
    if ($choice !== null) {
      $actions = $this->confirmation;
      $this->confirmation = null;
      $this->notice($this->tip);
      $actions[$choice]();
      $this->consumeTextInput = $choice !== 2;
    }
    return true;
  }

  public function kind(): string {
    return $this->kind;
  }

  private function message(string $message, string $kind): void {
    $this->kind = $kind;
    $this->show(str_replace(["\n", "\r"], ' ', $message));
  }

  /** Avoid resetting the rendered text when the current tip has not changed. */
  private function show(string $text): void {
    if ($text !== $this->shown) {
      $this->text->setText($text);
      $this->shown = $text;
    }
  }

  /** Paint the current status using regular grid text. */
  public function paint(GridWriter $writer): void {
    $foreground = match ($this->kind) {
      'warning' => $this->style->selected,
      'error' => $this->style->error,
      default => $this->style->foreground,
    };
    $writer->fill($foreground, $this->style->background);
    $writer->write(0, 0, $this->shown, $foreground, $this->style->background);
  }

  /** Use the normal tile background around the status row. */
  public function background(): Color {
    return $this->text->background();
  }

  /** Leave Return in tile-navigation mode. */
  public function canActivate(): bool {
    return false;
  }

  /** Ignore direct input to the status tile. */
  public function handleInput(mixed $event): bool {
    return false;
  }

  /** Explain what the status tile displays when selected. */
  protected function defaultTip(bool $active): string {
    return 'Tips follow the selected widget; save and error messages appear here.';
  }

}
