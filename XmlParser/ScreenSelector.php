<?php

namespace SPTK\XmlParser;

use SPTK\Core\{Screen, Style};
use SPTK\Layout\{LayoutLeaf, LayoutNode, LayoutSeparator};
use SPTK\Widgets\Button\Button;
use SPTK\Widgets\Empty\Placeholder;

/** Expands a window ScreenSelector into a row of screen buttons on every screen. */
final class ScreenSelector {

  /** Prepend selector buttons in the declared order to each screen layout. */
  public function install(array $screens, string $list, Style $style): void {
    $ids = array_map('trim', explode(',', $list));
    if ($ids === [] || count($ids) > 12 || in_array('', $ids, true) || count($ids) !== count(array_unique($ids))) {
      throw new \RuntimeException('ScreenSelector needs 1 to 12 unique screen ids.');
    }
    $byId = [];
    foreach ($screens as $screen) {
      $byId[$screen->id] = $screen;
    }
    foreach ($ids as $id) {
      if (!isset($byId[$id])) {
        throw new \RuntimeException("ScreenSelector references unknown screen id: {$id}");
      }
    }
    foreach ($screens as $screen) {
      $this->installOnScreen($screen, $ids, $byId, $style);
    }
  }

  /** Wrap one screen's content with a horizontal button row and separator. */
  private function installOnScreen(Screen $screen, array $ids, array $byId, Style $style): void {
    $row = new LayoutNode('horizontal', '1*', '1');
    foreach ($ids as $index => $id) {
      $button = new Button($byId[$id]->title, 'f' . ($index + 1), null, $style->foreground, $style->background, $style->highlight, $id);
      $button->setId('screen_' . $id);
      $row->addLeaf(new LayoutLeaf('Button', '', '', $button));
    }
    $row->addLeaf(new LayoutLeaf('Empty', '1*', '', new Placeholder($style->background)));
    $root = new LayoutNode('vertical', '1*', '1*');
    $root->addNode($row);
    $root->addSeparator(new LayoutSeparator());
    $root->addNode($screen->layout);
    $screen->setLayout($root);
  }

}
