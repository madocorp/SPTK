<?php

define('APP_DIR', __DIR__);
require_once APP_DIR . '/SPTK/App.php';
spl_autoload_register(['SPTK\App', 'load']);

use SPTK\Core\{Screen, Style};
use SPTK\Events\EventLoop;
use SPTK\Layout\{LayoutLeaf, LayoutNode, Tile};
use SPTK\Rendering\{Grid, GridWriter};
use SPTK\SDLWrapper\SDL;
use SPTK\Widgets\List\ListView;
use SPTK\Widgets\StatusBar\StatusBar;
use SPTK\Widgets\TextEditor\TextEditor;

function expectStatus(mixed $actual, mixed $expected, string $name): void {
  if ($actual !== $expected) {
    throw new RuntimeException($name . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
  }
}

function statusKey(int $key): object {
  return (object)['type' => SDL::SDL_EVENT_KEY_DOWN, 'key' => (object)['key' => $key, 'mod' => 0, 'repeat' => false]];
}

$style = new Style();
$editor = new TextEditor(label: 'SOURCE');
$editor->setTips('Custom editor focus.', 'Custom editor active.');
$bar = new StatusBar($style);
$scheduled = [];
$bar->setScheduler(function(int $delayMs, callable $callback) use (&$scheduled): void {
  $scheduled[] = [$delayMs, $callback];
});
$list = new ListView(['A', 'B'], multiple: true, filterable: false, searchable: false, reorderable: true);
$root = new LayoutNode('horizontal', '', '');
$editorLeaf = new LayoutLeaf('TextEditor', '1*', '', $editor);
$listLeaf = new LayoutLeaf('List', '1*', '', $list);
$statusLeaf = new LayoutLeaf('StatusBar', '1*', '', $bar, [], false);
$root->addLeaf($editorLeaf);
$root->addLeaf($listLeaf);
$root->addLeaf($statusLeaf);
$screen = new Screen($root);
$screen->measureGrid(new Tile(0, 0, 72, 6));
expectStatus([$bar->text(), $bar->kind()], ['', 'empty'], 'status starts empty');
expectStatus($statusLeaf->navigate(), false, 'default status navigation is disabled');

$screen->handleEvent(statusKey(ord('h')));
expectStatus($bar->text(), 'Custom editor focus.', 'H shows selected tile help');
expectStatus($bar->kind(), 'hint', 'H shows a hint status');
expectStatus($screen->selectedLeaf(), $statusLeaf, 'help activates the status tile');
$screen->handleEvent(statusKey(SDL::KEY_RIGHT));
expectStatus($screen->selectedLeaf(), $statusLeaf, 'help drains unrelated navigation');
$screen->handleEvent(statusKey(SDL::KEY_ESCAPE));
expectStatus($screen->selectedLeaf(), $editorLeaf, 'Escape restores the previous tile');
expectStatus([$bar->text(), $bar->kind()], ['', 'empty'], 'closing help clears status');
$screen->handleEvent(statusKey(SDL::KEY_RIGHT));
$screen->handleEvent(statusKey(ord('h')));
expectStatus(str_contains($bar->text(), 'Return opens list'), true, 'H shows the toolkit default help when no override exists');
$screen->handleEvent(statusKey(SDL::KEY_RETURN));
expectStatus($screen->selectedLeaf(), $listLeaf, 'Return restores the list tile');
$screen->handleEvent(statusKey(SDL::KEY_LEFT));

$screen->handleEvent(statusKey(SDL::KEY_RETURN));
expectStatus($screen->activeLeaf(), $editorLeaf, 'editor is active before help');
$screen->handleEvent(statusKey(ord('h')));
expectStatus($bar->text(), 'Custom editor active.', 'active tile help uses its active tip');
$screen->handleEvent(statusKey(SDL::KEY_RETURN));
expectStatus($screen->activeLeaf(), $editorLeaf, 'Return resumes the active editor');

$bar->notice('Saved document');
expectStatus([$bar->kind(), $screen->selectedLeaf()], ['notice', $statusLeaf], 'notice requires acknowledgment');
$screen->handleEvent(statusKey(SDL::KEY_RIGHT));
expectStatus($screen->selectedLeaf(), $statusLeaf, 'notice drains unrelated navigation');
$screen->handleEvent(statusKey(SDL::KEY_ESCAPE));
expectStatus([$bar->text(), $screen->activeLeaf()], ['', $editorLeaf], 'Escape acknowledges notice and resumes editor');
$bar->notice('Settings saved');
expectStatus([$bar->kind(), $screen->selectedLeaf()], ['notice', $statusLeaf], 'another notice requires acknowledgment');
$screen->handleEvent(statusKey(SDL::KEY_RETURN));
expectStatus($screen->activeLeaf(), $editorLeaf, 'Return acknowledges notice');

$bar->notice('More rows available', 'continuous');
expectStatus([$bar->behavior(), $bar->text(), $screen->activeLeaf()], ['continuous', 'More rows available', $editorLeaf], 'continuous guidance stays passive');
$bar->info('Loading issues', 'modal', lock: true);
expectStatus([$bar->locked(), $screen->selectedLeaf()], [true, $statusLeaf], 'locked info activates status');
$screen->handleEvent(statusKey(SDL::KEY_ESCAPE));
expectStatus($screen->selectedLeaf(), $statusLeaf, 'locked job drains Escape');
$bar->info('Issues loaded', 'background', 120);
expectStatus([$bar->locked(), $bar->behavior(), $screen->activeLeaf()], [false, 'background', $editorLeaf], 'background completion unlocks and restores input');
expectStatus($bar->background(), $style->highlight, 'info uses highlight background');
expectStatus($scheduled[0][0], 120, 'background message schedules its duration');
$scheduled[0][1]();
expectStatus([$bar->text(), $bar->kind(), $bar->behavior()], ['More rows available', 'notice', 'continuous'], 'background expiry restores ongoing guidance');
$bar->info('Update complete');
expectStatus([$bar->kind(), $bar->behavior(), $screen->selectedLeaf()], ['info', 'modal', $statusLeaf], 'info is modal by default');
$screen->handleEvent(statusKey(SDL::KEY_RETURN));
expectStatus([$bar->text(), $screen->activeLeaf()], ['More rows available', $editorLeaf], 'acknowledging info restores guidance and editor');
$bar->info('Another job', 'modal', lock: true);
$bar->clear();
expectStatus([$bar->locked(), $bar->text(), $screen->selectedLeaf()], [false, '', $editorLeaf], 'clear unlocks and restores the previous tile');

$bar->warning('Careful');
expectStatus([$bar->background(), $screen->selectedLeaf()], [$style->selected, $statusLeaf], 'warning uses selected background and focus');
$screen->handleEvent(statusKey(SDL::KEY_ESCAPE));
expectStatus($screen->selectedLeaf(), $editorLeaf, 'warning dismisses to prior tile');
$bar->error('Cannot save');
expectStatus($bar->background(), $style->error, 'error uses red background');
$screen->handleEvent(statusKey(SDL::KEY_RETURN));
expectStatus($screen->selectedLeaf(), $editorLeaf, 'error dismisses on Return');
$bar->warning('Brief warning', 'background', 80);
expectStatus([$bar->behavior(), $screen->selectedLeaf()], ['background', $editorLeaf], 'warning style can use background behavior');
$bar->notice('New action');
$scheduled[1][1]();
expectStatus([$bar->text(), $bar->behavior()], ['New action', 'modal'], 'expired background timer cannot dismiss a newer message');
$screen->handleEvent(statusKey(SDL::KEY_RETURN));
expectStatus($bar->kind(), 'empty', 'acknowledging the newer action clears it');

$bar->info('Visible information', 'continuous');
$grid = new Grid(72, 6);
$screen->paint($grid);
$cell = $grid->cell($statusLeaf->grid()->x, $statusLeaf->grid()->y);
expectStatus((array)$cell->bg, (array)$style->highlight->darkened(), 'unselected information background darkens');
expectStatus((array)$cell->fg, (array)$style->background->darkened(), 'unselected information text darkens');
$bar->info('Focused information');
$focusedGrid = new Grid(72, 6);
$screen->paint($focusedGrid);
$focusedCell = $focusedGrid->cell($statusLeaf->grid()->x, $statusLeaf->grid()->y);
expectStatus((array)$focusedCell->bg, (array)$style->highlight, 'modal information uses full highlight color');
$screen->handleEvent(statusKey(SDL::KEY_RETURN));

$decision = '';
$bar->confirm('Proceed?', function() use (&$decision): void { $decision = 'yes'; }, function() use (&$decision): void { $decision = 'no'; });
$screen->handleEvent(statusKey(SDL::KEY_RIGHT));
expectStatus([$bar->confirming(), $screen->selectedLeaf()], [true, $statusLeaf], 'confirmation drains unrelated input');
$screen->handleEvent(statusKey(ord('n')));
expectStatus([$decision, $bar->confirming(), $screen->selectedLeaf()], ['no', false, $editorLeaf], 'N declines and restores focus');
expectStatus([$bar->text(), $bar->behavior()], ['Visible information', 'continuous'], 'confirmation restores continuous message');
$bar->confirm('Proceed?', function() use (&$decision): void { $decision = 'yes'; }, function() use (&$decision): void { $decision = 'no'; });
$screen->handleEvent(statusKey(SDL::KEY_ESCAPE));
expectStatus($decision, 'no', 'Escape declines confirmation');
$decision = '';
$bar->confirm('Legacy decision?', function() use (&$decision): void { $decision = 'yes'; }, function() use (&$decision): void { $decision = 'no'; }, function() use (&$decision): void { $decision = 'cancel'; });
$screen->handleEvent(statusKey(SDL::KEY_ESCAPE));
expectStatus($decision, 'cancel', 'legacy fourth callback keeps Escape cancellation');
$bar->confirm('Proceed?', function() use (&$decision): void { $decision = 'yes'; }, function() use (&$decision): void { $decision = 'no'; });
$screen->handleEvent(statusKey(SDL::KEY_RETURN));
expectStatus($decision, 'yes', 'Return accepts confirmation');
$decision = '';
$bar->confirm('Proceed?', function() use (&$decision): void { $decision = 'yes'; }, function() use (&$decision): void { $decision = 'no'; });
$screen->handleEvent(statusKey(ord('y')));
expectStatus($decision, 'yes', 'Y accepts confirmation');
$screen->handleEvent((object)['type' => SDL::SDL_EVENT_KEY_UP, 'key' => (object)['key' => ord('y'), 'mod' => 0, 'repeat' => false]]);
$textInput = (object)['type' => SDL::SDL_EVENT_TEXT_INPUT];
expectStatus($screen->handleEvent($textInput), true, 'text input after Y is drained even after key release');

$list->setTips('One XML tip.');
expectStatus($list->tip(true), 'One XML tip.', 'single override covers active state');
$list->setTips('Focus tip.', 'Active tip.');
expectStatus($list->tip(true), 'Active tip.', 'active override wins');
try {
  $list->setTips("Bad\ntext");
  throw new RuntimeException('Invalid multiline tip should be rejected.');
} catch (InvalidArgumentException $error) {
  expectStatus($list->tip(true), 'Active tip.', 'invalid tip leaves existing overrides intact');
}

$editor->on('deactivate', function() use ($bar): void { $bar->error('Invalid JQL'); });
$screen->handleEvent(statusKey(SDL::KEY_RETURN));
$screen->handleEvent(statusKey(SDL::KEY_ESCAPE));
expectStatus([$bar->text(), $bar->kind(), $screen->selectedLeaf()], ['Invalid JQL', 'error', $statusLeaf], 'deactivation alert stays visible and focused');

$loop = new EventLoop();
$expired = 0;
$timerId = $loop->after(1, function() use (&$expired): void {
  $expired++;
});
$timers = (new ReflectionProperty($loop, 'timers'))->getValue($loop);
$timers[$timerId]['deadline'] = hrtime(true) - 1;
(new ReflectionProperty($loop, 'timers'))->setValue($loop, $timers);
(new ReflectionMethod($loop, 'dispatchDueTimers'))->invoke($loop);
(new ReflectionMethod($loop, 'dispatchDueTimers'))->invoke($loop);
expectStatus($expired, 1, 'one-shot background timer fires only once');

$navigableRoot = new LayoutNode('horizontal', '', '');
$navigableRoot->addLeaf(new LayoutLeaf('TextEditor', '1*', '', new TextEditor()));
$navigableStatus = new LayoutLeaf('StatusBar', '1*', '', new StatusBar(), [], true);
$navigableRoot->addLeaf($navigableStatus);
$navigableScreen = new Screen($navigableRoot);
$navigableScreen->measureGrid(new Tile(0, 0, 72, 6));
$navigableScreen->handleEvent(statusKey(SDL::KEY_RIGHT));
expectStatus($navigableScreen->selectedLeaf(), $navigableStatus, 'explicit status navigation allows arrow selection');
echo "Status bar checks passed\n";
