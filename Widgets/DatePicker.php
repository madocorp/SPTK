<?php

namespace SPTK\Widgets;

use SPTK\Core\Element;
use SPTK\Core\Rect;
use SPTK\Core\RenderTarget;

/**
 * Calendar date picker with month and year controls.
 */
class DatePicker extends Element {

  protected DateCalendarGrid $grid;
  protected Selector $month;
  protected Selector $year;
  protected \DateTimeImmutable $value;
  protected int $rangeStart;
  protected int $rangeEnd;
  protected bool $syncing = false;
  protected $onChange = null;

  public function __construct(string $name = '', string $value = '', array $options = []) {
    parent::__construct($name);
    $todayYear = (int)date('Y');
    $this->rangeStart = (int)($options['yearStart'] ?? ($todayYear - 100));
    $this->rangeEnd = (int)($options['yearEnd'] ?? ($todayYear + 100));
    if ($this->rangeEnd < $this->rangeStart) {
      [$this->rangeStart, $this->rangeEnd] = [$this->rangeEnd, $this->rangeStart];
    }
    if (array_key_exists('onChange', $options) && is_callable($options['onChange'])) {
      $this->onChange = $options['onChange'];
    }
    $this->value = $this->parseDate($value === '' ? date('Y-m-d') : $value);
    $this->grid = new DateCalendarGrid($name === '' ? 'date-picker-grid' : $name . '-grid', $this->getValue());
    $this->grid->setOnChange(function(DateCalendarGrid $grid): void {
      $this->gridChanged($grid->getValue());
    });
    $this->month = new Selector($name === '' ? 'date-picker-month' : $name . '-month', $this->monthItems(), (int)$this->value->format('n'), [
      'title' => 'Select Month',
      'panelRows' => 12,
    ]);
    $this->year = new Selector($name === '' ? 'date-picker-year' : $name . '-year', $this->yearItems((int)$this->value->format('Y')), (int)$this->value->format('Y'), [
      'title' => 'Select Year',
      'panelRows' => 8,
    ]);
    $this->month->setOptions([
      'onChange' => function(Selector $selector): void {
        $this->controlChanged((int)$selector->getValue(), (int)$this->year->getValue());
      },
    ]);
    $this->year->setOptions([
      'onChange' => function(Selector $selector): void {
        $this->controlChanged((int)$this->month->getValue(), (int)$selector->getValue());
      },
    ]);
    $this->add($this->grid);
    $this->add($this->month);
    $this->add($this->year);
    $this->setPreferredRows(8);
    $this->setPreferredColumns(28);
    $this->syncControls();
  }

  public function setValue(mixed $value): static {
    $date = $this->parseDate((string)$value);
    if ($this->getValue() === $date->format('Y-m-d')) {
      return $this;
    }
    $this->value = $date;
    $this->grid->setValue($this->getValue());
    $this->syncControls();
    $this->invalidateRender();
    if ($this->onChange !== null) {
      ($this->onChange)($this);
    }
    return $this;
  }

  public function getValue(): string {
    return $this->value->format('Y-m-d');
  }

  public function grid(): DateCalendarGrid {
    return $this->grid;
  }

  public function monthSelector(): Selector {
    return $this->month;
  }

  public function yearSelector(): Selector {
    return $this->year;
  }

  public function setFrame(Rect $frame): void {
    $this->frame = $frame;
    $this->layout();
  }

  public function layout(): void {
    $controlsHeight = min(1, $this->frame->height);
    $monthWidth = intdiv($this->frame->width, 2);
    $yearWidth = max(0, $this->frame->width - $monthWidth);
    $this->month->setFrame(new Rect($this->frame->x, $this->frame->y, $monthWidth, $controlsHeight));
    $this->year->setFrame(new Rect($this->frame->x + $monthWidth, $this->frame->y, $yearWidth, $controlsHeight));
    $headerY = $this->frame->y + $controlsHeight;
    $gridY = $headerY + min(1, max(0, $this->frame->bottom() - $headerY));
    $this->grid->setFrame(new Rect($this->frame->x, $gridY, min(28, $this->frame->width), max(0, $this->frame->bottom() - $gridY)));
  }

  protected function paint(RenderTarget $target): void {
    $target->fill($this->frame, ' ', $this->theme->fg, $this->theme->bg);
    $headerY = $this->frame->y + 1;
    if ($headerY < $this->frame->bottom()) {
      $days = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
      foreach ($days as $index => $day) {
        $x = $this->frame->x + $index * 4 + 1;
        if ($x < $this->frame->right()) {
          $target->write($x, $headerY, mb_substr($day, 0, max(0, $this->frame->right() - $x)), $this->theme->muted, $this->theme->bg);
        }
      }
    }
  }

  protected function gridChanged(string $value): void {
    $date = $this->parseDate($value);
    if ($this->getValue() === $value) {
      return;
    }
    $this->value = $date;
    $this->syncControls();
    $this->invalidateRender();
    if ($this->onChange !== null) {
      ($this->onChange)($this);
    }
  }

  protected function controlChanged(int $month, int $year): void {
    if ($this->syncing) {
      return;
    }
    $month = max(1, min(12, $month));
    $year = max(1, $year);
    $day = min((int)$this->value->format('j'), cal_days_in_month(CAL_GREGORIAN, $month, $year));
    $this->value = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    $this->grid->setValue($this->getValue());
    $this->syncControls();
    $this->invalidateRender();
    if ($this->onChange !== null) {
      ($this->onChange)($this);
    }
  }

  protected function syncControls(): void {
    $this->syncing = true;
    $year = (int)$this->value->format('Y');
    $this->year->setItems($this->yearItems($year));
    $this->month->setValue((int)$this->value->format('n'));
    $this->year->setValue($year);
    $this->grid->setVisibleMonth($year, (int)$this->value->format('n'));
    $this->syncing = false;
  }

  protected function monthItems(): array {
    return [
      ['value' => 1, 'text' => 'January'],
      ['value' => 2, 'text' => 'February'],
      ['value' => 3, 'text' => 'March'],
      ['value' => 4, 'text' => 'April'],
      ['value' => 5, 'text' => 'May'],
      ['value' => 6, 'text' => 'June'],
      ['value' => 7, 'text' => 'July'],
      ['value' => 8, 'text' => 'August'],
      ['value' => 9, 'text' => 'September'],
      ['value' => 10, 'text' => 'October'],
      ['value' => 11, 'text' => 'November'],
      ['value' => 12, 'text' => 'December'],
    ];
  }

  protected function yearItems(int $selectedYear): array {
    $start = min($this->rangeStart, $selectedYear);
    $end = max($this->rangeEnd, $selectedYear);
    $items = [];
    for ($year = $start; $year <= $end; $year++) {
      $items[$year] = (string)$year;
    }
    return $items;
  }

  protected function parseDate(string $value): \DateTimeImmutable {
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
      throw new \InvalidArgumentException('Date must use YYYY-MM-DD.');
    }
    return $date;
  }

}
