<?php
namespace iHerbarium;
require_once("myPhpLib.php");

function mimeTypeToString($type) {
  switch($type) {
    case TYPETEXT        : return "text";
    case TYPEMULTIPART   : return "multipart";
    case TYPEMESSAGE     : return "message";
    case TYPEAPPLICATION : return "application";
    case TYPEAUDIO       : return "audio";
    case TYPEIMAGE       : return "image";
    case TYPEVIDEO       : return "video";
    case TYPEOTHER       : return "other";
    default              : return "ERROR!";
  }
}

abstract class MimeContent {
  // Part's content type, subtype and it's parameters.
  public $type;
  public $subtype;
  public $type_parameters;

  // Part's content disposition (if any) and it's parameters.
  public $disposition;
  public $disposition_parameters;

  // Check disposition.
  final public function isInline() { return (! $this->isAttached() ); }
  final public function isAttached() { return isset($this->disposition) && ($this->disposition == "attached"); }

  // Debug printing.
  protected function debugStringsArray() {
    $lines   = array();
    $lines[] = "TYPE/SUBTYPE: " . mimeTypeToString($this->type) . "/" . $this->subtype;
    $lines[] = "DISPOSITION: " . ( $this->disposition ? $this->disposition : "[unset]" );
    return $lines;
  }
  
  final public function debugString() {
    return 
      mkString(
        $this->debugStringsArray(),
	"<p>MIME CONTENT<ul><li>", "</li><li>", "</li></ul></p>"
      );
  }

  function __toString() { return $this->debugString(); }

  // Get an array of all attached (and inline) files.
  abstract public function getFiles();

// Get an array of all attached (and inline) images.
  abstract public function getImages();
  
  // Save attached (and inline) files.
  abstract public function saveFiles($dir);  
}

abstract class MimeSimpleContent extends MimeContent {
  public $data;

  public function getFiles() { return array(); }
  public function getImages() { return array(); }
  
  public function saveFiles($dir) {}
}

class MimeTextContent extends MimeSimpleContent {
  
  protected function debugStringsArray() {
    $lines = parent::debugStringsArray();
    $lines[] = "TEXT: " . ($this->data ? $this->data : "[no text]");
    return $lines;
  }
  
}

class MimeFileContent extends MimeSimpleContent {
  
  public $filename;
  public $saved = False;
  public $localFilename = NULL;
  public $localDir = NULL;

  protected function debugStringsArray() {
    $lines   = parent::debugStringsArray();

    $lines[] = "FILENAME: " . $this->filename;
    $lines[] = "SAVED: " . $this->saved;
    $lines[] = "LOCAL FILENAME: " . $this->localFilename;    
    $lines[] = "LOCAL DIR: " . $this->localDir;
    $lines[] = "FILE: <a href=\"" . Config::get("attachmentsURL") . "$this->localFilename\" >HERE</a>" ;

    return $lines;
  }

  public function getFiles() { return array($this); }
  
  public function saveFiles($dir) {
    // Prepare local name.
    $localFilename = "file_" . time() . "_" . rand() . "." . $this->subtype;

    // Prepare local path.
    $saveToPath = $dir . $localFilename;

    // Write the data to disk.
    debug("Debug", "MimeFileContent", "Writing attachment to $saveToPath!");
    file_put_contents($saveToPath, $this->data);
    
    // Update properties.
    $this->saved = True;
    $this->localFilename = $localFilename;
    $this->localDir = $dir;
  }
}

class MimeImageContent 
extends MimeFileContent {

  protected function debugStringsArray() {
    $lines   = parent::debugStringsArray();
    $lines[] = "PREVIEW: <img height=150px width=150px src=\""  . Config::get("attachmentsURL") . "$this->localFilename\" />" ;

    return $lines;
  }

  public function getImages() { return array($this); }

}

class MimeMultipartContent extends MimeContent {
  public $parts;

  protected function debugStringsArray() {
    $partDebugTextCallback = 
      function(MimeContent $part) { return $part->debugString(); };

    $lines = parent::debugStringsArray();
    $lines[] =
      mkString(
	       array_map($partDebugTextCallback, $this->parts),
	       "<p>PARTS<ul><li>", "</li><li>", "</li></ul></p>"
	       );
    
    return $lines;
  }

  public function getFiles() {
    $partGetFilesCallback =
      function(MimeContent $part) { return $part->getFiles(); };

    return array_flatten(array_map($partGetFilesCallback, $this->parts));
  }


  public function getImages() {
    $partGetImagesCallback =
      function(MimeContent $part) { return $part->getImages(); };

    return array_flatten(array_map($partGetImagesCallback, $this->parts));
  }

  public function saveFiles($dir) {
    $partSaveFilesCallback = function(MimeContent $part) use ($dir) { 
      $part->saveFiles($dir); 
    };

    array_iter($partSaveFilesCallback, $this->parts);
  }

}

?>