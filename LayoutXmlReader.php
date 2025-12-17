<?php

namespace SPTK;

use XMLReader;

class LayoutXmlReader {

  private $root;
  private $event = false;
  private $current;

  public function __construct($file) {
    if (!file_exists($file)) {
      throw new \Exception("File not found: {$file}");
    }
    $this->root = new Root();
    $this->current = $this->root;
    $xml = new XMLReader();
    if (!$xml->open($file)) {
      throw new \Exception("Couldn't open file: {$file}");
    }
    $this->parseWithIncludes($xml);
    $xml->close();
  }

  private function parseWithIncludes($xml) {
    while ($xml->read()) {
      switch ($xml->nodeType) {
        case XMLReader::ELEMENT:
          if ($xml->name === 'Root') {
            // skip
          } else if ($xml->name === 'Include') {
            $file = $xml->getAttribute('file');
            $included = new XmlReader();
            $included->open($file);
            $this->parseWithIncludes($included);
            $included->close();
          } else if ($xml->name === 'Event') {
            $this->event = $xml->getAttribute('type') ?? 'nope';
          } else if ($xml->name === 'AC') {
            $this->current->addChildClass($xml->getAttribute('class'));
          } else {
            $type = str_replace('_', '', ucwords($xml->name, '_'));
            $element = 'SPTK\\' . $type;
            $name = $xml->getAttribute('name') ?? false;
            $class = $xml->getAttribute('class') ?? false;
            if (!class_exists($element)) {
              $element = 'SPTK\\Element';
            }
            $this->current = new $element($this->current, $name, $class, $type);
            $attributes = $this->current->getAttributeList();
            foreach ($attributes as $attribute) {
              $value = $xml->getAttribute($attribute) ?? false;
              $fname = 'set' . ucfirst($attribute);
              $this->current->$fname($value);
            }
            if ($xml->isEmptyElement) {
              if (!is_null($this->current)) {
                $this->current = $this->current->getAncestor();
              }
            }
          }
          break;
        case XMLReader::END_ELEMENT:
          if ($xml->name === 'Root') {
            // skip
          } else if ($xml->name === 'Include') {
            // skip
          } else if ($xml->name === 'Event') {
            $this->event = false;
          } else if ($xml->name === 'AC') {
            $this->current->removeChildClass($xml->getAttribute('class'));
          } else {
            if (!is_null($this->current)) {
              $this->current = $this->current->getAncestor();
            }
          }
          break;
        case XMLReader::TEXT:
          if ($this->event !== false) {
            $this->current->addEvent($this->event, trim($xml->value));
            break;
          }
          $txt = trim($xml->value);
          $txt = strtr($txt, ["\n" => ' ', "\t" => ' ']);
          $txt = preg_replace('/ +/', ' ', $txt);
          $words = explode(' ', $txt);
          foreach ($words as $i => $word) {
            $w = new Word($this->current);
            $w->setValue($word);
            unset($w);
          }
          break;
      }
    }
  }

}
