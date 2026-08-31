<?php

require_once dirname(__DIR__) . '/Autoload.php';

use SPTK\Core\Place;
use SPTK\Core\Theme;
use SPTK\Examples\Widgets\BrowserExamples;
use SPTK\Examples\Widgets\CollectionExamples;
use SPTK\Examples\Widgets\EditorExamples;
use SPTK\Examples\Widgets\ExampleHost;
use SPTK\Examples\Widgets\FeedbackExamples;
use SPTK\Examples\Widgets\FormExamples;
use SPTK\Examples\Widgets\LayoutExamples;
use SPTK\Examples\Widgets\MenuExamples;
use SPTK\Examples\Widgets\TextExamples;
use SPTK\Runtime\SdlApp;
use SPTK\Runtime\SdlWindowOptions;
use SPTK\Widgets\DialogLayer;
use SPTK\Widgets\Dock;
use SPTK\Widgets\Label;
use SPTK\Widgets\MenuBar;
use SPTK\Widgets\StatusBar;

$root = new Dock('root');
$root->setTheme(new Theme(bg: '#000088'));

$menu = new MenuBar('menu');
$status = new StatusBar('status', 'SPTK widget examples');
$main = new Dock('example-main');
$dialogs = new DialogLayer('dialogs');
$host = new ExampleHost($main, $dialogs, $status);

foreach ([
  TextExamples::menu($host),
  FormExamples::menu($host),
  BrowserExamples::menu($host),
  CollectionExamples::menu($host),
  EditorExamples::menu($host),
  LayoutExamples::menu($host),
  FeedbackExamples::menu($host),
  MenuExamples::menu($host),
] as $item) {
  $menu->addItem($item);
}

$host->showMain('Choose a widget example from the menu.', new Label('welcome', 'Choose a widget example from the menu.'));
$root->place($menu, Place::dock('top'));
$root->place($status, Place::dock('bottom'));
$root->place($main, Place::fill());
$root->add($dialogs);

$app = new SdlApp();
$app->addWindow($root, new SdlWindowOptions(
  title: 'SPTK Widget Examples',
  columns: 110,
  rows: 34
));
$app->run();
