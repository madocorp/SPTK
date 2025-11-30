<?php

namespace SPTK;

use XMLReader;

class LayoutXmlReader {

  protected $root;

  public function __construct($file) {
    if (!file_exists($file)) {
      throw new \Exception("File not found: {$file}");
    }
    $this->root = new Root();
    $current = $this->root;
    $xml = new XMLReader();
    if (!$xml->open($file)) {
      throw new \Exception("Couldn't open file: {$file}");
    }
    $event = false;
    while ($xml->read()) {
      switch ($xml->nodeType) {
        case XMLReader::ELEMENT:
          if ($xml->name === 'Event') {
            $event = $xml->getAttribute('type') ?? 'nope';
          } else if ($xml->name === 'AC') {
            $current->addChildClass($xml->getAttribute('class'));
          } else {
            $type = 'SPTK\\' . str_replace('_', '', ucwords($xml->name, '_'));
            $id = $xml->getAttribute('id') ?? false;
            $class = $xml->getAttribute('class') ?? false;
            $current = new $type($current, $id, $class);
            $attributes = $current->getAttributeList();
            foreach ($attributes as $attribute) {
              $value = $xml->getAttribute($attribute) ?? false;
              $fname = 'set' . ucfirst($attribute);
              $current->$fname($value);
            }
            if ($xml->isEmptyElement) {
              if (!is_null($current)) {
                $current = $current->getAncestor();
              }
            }
          }
          break;
        case XMLReader::END_ELEMENT:
          if ($xml->name === 'Event') {
            $event = false;
          } else if ($xml->name === 'AC') {
            $current->removeChildClass($xml->getAttribute('class'));
          } else {
            if (!is_null($current)) {
              $current = $current->getAncestor();
            }
          }
          break;
        case XMLReader::TEXT:
          if ($event !== false) {
            $current->addEvent($event, trim($xml->value));
            break;
          }
          $txt = trim($xml->value);
          $txt = strtr($txt, ["\n" => ' ', "\t" => ' ']);
          $txt = preg_replace('/ +/', ' ', $txt);
          $words = explode(' ', $txt);
          foreach ($words as $i => $word) {
            $w = new Word($current);
            $w->setValue($word);
            unset($w);
          }
          break;
      }
    }
    $xml->close();
  }

}
