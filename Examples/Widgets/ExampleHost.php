<?php

namespace SPTK\Examples\Widgets;

use SPTK\Core\Element;
use SPTK\Core\Place;
use SPTK\Widgets\Button;
use SPTK\Widgets\DialogLayer;
use SPTK\Widgets\DialogPanel;
use SPTK\Widgets\Dock;
use SPTK\Widgets\StatusBar;

/**
 * Shared host helpers for the interactive widget example app.
 */
class ExampleHost {

  protected ?Element $preview = null;

  public function __construct(
    protected Dock $main,
    protected DialogLayer $dialogs,
    protected StatusBar $status
  ) {
  }

  public function showMain(string $title, Element $element): void {
    if ($this->preview !== null) {
      $this->main->remove($this->preview);
    }
    $this->preview = $element;
    $this->main->place($element, Place::fill());
    $this->status->setText($title);
    $element->requestFocus();
  }

  public function showPanel(string $title, DialogPanel $panel): void {
    $close = new Button($panel->name() . '-close', 'Close');
    $close->setOnPress(function() use ($panel): void {
      $this->dialogs->pop($panel);
      $this->status->setText('Closed ' . $panel->title());
    });
    $panel->addButton($close);
    $this->dialogs->push($panel);
    $this->status->setText($title);
  }

  public function panel(string $name, string $title, string $size = 'normal'): DialogPanel {
    return new DialogPanel($name, ['title' => $title, 'size' => $size]);
  }

}
