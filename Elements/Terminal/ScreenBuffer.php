<?php

namespace SPTK\Terminal;

class ScreenBuffer {

  const GLYPH = 0;
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
  protected $applicationCursor = false;

  public function __construct() {
    $this->mainScreen = [];
    $this->currentScreen = &$this->mainScreen;
    $this->parser = new ANSIParser($this);
    $this->fg = $this->parser->colors[7];
    $this->bg = $this->parser->colors[0];
    $this->initAltScreen();
  }

  public function parse($output) {
    $this->parser->parse($output);
  }

  protected function initAltScreen() {
    $this->altScreen = [];
    for ($i = 0; $i < $this->rows; $i++) {
      for ($j = 0; $j < $this->cols; $j++) {
        $this->altScreen[$i][$j] = [
          self::GLYPH => ' ',
          self::BG => $this->bg,
          self::FG => $this->fg,
          self::ATTR =>  $this->attrs
        ];
      }
    }
  }

  public function setCurrentBuffer($buffer) {
    if ($buffer === 0) {
echo "NORMAL BUFFER\n";
      $this->currentScreen = &$this->mainScreen;
    } else {
echo "ALT BUFFER\n";
      $this->currentScreen = &$this->altScreen;
    }
  }

  public function putChar($chr) {
echo "PUT {$chr} {$this->row}:{$this->col}\n";
    $this->currentScreen[$this->row][$this->col] = [
      self::GLYPH => $chr,
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
echo "LF\n";
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
        self::GLYPH => ' ',
        self::BG => $this->bg,
        self::FG => $this->fg,
        self::ATTR =>  $this->attrs
      ];
    }
    $this->col = 0;
  }

  public function carriageReturn() {
echo "CR\n";
    $this->col = 0;
  }

  public function backSpace() {
echo "BS\n";
    $this->col--;
    if ($this->col < 0) {
      $this->col = 0;
    }
  }

  public function tab() {
echo "TAB\n";
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
        echo $this->currentScreen[$i][$j][self::GLYPH] ?? ' ';
      }
      echo "|\n";
    }
    echo str_repeat('-', $this->cols + 2), "\n";
    foreach ($this->scrollBuffer as $line) {
      echo "> ";
      for ($j = 0; $j < $this->cols; $j++) {
        echo $line[$j][self::GLYPH] ?? ' ';
      }
      echo "\n";
    }
    echo count($this->scrollBuffer), "\n";
  }

  public function setForeground($color) {
echo "  => FG\n";
    $this->fg = $color;
  }

  public function setBackground($color) {
echo "  => BG\n";
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

  public function cursorUp($n) {
    if ($this->row >= $n) {
      $this->row -= $n;
    } else {
      $this->row = 0;
    }
  }

  public function cursorDown($n) {
    if ($this->row < $this->rows - $n - 1) {
      $this->row += $n;
    } else {
      $this->row = $this->rows - 1;
    }
  }

  public function cursorLeft($n) {
    if ($this->col >= $n) {
      $this->col -= $n;
    } else {
      $this->col = 0;
    }
  }

  public function cursorRight($n) {
    if ($this->col < $this->cols - $n - 1) {
      $this->col += $n;
    } else {
      $this->col = $this->cols - 1;
    }
  }

  public function cursorPos($n, $m) {
echo "  => CURSOR POS: {$n} {$m}\n";
    if ($n !== false) {
      $this->row = $n - 1;
      if ($this->row < 0) {
        $this->row = 0;
      }
      if ($this->row > $this->rows - 1) {
        $this->row = $this->rows - 1;
      }
    }
    if ($m !== false) {
      $this->col = $m - 1;
      if ($this->col < 0) {
        $this->col = 0;
      }
      if ($this->col > $this->cols - 1) {
        $this->col = $this->cols - 1;
      }
    }
  }

  public function eraseDisplay($n) {
echo "  => ERASE DISPLAY\n";
    switch ($n) {
      case 1: // erase from cursor to beginning of screen
        for ($i = 0; $i <= $this->row; $i++) {
          if ($i === $this->row) {
            $end = $this->col;
          } else {
            $end = $this->cols;
          }
          for ($j = 0; $j < $end; $j++) {
            $this->currentScreen[$i][$j] = [
              self::GLYPH => ' ',
              self::BG => $this->bg,
              self::FG => $this->fg,
              self::ATTR =>  $this->attrs
            ];
          }
        }
        break;
      case 3: // erase saved lines
        $this->scrollBuffer = [];
        // no break, clear the screen too
      case 2: // erase entire screen
        for ($i = 0; $i < $this->rows; $i++) {
          for ($j = 0; $j < $this->cols; $j++) {
            $this->currentScreen[$i][$j] = [
              self::GLYPH => ' ',
              self::BG => $this->bg,
              self::FG => $this->fg,
              self::ATTR =>  $this->attrs
            ];
          }
        }
        break;
      default: // erase from cursor until end of screen
        for ($i = $this->row; $i < $this->rows; $i++) {
          if ($i === $this->row) {
            $start = $this->col;
          } else {
            $start = 0;
          }
          for ($j = $start; $j < $this->cols; $j++) {
            $this->currentScreen[$i][$j] = [
              self::GLYPH => ' ',
              self::BG => $this->bg,
              self::FG => $this->fg,
              self::ATTR =>  $this->attrs
            ];
          }
        }
        break;
    }
  }

  public function eraseLine($n) {
echo "  => ERASE LINE\n";
    switch ($n) {
      case 1: // erase start of line to the cursor
        $start = 0;
        $end = $this->col;
        break;
      case 2: // erase the entire line
        $start = 0;
        $end = $this->cols;
        break;
      default: // erase from cursor to end of line
        $start = $this->col;
        $end = $this->cols;
        break;
    }
    for ($j = $start; $j < $end; $j++) {
      $this->currentScreen[$this->row][$j] = [
        self::GLYPH => ' ',
        self::BG => $this->bg,
        self::FG => $this->fg,
        self::ATTR =>  $this->attrs
      ];
    }
  }

  public function insertLine($n) {

  }

  public function deleteLine($n) {

  }

  public function insertChars($n) {

  }

  public function scrollUp($n) {

  }

  public function scrollDown($n) {

  }

  public function scrollRegion($n, $m) {

  }

  public function applicationCursor($state) {
    $this->applicationCursor = $state;
  }

  public function getApplicationCursorState() {
    return $this->applicationCursor;
  }

  public function getLines() {
    return $this->currentScreen;
  }

  public function countLines() {
    return count($this->currentScreen);
  }

}
