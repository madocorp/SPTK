<?php

namespace SPTK;

class FilePanel extends Panel {

  private $dir;
  private $file;
  private $theList;
  private $title;
  private $createDirBtn;
  private $okBtn;
  private $fileNameLabel;
  private $fileNameInput;
  private $path;
  private $onSelect = false;
  private $fileFilter = true;
  private $create = false;

  protected function init() {
    parent::init();
    $this->title = new Element($this, false, false, 'PanelTitle');
    $content = new Element($this, false, false, 'PanelContent');
    $this->path = new Element($content, false, 'w100', 'Path');
    $label = new Element($content, false, false, 'Label');
    $this->theList = new ListBox($label, false, 'wh50');
    $this->theList->setOnChange([$this, 'changed']);
    $this->theList->setTyping('search');
    $this->fileNameLabel = new Element($content, false, false, 'Label');
    $this->fileNameLabel->addText('New name:');
    $this->fileNameInput = new Input($this->fileNameLabel, 'fileName');
    $this->fileNameInput->setOnChange([$this, 'changed']);
    $this->fileNameInput->addEvent('KeyPress', [$this, 'handleReturn']);
    $buttons = new Element($content, false, false, 'ButtonBox');
    $cancel = new Button($buttons);
    $cancel->setHotKey('ESCAPE');
    $cancel->addText('Cancel');
    new Space($buttons);
    $this->createDirBtn = new Button($buttons);
    $this->createDirBtn->setHotKey('F7');
    $this->createDirBtn->addText('Create dir');
    $this->createDirBtn->setOnPress([$this, 'createDir']);
    new Space($buttons);
    $this->okBtn = new Button($buttons, 'okBtn');
    $this->okBtn->setHotKey('SPACE');
    $this->okBtn->addText('OK');
    $this->okBtn->setOnPress([$this, 'choose']);
  }

  public function setOnSelect($callback) {
    $this->onSelect = $callback;
  }

  public function setFileFilter($filter) {
    $this->fileFilter = $filter;
    if ($this->fileFilter === false) {
      $this->title->addText('Choose a directory!');
    } else {
      $this->title->addText('Choose a file!');
    }
  }

  public function setCreate($create) {
    $this->create = $create;
    if (!$this->create) {
      $this->fileNameInput->hide();
      $this->fileNameLabel->hide();
      $this->createDirBtn->hide();
    }
  }

  public function setValue($value) {
    $this->value = $value;
  }

  public function getValue() {
    return $this->value;
  }

  public function setPath($path, $selected = false) {
    $this->theList->resetSearch();
    if ($path === '/' || empty($path)) {
      $this->dir = '/';
      $this->file = '';
    } else {
      $path = '/' . trim($path, '/');
      if (!file_exists($path)) {
        $this->dir = dirname($path) . '/';
        $this->file = '';
        $this->fileNameInput->setValue(basename($path));
      } else {
        if (is_file($path)) {
          $this->dir = dirname($path) . '/';
          $this->file = basename($path);
          $selected = $this->file;
        } else {
          $this->dir = $path . '/';
          $this->file = '';
        }
      }
    }
    $res = $this->loadDir($dirs, $files);
    if (!$res) {
      return;
    }
    $this->fillUpTheList($dirs, $files, $selected);
    $this->refreshPath();
  }

  public function show() {
    parent::show();
    $this->refreshPath();
  }

  private function loadDir(&$dirs, &$files) {
    $handle = opendir($this->dir);
    if ($handle === false) {
      return false;
    }
    $dirs = [];
    $files = [];
    while (($f = readdir($handle)) !== false) {
      if ($f == '.' || $f == '..') {
        continue;
      }
      $path = "{$this->dir}{$f}";
      if (is_dir($path)) {
        $dirs[] = $f;
      } else {
        $files[] = $f;
      }
    }
    closedir($handle);
    sort($dirs);
    sort($files);
    array_unshift($dirs, ($this->dir === '/' ? '/' : '..'));
    return true;
  }

  private function fillUpTheList($dirs, $files, $selected) {
    $this->theList->clear();
    $i = 0;
    $cursor = 0;
    foreach ($dirs as $dir) {
      $li = new ListItem($this->theList);
      if ($i > 0) {
        $li->setLeft('/');
      }
      $li->setValue($dir);
      $li->setFilterable(true);
      if ($dir === $selected) {
        $cursor = $i;
      }
      $i++;
    }
    if ($this->fileFilter !== false) {
      foreach ($files as $file) {
        if ($this->fileFilter !== true) {
          $match = false;
          foreach ($this->fileFilter as $extension) {
            if (str_ends_with(mb_strtolower($file), $extension)) {
              $match = true;
              break;
            }
          }
          if (!$match) {
            continue;
          }
        }
        $li = new ListItem($this->theList);
        $li->addClass('file', true);
        $li->setFilterable(true);
        $li->setValue($file);
        if ($file === $selected) {
          $cursor = $i;
        }
        $i++;
      }
    }
    $this->theList->moveCursor($cursor);
    $this->theList->recalculateGeometry();
  }

  private function refreshPath() {
    $this->path->clear();
    $path = rtrim($this->dir .  ($this->file === '..'  ? '' : $this->file), '/');
    if ($this->create) {
      $fileName = $this->fileNameInput->getValue();
      if ($fileName !== '') {
        $path = $this->dir . $fileName;
      }
    }
    $this->value = $path;
    $this->path->addText($path);
    $this->path->recalculateGeometry();
    $this->path->scrollToRight();
    Element::immediateRender($this->path, false);
  }

  public function changed($element) {
    $this->file = $this->theList->getValue();
    if ($this->file === '..') {
      $this->file = '';
    } else {
      $this->file = trim($this->file, '/');
    }
    $this->refreshPath();
  }

  public function createDir($element) {
    if (!$this->fileNameInput->hasClass('active', true)) {
      $this->inactivateInput();
      $this->activateInput('fileName');
      Element::immediateRender($this);
      return;
    }
    if (file_exists($this->value)) {
      return;
    }
    $this->fileNameInput->setValue('');
    $created = mkdir($this->value);
    if ($created) {
      $this->setPath($this->value);
    }
    Element::immediateRender($this);
  }

  public function choose() {
    $path = $this->value;
    if ($this->create === false && $this->fileFilter !== false && !is_file($path)) {
      return true;
    }
    $this->hide();
    $this->remove();
    Element::refresh();
    if ($this->onSelect !== false) {
      call_user_func($this->onSelect, $path);
    }
  }

  public function keyPressHandler($element, $event) {
    switch (KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
      case Action::SELECT_ITEM:
        $this->choose();
        return true;
      case Action::LEVEL_DOWN:
      case Action::DO_IT:
        $dir = trim($this->theList->getValue(), '/');
        if ($dir === '..') {
          $this->setPath(dirname($this->dir), basename($this->dir));
          $this->theList->bringToMiddle();
        } else if (is_dir("{$this->dir}{$dir}")) {
          $this->setPath("{$this->dir}{$dir}");
        }
        Element::refresh();
        return true;
      case Action::LEVEL_UP:
        $this->setPath(dirname($this->dir), basename($this->dir));
        $this->theList->bringToMiddle();
        Element::refresh();
        return true;
    }
    return parent::keyPressHandler($element, $event);
  }

  public function handleReturn($element, $event) {
    switch (KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
      case Action::DO_IT:
        $this->activateInput('okBtn');
        Element::refresh();
        return true;
    }
    return $element->keyPressHandler($element, $event);
  }

}
