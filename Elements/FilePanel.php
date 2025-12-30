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
    $this->fileNameLabel->addText('File name:');
    $this->fileNameInput = new Input($this->fileNameLabel);
    $this->fileNameInput->setOnChange([$this, 'changed']);
    $buttons = new Element($content, false, false, 'ButtonBox');
    $cancel = new Button($buttons);
    $cancel->setHotKey('ESCAPE');
    $cancel->addText('Cancel');
    $this->createDirBtn = new Button($buttons);
    $this->createDirBtn->setHotKey('F7');
    $this->createDirBtn->addText('Create dir');
    $this->createDirBtn->setOnPress([$this, 'createDir']);
    $this->okBtn = new Button($buttons);
    $this->okBtn->setOnPress([$this, 'choose']);
  }

  public function setOnSelect($callback) {
    $this->onSelect = $callback;
  }

  public function setFileFilter($filter) {
    $this->fileFilter = $filter;
    if ($this->fileFilter === false) {
      $this->title->addText('Choose a directory!');
      $this->fileNameInput->hide();
      $this->fileNameLabel->hide();
      $this->okBtn->setHotKey('SPACE');
      $this->okBtn->addText('OK');
    } else {
      $this->title->addText('Choose a file!');
      $this->okBtn->setHotKey('RETURN');
      $this->okBtn->addText('OK');
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

  public function getValue() {
    return $this->value;
  }

  public function setValue($value) {
    $this->value = $value;
  }

  public function setPath($path, $selected = false) {
// new file...
    if ($path === '/' || empty($path)) {
      $this->dir = '/';
      $this->file = '';
    } else {
      $path = '/' . trim($path, '/');
      if (is_file($path)) {
        $this->dir = dirname($path) . '/';
        $this->file = basename($path);
        $selected = $this->file;
      } else {
        $this->dir = $path . '/';
        $this->file = '';
      }
    }
    $res = $this->loadDir($dirs, $files);
    if (!$res) {
      return;
    }
    $this->fillUpTheList($dirs, $files, $selected);
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
      $li->addText(($i > 0 ? '/' : '') . $dir);
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
        $li->addText($file);
        $li->setValue($file);
        if ($file === $selected) {
          $cursor = $i;
        }
        $i++;
      }
    }
    $this->theList->moveCursor($cursor);
  }

  private function refreshPath() {
    $this->path->clear();
    $path = $this->dir . $this->file;
    if ($this->fileFilter !== false && $this->create) {
      $fileName = $this->fileNameInput->getValue();
      if (is_file($path)) {
        if ($fileName !== '') {
          $path = $this->dir . $fileName;
        }
      } else {
        if ($fileName !== '') {
          $path = rtrim($path, '/') . '/' . $fileName;
        }
      }
    }
    $this->value = $path;
    $this->path->addText($path);
    Element::immediateRender($this->path);
    Element::immediateRender($this->fileNameInput);
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

  }

  public function choose() {
    $path = $this->value;
    if ($this->create === false && $this->fileFilter !== false && !is_file($path)) {
      return true;
    }
    $this->hide();
    $this->remove();
    if ($this->onSelect !== false) {
      call_user_func($this->onSelect, $path);
    }
  }

  public function keyPressHandler($element, $event) {
    switch (KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
      case Action::SELECT_ITEM:
        $this->choose();
        return true;
      case Action::DO_IT:
        $dir = trim($this->theList->getValue(), '/');
        if ($dir === '..') {
          $this->setPath(dirname($this->dir), basename($this->dir));
          $this->theList->bringToMiddle();
        } else if (is_dir("{$this->dir}{$dir}")) {
          $this->setPath("{$this->dir}{$dir}");
        } else if ($this->fileFilter !== false) {
          $this->choose();
        }
        Element::refresh();
        return true;
      case Action::DELETE_BACK:
        $this->setPath(dirname($this->dir), basename($this->dir));
        $this->theList->bringToMiddle();
        Element::refresh();
        return true;
    }
    return parent::keyPressHandler($element, $event);
  }

}
