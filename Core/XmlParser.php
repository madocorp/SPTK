<?php

namespace SPTK\Core;

use \XMLReader;

/** Loads XML and constructs Window/Screen/Tile structure. */
final class XmlParser {

  use AttributeParser;

  public $fontName = 'LiberationMono-Bold';
  public $fontSize = 17;
  public $windows = [];

  public function __construct() {
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
    while ($reader->read()) {
      if ($reader->nodeType === XMLReader::ELEMENT) {
        if ($reader->name !== 'Window') {
          throw new \RuntimeException("App must contain Window elements!");
        }
        $this->parseWindow($reader);
      }
    }
  }

  private function parseWindow($reader): void {
    $this->assertAttributes($reader, ['title', 'width', 'height', 'mode', 'resizable']);
    $window = [
      'title' => $this->attrString($reader, 'title', 'SPTK window'),
      'width' => $this->attrSize($reader, 'width', 80),
      'height' => $this->attrSize($reader, 'height', 25),
      'state' => $this->attrEnum($reader, 'mode', ['normal', 'minimized', 'maximized', 'fullscreen']),
      'resizable' => $this->attrBoolean($reader, 'resizable', true),
      'screens' => []
    ];
    while ($reader->read()) {
      if ($reader->nodeType === XMLReader::ELEMENT) {
        if ($reader->name !== 'Screen') {
          throw new \RuntimeException("Window must contain Screen elements!");
        }
        $this->assertAttributes($reader, ['file']);
        $screenFile = $this->attrString($reader, 'file');
        if ($screenFile === null) {
          throw new \RuntimeException("Screen must have a file attribute!");
        }
        $window['screens'][] = $this->parseScreen($screenFile);
      }
      if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'Window') {
        $this->windows[] = $window;
        return;
      }
    }
  }

  private function parseScreen($file): Screen {
    $reader = $this->open(APP_DIR . '/Layout/' . $file);
    $reader->read();
    if ($reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'Layout') {
      throw new \RuntimeException("Screen must be started with a Layout!");
    }
    $layout = $this->parseLayout($reader);
    $reader->close();
    return new Screen($layout);
  }

  private function parseLayout($reader, ?string $parentDirection = null) {
    $direction = $this->attrEnum($reader, 'direction', ['vertical', 'horizontal']);
    $allowed = ['direction'];
    if ($parentDirection === 'horizontal') {
      $allowed[] = 'width';
    } else if ($parentDirection === 'vertical') {
      $allowed[] = 'height';
    }
    $this->assertAttributes($reader, $allowed);
    $width = $this->attrSize($reader, 'width');
    $height = $this->attrSize($reader, 'height');
    $layout = new \SPTK\Layout\LayoutNode($direction, $width, $height);
    while ($reader->read()) {
      if ($reader->nodeType === XMLReader::ELEMENT) {
        if ($reader->name === 'Layout') {
          $subLayout = $this->parseLayout($reader, $direction);
          $layout->addNode($subLayout);
        } else {
          $parserClass = 'SPTK\\Widgets\\' . $reader->name . '\\Parser';
          if (!class_exists($parserClass)) {
            throw new \RuntimeException("Unknown widget: {$reader->name}");
          }
          $widgetName = $reader->name;
          $parser = new $parserClass();
          if (!$parser instanceof WidgetParser) {
            throw new \RuntimeException("Widget parser must implement WidgetParser: {$parserClass}");
          }
          $allowed = $direction === 'horizontal' ? ['width'] : ['height'];
          $parser->validateAttributes($reader, $allowed);
          $width = $direction === 'horizontal' ? $this->attrSize($reader, 'width') : '1*';
          $height = $direction === 'vertical' ? $this->attrSize($reader, 'height') : '1*';
          $widget = $parser->parse($reader);
          $layout->addLeaf(new \SPTK\Layout\LayoutLeaf($widgetName, $width, $height, $widget));
        }
      } else if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'Layout') {
        break;
      }
    }
    return $layout;
  }

}
