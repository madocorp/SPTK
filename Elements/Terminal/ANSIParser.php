<?php

namespace SPTK\Terminal;

class ANSIParser {

  const GROUND = 0;
  const ESCAPE = 1;
  const CSI = 2;
  const OSC = 3;

  public $screen;
  public $state = self::GROUND;
  public $buffer = '';
  public $seqLen = 0;

  public $colors = [0x000000, 0xbb0000, 0x00bb00, 0xbbbb00, 0x0000bb, 0xbb00bb, 0x00bbbb, 0xbbbbbb];
  public $brightColors = [0x555555, 0xff5555, 0x55ff55, 0xffff55, 0x5555ff, 0xff55ff, 0x55ffff, 0xffffff];

  public function __construct($screenBuffer) {
    $this->screen = $screenBuffer;
  }

  public function parse($str) {
    $codepoints = $this->decodeUTF8($str);
    foreach ($codepoints as $cp) {
      switch ($this->state) {
        case self::GROUND:
          if ($cp === "\e") { // ESC
            $this->state = self::ESCAPE;
          } elseif ($this->isPrintable($cp)) {
            $this->screen->putChar($cp);
          } else {
            $this->handleControl($cp);
          }
          break;
        case self::ESCAPE:
          if ($cp === '[') {
            $this->state = self::CSI;
            $this->buffer = '';
          } elseif ($cp === ']') {
            $this->state = self::OSC;
            $this->buffer = '';
          } else {
            $this->state = self::GROUND;
          }
          break;
        case self::CSI:
          $this->buffer .= $cp;
          if ($this->isFinalByte($cp)) {
            $this->executeCSI();
            $this->state = self::GROUND;
            $this->buffer = '';
          }
          break;
        case self::OSC:
          if ($cp === 0x07) { // BEL
            $this->state = self::GROUND;
          } else {
            $this->buffer .= $cp;
          }
          break;
      }
    }
  }

  public function decodeUTF8($str) {
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
    $final = substr($this->buffer, -1);
    $params = explode(';', substr($this->buffer, 0, -1));
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
        $this->sceen->cursorUp($params[0] ?? 1);
        break;
      case 'B':
        $this->sceen->cursorDown($params[0] ?? 1);
        break;
      case 'C':
        $this->sceen->cursorLeft($params[0] ?? 1);
        break;
      case 'D':
        $this->sceen->cursorRight($params[0] ?? 1);
        break;
      case 'H':
        $this->sceen->cursorPos($params[0] ?? 1, $params[1] ?? 1);
        break;
      case 'J':
        $this->sceen->eraseDisplay($params[0] ?? 0);
        break;
      case 'K':
        $this->sceen->eraseLine($params[0] ?? 0);
        break;
      case 'L':
        $this->sceen->insertLine($params[0] ?? 0);
        break;
      case 'M':
        $this->sceen->deleteLine($params[0] ?? 0);
        break;
      case '@':
        $this->sceen->insertChars($params[0] ?? 0);
        break;
      case 'S':
        $this->sceen->scrollUp($params[0] ?? 0);
        break;
      case 'T':
        $this->sceen->scrollDown($params[0] ?? 0);
        break;
      case 'r':
        $this->sceen->scrollRegion($params[0] ?? 0);
        break;
      // save cursor...
      case 'h': // altbuffer on
      case 'l': // altbuffer off
    }
  }

  public function handleControl($cp) {
    switch ($cp) {
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
        break;
    }
  }

  public function isPrintable($cp) {
    $code = mb_ord($cp, 'UTF-8');
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

  public function isFinalByte($cp) {
    $ord = ord($cp);
    if ($ord >= 0x40 && $ord <= 0x7e) {
      return true;
    }
    return false;
  }

}
