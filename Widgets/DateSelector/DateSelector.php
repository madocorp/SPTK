<?php

namespace SPTK\Widgets\DateSelector;

use SPTK\Core\{Color, Style, Widget};
use SPTK\Events\{KeyNormalizer, WidgetEventEmitter};
use SPTK\Rendering\GridWriter;
use SPTK\SDLWrapper\SDL;

/** Selects a calendar date with month navigation and direct eight-digit entry. */
final class DateSelector extends Widget {

  use WidgetEventEmitter;

  private \DateTimeImmutable $date;
  private bool $active = false;
  private ?string $draft = null;

  /** Parse a valid four-digit ISO date without timezone-dependent arithmetic. */
  private static function parseDate(string $value): \DateTimeImmutable {
    if (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $value, $parts) !== 1 || !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])) {
      throw new \InvalidArgumentException('DateSelector requires a valid date in Y-m-d format (years 0001–9999).');
    }
    return \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
  }

  /** Initialize the selected date, defaulting to today. */
  public function __construct(?string $value = null, private readonly Style $style = new Style()) {
    $this->setValue($value ?? date('Y-m-d'));
    $this->on('activate', $this->activate(...));
    $this->on('deactivate', $this->deactivate(...));
  }

  /** Return the last complete selected date in ISO format. */
  public function getValue(): string {
    return $this->date->format('Y-m-d');
  }

  /** Select a valid date without emitting a user change event. */
  public function setValue(string $value): void {
    $this->date = self::parseDate($value);
    $this->draft = null;
  }

  /** Return the displayed date field, including an incomplete draft. */
  public function dateText(): string {
    if ($this->draft === null) {
      return $this->getValue();
    }
    $digits = str_pad($this->draft, 8, '_');
    return substr($digits, 0, 4) . '-' . substr($digits, 4, 2) . '-' . substr($digits, 6, 2);
  }

  /** Report whether this widget currently receives keyboard input. */
  public function active(): bool {
    return $this->active;
  }

  /** Suggest enough width for seven four-column weekday slots. */
  public function preferredWidth(): ?int {
    return 28;
  }

  /** Reserve a heading, weekday row, six weeks, and a date field. */
  public function preferredHeight(): ?int {
    return 9;
  }

  /** Return the inherited tile background. */
  public function background(): Color {
    return $this->style->background;
  }

  /** Paint a Monday-first calendar and the editable date field. */
  public function paint(GridWriter $writer): void {
    $writer->fill($this->style->foreground, $this->style->background);
    $width = min(28, $writer->width());
    $heading = $this->date->format('F Y');
    $writer->write(max(0, intdiv($width - strlen($heading), 2)), 0, $heading, $this->style->foreground, $this->style->background);
    $writer->write(0, 1, 'Mon Tue Wed Thu Fri Sat Sun ', $this->style->foreground, $this->style->background);
    $first = $this->date->modify('first day of this month');
    $offset = (int)$first->format('N') - 1;
    $start = $first->modify("-{$offset} days");
    $month = $this->date->format('Y-m');
    $selected = $this->getValue();
    $dim = new Color((int)round(($this->style->foreground->r + $this->style->background->r) / 2), (int)round(($this->style->foreground->g + $this->style->background->g) / 2), (int)round(($this->style->foreground->b + $this->style->background->b) / 2));
    for ($week = 0; $week < 6; $week++) {
      for ($weekday = 0; $weekday < 7; $weekday++) {
        $day = $start->modify('+' . ($week * 7 + $weekday) . ' days');
        $year = (int)$day->format('Y');
        if ($year < 1 || $year > 9999) {
          continue;
        }
        $current = $day->format('Y-m-d') === $selected;
        $fg = $current ? $this->style->selected : ($day->format('Y-m') === $month ? $this->style->foreground : $dim);
        $bg = $current && $this->active && $this->draft === null ? $this->style->cursorBackground : $this->style->background;
        $writer->write($weekday * 4, $week + 2, sprintf(' %2d ', (int)$day->format('j')), $fg, $bg);
      }
    }
    $fieldBg = $this->draft === null ? $this->style->background : $this->style->cursorBackground;
    $writer->write(max(0, intdiv($width - 10, 2)), 8, $this->dateText(), $this->style->foreground, $fieldBg);
  }

  /** Apply direct entry and calendar movement while the widget is active. */
  public function handleInput(mixed $event): bool {
    if ($event->type === SDL::SDL_EVENT_TEXT_INPUT) {
      $text = \FFI::string($event->text->text);
      if (preg_match('/^[0-9]+$/D', $text) !== 1) {
        return false;
      }
      $before = $this->getValue();
      $draft = $this->draft ?? '';
      $this->draft = substr((strlen($draft) === 8 ? '' : $draft) . $text, 0, 8);
      if (strlen($this->draft) === 8) {
        try {
          $this->date = self::parseDate($this->dateText());
        } catch (\InvalidArgumentException $error) {
          // Keep the invalid draft visible so Backspace can correct it.
        }
      }
      if ($before !== $this->getValue()) {
        $this->emit('change');
      }
      return true;
    }
    if ($event->type !== SDL::SDL_EVENT_KEY_DOWN) {
      return false;
    }
    $modifiers = (int)$event->key->mod;
    $key = KeyNormalizer::normalize((int)$event->key->key, $modifiers);
    if ($key === SDL::KEY_BACKSPACE || $key === SDL::KEY_DELETE) {
      $this->draft = $key === SDL::KEY_DELETE ? '' : substr($this->draft ?? $this->date->format('Ymd'), 0, -1);
      return true;
    }
    $before = $this->getValue();
    $candidate = $this->navigate($key, $modifiers);
    if ($candidate === null) {
      return false;
    }
    $this->draft = null;
    $year = (int)$candidate->format('Y');
    $arrow = in_array($key, [SDL::KEY_LEFT, SDL::KEY_RIGHT, SDL::KEY_UP, SDL::KEY_DOWN], true);
    if ($year >= 1 && $year <= 9999 && (!$arrow || $candidate->format('Y-m') === $this->date->format('Y-m'))) {
      $this->date = $candidate;
    }
    if ($before !== $this->getValue()) {
      $this->emit('change');
    }
    return true;
  }

  /** Keep the last complete date when Escape releases the widget. */
  public function releaseNotification(int $key, int $modifiers): ?string {
    return $key === SDL::KEY_ESCAPE ? 'accept' : parent::releaseNotification($key, $modifiers);
  }

  /** Compute a date reached through a recognized calendar key. */
  private function navigate(int $key, int $modifiers): ?\DateTimeImmutable {
    if ($key === SDL::KEY_PAGEUP || $key === SDL::KEY_PAGEDOWN) {
      $months = ($modifiers & SDL::MOD_SHIFT) !== 0 ? 12 : 1;
      $months *= $key === SDL::KEY_PAGEUP ? -1 : 1;
      $first = $this->date->modify('first day of this month')->modify("{$months} months");
      return $first->setDate((int)$first->format('Y'), (int)$first->format('n'), min((int)$this->date->format('j'), (int)$first->format('t')));
    }
    return match ($key) {
      SDL::KEY_LEFT => $this->date->modify('-1 day'),
      SDL::KEY_RIGHT => $this->date->modify('+1 day'),
      SDL::KEY_UP => $this->date->modify('-7 days'),
      SDL::KEY_DOWN => $this->date->modify('+7 days'),
      SDL::KEY_HOME => $this->date->modify('first day of this month'),
      SDL::KEY_END => $this->date->modify('last day of this month'),
      SDL::KEY_SPACE => self::parseDate(date('Y-m-d')),
      default => null,
    };
  }

  /** Mark the calendar active after tile activation. */
  private function activate(): void {
    $this->active = true;
  }

  /** Discard incomplete date text after leaving input mode. */
  private function deactivate(): void {
    $this->active = false;
    $this->draft = null;
  }

}
