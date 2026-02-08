<?php

namespace SPTK\Terminal;

class ScreenBuffer {

  const CHAR = 0;
  const BG = 1;
  const FG = 2;
  const ATTR = 3;

  protected $mainScreen;
  protected $altScreen;
  protected $currentScreen;
  protected $scrollBuffer = [];
  protected $rows = 25;
  protected $cols = 80;
  protected $row = 0;
  protected $col = 0;
  protected $showCursor = true;
  protected $scroll = true;
  protected $fg = 0xffffff;
  protected $bg = 0x000000;
  protected $attrs = 0;
  protected $parser;

  public function __construct() {
    $this->mainScreen = [];
    $this->currentScreen = &$this->mainScreen;
    $this->parser = new ANSIParser($this);
    $this->fg = $this->parser->colors[7];
    $this->bg = $this->parser->colors[0];
  }

  public function parse($output) {
    $this->parser->parse($output);
  }

  protected function initAltScreen() {
    $this->currentScreen = [];
    for ($i = 0; $i < $this->rows; $i++) {
      for ($j = 0; $j < $this->cols; $j++) {
        $this->currentScreen[$i][$j] = [
          self::CHAR => ' ',
          self::BG => $this->bg,
          self::FG => $this->fg,
          self::ATTR =>  $this->attrs
        ];
      }
    }
  }

  public function putChar($chr) {
    $this->currentScreen[$this->row][$this->col] = [
      self::CHAR => $chr,
      self::BG => $this->bg,
      self::FG => $this->fg,
      self::ATTR =>  $this->attrs
    ];
    $this->col++;
    if ($this->col > $this->cols) {
      $this->lineFeed();
    }
  }

  public function lineFeed() {
    if ($this->row >= $this->rows) {
      if ($this->scroll) {
        $this->scrollBuffer[] = $this->currentScreen[0];
      }
      array_splice($this->currentScreen, 0, 1);
    } else {
      $this->row++;
    }
    for ($j = 0; $j < $this->cols; $j++) {
      $this->currentScreen[$this->row][$j] = [
        self::CHAR => ' ',
        self::BG => $this->bg,
        self::FG => $this->fg,
        self::ATTR =>  $this->attrs
      ];
    }
    $this->col = 0;
  }

  public function carriageReturn() {
    $this->col = 0;
  }

  public function backSpace() {
    $this->col--;
    if ($this->col < 0) {
      $this->col = 0;
    }
  }

  public function tab() {
    $this->col = (int)($this->col / 8) * 8 + 8;
    if ($this->col > $this->cols) {
      $this->row++;
      $this->col = 0;
    }
  }

  public function debug() {
    echo str_repeat('-', $this->cols + 2), "\n";
    for ($i = 0; $i < $this->rows; $i++) {
      echo "|";
      for ($j = 0; $j < $this->cols; $j++) {
        echo $this->currentScreen[$i][$j][self::CHAR] ?? ' ';
      }
      echo "|\n";
    }
    echo str_repeat('-', $this->cols + 2), "\n";
    foreach ($this->scrollBuffer as $line) {
      echo "> ";
      for ($j = 0; $j < $this->cols; $j++) {
        echo $line[$j][self::CHAR] ?? ' ';
      }
      echo "\n";
    }
    echo count($this->scrollBuffer), "\n";
  }

  public function setForeground($color) {
    $this->fg = $color;
  }

  public function setBackground($color) {
    $this->bg = $color;
  }

  public function setBold($bold) {
    if ($bold) {
      $this->attrs = 1;
    } else {
      $this->attrs = 0;
    }
  }

  public function isBold() {
    return ($this->attrs & 1) > 0;
  }

  public function getLines() {
    return $this->currentScreen;
  }

}
