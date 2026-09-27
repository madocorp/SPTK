<?php

namespace SPTK\Widgets\Button;

use SPTK\Core\{Color, Widget, Window};
use SPTK\Events\{EventContext, EventDefinition, EventDispatcher, WidgetEventEmitter};
use SPTK\Rendering\{GridWriter, TextMetrics};

/** Runs an action when pressed and displays an optional highlighted hotkey. */
final class Button extends Widget {

  use WidgetEventEmitter;

  private bool $activated = false;
  private ?Window $window = null;

  /** Create a button for a static action or a screen identifier. */
  public function __construct(
    private string $label,
    private ?string $hotkey,
    private ?string $action,
    private Color $foreground,
    private Color $backgroundColor,
    private Color $highlight,
    private ?string $screenId = null,
  ) {
  }

  /** Bind the owning window for screen selection. */
  public function setWindow(Window $window): void {
    $this->window = $window;
  }

  /** Return the normalized hotkey for screen-level registration. */
  public function hotkey(): ?string {
    return $this->hotkey;
  }

  /** Return the screen selected by this button, if any. */
  public function screenId(): ?string {
    return $this->screenId;
  }

  /** Mark whether this button represents the current screen. */
  public function setActivated(bool $activated): void {
    $this->activated = $activated;
  }

  /** Report whether this button represents the current screen. */
  public function activated(): bool {
    return $this->activated;
  }

  /** Run the button action for Return or its hotkey. */
  public function press(mixed $input = null): void {
    if ($this->screenId !== null) {
      $this->window?->setCurrentScreenId($this->screenId);
    } else if ($this->action !== null) {
      $event = new EventDefinition('activate', null, $this->action);
      (new EventDispatcher())->dispatch([$event], new EventContext('activate', $this, $input), false);
    }
  }

  /** Return the normal color for the full pixel background around grid cells. */
  public function background(): Color {
    return $this->backgroundColor;
  }

  /** Paint the hotkey before the label using the current button colors. */
  public function paint(GridWriter $writer): void {
    $foreground = $this->activated ? $this->backgroundColor : $this->foreground;
    $background = $this->activated ? $this->foreground : $this->backgroundColor;
    $writer->fill($foreground, $background);
    $x = 1;
    if ($this->hotkey !== null) {
      $key = strtoupper($this->hotkey);
      $writer->write($x, 0, $key, $this->highlight, $background);
      $x += TextMetrics::width($key);
      $writer->write($x, 0, ' ', $foreground, $background);
      $x++;
    }
    $writer->write($x, 0, $this->label, $foreground, $background);
  }

  /** Buttons do not enter input mode. */
  public function handleInput(mixed $event): bool {
    return false;
  }

  /** Return the text width including the optional hotkey and separating space. */
  public function preferredWidth(): ?int {
    return TextMetrics::width($this->label) + ($this->hotkey === null ? 0 : TextMetrics::width(strtoupper($this->hotkey)) + 1) + 2;
  }

  /** Buttons use one grid row. */
  public function preferredHeight(): ?int {
    return 1;
  }

}
