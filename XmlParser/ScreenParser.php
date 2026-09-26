<?php

namespace SPTK\XmlParser;

use SPTK\Core\{Screen, Style};
use SPTK\Layout\{LayoutLeaf, LayoutNode, LayoutSeparator};

/** Loads screen XML files and builds their layouts, widgets, and event subscriptions. */
final class ScreenParser {

  use AttributeParser;

  private StyleParser $styleParser;

  public function __construct() {
    $this->styleParser = new StyleParser();
  }

  /** Parse a screen file using the style inherited from its window. */
  public function parse(string $file, Style $parentStyle): Screen {
    $reader = $this->open(APP_DIR . '/Layout/' . $file);
    $reader->read();
    if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->name !== 'Screen') {
      throw new \RuntimeException("Screen file must be started with a Screen element!");
    }
    $this->assertAttributes($reader, []);
    $layout = null;
    $events = [];
    $style = $parentStyle;
    while ($reader->read()) {
      if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Style') {
        $style = $this->styleParser->parse($reader, $style);
      } else if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Event') {
        $event = (new EventParser())->parse($reader);
        if (in_array($event->type, ['init', 'close', 'timer'], true)) {
          throw new \RuntimeException('App lifecycle and timer events must be declared in app.xml.');
        }
        $events[] = $event;
      } else if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'Layout') {
        if ($layout !== null) {
          throw new \RuntimeException("Screen must contain exactly one Layout!");
        }
        $layout = $this->parseLayout($reader, $style);
      } else if ($reader->nodeType === \XMLReader::ELEMENT) {
        throw new \RuntimeException("Screen may contain Event declarations and one Layout!");
      } else if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'Screen') {
        break;
      }
    }
    $reader->close();
    if ($layout === null) {
      throw new \RuntimeException("Screen must contain a Layout!");
    }
    return new Screen($layout, $style->separator, $events);
  }

  /** Parse a layout tree and pass inherited styles to nested layouts and widgets. */
  private function parseLayout(\XMLReader $reader, Style $parentStyle, ?string $parentDirection = null): LayoutNode {
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
    $layout = new LayoutNode($direction, $width, $height);
    $style = $parentStyle;
    while ($reader->read()) {
      if ($reader->nodeType === \XMLReader::ELEMENT) {
        if ($reader->name === 'Style') {
          $style = $this->styleParser->parse($reader, $style);
        } else if ($reader->name === 'Layout') {
          $layout->addNode($this->parseLayout($reader, $style, $direction));
        } else if ($reader->name === 'Separator') {
          $this->assertAttributes($reader, []);
          $layout->addSeparator(new LayoutSeparator());
        } else {
          $this->addWidget($reader, $layout, $direction, $style);
        }
      } else if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'Layout') {
        break;
      }
    }
    return $layout;
  }

  /** Parse one widget element and add its definition to the current layout. */
  private function addWidget(\XMLReader $reader, LayoutNode $layout, string $direction, Style $style): void {
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
    $width = $direction === 'horizontal' ? $this->attrSize($reader, 'width', '') : '';
    $height = $direction === 'vertical' ? $this->attrSize($reader, 'height', '') : '';
    $definition = $parser->parse($reader, $style);
    $layout->addLeaf(new LayoutLeaf($widgetName, $width, $height, $definition->widget, $definition->events));
  }

  /** Open an XML file and report a clear error if it cannot be read. */
  private function open(string $path): \XMLReader {
    $path = realpath($path);
    if (!is_file($path)) {
      throw new \RuntimeException("XML file not found: {$path}");
    }
    $reader = new \XMLReader();
    if (!$reader->open('file://' . $path)) {
      throw new \RuntimeException("Couldn't open XML file: {$path}");
    }
    return $reader;
  }

}
