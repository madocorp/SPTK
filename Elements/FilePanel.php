<?php

namespace SPTK;

class FilePanel extends Panel {

  private $dir;
  private $dirList;
  private $fileList;
  private $path;

  protected function init() {
    parent::init();
    $title = new Element($this, false, false, 'PanelTitle');
    $title->addText('Choose a file!');
    $content = new Element($this, false, false, 'PanelContent');
    $plabel = new Element($content, false, false, 'Label');
    $plabel->addText('Path:');
    $this->path = new Element($plabel, false, 'w100', 'Path');
    $left = new Element($content, false, false, 'HalfBoxLeft');
    $dlabel = new Element($left, false, false, 'Label');
    $dlabel->addText("Dirs:");
    $this->dirList = new ListBox($dlabel);
    $right = new Element($content, false, false, 'HalfBoxRight');
    $flabel = new Element($right, false, false, 'Label');
    $flabel->addText("Files:");
    $this->fileList = new ListBox($flabel);
    $buttons = new Element($content, false, false, 'ButtonBox');
    $cancel = new Button($buttons);
    $cancel->setHotKey('ESCAPE');
    $cancel->addText('Cancel');
    $ok = new Button($buttons);
    $ok->setHotKey('SPACE');
    $ok->addText('OK');
    $ok->setOnPress([$this, 'close']);
  }

  public function setDir($dir) {
    $this->dir = $dir;
$this->dir = '/home/mado/';
    $this->path->clear();
    $this->path->addText($this->dir);
    $handle = opendir($this->dir);
    $dirs = [];
    $files = [];
    while (($file = readdir($handle)) !== false) {
      if ($file == '.' || $file == '..') {
        continue;
      }
      $path = $this->dir . $file;
      if (is_dir($path)) {
        $dirs[] = $file;
      } else {
        $files[] = $file;
      }
    }
    closedir($handle);
    sort($dirs);
    sort($files);
    array_unshift($dirs, '..');
    $this->dirList->clear();
    foreach ($dirs as $dir) {
      $li = new ListItem($this->dirList);
      $li->addText($dir);
    }
    $this->dirList->moveCursor(0);
    $this->fileList->clear();
    foreach ($files as $file) {
      $li = new ListItem($this->fileList);
      $li->addText($file);
    }
    $this->dirList->raise();
    $this->recalculateGeometry();
    $this->recalculateGeometry();
    $this->recalculateGeometry(); // ...
$this->debug();
  }

  public function keyPressHandler($element, $event) {
    switch (KeyCombo::resolve($event['mod'], $event['scancode'], $event['key'])) {
      case Action::DO_IT:
        // enter dir
        return true;
      case Action::DELETE_BACK:
        // go parent
        return true;
      case Action::MOVE_LEFT:
      case Action::MOVE_RIGHT:
        // switch file/dir list
        return true;
    }
    return parent::keyPressHandler($element, $event);
  }

}
