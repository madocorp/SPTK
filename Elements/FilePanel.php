<?php

namespace SPTK;

class FilePanel extends Panel {

  private $dir;
  private $file;
  private $theList;
  private $path;
  private $onSelect = false;
  private $fileFilter = true;

  protected function init() {
    parent::init();
    $title = new Element($this, false, false, 'PanelTitle');
    $title->addText('Choose a file!');
    $content = new Element($this, false, false, 'PanelContent');
    $this->path = new Element($content, false, 'w100', 'Path');
    $this->theList = new ListBox($content, false, 'wh60');
    $this->theList->setOnChange([$this, 'changed']);
    $this->theList->setTyping('filter');
    $buttons = new Element($content, false, false, 'ButtonBox');
    $cancel = new Button($buttons);
    $cancel->setHotKey('ESCAPE');
    $cancel->addText('Cancel');
    $ok = new Button($buttons);
    $ok->setHotKey('SPACE');
    $ok->addText('OK');
    $ok->setOnPress([$this, 'close']);
  }

  public function setOnSelect($callback) {
    $this->onSelect = $callback;
  }

  public function setFileFilter($filter) {
    $this->fileFilter = $filter;
  }

  public function setPath($path, $selected = false) {
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
    $this->theList->addClass('active', true);
    $this->theList->raise();
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
    $this->path->addText($this->dir . $this->file);
    Element::immediateRender($this->path);
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

  public function keyPressHandler($element, $event) {
    switch (KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
      case Action::SELECT_ITEM:
        $path = "{$this->dir}{$this->file}";
        if ($this->fileFilter !== false && !is_file($path)) {
          return true;
        }
        $this->hide();
        $this->remove();
        if ($this->onSelect !== false) {
          call_user_func($this->onSelect, $path);
        }
        return true;
      case Action::DO_IT:
        $dir = trim($this->theList->getValue(), '/');
        if ($dir === '..') {
          $this->setPath(dirname($this->dir), basename($this->dir));
          $this->theList->bringToMiddle();
        } else {
          $this->setPath("{$this->dir}{$dir}");
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
