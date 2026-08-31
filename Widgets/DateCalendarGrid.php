<?php

namespace SPTK\Widgets;

use SPTK\Core\Element;
use SPTK\Core\InputAction;
use SPTK\Core\InputEvent;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;

/**
 * Focusable six-week month grid for DatePicker.
 */
class DateCalendarGrid extends Element {

  protected const COLUMNS = 7;
  protected const ROWS = 6;
  protected const SLOT_WIDTH = 4;

  protected \DateTimeImmutable $value;
  protected int $visibleYear;
  protected int $visibleMonth;
  protected $onChange = null;

  public function __construct(string $name = '', string $value = '') {
    parent::__construct($name);
    $this->focusable = true;
    $this->setPreferredRows(self::ROWS);
    $this->setPreferredColumns(self::COLUMNS * self::SLOT_WIDTH);
    $this->value = $this->parseDate($value === '' ? date('Y-m-d') : $value);
    $this->visibleYear = (int)$this->value->format('Y');
    $this->visibleMonth = (int)$this->value->format('n');
  }

  public function setOnChange(callable $onChange): static {
    $this->onChange = $onChange;
    return $this;
  }

  public function setValue(string $value): static {
    $date = $this->parseDate($value);
    $this->value = $date;
    $this->visibleYear = (int)$date->format('Y');
    $this->visibleMonth = (int)$date->format('n');
    $this->invalidateRender();
    return $this;
  }

  public function setVisibleMonth(int $year, int $month): static {
    $year = max(1, $year);
    $month = max(1, min(12, $month));
    $this->visibleYear = $year;
    $this->visibleMonth = $month;
    $this->invalidateRender();
    return $this;
  }

  public function getValue(): string {
    return $this->value->format('Y-m-d');
  }

  public function visibleYear(): int {
    return $this->visibleYear;
  }

  public function visibleMonth(): int {
    return $this->visibleMonth;
  }

  public function handle(InputEvent $event): bool {
    if ($event->type !== 'key') {
      return false;
    }
    $ctrl = !empty($event->modifiers['ctrl']);
    if (InputAction::left($event, 'calendar')) {
      $this->moveDays(-1);
    } else if (InputAction::right($event, 'calendar')) {
      $this->moveDays(1);
    } else if (InputAction::up($event, 'calendar')) {
      $this->moveDays(-7);
    } else if (InputAction::down($event, 'calendar')) {
      $this->moveDays(7);
    } else if (InputAction::home($event, 'calendar')) {
      $this->moveDays(-$this->weekdayIndex($this->value));
    } else if (InputAction::end($event, 'calendar')) {
      $this->moveDays(6 - $this->weekdayIndex($this->value));
    } else if (InputAction::pageUp($event, 'calendar')) {
      $ctrl ? $this->moveMonths(-12) : $this->moveMonths(-1);
    } else if (InputAction::pageDown($event, 'calendar')) {
      $ctrl ? $this->moveMonths(12) : $this->moveMonths(1);
    } else {
      return false;
    }
    return true;
  }

  protected function paint(RenderTarget $target): void {
    $target->fill($this->frame, ' ', $this->theme->fg, $this->theme->bg);
    $first = $this->firstVisibleDate();
    for ($week = 0; $week < self::ROWS; $week++) {
      for ($day = 0; $day < self::COLUMNS; $day++) {
        $date = $first->modify('+' . ($week * self::COLUMNS + $day) . ' days');
        $this->paintDay($target, $week, $day, $date);
      }
    }
  }

  protected function paintDay(RenderTarget $target, int $week, int $day, \DateTimeImmutable $date): void {
    $x = $this->frame->x + $day * self::SLOT_WIDTH;
    $y = $this->frame->y + $week;
    if ($x >= $this->frame->right() || $y >= $this->frame->bottom()) {
      return;
    }
    $width = min(self::SLOT_WIDTH, max(0, $this->frame->right() - $x));
    if ($width <= 0) {
      return;
    }
    $currentMonth = (int)$date->format('Y') === $this->visibleYear && (int)$date->format('n') === $this->visibleMonth;
    $selected = $date->format('Y-m-d') === $this->getValue();
    $fg = $currentMonth ? $this->theme->fg : $this->theme->muted;
    $bg = $this->theme->bg;
    if ($selected) {
      $fg = $this->focused ? $this->theme->cursorFg : $this->theme->inactiveCursorFg;
      $bg = $this->focused ? $this->theme->cursorBg : $this->theme->inactiveCursorBg;
    }
    if ($selected) {
      $target->fill(new Rect($x, $y, $width, 1), ' ', $fg, $bg);
    }
    $textX = $x + min(1, max(0, $width - 1));
    $textWidth = min(2, max(0, $this->frame->right() - $textX));
    if ($textWidth <= 0) {
      return;
    }
    $text = str_pad($date->format('j'), $textWidth, ' ', STR_PAD_LEFT);
    $target->write($textX, $y, mb_substr($text, 0, $textWidth), $fg, $bg);
  }

  protected function moveDays(int $days): void {
    $candidate = $this->value->modify(($days >= 0 ? '+' : '') . $days . ' days');
    if ((int)$candidate->format('Y') !== $this->visibleYear || (int)$candidate->format('n') !== $this->visibleMonth) {
      return;
    }
    $this->value = $candidate;
    $this->changed();
  }

  protected function moveMonths(int $months): void {
    $this->value = $this->shiftMonths($this->value, $months);
    $this->syncVisibleToValue();
    $this->changed();
  }

  protected function changed(): void {
    $this->invalidateRender();
    if ($this->onChange !== null) {
      ($this->onChange)($this);
    }
  }

  protected function syncVisibleToValue(): void {
    $this->visibleYear = (int)$this->value->format('Y');
    $this->visibleMonth = (int)$this->value->format('n');
  }

  protected function shiftMonths(\DateTimeImmutable $date, int $months): \DateTimeImmutable {
    $year = (int)$date->format('Y');
    $month = (int)$date->format('n') + $months;
    while ($month < 1) {
      $month += 12;
      $year--;
    }
    while ($month > 12) {
      $month -= 12;
      $year++;
    }
    $day = min((int)$date->format('j'), cal_days_in_month(CAL_GREGORIAN, $month, $year));
    return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
  }

  protected function firstVisibleDate(): \DateTimeImmutable {
    $first = new \DateTimeImmutable(sprintf('%04d-%02d-01', $this->visibleYear, $this->visibleMonth));
    return $first->modify('-' . $this->weekdayIndex($first) . ' days');
  }

  protected function weekdayIndex(\DateTimeImmutable $date): int {
    return (int)$date->format('N') - 1;
  }

  protected function parseDate(string $value): \DateTimeImmutable {
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
      throw new \InvalidArgumentException('Date must use YYYY-MM-DD.');
    }
    return $date;
  }

}
