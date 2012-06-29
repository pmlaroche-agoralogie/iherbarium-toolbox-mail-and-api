<?php
namespace iHerbarium;
require_once("myPhpLib.php");

abstract class State {
  const name = PROTOCOL_STATE_NO_STATE;

  final public function name() { return static::name; }

  // Debug printing
  protected function debugStringsArray() {
    $lines   = array();
    $lines[] = "name: " . protocolStateArray($this->name());
    return $lines;
  }

  final protected function debugString() {
    return 
      mkString(
	       $this->debugStringsArray(),
	       "<p>State (Model)<ul><li>", "</li><li>", "</li></ul></p>"
	       );
  }

  function __toString() { return $this->debugString(); }
}

class StateInit extends State {
  const name = PROTOCOL_STATE_INIT;
}

class StateCollectPhotos extends State {
  const name = PROTOCOL_STATE_COLLECT_PHOTOS;

  public $handler = NULL;
  public $lang    = NULL;

  // Debug printing
  protected function debugStringsArray() {
    $lines   = parent::debugStringsArray();
    $lines[] = "handler: " . $this->handler;
    $lines[] = "lang: "    . $this->lang;
    
    return $lines;
  }

}

class StateConfirm extends State {
  const name = PROTOCOL_STATE_CONFIRM;
}


class User {
  public $eMail = NULL;
  public $state = NULL;

  // Debug printing
  protected function debugStringsArray() {
    $lines   = array();
    $lines[] = "eMail: " . $this->eMail;
    $lines[] = "state: " . $this->state;

    return $lines;
  }

  final protected function debugString() {
    return 
      mkString(
	       $this->debugStringsArray(),
	       "<p>User (Model)<ul><li>", "</li><li>", "</li></ul></p>"
	       );
  }

  function __toString() { return $this->debugString(); }
}

class Photo {
  public $id           = NULL;
  public $user         = NULL;
  public $timestamp    = NULL;
  public $tag          = NULL;
  public $comments     = NULL;
  public $image        = NULL;
  public $imageSubtype = NULL;

  // Debug printing
  protected function debugStringsArray() {
    $lines   = array();
    $lines[] = "id: "           . $this->id;
    $lines[] = "user: "         . $this->user;
    $lines[] = "timestamp: "    . $this->timestamp;
    $lines[] = "tag: "          . (($this->tag) ? $this->tag : "[no tag!]");
    $lines[] = "comments: "     . (($this->comments) ? $this->comments : "[no comments!]");
    $lines[] = "image: "        . (($this->image) ? "[lots of data]" : "[no data!]");
    $lines[] = "imageSubtype: " . $this->imageSubtype;

    return $lines;
  }

  final protected function debugString() {
    return 
      mkString(
	       $this->debugStringsArray(),
	       "<p>Photo (Model)<ul><li>", "</li><li>", "</li></ul></p>"
	       );
  }

  function __toString() { return $this->debugString(); }
}

class Observation {
  public $id = NULL;
  public $user = NULL;
  public $confirmationCode = NULL;
  public $isConfirmed = NULL;
  public $photos = NULL;

  public function confirmWith($confirmationCode) {
    $this->isConfirmed = True;
    //$this->isConfirmed = ($confirmationCode == $this->confirmationCode);
    debug("Debug", "Observation", "Confirming '$confirmationCode' against '$this->confirmationCode' : $this->isConfirmed");
  }

  // Debug printing
  protected function debugStringsArray() {
    $lines   = array();
    $lines[] = "id: " . $this->id;
    $lines[] = "user: " . $this->user;
    $lines[] = "confirmationCode: " . $this->confirmationCode;
    $lines[] = "isConfirmed: " . ( is_null($this->isConfirmed) ? "NULL" : ($this->isConfirmed ? "True" : "False"));
    $lines[] = "photos: " . 
      mkString(
	       array_map(function(Photo $photo) { return $photo; }, $this->photos),
	       "<p>Photos:<ul><li>", "</li><li>", "</li></ul></p>"
	       );

    return $lines;
  }

  final protected function debugString() {
    return 
      mkString(
	       $this->debugStringsArray(),
	       "<p>Observation (Model)<ul><li>", "</li><li>", "</li></ul></p>"
	       );
  }

  function __toString() { return $this->debugString(); }
}

?>