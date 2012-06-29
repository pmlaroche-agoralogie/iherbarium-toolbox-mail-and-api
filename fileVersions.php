<?php
namespace iHerbarium;

require_once("myPhpLib.php");


/* Description of a file version - where it should be put, it's maximal dimensions etc. */
class TypoherbariumFileVersion {

  public $name    = NULL; public function setName($name) { $this->name = $name; return $this; }
  public $dir     = NULL; public function setDir($dir) { $this->dir = $dir; return $this; }
  public $url     = NULL; public function setUrl($url) { $this->url = $url; return $this; }
  public $maxSize = NULL; public function setMaxSize($maxSize) { $this->maxSize = $maxSize; return $this; }

  static public function make() {
    return new static();
  }

  static public function fromObj($obj) {
    $fileVersion = new static();
    return fillFromStdObj($fileVersion, $obj);
  }

  public function instantiate($filename) {
    return 
      TypoherbariumFileVersionExisting::fromObj($this)
      ->setFilename($filename);
  }
  
}

/* Description of an existing file version (the described file really exists on the hard disk). */
class TypoherbariumFileVersionExisting
extends TypoherbariumFileVersion {
  public $filename    = NULL; public function setFilename($filename) { $this->filename = $filename; return $this; }  

  public function url() {
    return $this->url . $this->filename;
  }

  public function path() {
    return $this->dir . $this->filename;
  }

}


?>