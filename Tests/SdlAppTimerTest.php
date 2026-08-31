<?php

namespace SPTK\Tests;

use SPTK\Runtime\SdlApp;

class TimerProbeApp extends SdlApp {

  public float $now = 0;

  public function fire(float $now): void {
    $this->now = $now;
    $this->runTimers($now);
  }

  public function waitAt(float $now): int {
    $this->now = $now;
    return $this->eventWaitTimeoutMs($now);
  }

  protected function currentTimeMs(): float {
    return $this->now;
  }

}

return [
  'app timer fires after its interval' => function(): void {
    $app = new TimerProbeApp();
    $calls = [];
    $app->addTimer(100, function(int $id, float $now, float $elapsed) use (&$calls): void {
      $calls[] = [$id, (int)$now, (int)$elapsed];
    });
    $app->fire(99);
    assertSame([], $calls, 'Timer should not fire before it is due.');
    $app->fire(100);
    assertSame([[1, 100, 100]], $calls, 'Timer should fire when it reaches its interval.');
    $app->fire(199);
    assertSame([[1, 100, 100]], $calls, 'Repeating timer should wait for the next due time.');
    $app->fire(200);
    assertSame([[1, 100, 100], [1, 200, 100]], $calls, 'Repeating timer should fire again after another interval.');
  },

  'app timer can stop by returning false' => function(): void {
    $app = new TimerProbeApp();
    $calls = 0;
    $app->addTimer(50, function() use (&$calls): bool {
      $calls++;
      return false;
    });
    $app->fire(50);
    $app->fire(100);
    assertSame(1, $calls, 'Returning false should remove the timer.');
  },

  'app timer can update interval by return value' => function(): void {
    $app = new TimerProbeApp();
    $calls = [];
    $app->addTimer(50, function(int $id, float $now) use (&$calls): ?int {
      $calls[] = (int)$now;
      return count($calls) === 1 ? 150 : null;
    });
    $app->fire(50);
    $app->fire(199);
    $app->fire(200);
    assertSame([50, 200], $calls, 'Positive integer return value should become the next interval.');
  },

  'app timer interval can be changed externally' => function(): void {
    $app = new TimerProbeApp();
    $calls = [];
    $id = $app->addTimer(100, function(int $id, float $now) use (&$calls): void {
      $calls[] = (int)$now;
    });
    assertSame(100, $app->timerInterval($id), 'Timer interval should be observable.');
    assertSame(true, $app->setTimerInterval($id, 30), 'Existing timer interval should be updateable.');
    assertSame(30, $app->timerInterval($id), 'Updated timer interval should be observable.');
    $app->fire(29);
    $app->fire(30);
    assertSame([30], $calls, 'Resetting interval should reschedule the next due time.');
  },

  'one shot app timer is removed after firing' => function(): void {
    $app = new TimerProbeApp();
    $calls = 0;
    $app->addTimer(25, function() use (&$calls): void {
      $calls++;
    }, false);
    $app->fire(25);
    $app->fire(50);
    assertSame(1, $calls, 'One-shot timer should fire once.');
  },

  'app wait timeout follows next due timer' => function(): void {
    $app = new TimerProbeApp();
    $app->setMaxEventWait(50);
    $app->addTimer(120, function(): void {
    });
    assertSame(50, $app->waitAt(0), 'Wait timeout should cap at the configured maximum.');
    assertSame(20, $app->waitAt(100), 'Wait timeout should shrink near the next due timer.');
    assertSame(0, $app->waitAt(120), 'Wait timeout should be zero when a timer is due.');
  },
];
