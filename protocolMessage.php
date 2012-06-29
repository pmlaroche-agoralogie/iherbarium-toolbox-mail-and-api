<?php
namespace iHerbarium;
require_once("myPhpLib.php");

abstract class ProtocolMessage {
  public $to;
  public $lang;

  abstract public function type();

  abstract public function getConsumedBy(ProtocolMessageConsumer $consumer);

  // Debug printing
  protected function debugStringsArray() {
    $lines   = array();
    $lines[] = "TYPE: " . $this->type();
    $lines[] = "TO: " . $this->to;
    $lines[] = "LANG: " . $this->lang;

    return $lines;
  }

  final protected function debugString() {
    return 
      mkString(
	       $this->debugStringsArray(),
	       "<p>PROTOCOL MESSAGE<ul><li>", "</li><li>", "</li></ul></p>"
	       );
  }

  function __toString() { return $this->debugString(); }
}

class YouAreNotRegisteredMessage extends ProtocolMessage {

  public function type() {
    return "YouAreNotRegisteredMessage";
  }

  public function getConsumedBy(ProtocolMessageConsumer $consumer) {
    $consumer->consumeYouAreNotRegisteredMessage($this);
  }
  
  // Debug printing   
  protected function debugStringsArray() {
    $lines   = parent::debugStringsArray();  
    return $lines;
  }
}

class YouHaveBeenRegisteredMessage extends ProtocolMessage {
  public $username;
  public $password;

  public function type() {
    return "YouHaveBeenRegisteredMessage";
  }

  public function getConsumedBy(ProtocolMessageConsumer $consumer) {
    $consumer->consumeYouHaveBeenRegisteredMessage($this);
  }
  
  // Debug printing   
  protected function debugStringsArray() {
    $lines   = parent::debugStringsArray();  
    $lines[] = "USERNAME: " . $this->username;
    $lines[] = "PASSWORD: " . $this->password;
    return $lines;
  }
}

class FirstPhotoAckMessage extends ProtocolMessage {

  public function type() {
    return "FirstPhotoAckMessage";
  }

  public function getConsumedBy(ProtocolMessageConsumer $consumer) {
    $consumer->consumeFirstPhotoAckMessage($this);
  }

  // Debug printing   
  protected function debugStringsArray() {
    $lines   = parent::debugStringsArray();
    return $lines;
  }
}

class RequestConfirmationMessage extends ProtocolMessage {
  public $confirmationCode;

  public function type() {
    return "RequestConfirmationMessage";
  }

  public function getConsumedBy(ProtocolMessageConsumer $consumer) {
    $consumer->consumeRequestConfirmationMessage($this);
  }

  // Debug printing
  protected function debugStringsArray() {
    $lines   = parent::debugStringsArray();
    $lines[] = "CONFIRMATION CODE: " . $this->confirmationCode;
    return $lines;
  }

}

class ConfirmationAckMessage extends ProtocolMessage {

  public function type() {
    return "ConfirmationAckMessage";
  }

  public function getConsumedBy(ProtocolMessageConsumer $consumer) {
    $consumer->consumeConfirmationAckMessage($this);
  }

  // Debug printing   
  protected function debugStringsArray() {
    $lines   = parent::debugStringsArray();
    return $lines;
  }
}
