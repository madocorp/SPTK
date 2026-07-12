<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\SDLWrapper\KeyCode;
use \SPTK\SDLWrapper\KeyCombo;
use \SPTK\SDLWrapper\KeyModifier;
use \SPTK\SDLWrapper\ScanCode;
use \SPTK\SDLWrapper\Action;

class Panel extends Element {

  private $inputList;
  private $focusIndex;
  private $hotKeys = [];
  private $defaultButtonAction = false;
  protected $arrowTabs = false;
  protected $destroyAtClose = false;
  protected $pin = false;

  protected function init(): void {
    $this->display = false;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
    $this->focusIndex = -1;
  }

  public function getAttributeList(): array {
    return ['arrowTabs'];
  }

  public function setArrowTabs($value): void {
    $this->arrowTabs = ($value === true || $value === 'true');
  }

  public function show(): void {
    $this->display = true;
    $this->refreshInputList();
    $this->syncActive();
  }

  public function eventHandler(array $event): bool {
    if (!$this->display) {
      return false;
    }
    if (
      isset($event['name']) &&
      $event['name'] === 'KeyPress' &&
      ($event['mod'] & KeyModifier::CTRL) &&
      ($event['scancode'] === ScanCode::RETURN || $event['key'] === KeyCode::RETURN) &&
      $this->defaultButtonAction !== false
    ) {
      $this->callDefaultButtonAction();
      return true;
    }
    $n = count($this->stack);
    if ($n > 0) {
      for ($i = 0; $i < $n; $i++) {
        $descendant = $this->stack[($n + $i - 1) % $n];
        if ($descendant->display) {
          if ($descendant->eventHandler($event)) {
            return true;
          }
          break;
        }
      }
    }
    if (isset($event['name']) && isset($this->events[$event['name']])) {
      return call_user_func($this->events[$event['name']], $this, $event);
    }
    return false;
  }

  public function refreshInputList($focus = false): void {
    if (isset($this->inputList[$this->focusIndex])) {
      $this->inputList[$this->focusIndex]['element']->removeVariant('active');
    }
    $focusId = false;
    $focusName = false;
    if ($focus instanceof Element) {
      $focusId = $focus->getId();
    } else if (is_string($focus)) {
      $focusName = $focus;
    } else if (isset($this->inputList[$this->focusIndex])) {
      $focusId = $this->inputList[$this->focusIndex]['element']->getId();
    }
    $this->inputList = [];
    $this->recalculateGeometry();
    $this->syncTabs($this);
    $this->setInputList($this);
    if (empty($this->inputList)) {
      $this->focusIndex = -1;
      $this->raise();
    } else {
      $selectedIndex = false;
      foreach ($this->inputList as $idx => $input) {
        if (
          ($focusId !== false && $input['element']->getId() === $focusId) ||
          ($focusName !== false && $input['element']->getName() === $focusName)
        ) {
          $selectedIndex = $idx;
          break;
        }
      }
      if ($selectedIndex !== false) {
        $this->focusIndex = $selectedIndex;
      } else if ($this->focusIndex < 0 || $this->focusIndex >= count($this->inputList)) {
        $this->focusIndex = 0;
      }
      $focusedElement = $this->inputList[$this->focusIndex]['element'];
      $focusedElement->raise();
      $focusedElement->addVariant('active');
    }
  }

  public function getValue(): mixed {
    $value = [];
    foreach ($this->inputList as $input) {
      $key = $input['element']->getName();
      if (is_string($key)) {
        $value[$key] = $input['element']->getValue();
      }
    }
    return $value;
  }

  public function setValue($values): void {
    foreach ($values as $name => $value) {
      $element = Element::byName($name, $this);
      if ($element !== false) {
        $element->setValue($value);
      }
    }
  }

  public function setText($text): void {
    $content = Element::firstByType('PanelContent', $this);
    if ($content === false) {
      $content = Element::firstByType('WarningPanelContent', $this);
    }
    if ($content === false) {
      $content = Element::firstByType('ErrorPanelContent', $this);
    }
    if ($content === false) {
      return;
    }
    $content->clear();
    $content->addText($text);
  }

  private function setInputList($element) {
    if (!$element->display) {
      return;
    }
    if ($element->acceptInput && $element->display) {
      $this->inputList[] = $this->getInputElementDetails($element);
      if ($element->getType() !== 'Tabs') {
        return;
      }
    }
    foreach ($element->descendants as $descendant) {
      $this->setInputList($descendant);
    }
  }

  private function syncTabs($element) {
    if (!$element->display) {
      return;
    }
    if ($element->getType() === 'Tabs' && method_exists($element, 'syncContentDisplay')) {
      $element->syncContentDisplay();
    }
    foreach ($element->getDescendants() as $descendant) {
      $this->syncTabs($descendant);
    }
  }

  private function getInputElementDetails($element) {
    $details = [];
    $details['id'] = $element->id;
    $details['element'] = $element;
    $x = 0;
    $y = 0;
    self::getRelativePos($this->id, $element, $x, $y);
    $details['x1'] = $x;
    $details['y1'] = $y;
    $details['x2'] = $x + $element->geometry->width;
    $details['y2'] = $y + $element->geometry->height;
    return $details;
  }

  public function hide(): void {
    $this->display = false;
    $this->lower();
  }

  public function raise(): void {
    parent::raise();
    $this->syncActive();
  }

  public function lower(): void {
    parent::lower();
    $this->syncActive();
  }

  public function addVariant(string $class): void {
    if ($class === 'active') {
      $title = Element::firstByType('PanelTitle', $this);
      if ($title !== false) {
        $title->addVariant('active');
      }
    }
    parent::addVariant($class);
  }

  public function removeVariant(string $class): void {
    if ($class === 'active') {
      $title = Element::firstByType('PanelTitle', $this);
      if ($title !== false) {
        $title->removeVariant('active');
      }
    }
    parent::removeVariant($class);
  }

  private function syncActive(): void {
    if ($this->ancestor === null) {
      return;
    }
    $activePanel = false;
    foreach ($this->ancestor->stack as $element) {
      if ($element->type === 'Panel' && $element->display) {
        $activePanel = $element;
      }
    }
    $panels = Element::allByType('Panel', $this->ancestor);
    foreach ($panels as $panel) {
      $panel->removeVariant('active');
    }
    if ($activePanel) {
      $activePanel->addVariant('active');
    }
  }

  public function activateInput($name = false) {
    if (isset($this->inputList[$this->focusIndex])) {
      $this->inputList[$this->focusIndex]['element']->removeVariant('active');
    }
    if ($name !== false) {
      foreach ($this->inputList as $idx => $input) {
        if ($input['element']->name === $name) {
          $this->focusIndex = $idx;
          break;
        }
      }
    }
    $element = $this->inputList[$this->focusIndex]['element'];
    $element->addVariant('active');
    $element->raise();
  }

  public function inactivateInput() {
    $this->inputList[$this->focusIndex]['element']->removeVariant('active');
  }

  public function addHotKey($key, $callback) {
    $this->hotKeys[$key] = $callback;
  }

  public function removeHotKey($key) {
    unset($this->hotKeys[$key]);
  }

  public function setDefaultButtonAction($callback): void {
    $this->defaultButtonAction = $callback;
  }

  public function clearDefaultButtonAction($callback = false): void {
    if ($callback === false || $this->defaultButtonAction == $callback) {
      $this->defaultButtonAction = false;
    }
  }

  public function callDefaultButtonAction(): bool {
    if ($this->defaultButtonAction === false) {
      return false;
    }
    call_user_func($this->defaultButtonAction, $this);
    return true;
  }

  private function findClosestInput($direction) {
    $focus = $this->inputList[$this->focusIndex];
    $bestPrimary = PHP_INT_MAX;
    $bestSecondary = PHP_INT_MAX;
    $bestIdx = false;
    foreach ($this->inputList as $i => $input) {
      if ($input['id'] === $focus['id']) {
        continue;
      }
      $valid = false;
      switch ($direction) {
        case 'left':
          $valid = $input['x2'] <= $focus['x1'];
          if ($input['y1'] === $focus['y1']) {
            $primary = 0;
            $secondary = abs($focus['x1'] - $input['x1']);
          } else {
            $primary = $focus['x1'] - $input['x2'];
            if (
              ($input['y1'] >= $focus['y1'] && $input['y2'] <= $focus['y2']) ||
              ($input['y1'] <= $focus['y1'] && $input['y2'] >= $focus['y2'])
            ) {
              $multiplier = 1;
            } else {
              $multiplier = 1000;
            }
            $secondary = abs($focus['y1'] - $input['y1']) * $multiplier;
          }
          break;
        case 'right':
          $valid = $input['x1'] >= $focus['x2'];
          if ($input['y1'] === $focus['y1']) {
            $primary = 0;
            $secondary = abs($focus['x1'] - $input['x1']);
          } else {
            $primary = $input['x1'] - $focus['x2'] ;
            if (
              ($input['y1'] >= $focus['y1'] && $input['y2'] <= $focus['y2']) ||
              ($input['y1'] <= $focus['y1'] && $input['y2'] >= $focus['y2'])
            ) {
              $multiplier = 1;
            } else {
              $multiplier = 1000;
            }
            $secondary = abs($focus['y1'] - $input['y1']) * $multiplier;
          }
          break;
        case 'up':
          $valid = $input['y2'] <= $focus['y1'];
          if ($input['x1'] === $focus['x1']) {
            $primary = 0;
            $secondary = abs($focus['y1'] - $input['y1']);
          } else {
            $primary = $focus['y1'] - $input['y2'];
            if (
              ($input['x1'] >= $focus['x1'] && $input['x2'] <= $focus['x2']) ||
              ($input['x1'] <= $focus['x1'] && $input['x2'] >= $focus['x2'])
            ) {
              $multiplier = 1;
            } else {
              $multiplier = 1000;
            }
            $secondary = abs($focus['x1'] - $input['x1']) * $multiplier;
          }
          break;
        case 'down':
          $valid = $input['y1'] >= $focus['y2'];
          $primary = $input['y1'] - $focus['y2'];
          if ($input['x1'] === $focus['x1']) {
            $primary = 0;
            $secondary = abs($focus['y1'] - $input['y1']);
          } else {
            if (
              ($input['x1'] >= $focus['x1'] && $input['x2'] <= $focus['x2']) ||
              ($input['x1'] <= $focus['x1'] && $input['x2'] >= $focus['x2'])
            ) {
              $multiplier = 1;
            } else {
              $multiplier = 1000;
            }
            $secondary = abs($focus['x1'] - $input['x1']) * $multiplier;
          }
          break;
        default:
          throw new \Exception("Invalid direction: {$direction}");
      }
      if ($valid && ($primary < $bestPrimary || ($primary == $bestPrimary && $secondary < $bestSecondary))) {
        $bestPrimary = $primary;
        $bestSecondary = $secondary;
        $bestIdx = $i;
      }
    }
    return $bestIdx;
  }

 private function findFurthestInput($direction) {
    $focus = $this->inputList[$this->focusIndex];
    $bestPrimary = PHP_INT_MAX;
    $bestSecondary = 0;
    $bestIdx = false;
    foreach ($this->inputList as $i => $input) {
      if ($input['id'] === $focus['id']) {
        continue;
      }
      $valid = false;
      switch ($direction) {
        case 'left':
          $valid = $input['x2'] > $focus['x1'];
          if ($input['y1'] === $focus['y1']) {
            $primary = 0;
            $secondary = abs($focus['x1'] - $input['x1']);
          } else {
            $primary = $input['x2'] - $focus['x1'];
            if (
              ($input['y1'] >= $focus['y1'] && $input['y2'] <= $focus['y2']) ||
              ($input['y1'] <= $focus['y1'] && $input['y2'] >= $focus['y2'])
            ) {
              $multiplier = 1;
            } else {
              $multiplier = 1000;
            }
            $secondary = abs($focus['y1'] - $input['y1']) * $multiplier;
          }
          break;
        case 'right':
          $valid = $input['x1'] < $focus['x2'];
          if ($input['y1'] === $focus['y1']) {
            $primary = 0;
            $secondary = abs($focus['x1'] - $input['x1']);
          } else {
            $primary = $focus['x2'] - $input['x1'];
            if (
              ($input['y1'] >= $focus['y1'] && $input['y2'] <= $focus['y2']) ||
              ($input['y1'] <= $focus['y1'] && $input['y2'] >= $focus['y2'])
            ) {
              $multiplier = 1;
            } else {
              $multiplier = 1000;
            }
            $secondary = abs($focus['y1'] - $input['y1']) * $multiplier;
          }
          break;
        case 'up':
          $valid = $input['y1'] > $focus['y2'];
          if ($input['x1'] === $focus['x1']) {
            $primary = 0;
            $secondary = abs($focus['y1'] - $input['y1']);
          } else {
            $primary = $input['y1'] - $focus['y2'];
            if (
              ($input['x1'] >= $focus['x1'] && $input['x2'] <= $focus['x2']) ||
              ($input['x1'] <= $focus['x1'] && $input['x2'] >= $focus['x2'])
            ) {
              $multiplier = 1;
            } else {
              $multiplier = 1000;
            }
            $secondary = abs($focus['x1'] - $input['x1']) * $multiplier;
          }
          break;
        case 'down':
          $valid = $input['y2'] < $focus['y1'];
          if ($input['x1'] === $focus['x1']) {
            $primary = 0;
            $secondary = abs($focus['y1'] - $input['y1']);
          } else {
            $primary = $input['y2'] - $focus['y1'];
            if (
              ($input['x1'] >= $focus['x1'] && $input['x2'] <= $focus['x2']) ||
              ($input['x1'] <= $focus['x1'] && $input['x2'] >= $focus['x2'])
            ) {
              $multiplier = 1;
            } else {
              $multiplier = 1000;
            }
            $secondary = abs($focus['x1'] - $input['x1']) * $multiplier;
          }
          break;
        default:
          throw new \Exception("Invalid direction: {$direction}");
      }
      if ($valid && ($primary < $bestPrimary || ($primary == $bestPrimary && $secondary > $bestSecondary))) {
        $bestPrimary = $primary;
        $bestSecondary = $secondary;
        $bestIdx = $i;
      }
    }
    return $bestIdx;
  }

  private function activateClosestInput($direction) {
    if ($this->focusIndex < 0) {
      return;
    }
    $this->inactivateInput();
    $idx = $this->findClosestInput($direction);
    if ($idx === false) {
      $idx = $this->findFurthestInput($direction);
    }
    if ($idx !== false) {
      $this->focusIndex = $idx;
    }
    $this->activateInput();
    $this->refreshIfRendered();
  }

  private function isInside(Element $element, Element $container) {
    $current = $element;
    while ($current !== false && $current !== null) {
      if ($current->getId() === $container->getId()) {
        return true;
      }
      $current = $current->getAncestor();
    }
    return false;
  }

  private function activateFirstInputIn($container) {
    if ($container === false) {
      return;
    }
    $this->refreshInputList(false);
    foreach ($this->inputList as $idx => $input) {
      if ($this->isInside($input['element'], $container)) {
        $this->inactivateInput();
        $this->focusIndex = $idx;
        $this->activateInput();
        return;
      }
    }
  }

  private function activateAdjacentTab($offset) {
    if (!$this->arrowTabs) {
      return false;
    }
    $tabs = Element::firstByType('Tabs', $this);
    if ($tabs === false || !method_exists($tabs, 'selectRelative')) {
      return false;
    }
    if (!$tabs->selectRelative($offset, false)) {
      return false;
    }
    $this->activateFirstInputIn($tabs->getTabContent());
    $this->refreshIfRendered();
    return true;
  }

  private function activateNextInput(): void {
    if ($this->focusIndex < 0) {
      return;
    }
    $this->inactivateInput();
    $this->focusIndex++;
    if ($this->focusIndex >= count($this->inputList)) {
      $this->focusIndex = 0;
    }
    $this->activateInput();
    $this->refreshIfRendered();
  }

  private function activatePreviousInput(): void {
    if ($this->focusIndex < 0) {
      return;
    }
    $this->inactivateInput();
    $this->focusIndex--;
    if ($this->focusIndex < 0) {
      $this->focusIndex = count($this->inputList) - 1;
    }
    $this->activateInput();
    $this->refreshIfRendered();
  }

  private function refreshIfRendered(): void {
    if ($this->renderer instanceof \FFI\CData) {
      Element::refresh();
    }
  }

  public function keyPressHandler($element, $event) {
    if (!$this->display) {
      return false;
    }
    if (
      ($event['mod'] & KeyModifier::CTRL) &&
      ($event['scancode'] === ScanCode::RETURN || $event['key'] === KeyCode::RETURN)
    ) {
      $this->callDefaultButtonAction();
      return true;
    }
    $action = KeyCombo::resolve($event['mod'], $event['scancode'], $event['key']);
    if ($action !== Action::DO_IT && isset($this->hotKeys[$event['key']])) {
      call_user_func($this->hotKeys[$event['key']], $this);
      return true;
    }
    switch ($action) {
      case Action::DO_IT:
        $this->activateNextInput();
        return true;
      case Action::CLOSE:
        $this->close();
        return true;
      case Action::SWITCH_NEXT:
        $this->activateNextInput();
        return true;
      case Action::SWITCH_PREVIOUS:
        $this->activatePreviousInput();
        return true;
      case Action::MOVE_LEFT:
        if ($this->activateAdjacentTab(-1)) {
          return true;
        }
        $this->activateClosestInput('left');
        return true;
      case Action::SWITCH_LEFT:
        $this->activateClosestInput('left');
        return true;
      case Action::MOVE_RIGHT:
        if ($this->activateAdjacentTab(1)) {
          return true;
        }
        $this->activateClosestInput('right');
        return true;
      case Action::SWITCH_RIGHT:
        $this->activateClosestInput('right');
        return true;
      case Action::MOVE_UP:
      case Action::SWITCH_UP:
        $this->activateClosestInput('up');
        return true;
      case Action::MOVE_DOWN:
      case Action::SWITCH_DOWN:
        $this->activateClosestInput('down');
        return true;
    }
    return true;
  }

  public function close() {
    if ($this->destroyAtClose) {
      $this->remove();
    } else {
      $this->inputList = [];
      $this->hide();
    }
    Element::refresh();
  }

  public static function forge($title, $text, $buttons = false, $name = false, $sclass = false) {
    $parent = Element::firstByType('Window');
    $className = static::class;
    $panelName = basename(str_replace('\\', '/', $className));
    $panel = new $className($parent, $name, $sclass);
    $titleElement = new Element($panel, null, null, "{$panelName}Title");
    $titleElement->addText($title);
    $conetentElement = new Element($panel, null, null, "{$panelName}Content");
    if (strpos($text, '%CONFIRMATION%') !== false) {
      $code = sprintf('%03d', rand(0, 999));
      $confirmMessages = [
        'To continue, enter %CONFIRMATION_CODE% to confirm that you have read and understood the consequences of this action.',
        'This action requires confirmation. Use the code %CONFIRMATION_CODE% only if you intend to proceed and understand what will happen next.',
        'Confirmation code %CONFIRMATION_CODE% is required before proceeding. Please make sure you fully understand this action before entering it.',
        'Before moving forward, locate the confirmation code %CONFIRMATION_CODE% in this message and enter it to verify your intention.',
        'Carefully review this notice. Once you are certain you want to proceed, confirm your intent using %CONFIRMATION_CODE%.',
        'Enter %CONFIRMATION_CODE% to confirm that this action is intentional and that you have carefully read this message.',
        'Only proceed if you fully understand the impact of this operation. The required confirmation code is %CONFIRMATION_CODE%.',
        'To verify your intent, use %CONFIRMATION_CODE% when prompted after reviewing this confirmation notice.',
        'This request cannot continue without confirmation. Please supply %CONFIRMATION_CODE% as proof that you intend to proceed.',
        'Confirmation is mandatory for this action. After reviewing the details, enter %CONFIRMATION_CODE% to continue.'
      ];
      $confirmText = $confirmMessages[$code % count($confirmMessages)];
      $confirmText = str_replace('%CONFIRMATION_CODE%', $code, $confirmText);
      $text = str_replace('%CONFIRMATION%', $confirmText, $text);
      $conetentElement->addText($text);
      $labelElement = new Element($conetentElement, null, null, 'Label');
      $labelElement->addText('Code:');
      $codeElement = new ConfirmationCode($labelElement, 'confirmed');
      $codeElement->setCode($code);
    } else {
      $conetentElement->addText($text);
    }
    if (is_array($buttons)) {
      $buttonBoxElement = new Element($conetentElement, null, null, 'ButtonBox');
      foreach ($buttons as $button) {
        $buttonElement = new Button($buttonBoxElement);
        if (isset($button['hotKey'])) {
          $buttonElement->setHotKey($button['hotKey']);
        }
        if (isset($button['onPress'])) {
          if ($button['onPress'] === 'close') {
            $buttonElement->setOnPress([$panel, 'close']);
          } else {
            $buttonElement->setOnPress($button['onPress']);
          }
        }
        $buttonElement->addText($button['text']);
        new Space($buttonBoxElement);
      }
    }
    $panel->show();
    Element::refresh();
  }

}
