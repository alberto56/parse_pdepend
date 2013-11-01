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
 *     parse_pdepend.php sites/default/files/ci/test.xml csv
 *
 *     - or -
 *
 *     parse_pdepend.php sites/default/files/ci/test.xml
 *
 * If you add the argument "csv", the output will be in CSV, otherwise
 * it will be in human-readable form.
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
      Output::Line('Max logical lines of code per ' . $type, $object->Max());
      Output::Line('All logical lines of code per ' . $type, $object->All());
      Output::Line($type . ' with the most lines of code', $object->MaxName());
      Output::Line('Average logical lines of code per ' . $type, $object->Average());
      Output::Line('Mean logical lines of code per ' . $type, $object->Mean());
    }
    Output::Display();
  }
  catch (Exception $e) {
    Output::Line('The following error occurred', $e->getMessage());
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
    return self::GetExternalArg(ARG_POSITION_DEST, FALSE);
  }

  static function GetExternalArg($position, $required = TRUE) {
    global $argv;
    if ($required && !isset($argv[$position])) {
      throw new Exception('Expected arguments were not present. Usage: php parse_pdepend source.xml dest.csv.');
    }
    $return = NULL;
    if (isset($argv[$position])) {
      $return = $argv[$position];
    }
    return $return;
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

  function Max($type = 'lloc') {
    $lines = 0;
    foreach ($this->GetList() as $item) {
      $lines = max((int)$item[$type], $lines);
    }
    return $lines;
  }

  function All($type = 'lloc') {
    $lines = array();
    foreach ($this->GetList() as $item) {
      $lines[(string)$item->attributes()->name[0]] = (int)$item[$type];
    }
    asort($lines);
    return $lines;
  }

  function MaxName($type = 'lloc') {
    $lines = 0;
    $name = 'none';
    foreach ($this->GetList() as $item) {
      if ((int)$item[$type] > $lines) {
        $lines = (int)$item[$type];
        $name = (string)$item->attributes()->name[0];
      }
    }
    return $name;
  }

  function Average($type = 'lloc') {
    $lines = 0;
    foreach ($this->GetList() as $item) {
      $lines += (int)$item[$type];
    }
    return floor($lines / count($this->GetList()));
  }

  function Mean($type = 'lloc') {
    $lines = array();
    foreach ($this->GetList() as $item) {
      $lines[] = (int)$item[$type];
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

abstract class Output {
  static protected $lines;

  static function Line($param, $value) {
    if (!is_array(self::$lines)) {
      self::$lines = array();
    }
    self::$lines[$param] = $value;
  }

  static function Display() {
    self::GetObject()->_Display_();
  }

  private static $object;

  static function GetObject() {
    if (!self::$object) {
      $dest = Environment::GetDest();
      if ($dest) {
        self::$object = new CSVOutput;
      }
      else {
        self::$object = new LineOutput;
      }
    }
    return self::$object;
  }

  abstract function _Display_();
}

class LineOutput extends Output {
  function _Display_() {
    foreach (self::$lines as $param => $value) {
      echo $param . ':
';
      echo print_r($value, TRUE) . '
';
    }
  }
}

class CSVOutput extends Output {
  function _Display_() {
    $header = array();
    $values = array();

    foreach (self::$lines as $param => $value) {
      if (is_int($value)) {
        $header[] = $param;
        $values[] = $value;
      }
    }

    echo implode(',', $header) . '
' . implode(',', $values);
  }
}
