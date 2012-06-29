<?php
namespace iHerbarium;
require_once("myPhpLib.php");

/* An Event of iHerbarium Observation Deposit Protocol. */
abstract class ProtocolEvent {
  public $date;
  public $lang;

  abstract public function type();

  abstract public function getConsumedBy(ProtocolEventConsumer $consumer);

  // Debug printing
  protected function debugStringsArray() {
    $lines = array();
    $lines[] = "TYPE: " . $this->type();
    $lines[] = "DATE: " . $this->date;
    $lines[] = "LANG: " . $this->lang;

    return $lines;
  }

  final protected function debugString() {
    return 
      mkString(
	       $this->debugStringsArray(),
	       "<p>EVENT<ul><li>", "</li><li>", "</li></ul></p>"
      );
  }
  
  function __toString() { return $this->debugString(); }
}

/* NewPhoto: Somebody sends a new Photo. */
class NewPhotoEvent extends ProtocolEvent {
  
  public $from;
  public $tag;
  public $comments;
  public $image;
  public $imageSubtype;

  public function type() {
    return "NewPhotoEvent";
  }

  public function getConsumedBy(ProtocolEventConsumer $consumer) {
    $consumer->consumeNewPhotoEvent($this);
  }

  // Debug printing
  protected function debugStringsArray() {
    $lines   = parent::debugStringsArray();
    $lines[] = "FROM: " . $this->from;
    $lines[] = "TAG: " . $this->tag;
    $lines[] = "COMMENTS: " . $this->comments;
    $lines[] = "IMAGE: " . (isset($this->image) ? "[lots of data]" : "[no data!]");
    $lines[] = "IMAGE SUBTYPE: " . $this->imageSubtype; 
    
    return $lines;
  }
}

/* SaveObservationRequest: A User asks to save all
 * the Photos he recently sent us as a new Observation. */
class SaveObservationRequestEvent
extends ProtocolEvent {
  
  public $from;

  public function type() {
    return "SaveObservationRequestEvent";
  }

  public function getConsumedBy(ProtocolEventConsumer $consumer) {
    $consumer->consumeSaveObservationRequestEvent($this);
  }

  // Debug printing
  protected function debugStringsArray() {
    $lines   = parent::debugStringsArray();
    $lines[] = "FROM: " . $this->from;
    return $lines;
  }
}

/* SaveObservationConfirmation: A User confirms that he sent us
 * some Photos and asked to save them as a new Observation */
class SaveObservationConfirmationEvent 
extends ProtocolEvent {

  public $from;
  public $confirmationCode;

  public function type() {
    return "SaveObservationConfirmationEvent";
  }

  public function getConsumedBy(ProtocolEventConsumer $consumer) {
    $consumer->consumeSaveObservationConfirmationEvent($this);
  }

  // Debug printing
  protected function debugStringsArray() {
    $lines   = parent::debugStringsArray();
    $lines[] = "FROM: " . $this->from;
    $lines[] = "CONFIRMATION CODE: " . $this->confirmationCode;
    return $lines;
  }
}

/* ResetRequest: A User requests to discard all his
 * not confirmed Observations and not saved Photos. */
class ResetRequestEvent 
extends ProtocolEvent {
  
  public $from;

  public function type() {
    return "ResetRequestEvent";
  }

  public function getConsumedBy(ProtocolEventConsumer $consumer) {
    $consumer->consumeResetRequestEvent($this);
  }

  // Debug printing
  protected function debugStringsArray() {
    $lines   = parent::debugStringsArray();
    $lines[] = "FROM: " . $this->from;
    return $lines;
  }
}


?>