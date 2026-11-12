<?php

/** Handles demo lifecycle messages and screen navigation. */
class Controller {

  private static ?\SPTK\Core\Texture $canvasAtlas = null;
  private static int $canvasStep = 0;
  private static string $deckFruit = 'apple';
  private static ?\SPTK\Layout\LayoutLeaf $scrollInput = null;
  private static ?\SPTK\Layout\LayoutLeaf $scrollEditor = null;

  /** Compose sprite-sheet tiles and a scaled character into the demo canvas. */
  public static function paintCanvas(\SPTK\Core\Texture $surface, \SPTK\Core\TextureContext $textures): void {
    if (self::$canvasAtlas === null || self::$canvasAtlas->destroyed()) {
      self::$canvasAtlas = $textures->createTexture(32, 16);
      self::$canvasAtlas->fillRect(0, 0, 16, 16, '#285838');
      self::$canvasAtlas->drawLine(0, 0, 15, 15, '#387848');
      self::$canvasAtlas->fillRect(21, 1, 6, 5, '#ffd080');
      self::$canvasAtlas->fillRect(20, 6, 8, 7, '#80c0ff');
      self::$canvasAtlas->fillRect(20, 13, 3, 3, '#d0d0e0');
      self::$canvasAtlas->fillRect(25, 13, 3, 3, '#d0d0e0');
    }
    for ($y = 0; $y < $surface->height(); $y += 32) {
      for ($x = 0; $x < $surface->width(); $x += 32) {
        self::$canvasAtlas->copy($surface, 0, 0, $x, $y, 16, 16, 32, 32);
      }
    }
    $x = 8 + self::$canvasStep % max(1, $surface->width() - 40);
    self::$canvasAtlas->copy($surface, 16, 0, $x, max(0, intdiv($surface->height() - 48, 2)), 16, 16, 48, 48);
  }

  /** Advance the sprite and invalidate its retained canvas surface. */
  public static function advanceCanvas(\SPTK\Events\EventContext $event): bool {
    self::$canvasStep += 16;
    $event->widget->invalidate();
    return true;
  }

  /** Print the selected RGB value from the color selector. */
  public static function colorChanged(\SPTK\Events\EventContext $event): void {
    echo $event->widget->getValue() . "\n";
  }

  /** Print the selected ISO date from the calendar. */
  public static function dateChanged(\SPTK\Events\EventContext $event): void {
    echo $event->widget->getValue() . "\n";
  }

  /** Print the accepted path from the file selector. */
  public static function fileAccepted(\SPTK\Events\EventContext $event): void {
    echo json_encode($event->widget->getValue(), JSON_UNESCAPED_UNICODE) . "\n";
  }

  /** Print changed choice values from the demo. */
  public static function choiceChanged(\SPTK\Events\EventContext $event): void {
    echo json_encode($event->widget->getValue(), JSON_UNESCAPED_UNICODE) . "\n";
  }

  /** Print changed list values from the demo. */
  public static function listChanged(\SPTK\Events\EventContext $event): void {
    echo json_encode($event->widget->getValue(), JSON_UNESCAPED_UNICODE) . "\n";
  }

  /** Remember the deck fruit for its closed summary. */
  public static function deckFruitChanged(\SPTK\Events\EventContext $event): void {
    self::$deckFruit = (string)$event->widget->getValue();
  }

  /** Return a title line and a value line for a closed deck tile. */
  public static function deckSummary(?string $id): string {
    return $id === 'deck-fruit' ? "Fruit\n" . ucfirst(self::$deckFruit) : "Person\nFirst and last name";
  }

  /** Replace the Status input with a taller list, as an app's in-place editor does. */
  public static function editScrollStatus(): bool {
    return self::editScrollProperty('scroll-status', 'Status', ['Open', 'In progress', 'Review', 'Testing', 'Blocked', 'Ready', 'Done', 'Closed']);
  }

  /** Replace the Assignee input with a long list that needs scrolling. */
  public static function editScrollAssignee(): bool {
    return self::editScrollProperty('scroll-assignee', 'Assignee', array_map(self::scrollUserName(...), range(1, 14)));
  }

  /** Put the input back and copy the chosen value into it. */
  public static function finishScrollEdit(\SPTK\Events\EventContext $event): void {
    if (self::$scrollEditor === null) {
      return;
    }
    $screen = self::scrollScreen();
    self::$scrollInput->instance()->setValue((string)self::$scrollEditor->instance()->getValue());
    $screen->layout->replaceChild(self::$scrollEditor, self::$scrollInput);
    $screen->setLayout($screen->layout);
    $screen->selectLeaf(self::$scrollInput);
    self::$scrollEditor = null;
    \SPTK\App::eventLoop()->windows()[0]->refreshLayout();
  }

  /** Swap a two-row input for a list tile with one row per value. */
  private static function editScrollProperty(string $id, string $title, array $values): bool {
    if (self::$scrollEditor !== null) {
      return true;
    }
    $screen = self::scrollScreen();
    self::$scrollInput = $screen->layout->findTile($id);
    $items = [];
    foreach ($values as $value) {
      $items[] = ['value' => $value, 'label' => $value];
    }
    $list = new \SPTK\Widgets\List\ListView($items, false, true, true, false, new \SPTK\Core\Style(), $title);
    self::$scrollEditor = new \SPTK\Layout\LayoutLeaf('List', '1*', (string)(count($items) + 1), $list, [
      new \SPTK\Events\EventDefinition('deactivate', null, self::class . '::finishScrollEdit'),
    ]);
    $screen->layout->replaceChild(self::$scrollInput, self::$scrollEditor);
    $screen->setLayout($screen->layout);
    $screen->activateLeaf(self::$scrollEditor);
    \SPTK\App::eventLoop()->windows()[0]->refreshLayout();
    return true;
  }

  private static function scrollUserName(int $number): string {
    return 'User ' . $number;
  }

  private static function scrollScreen(): \SPTK\Core\Screen {
    return \SPTK\App::eventLoop()->windows()[0]->screen('scroll');
  }

  /** Print the file chosen in the overwrite demo after the selector is restored. */
  public static function overwriteFileChosen(\SPTK\Events\EventContext $event): void {
    echo 'overwrite file: ' . json_encode($event->widget->getValue(), JSON_UNESCAPED_UNICODE) . "\n";
  }

  /** Print a demo callback message. */
  public static function test(): bool {
    echo "test callback\n";
    return true;
  }

  /** Print a callback message from the top text tile. */
  public static function testTop(): bool {
    echo "testTop callback\n";
    return true;
  }

  /** Demonstrate a highlighted modal information message. */
  public static function statusInfo(): bool {
    self::textStatus()->info('Information is modal. Press Return or Esc to continue.');
    return true;
  }

  /** Demonstrate a selected-color modal warning. */
  public static function statusWarning(): bool {
    self::textStatus()->warning('Warning is modal too. Press Return or Esc.');
    return true;
  }

  /** Demonstrate a red modal error. */
  public static function statusError(): bool {
    self::textStatus()->error('Error stays visible until Return or Esc.');
    return true;
  }

  /** Demonstrate a yes/no decision that drains other input. */
  public static function statusConfirm(): bool {
    self::textStatus()->confirm('Continue with the demo?', self::confirmYes(...), self::confirmNo(...));
    return true;
  }

  /** Keep a normal-color message beneath temporary messages. */
  public static function statusContinuous(): bool {
    self::textStatus()->notice('Continuous message returns after other messages.', 'continuous');
    return true;
  }

  /** Show information briefly without taking tile focus. */
  public static function statusBackground(): bool {
    self::textStatus()->info('Background message disappears after three seconds.', 'background', 3000);
    return true;
  }

  /** Report the accepted confirmation choice for a short time. */
  private static function confirmYes(): void {
    self::textStatus()->notice('You chose Yes.', 'background', 2500);
  }

  /** Report the declined confirmation choice for a short time. */
  private static function confirmNo(): void {
    self::textStatus()->notice('You chose No.', 'background', 2500);
  }

  /** Return the Text screen's demonstration status bar. */
  private static function textStatus(): \SPTK\Widgets\StatusBar\StatusBar {
    return \SPTK\App::eventLoop()->windows()[0]->screen('text')->statusBar;
  }

  /** Print a message when the demo starts. */
  public static function init(): bool {
    echo "init callback\n";
    return true;
  }

}
