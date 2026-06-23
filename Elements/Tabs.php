<?php

namespace SPTK\Elements;

use \SPTK\Element;
use \SPTK\SDLWrapper\KeyCode;
use \SPTK\SDLWrapper\KeyCombo;
use \SPTK\SDLWrapper\Action;

class Tabs extends Element {

  protected $tabs = 0;
  protected $currentTab = 0;

  protected function init(): void {
    $this->acceptInput = true;
    $this->addEvent('KeyPress', [$this, 'keyPressHandler']);
  }

  private function tabElements() {
    $tabs = [];
    foreach ($this->descendants as $element) {
      if ($element->type === 'Tab') {
        $tabs[] = $element;
      }
    }
    return $tabs;
  }

  private function tabContentName() {
    $tabs = $this->tabElements();
    $tab = $tabs[$this->currentTab] ?? false;
    if ($tab !== false && method_exists($tab, 'getContentName')) {
      return $tab->getContentName();
    }
    return false;
  }

  private function siblingTabContents() {
    $contents = [];
    if ($this->ancestor === null) {
      return $contents;
    }
    foreach ($this->ancestor->getDescendants() as $element) {
      if ($element->getId() === $this->id) {
        continue;
      }
      if ($element->getType() === 'TabBox') {
        $contents[] = $element;
      }
    }
    return $contents;
  }

  private function tabContents() {
    return $this->siblingTabContents();
  }

  public function getTabContent() {
    $contents = $this->tabContents();
    $contentName = $this->tabContentName();
    if ($contentName !== false) {
      foreach ($contents as $content) {
        if ($content->getName() === $contentName) {
          return $content;
        }
      }
    }
    if (isset($contents[$this->currentTab])) {
      return $contents[$this->currentTab];
    }
    return false;
  }

  public function addDescendant($element): void {
    if ($element->type !== 'Tab') {
      throw new \Exception('Tabs can only contain Tab elements. Place tab content in sibling TabBox elements and reference them with Tab contentName.');
    }
    parent::addDescendant($element);
    $this->tabs++;
    $this->selectTab();
  }

  public function selectTab($selected = null, $focusTabs = true) {
    if ($selected === null) {
      $selected = $this->currentTab;
    } else {
      $this->currentTab = $selected;
    }
    foreach ($this->tabElements() as $ti => $element) {
      if ($ti === $selected) {
        $element->addVariant('active');
        if ($this->hasVariant('active')) {
          $element->addVariant('focused');
        }
      } else {
        $element->removeVariant('active');
        $element->removeVariant('focused');
      }
    }
    $this->syncContentDisplay();
    $panel = $this->findAncestorByType('Panel');
    if ($panel !== false && $panel->isDisplayed()) {
      $panel->refreshInputList($focusTabs ? $this : false);
    }
  }

  public function selectRelative($offset, $focusTabs = true): bool {
    if ($this->tabs <= 0) {
      return false;
    }
    $this->currentTab += $offset;
    while ($this->currentTab < 0) {
      $this->currentTab += $this->tabs;
    }
    while ($this->currentTab >= $this->tabs) {
      $this->currentTab -= $this->tabs;
    }
    $this->selectTab($this->currentTab, $focusTabs);
    return true;
  }

  public function syncContentDisplay(): void {
    $selectedContent = $this->getTabContent();
    foreach ($this->tabContents() as $element) {
      if ($selectedContent !== false && $element->getId() === $selectedContent->getId()) {
        $element->show();
        $element->recalculateGeometry();
        $element->raise();
      } else {
        $element->hide();
      }
    }
  }

  private function currentTabElement() {
    $tabs = $this->tabElements();
    return $tabs[$this->currentTab] ?? false;
  }

  public function addClass($class, $variant = false): void {
    parent::addClass($class, $variant);
    if ($variant && $class === 'active') {
      $tab = $this->currentTabElement();
      if ($tab !== false) {
        $tab->addVariant('focused');
      }
    }
  }

  public function removeClass($class, $variant = false): void {
    if ($variant && $class === 'active') {
      $tab = $this->currentTabElement();
      if ($tab !== false) {
        $tab->removeVariant('focused');
      }
    }
    parent::removeClass($class, $variant);
  }

  public function keyPressHandler($element, $event) {
    $keycombo = KeyCombo::resolve($event['mod'], $event['scancode'], $event['key']);
    switch ($keycombo) {
      case Action::MOVE_LEFT:
      case Action::SWITCH_LEFT:
        $this->selectRelative(-1);
        \SPTK\Element::refresh();
        return true;
      case Action::MOVE_RIGHT:
      case Action::SWITCH_RIGHT:
        $this->selectRelative(1);
        \SPTK\Element::refresh();
        return true;
    }
    return false;
  }

}
