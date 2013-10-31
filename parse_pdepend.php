<?php

/**
 * @file
 *
 * This script is meant to be used via the command line to parse
 * output from pDepend into easy-to-read information about logical
 * lines of code in files and functions.
 *
 * Usage:
 *
 * (1) Install [pDepend](http://pdepend.org/)
 * (2) Run pDepend to generate an xml file, for example with Drupal
 *     you may run something like:
 *
 *     pdepend --summary-xml='sites/default/files/ci/test.xml'\
 *     --suffix=test,install,module\
 *     sites/all/modules/custom
 * (3) Run:
 *
 *     parse_pdepend.php sites/default/files/ci/test.xml dest.csv
 *     in this version dest.csv is ignored. Eventually dest.csv might
 *     be used to export the data for use with Jenkins.
 *
 * by Albert Albala, https://drupal.org/user/245583
 */

define('ARG_POSITION_SOURCE', 1);
define('ARG_POSITION_DEST', 2);

main();

function main() {
  try {
    Environment::Check();
    $xml = Environment::GetSourceXML();
    $output = array(
      'file' => $xml->Files(),
      'function' => $xml->Functions(),
    );
    foreach ($output as $type => $object) {
      Output::Line('Max logical lines of code per ' . $type . ': ' . $object->Max());
      Output::Line('All logical lines of code per ' . $type . ': ');
      Output::Line($object->All());
      Output::Line($type . ' with the most lines of code: ' . $object->MaxName());
      Output::Line('Average logical lines of code per ' . $type . ': ' . $object->Average());
      Output::Line('Mean logical lines of code per ' . $type . ': ' . $object->Mean());
    }
  }
  catch (Exception $e) {
    Output::Line('The following error occurred: ' . $e->getMessage());
  }
}

class Environment {
  static function Check() {
    self::GetSourcePath();
    self::GetDest();
  }

  static function GetSourceXML() {
    $file = @simplexml_load_file(self::GetSourcePath());
    if (!$file) {
      throw new Exception('Your XML file does not seem to be valid: it cannot be parsed by simplexml_load_file()');
    }
    return new PDependXML($file);
  }

  static function GetSourcePath() {
    return self::GetExternalArg(ARG_POSITION_SOURCE);
  }

  static function GetDest() {
    return self::GetExternalArg(ARG_POSITION_DEST);
  }

  static function GetExternalArg($position) {
    global $argv;
    if (!isset($argv[$position])) {
      throw new Exception('Expected arguments were not present. Usage: php parse_pdepend source.xml dest.csv.');
    }
    return $argv[$position];
  }
}

class PDependXML {
  private $xml_object;
  
  function __construct($xml_object) {
    $this->xml_object = $xml_object;
  }

  function GetXMLObject() {
    return $this->xml_object;
  }

  function Files() {
    return new PDependXMLFiles($this);
  }

  function Functions() {
    return new PDependXMLFunctions($this);
  }
}

abstract class PDependXMLList {
  private $object;

  function __construct($object) {
    $this->object = $object;
  }

  function GetXMLObject() {
    return $this->object->GetXMLObject();
  }

  function Count() {
    return count($this->GetList());
  }

  function Max() {
    $lines = 0;
    foreach ($this->GetList() as $item) {
      $lines = max((int)$item->attributes()->lloc[0], $lines);
    }
    return $lines;
  }

  function All() {
    $lines = array();
    foreach ($this->GetList() as $item) {
      $lines[(string)$item->attributes()->name[0]] = (int)$item->attributes()->lloc[0];
    }
    asort($lines);
    return $lines;
  }

  function MaxName() {
    $lines = 0;
    $name = 'none';
    foreach ($this->GetList() as $item) {
      if ((int)$item->attributes()->lloc[0] > $lines) {
        $lines = (int)$item->attributes()->lloc[0];
        $name = (string)$item->attributes()->name[0];
      }
    }
    return $name;
  }

  function Average() {
    $lines = 0;
    foreach ($this->GetList() as $item) {
      $lines += (int)$item->attributes()->lloc[0];
    }
    return floor($lines / count($this->GetList()));
  }

  function Mean() {
    $lines = array();
    foreach ($this->GetList() as $item) {
      $lines[] = (int)$item->attributes()->lloc[0];
    }
    rsort($lines);
    return $lines[floor(count($lines) / 2)];
  }

  abstract function GetList();
}

class PDependXMLFiles extends PDependXMLList {
  function GetList() {
    return $this->GetXMLObject()->files->file;
  }
}

class PDependXMLFunctions extends PDependXMLList {
  function GetList() {
    return $this->GetXMLObject()->package->function;
  }
}

class Output {
  static function Line($string) {
    echo '
' . print_r($string, TRUE) . '
';
  }
}
