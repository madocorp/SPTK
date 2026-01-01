<?php

namespace SPTK;

trait ElementLayout {

  public function recalculateGeometry() {
    $this->measure();
    $this->calculateWidths();
    $this->calculateHeights();
    $this->layout();
    $this->redraw();
  }

  protected function measure() {
    $this->geometry->setValues($this->ancestor->geometry, $this->style);
    foreach ($this->descendants as $descendant) {
      $descendant->measure();
    }
  }

  protected function calculateWidths() {
    if ($this->display === false) {
      return;
    }
    foreach ($this->descendants as $descendant) {
      $descendant->calculateWidths();
    }
    if ($this->geometry->width === 'content') {
      $width = 0;
      $spaceCount = 0;
      $previousIsWord = false;
      foreach ($this->descendants as $descendant) {
        if ($descendant->display === false) {
          continue;
        }
        if ($descendant->geometry->position === 'inline') {
          if ($descendant->isWord()) {
            if ($previousIsWord) {
              $spaceCount++;
            }
            $previousIsWord = true;
          } else {
            $previousIsWord = false;
          }
          $width += $descendant->geometry->fullWidth;
        }
      }
      $this->geometry->width =
        $this->geometry->borderLeft +
        $this->geometry->paddingLeft +
        $width +
        $spaceCount * $this->geometry->wordSpacing +
        $this->geometry->paddingRight +
        $this->geometry->borderRight;
      $this->geometry->setDerivedWidths();
    }
  }

  protected function calculateHeights() {
    if ($this->display === false) {
      return;
    }
    foreach ($this->descendants as $descendant) {
      $descendant->calculateHeights();
    }
    $lines = [];
    $line = ['ascent' => 0, 'descent' => 0, 'width' => 0, 'spaceCount' => 0, 'elements' => []];
    $lineWidth = 0;
    $previousIsWord = false;
    foreach ($this->descendants as $descendant) {
      if ($descendant->geometry->position === 'absolute') {
        continue;
      }
      if ($descendant->display === false) {
        continue;
      }
      $space = 0;
      if ($descendant->isWord()) {
        if ($previousIsWord) {
          $space = $this->geometry->wordSpacing;
        }
        $previousIsWord = true;
      } else {
        $previousIsWord = false;
      }
      if (
        (
          $this->geometry->textWrap === 'auto' &&
          $line['width'] > 0 &&
          $lineWidth + $space + $descendant->geometry->fullWidth > $this->geometry->innerWidth
        ) ||
        $descendant->type === 'NL'
      ) {
        if ($line['ascent'] + $line['descent'] < $this->geometry->lineHeight) {
          $line['descent'] = $this->geometry->lineHeight - $line['ascent'];
        }
        $lines[] = $line;
        $line = ['ascent' => 0, 'descent' => 0, 'width' => 0, 'spaceCount' => 0, 'elements' => []];
        $lineWidth = 0;
        $space = 0;
      }
      $lineWidth += $space;
      $lineWidth += $descendant->geometry->fullWidth;
      $line['elements'][] = $descendant;
      $line['ascent'] = max($line['ascent'], $descendant->geometry->ascent);
      $line['descent'] = max($line['descent'], $descendant->geometry->descent);
      $line['width'] += $descendant->geometry->fullWidth;
      $line['spaceCount'] += ($space > 0 ? 1 : 0);
    }
    if (count($line['elements']) > 0) {
      if ($line['ascent'] + $line['descent'] < $this->geometry->lineHeight) {
        $line['descent'] = $this->geometry->lineHeight - $line['ascent'];
      }
      $lines[] = $line;
    }
    $this->geometry->lines = $lines;
    $ascent = $this->style->get('ascent', $this->geometry);
    $this->geometry->setContentHeight($ascent);
  }

  protected function layout() {
    if ($this->display === false) {
      return;
    }
    foreach ($this->descendants as $descendant) {
      $descendant->layout();
    }
    if ($this->geometry->position === 'absolute') {
      $this->geometry->setAbsolutePosition($this->ancestor->geometry, $this->style);
    }
    $y = $this->geometry->borderTop + $this->geometry->paddingTop;
    $space = $this->geometry->wordSpacing;
    $maxX = 0;
    foreach ($this->geometry->lines as $line) {
      $y += $line['ascent'];
      $x = $this->geometry->borderLeft + $this->geometry->paddingLeft;
      if ($this->geometry->textAlign === 'right') {
        $x += $this->geometry->innerWidth - $line['width'] - $line['spaceCount'] * $space;
      } else if ($this->geometry->textAlign === 'center') {
        $x += (int)(($this->geometry->innerWidth - $line['width'] - $line['spaceCount'] * $space) / 2);
      } if ($this->geometry->textAlign === 'justify') {
        if ($line['spaceCount'] > 0) {
          $space = (int)(($this->geometry->innerWidth - $line['width']) / $line['spaceCount']);
        } else {
          $space = 0;
        }
      }
      $previousIsWord = false;
      foreach ($line['elements'] as $element) {
        $element->geometry->y = $y - $element->geometry->ascent + $element->geometry->marginTop;
        if ($element->isWord()) {
          if ($previousIsWord) {
            $x += $space;
          }
          $previousIsWord = true;
        }
        $element->geometry->x = $x + $element->geometry->marginLeft;
        $x += $element->geometry->fullWidth;
        $maxX = max($maxX, $x);
      }
      $y += $line['descent'];
    }
    $this->geometry->contentWidth = $maxX;
  }

  protected function redraw() {
    if ($this->display === false) {
      return false;
    }
    if ($this->styleChanged ||  $this->geometry->sizeChanged()) {
      $this->draw();
    }
    foreach ($this->stack as $i => $descendant) {
      if ($descendant->display === false) {
        continue;
      }
      $descendant->clipped = false;
      if ($descendant->geometry->x - $this->scrollX > $this->geometry->width) {
        $descendant->clipped = true;
        continue;
      }
      if ($descendant->geometry->y - $this->scrollY > $this->geometry->height) {
        $descendant->clipped = true;
        continue;
      }
      if ($descendant->geometry->x + $descendant->geometry->width - $this->scrollX < 0) {
        $descendant->clipped = true;
        continue;
      }
      if ($descendant->geometry->y + $descendant->geometry->height - $this->scrollY < 0) {
        $descendant->clipped = true;
        continue;
      }
      $descendant->redraw();
    }
  }

  protected function draw() {
    $color = $this->style->get('backgroundColor');
    $width = $this->geometry->width;
    $height = $this->geometry->height;
    $this->texture = new Texture($this->renderer, $width, $height, $color);
    $this->styleChanged = false;
  }

  protected function render() {
    if ($this->display === false) {
      return false;
    }
    if ($this->texture === false) {
      return false;
    }
    $tmpTexture = new Texture($this->renderer, $this->geometry->width, $this->geometry->height, [0, 0, 0, 0]);
    $this->texture->copyTo($tmpTexture, 0, 0);
    foreach ($this->stack as $descendant) {
      if ($descendant->display === false) {
        continue;
      }
      if ($descendant->clipped) {
        continue;
      }
      $dTexture = $descendant->render();
      if ($dTexture !== false) {
        $dTexture->copyTo($tmpTexture, $descendant->geometry->x - $this->scrollX, $descendant->geometry->y - $this->scrollY);
      }
    }
    new Border($tmpTexture, $this->geometry, $this->ancestor->geometry, $this->style);
    if ($this->style->get('scrollable')) {
      new Scrollbar($tmpTexture, $this->scrollX, $this->scrollY, $this->geometry->contentWidth, $this->geometry->contentHeight, $this->geometry, $this->style);
    }
    return $tmpTexture;
  }

}
