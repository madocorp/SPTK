<?php

namespace SPTK\XmlParser;

use SPTK\Core\{Screen, Style};
use SPTK\Layout\{LayoutLeaf, LayoutNode, LayoutSeparator, PixelBox};

/** Loads screen XML files and builds their layouts, widgets, and event subscriptions. */
final class ScreenParser {

  use AttributeParser;

  private StyleParser $styleParser;

  public function __construct() {
    $this->styleParser = new StyleParser();
  }

  /** Parse a screen file using the style inherited from its window. */
  public function parse(string $file, Style $parentStyle, string $id, string $title): Screen {
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
    return new Screen($layout, $style->separator, $events, $id, $title);
  }

  /** Parse a layout tree and pass inherited styles to nested layouts and widgets. */
  private function parseLayout(\XMLReader $reader, Style $parentStyle, ?string $parentDirection = null, bool $parentPixel = false): LayoutNode {
    $direction = $this->attrEnum($reader, 'direction', ['vertical', 'horizontal']);
    $pixel = $parentPixel || $this->attrBoolean($reader, 'pixel', false);
    $allowed = ['direction', 'navigateChildren', 'enterChildren', 'navigate', 'id', 'tip', 'pixel'];
    if ($parentDirection === 'horizontal') {
      $allowed[] = 'width';
    } else if ($parentDirection === 'vertical') {
      $allowed[] = 'height';
    }
    $this->assertAttributes($reader, $allowed);
    $width = $this->attrSize($reader, 'width');
    $height = $this->attrSize($reader, 'height');
    $layout = new LayoutNode($direction, $width, $height, $this->attrBoolean($reader, 'navigateChildren', true), $reader->getAttribute('id'), $reader->getAttribute('tip'), $this->attrBoolean($reader, 'navigate', true), $this->attrBoolean($reader, 'enterChildren', false), !$parentPixel && $pixel, $pixel ? $this->pixelBox($parentStyle) : null, $pixel && !$parentPixel ? $parentStyle->background : null);
    $nodeStyle = $parentStyle;
    $style = $pixel ? $parentStyle->forChild() : $parentStyle;
    $hasItems = false;
    while ($reader->read()) {
      if ($reader->nodeType === \XMLReader::ELEMENT) {
        if ($reader->name === 'Style') {
          if ($pixel && !$hasItems) {
            $nodeStyle = $this->styleParser->parse($reader, $nodeStyle);
            $layout->setPixelBox($this->pixelBox($nodeStyle));
            $style = $nodeStyle->forChild();
          } else {
            $style = $this->styleParser->parse($reader, $style);
          }
        } else if ($reader->name === 'Layout') {
          $hasItems = true;
          $layout->addNode($this->parseLayout($reader, $style, $direction, $pixel));
        } else if ($reader->name === 'Separator') {
          if ($pixel) {
            throw new \RuntimeException('Pixel layouts use box borders, not Separator elements.');
          }
          $this->assertAttributes($reader, []);
          $hasItems = true;
          $layout->addSeparator(new LayoutSeparator());
        } else {
          $hasItems = true;
          $this->addWidget($reader, $layout, $direction, $style, $pixel);
        }
      } else if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->name === 'Layout') {
        break;
      }
    }
    return $layout;
  }

  /** Map one effective Style to a layout-owned pixel box. */
  private function pixelBox(Style $style): PixelBox {
    return new PixelBox(
      $style->margin,
      $style->borderWidth,
      $style->padding,
      $style->background,
      $style->borderColor,
    );
  }

  /** Parse one widget element and add its definition to the current layout. */
  private function addWidget(\XMLReader $reader, LayoutNode $layout, string $direction, Style $style, bool $pixel): void {
    $parserClass = 'SPTK\\Widgets\\' . $reader->name . '\\Parser';
    if (!class_exists($parserClass)) {
      throw new \RuntimeException("Unknown widget: {$reader->name}");
    }
    $widgetName = $reader->name;
    $parser = new $parserClass();
    if (!$parser instanceof WidgetParser) {
      throw new \RuntimeException("Widget parser must implement WidgetParser: {$parserClass}");
    }
    $allowed = $direction === 'horizontal' ? ['width', 'navigate', 'id', 'tip', 'activeTip'] : ['height', 'navigate', 'id', 'tip', 'activeTip'];
    $parser->validateAttributes($reader, $allowed);
    $width = $direction === 'horizontal' ? $this->attrSize($reader, 'width', '') : '';
    $height = $direction === 'vertical' ? $this->attrSize($reader, 'height', '') : '';
    $id = $reader->getAttribute('id');
    $tip = $reader->getAttribute('tip');
    $activeTip = $reader->getAttribute('activeTip');
    $navigate = $this->attrBoolean($reader, 'navigate', true);
    if ($id !== null && !preg_match('/^[A-Za-z_][A-Za-z0-9_-]*$/', $id)) {
      throw new \RuntimeException("Invalid widget id: {$id}");
    }
    $definition = $parser->parse($reader, $style);
    $definition->widget->setId($id);
    $definition->widget->setTips($tip, $activeTip);
    $layout->addLeaf(new LayoutLeaf($widgetName, $width, $height, $definition->widget, $definition->events, $navigate, $pixel ? $this->pixelBox($definition->style ?? $style) : null));
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
