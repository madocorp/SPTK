<?php

namespace SPTK\Terminal;

require_once 'ANSIParser.php';

class Screen {

  public function putChar($char) {
    echo "putChar({$char})\n";
  }

  public function setForeground($fg) {
    var_dump($fg);
  }

}

$test = "\e[31mHello\e[0m Wőrld!";
$l = strlen($test);

for ($i = 0; $i < $l; $i ++) {
  $screen = new Screen();
  $parser = new ANSIParser($screen);
  $s1 = substr($test, 0, $i);
  $s2 = substr($test, $i);
  $parser->parse($s1);
  $parser->parse($s2);
  echo "-----\n";
}