<?php

namespace SPTK\Terminal;

class ANSIParser {

  const GROUND = 0;
  const ESCAPE = 1;
  const CSI = 2;
  const OSC = 3;
  const CHARSET = 4;

  const ASCII = 0;
  const DEC = 1;

  public $screen;
  public $state = self::GROUND;
  public $buffer = '';
  public $seqLen = 0;
  public $charset = self::ASCII;

  public $colors = [0x000000, 0xbb0000, 0x00bb00, 0xbbbb00, 0x0000bb, 0xbb00bb, 0x00bbbb, 0xbbbbbb];
  public $brightColors = [0x555555, 0xff5555, 0x55ff55, 0xffff55, 0x5555ff, 0xff55ff, 0x55ffff, 0xffffff];
  public $decMap = [
    '`' => '◆', 'a' => '▒', 'b' => '␉', 'c' => '␌',
    'd' => '␍', 'e' => '␊', 'f' => '°', 'g' => '±',
    'h' => '␤', 'i' => '␋', 'j' => '┘', 'k' => '┐',
    'l' => '┌', 'm' => '└', 'n' => '┼', 'o' => '⎺',
    'p' => '⎻', 'q' => '─', 'r' => '⎼', 's' => '⎽',
    't' => '├', 'u' => '┤', 'v' => '┴', 'w' => '┬',
    'x' => '│', 'y' => '≤', 'z' => '≥', '{' => 'π',
    '|' => '≠', '}' => '£', '~' => '·'
  ];

  public function __construct($screenBuffer) {
    $this->screen = $screenBuffer;
  }

  public function parse($str) {
    $parseUnits = $this->parseUTF8($str);
    foreach ($parseUnits as $pu) {
      switch ($this->state) {
        case self::GROUND:
          if ($pu === "\e") { // ESC
            $this->state = self::ESCAPE;
          } elseif ($this->isPrintable($pu)) {
            if (
              $this->charset === self::DEC &&
              strlen($pu) === 1 &&
              ord($pu) > 0x20 &&
              ord($pu) < 0x7F &&
              isset($this->decMap[$pu])
            ) {
              $pu = $this->decMap[$pu];
            }
            $this->screen->putChar($pu);
          } else {
            $this->handleControl($pu);
          }
          break;
        case self::ESCAPE:
          $this->buffer = '';
          if ($pu === '[') {
echo "CSI\n";
            $this->state = self::CSI;
          } elseif ($pu === ']') {
echo "OSC\n";
            $this->state = self::OSC;
          } elseif ($pu === '(') {
echo "SCS\n";
            $this->state = self::CHARSET;
          } else {
echo "UKNOWN ESCAPE SEQUENCE {$pu}\n";
            $this->state = self::GROUND;
          }
          break;
        case self::CSI:
          $this->buffer .= $pu;
          if ($this->isFinalByte($pu)) {
            $this->executeCSI();
            $this->state = self::GROUND;
            $this->buffer = '';
          }
          break;
        case self::CHARSET:
          $this->buffer .= $pu;
          if ($this->buffer == '0') {
            $this->charset = self::DEC;
          } else if ($this->buffer == 'B') {
            $this->charset = self::ASCII;
          }
          $this->state = self::GROUND;
          $this->buffer = '';
          break;
        case self::OSC:
          if (ord($pu) === 0x07 || ord($pu) === 0x9c) { // BEL or ST
echo "  {$this->buffer}\n";
            $this->state = self::GROUND;
            $this->buffer = '';
          } else if (ord($pu) === 0x5c && ord(substr($this->buffer, -1)) === 0x1b) { // ST
echo "  {$this->buffer}\n";
            $this->state = self::GROUND;
            $this->buffer = '';
          } else {
            $this->buffer .= $pu;
          }
          break;
      }
    }
  }

  public function parseUTF8($str) {
    $out = [];
    $this->buffer .= $str;
    $i = 0;
    $len = strlen($this->buffer);
    while ($i < $len) {
      $byte = ord($this->buffer[$i]);
      if ($byte <= 0x7F) {
        $out[] = $this->buffer[$i];
        $i++;
        continue;
      }
      if (($byte & 0xE0) === 0xC0) {
        $this->seqLen = 2;
      } elseif (($byte & 0xF0) === 0xE0) {
        $this->seqLen = 3;
      } elseif (($byte & 0xF8) === 0xF0) {
        $this->seqLen = 4;
      } else {
        $out[] = "�";
        $i++;
        continue;
      }
      if ($i + $this->seqLen > $len) {
        break;
      }
      $valid = true;
      for ($j = 1; $j < $this->seqLen; $j++) {
        if ((ord($this->buffer[$i + $j]) & 0xC0) !== 0x80) {
          $valid = false;
          break;
        }
      }
      if (!$valid) {
        $out[] = "�";
        $i++;
        continue;
      }
      $out[] = substr($this->buffer, $i, $this->seqLen);
      $i += $this->seqLen;
      $this->seqLen = 0;
    }
    $this->buffer = substr($this->buffer, $i);
    return $out;
  }

  public function executeCSI() {
echo "  {$this->buffer}\n";
    $final = substr($this->buffer, -1);
    $params = explode(';', substr($this->buffer, 0, -1));
    foreach ($params as $i => &$param) {
      if ($param === '') {
        $param = null;
      } else if (ctype_digit($param)) {
        $param = (int)$param;
      }
    }
    switch ($final) {
      case 'm':
        foreach ($params as $i => $param) {
          if ($param == 0) {
            $this->screen->setForeground($this->colors[7]);
            $this->screen->setBackground($this->colors[0]);
            $this->screen->setBold(false);
          }
          if ($param == 1) {
            $this->screen->setBold(true);
          }
          if ($param == 7) {
            // reverse
          }
          if ($param >= 30 && $param <= 37) {
            if ($this->screen->isBold()) {
              $this->screen->setForeground($this->brightColors[$param - 30]);
            } else {
              $this->screen->setForeground($this->colors[$param - 30]);
            }
          }
          if ($param == 39) {
            $this->screen->setForeground($this->colors[7]);
          }
          if ($param >= 40 && $param <= 47) {
            $this->screen->setBackground($this->colors[$param - 40]);
          }
          if ($param == 49) {
            $this->screen->setBackground($this->colors[0]);
          }
          if ($param >= 90 && $param <= 97) {
            $this->screen->setForeground($this->brightColors[$param - 90]);
          }
          if ($param >= 100 && $param <= 107) {
            $this->screen->setBackground($this->brightColors[$param - 100]);
          }
          if (($param == 38 || $param == 48) && $params[$i + 1] == 2) {
            $r = (int)$params[$i + 2] ?? 0;
            $g = (int)$params[$i + 3] ?? 0;
            $b = (int)$params[$i + 4] ?? 0;
            if ($param == 38) {
              $this->screen->setForeground($r << 8 + $g << 16 + $b);
            }
            if ($param == 48) {
              $this->screen->setBackground($r << 8 + $g << 16 + $b);
            }
          }
        }
        break;
      case 'A':
        $this->screen->cursorUp($params[0] ?? 1);
        break;
      case 'B':
        $this->screen->cursorDown($params[0] ?? 1);
        break;
      case 'C':
        $this->screen->cursorLeft($params[0] ?? 1);
        break;
      case 'D':
        $this->screen->cursorRight($params[0] ?? 1);
        break;
      case 'E':
        $this->screen->cursorDown($params[0] ?? 1);
        $this->screen->cursorPos(false, 1);
        break;
      case 'F':
        $this->screen->cursorUp($params[0] ?? 1);
        $this->screen->cursorPos(false, 1);
        break;
      case 'G':
        $this->screen->cursorPos(false, $params[0] ?? 1);
        break;
      case 'H':
      case 'f':
        $this->screen->cursorPos($params[0] ?? 1, $params[1] ?? 1);
        break;
      case 'J':
        $this->screen->eraseDisplay($params[0] ?? 0);
        break;
      case 'K':
        $this->screen->eraseLine($params[0] ?? 0);
        break;
      case 'L':
        $this->screen->insertLine($params[0] ?? 0);
        break;
      case 'M':
        $this->screen->deleteLine($params[0] ?? 0);
        break;
      case '@':
        $this->screen->insertChars($params[0] ?? 0);
        break;
      case 'S':
        $this->screen->scrollUp($params[0] ?? 0);
        break;
      case 'T':
        $this->screen->scrollDown($params[0] ?? 0);
        break;
      case 'r':
        $this->screen->scrollRegion($params[0] ?? 0, $params[1] ?? 0);
        break;
      // save cursor...
      case 'h':
        if ($params[0] == '?1') {
          $this->screen->applicationCursor(true);
        }
        if ($params[0] == '?1049') {
          $this->screen->setCurrentBuffer(1);
        }
        break;
      case 'l':
        if ($params[0] == '?1') {
          $this->screen->applicationCursor(false);
        }
        if ($params[0] == '?1049') {
          $this->screen->setCurrentBuffer(0);
        }
        break;
    }
  }

  public function handleControl($pu) {
    switch ($pu) {
      case "\n":
        $this->screen->lineFeed();
        break;
      case "\r":
        $this->screen->carriageReturn();
        break;
      case "\t":
        $this->screen->tab();
        break;
      case "\b":
        $this->screen->backspace();
        break;
      case 0x07: // BEL
echo "BEL\n";
        break;
      default:
echo "UNNKNOWN CONTROL: 0x", dechex(ord($pu)), "\n";
    }
  }

  public function isPrintable($pu) {
    $code = mb_ord($pu, 'UTF-8');
    // C0 + DEL
    if ($code <= 0x1F || $code === 0x7F) {
      return false;
    }
    // C1 controls
    if ($code >= 0x80 && $code <= 0x9F) {
      return false;
    }
    return true;
  }

  public function isFinalByte($pu) {
    $ord = ord($pu);
    if ($ord >= 0x40 && $ord <= 0x7e) {
      return true;
    }
    return false;
  }

}
