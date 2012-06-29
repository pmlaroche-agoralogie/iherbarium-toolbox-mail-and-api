<?php
namespace iHerbarium;
require_once("myPhpLib.php");
require_once("protocolMessage.php");
require_once("protocolMessageConsumer.php");
require_once("mailFormFactory.php");
require_once("contentTemplate.php");
require_once("protocolState.php");
require_once("protocolModel.php");
require_once("persistentObject.php");
require_once("protocolEventConsumerPieces.php");

// ProtocolEvent Consumers

class DebugEventConsumer
implements ProtocolEventConsumer {
  public function consumeProtocolEvent(ProtocolEvent $event) {
    debug("Debug", "DebugEventConsumer", "Consumes an event.", $event);
  }
}

class MultipleEventConsumer
implements ProtocolEventConsumer {
  private $eventConsumers = array();

  public function consumeProtocolEvent(ProtocolEvent $event) {
    foreach($this->eventConsumers as $eventConsumer) {
      $eventConsumer->consumeProtocolEvent($event);
    }
  }

  public function addEventConsumer(ProtocolEventConsumer $eventConsumer) {
    $this->eventConsumers[] = $eventConsumer;
  }
}

// ProtocolEvent Handlers

abstract class ProtocolEventHandler
implements
  ProtocolEventConsumer,
  NewPhotoEventConsumer, 
  SaveObservationRequestConsumer, 
  SaveObservationConfirmationConsumer, 
  ResetRequestConsumer {

  abstract public function me();

  final public function consumeProtocolEvent(ProtocolEvent $event) {
    //debug("Debug", "ProtocolEventConsumer", "Consumes a Message.", $event);
    Logger::logProtocolEventConsumerConsumed($this, $event);
    $event->getConsumedBy($this);
  }
}


// iHerbarium Event Handler

class IHerbariumEventHandler
extends ProtocolEventHandler
implements ProtocolEventConsumer {

  public $me = "";
  public function me() { return ($this->me . "EventHandler"); }
  
  public function debug($type, $string, $description = "") {
    debug($type, $this->me(), $string, $description);
  }

  // Local and Remote data storage.
  public $local = NULL;
  public $remote = NULL;

  // Message Consumers.
  public $mailSender = NULL;

  // Working Pieces
  public $pieceNewPhoto = NULL;
  public $pieceSaveObservationRequest = NULL;
  public $pieceSaveObservationConfirmation = NULL;
  public $pieceResetRequest = NULL;

  public function getUser($event) {
    // User's e-mail address.
    $eMail = $event->from;

    $this->debug("Begin", "Trying to find User...");       

    // Try to find him locally...
    $this->debug("Begin", "Trying to find User locally...");
    $user = $this->local->loadUser($eMail);
    if( is_null($user) ) {
      $this->debug("Begin", "User not found locally, searching remotely...");
      // This User is not cached locally, let's try remote.
      
      // Prepare UserInfoRequest.
      $userInfoRequest = new UserInfoRequest();
      $userInfoRequest->eMail = $eMail;
      $userInfoRequest->lang = $event->lang;

      // Get UserInfoAnswer.
      $userInfo = $this->remote->loadUserInfo($userInfoRequest);
      assert($userInfo);

      if($userInfo->status == "UserDoesntExist") {
	$this->debug("Ok", "User does not exist! Sending a YouAreNotRegisteredMessage.");
	// This User doesn't exist, so sender is not registered.
	// => Send him a YouAreNotRegisteredMessage
	  
	// Prepare a YouAreNotRegistered message.
	$msg = new YouAreNotRegisteredMessage();
	$msg->to = $eMail;
	$msg->lang = $event->lang;
	  
	// Send it.
	$this->mailSender->consumeProtocolMessage($msg);
	
	// Return nothing.
	return NULL;
      }


      if($userInfo->status == "UserJustCreated") {
	$this->debug("Debug", "User has just been registered remotely! Sending a YouHaveBeenRegisteredMessage.");
	// This User has just been registered automatically.
	// => Send him a YouHaveBeenRegisteredMessage
	  
	// Prepare a YouHaveBeenRegistered message.
	$msg = new YouHaveBeenRegisteredMessage();
	$msg->to = $eMail;
	$msg->lang = $event->lang;
	$msg->username = $userInfo->username;
	$msg->password = $userInfo->password;
	  
	// Send it.
	$this->mailSender->consumeProtocolMessage($msg);
	
	// As User exists now - go back to processing Event caused by him.
      }


      $this->debug("Ok", "User exists remotely!");
      
      // Preparing a User Model based on remote data.
      $user = new User();
      $user->eMail = $eMail;
      $user->state = new StateInit(); // Initially state 'Init'.

      $this->debug("Debug", "Saving a new User (based on remote data) in local database.");
      $this->local->saveUser($user);

      // Return
      return $user;
    } 
    else { 
      $this->debug("Ok", "User found locally!");
      return $user;
    }
  }

  public function consumeNewPhotoEvent(NewPhotoEvent $event) {
    $this->pieceNewPhoto->consumeNewPhotoEvent($event, $this);
  }
  
  public function consumeSaveObservationRequestEvent(SaveObservationRequestEvent $event) {
    $this->pieceSaveObservationRequest->consumeSaveObservationRequestEvent($event, $this);
  }

  
  public function consumeSaveObservationConfirmationEvent(SaveObservationConfirmationEvent $event) {
    $this->pieceSaveObservationConfirmation->consumeSaveObservationConfirmationEvent($event, $this);
  }

  public function consumeResetRequestEvent(ResetRequestEvent $event) {
    $this->pieceResetRequest->consumeResetRequestEvent($event, $this);
  }

}

?>