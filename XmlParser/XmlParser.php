<?php

namespace SPTK\XmlParser;

use \XMLReader;
use SPTK\Core\Style;

/** Loads app.xml and builds window definitions with inherited styles. */
final class XmlParser {

  use AttributeParser;

  public $fontName = 'LiberationMono-Bold';
  public $fontSize = 17;
  public $windows = [];
  public $events = [];
  private StyleParser $styleParser;
  private ScreenParser $screenParser;

  public function __construct() {
    $this->styleParser = new StyleParser();
    $this->screenParser = new ScreenParser();
    $reader = $this->open(APP_DIR . '/Layout/app.xml');
    $this->parseAppXml($reader);
  }

  private function open($path): XMLReader {
    $path = realpath($path);
    if (!is_file($path)) {
      throw new \RuntimeException("XML file not found: {$path}");
    }
    $reader = new XMLReader;
    if (!$reader->open('file://' . $path)) {
      throw new \RuntimeException("Couldn't open XML file: {$path}");
    }
    return $reader;
  }

  private function parseAppXml($reader): void {
    $reader->read();
    if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'App') {
      throw new \RuntimeException("App must be the first element in app.xml!");
    }
    $this->assertAttributes($reader, ['font', 'fontSize']);
    $this->fontName = $this->attrString($reader, 'font', $this->fontName);
    $this->fontSize = $this->attrInteger($reader, 'fontSize', $this->fontSize);
    $style = new Style();
    while ($reader->read()) {
      if ($reader->nodeType === XMLReader::ELEMENT) {
        if ($reader->name === 'Style') {
          $style = $this->styleParser->parse($reader, $style);
        } else if ($reader->name === 'Event') {
          $event = (new EventParser())->parse($reader);
          if (!in_array($event->type, ['init', 'close', 'timer'], true)) {
            throw new RuntimeException('App-level events must use init, close, or timer type.');
          }
          $this->events[] = $event;
        } else if ($reader->name === 'Window') {
          $this->parseWindow($reader, $style);
        } else {
          throw new \RuntimeException("App must contain Style, Event, or Window elements!");
        }
      }
    }
  }

  private function parseWindow($reader, Style $parentStyle): void {
    $this->assertAttributes($reader, ['title', 'width', 'height', 'mode', 'resizable']);
    $window = [
      'title' => $this->attrString($reader, 'title', 'SPTK window'),
      'width' => $this->attrSize($reader, 'width', 80),
      'height' => $this->attrSize($reader, 'height', 25),
      'state' => $this->attrEnum($reader, 'mode', ['normal', 'minimized', 'maximized', 'fullscreen']),
      'resizable' => $this->attrBoolean($reader, 'resizable', true),
      'screens' => []
    ];
    $style = $parentStyle;
    while ($reader->read()) {
      if ($reader->nodeType === XMLReader::ELEMENT) {
        if ($reader->name === 'Style') {
          $style = $this->styleParser->parse($reader, $style);
        } else if ($reader->name === 'Screen') {
          $this->assertAttributes($reader, ['file']);
          $screenFile = $this->attrString($reader, 'file');
          if ($screenFile === null) {
            throw new \RuntimeException("Screen must have a file attribute!");
          }
          $window['screens'][] = $this->screenParser->parse($screenFile, $style);
        } else {
          throw new \RuntimeException("Window must contain Screen elements!");
        }
      }
      if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'Window') {
        $this->windows[] = $window;
        return;
      }
    }
  }

}
