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
  protected $scrollRegionStart = 0;
  protected $scrollRegionEnd = 0;
  protected $savedCursor = [];
  protected $showCursor = true;
  protected $fg = 0xffffff;
  protected $bg = 0x000000;
  protected $attrs = 0;
  protected $parser;
  protected $applicationCursor = false;
  protected $applicationKeypad = false;
  protected $otherScreenState = false;

  public function __construct() {
    $this->mainScreen = [];
    $this->currentScreen = &$this->mainScreen;
    $this->parser = new ANSIParser($this);
    $this->fg = $this->parser->colors[7];
    $this->bg = $this->parser->colors[0];
    $this->scrollRegionStart = 0;
    $this->scrollRegionEnd = $this->rows;
    $this->initAltScreen();
  }

  public function parse($output) {
    $this->parser->parse($output);
  }

  protected function emptyCell() {
    return [
      self::GLYPH => ' ',
      self::BG => $this->bg,
      self::FG => $this->fg,
      self::ATTR =>  $this->attrs
    ];
  }

  protected function emptyLine() {
    $line = [];
    for ($j = 0; $j < $this->cols; $j++) {
      $line[$j] = $this->emptyCell();
    }
    return $line;
  }

  protected function initAltScreen() {
    $this->altScreen = [];
    for ($i = 0; $i < $this->rows; $i++) {
      $this->altScreen[$i] = $this->emptyLine();
    }
  }

  public function setCurrentBuffer($buffer) {
    $state = [
      'row' => $this->row,
      'col' => $this->col,
      'scrollRegionStart' => $this->scrollRegionStart,
      'scrollRegionEnd' => $this->scrollRegionEnd,
      'savedCursor' => $this->savedCursor
    ];
    if ($buffer === 0) {
      $this->currentScreen = &$this->mainScreen;
    } else {
      $this->currentScreen = &$this->altScreen;
    }
    if ($this->otherScreenState !== false) {
      $this->row = $this->otherScreenState['row'];
      $this->col = $this->otherScreenState['col'];
      $this->scrollRegionStart = $this->otherScreenState['scrollRegionStart'];
      $this->scrollRegionEnd = $this->otherScreenState['scrollRegionEnd'];
      $this->savedCursor = $this->otherScreenState['savedCursor'];
    }
    $this->otherScreenState = $state;
  }

  public function putChar($chr) {
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
    if ($this->row < $this->scrollRegionEnd) {
      $this->row++;
    } else if ($this->row == $this->scrollRegionEnd) {
      if ($this->currentScreen === $this->mainScreen) {
        $this->scrollBuffer[] = $this->currentScreen[$this->scrollRegionStart];
      }
      $this->scrollUp(1);
    } else if ($this->row < $this->rows) {
      $this->row++;
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
echo "  cursorLeft {$n}\n";
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
echo "  cursorPos {$n} {$m}\n";
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
    switch ($n) {
      case 1: // erase from cursor to beginning of screen
        for ($i = 0; $i <= $this->row; $i++) {
          if ($i === $this->row) {
            $end = $this->col;
          } else {
            $end = $this->cols;
          }
          for ($j = 0; $j < $end; $j++) {
            $this->currentScreen[$i][$j] = $this->emptyCell();
          }
        }
        break;
      case 3: // erase saved lines
        $this->scrollBuffer = [];
        // no break, clear the screen too
      case 2: // erase entire screen
        for ($i = 0; $i < $this->rows; $i++) {
          for ($j = 0; $j < $this->cols; $j++) {
            $this->currentScreen[$i][$j] = $this->emptyCell();
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
            $this->currentScreen[$i][$j] = $this->emptyCell();
          }
        }
        break;
    }
  }

  public function eraseLine($n) {
echo "eraseLine {$n}\n";
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
      $this->currentScreen[$this->row][$j] = $this->emptyCell();
    }
  }

  public function insertLine($n) {
echo "insertLine {$n}\n";
    for ($i = $this->scrollRegionEnd; $i >= $this->scrollRegionStart; $i--) {
      if ($i < $this->scrollRegionStart + $n) {
        $this->currentScreen[$i] = $this->emptyLine();
      } else {
        $this->currentScreen[$i] = $this->currentScreen[$i - $n];
      }
    }
  }

  public function deleteLine($n) {
echo "deleteLine {$n}\n";
    for ($i = $this->row; $i < $this->scrollRegionEnd; $i++) {
      if ($i < $this->row + $n && $this->row + $n < $this->scrollRegionEnd) {
        $this->currentScreen[$i] = $this->currentScreen[$i + $n];
      } else {
        $this->currentScreen[$i] = $this->emptyLine();
      }
    }
  }

  public function insertChars($n) {
echo "insertChars {$n}\n";
    $i = $this->row;
    for ($j = $this->cols - 1; $j >= $this->col; $j--) {
      if ($j < $this->col + $n) {
        $this->currentScreen[$i][$j] = $this->emptyCell();
      } else {
        $this->currentScreen[$i][$j] = $this->currentScreen[$i][$j - $n];
      }
    }
  }

  public function deleteChars($n) {
echo "deleteChars {$n}\n";
    $i = $this->row;
    for ($j = $this->col; $j < $this->cols; $j++) {
      if ($j < $this->col + $n && $this->col + $n < $this->cols) {
        $this->currentScreen[$i][$j] = $this->currentScreen[$i][$j + $n];
      } else {
        $this->currentScreen[$i][$j] = $this->emptyCell();
      }
    }
  }

  public function eraseChars($n) {
echo "eraseChars {$n}\n";
    $i = $this->row;
    for ($j = $this->col; $j < $this->cols && $j < $this->col + $n; $j++) {
      $this->currentScreen[$i][$j] = $this->emptyCell();
    }
  }

  public function scrollUp($n) {
echo "scrollUp {$n}\n";
    for ($i = $this->scrollRegionStart; $i <= $this->scrollRegionEnd; $i++) {
      if ($i + $n <= $this->scrollRegionEnd) {
        $this->currentScreen[$i] = $this->currentScreen[$i + $n];
      } else {
        $this->currentScreen[$i] = $this->emptyLine();
      }
    }
$this->debug();
  }

  public function scrollDown($n) {
echo "scrollDown {$n}\n";
    for ($i = $this->scrollRegionEnd; $i >= $this->scrollRegionStart; $i--) {
      if ($i < $this->scrollRegionStart + $n) {
        $this->currentScreen[$i] = $this->emptyLine();
      } else {
        $this->currentScreen[$i] = $this->currentScreen[$i - $n];
      }
    }
  }

  public function scrollRegion($n, $m) {
    if ($m <= 1 || $m >= $this->cols) {
      $m = $this->cols - 1;
    }
    if ($n < 1) {
      $n = 0;
    }
    if ($n > $m) {
      $n = $m - 1;
    }
    $this->scrollRegionStart = $n;
    $this->scrollRegionEnd = $m;
  }

  public function applicationCursor($state) {
    $this->applicationCursor = $state;
  }

  public function getApplicationCursorState() {
    return $this->applicationCursor;
  }

  public function applicationKeypad($state) {
    $this->applicationKeypad = $state;
  }

  public function getApplicationKeypadState() {
    return $this->applicationKeypad;
  }

  public function getLines() {
    return $this->currentScreen;
  }

  public function countLines() {
    return count($this->currentScreen);
  }

  public function saveCursor($saveState = false) {
echo "saveCursor: {$this->row}, {$this->col}\n";
    $this->savedCursor[0] = $this->row;
    $this->savedCursor[1] = $this->col;
    if ($saveState) {
      $this->savedCursor[2] = $this->attrs;
      $this->savedCursor[3] = $this->parser->getCharset();
      $this->savedCursor[4] = $this->applicationCursor;
      $this->savedCursor[5] = $this->applicationKeypad;
    }
  }

  public function restoreCursor($restoreState = false) {
    if (empty($this->savedCursor)) {
      return;
    }
echo "restoreCursor: {$this->row}, {$this->col}\n";
    $this->row = $this->savedCursor[0];
    $this->col = $this->savedCursor[1];
    if ($restoreState && count($this->savedCursor) > 2) {
      $this->attrs = $this->savedCursor[2];
      $this->parser->setCharset($this->savedCursor[3]);
      $this->applicationCursor = $this->savedCursor[4];
      $this->applicationKeypad = $this->savedCursor[5];
    }
  }

}
