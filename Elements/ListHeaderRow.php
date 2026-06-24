<?php

namespace SPTK\Elements;

use \SPTK\Element;

class ListHeaderRow extends Element {

  protected function init(): void {
    new Element($this, null, null, 'ItemLeft');
  }

}
