<?php
namespace iHerbarium;
require_once("myPhpLib.php");

// Consumer Interfaces

interface ProtocolEventConsumer {
  public function consumeProtocolEvent(ProtocolEvent $event);
}

interface NewPhotoEventConsumer {
  public function consumeNewPhotoEvent(NewPhotoEvent $event);
}

interface SaveObservationRequestConsumer {
  public function consumeSaveObservationRequestEvent(SaveObservationRequestEvent $event);
}

interface SaveObservationConfirmationConsumer {
  public function consumeSaveObservationConfirmationEvent(SaveObservationConfirmationEvent $event);
}

interface ResetRequestConsumer {
  public function consumeResetRequestEvent(ResetRequestEvent $event);
}

// Pieces

class NewPhotoConsumingPiece {

  public function consumeNewPhotoEvent(NewPhotoEvent $event, ProtocolEventHandler $context) {
    $context->debug("Begin", "<h3>Consumes a NewPhotoEvent.</h3>");

    // 1. Get the User.
    $context->debug("Debug", "Getting the User...");
    $user = $context->getUser($event);
    
    // If user is not registered.
    if( is_null($user) ) return;

    // 2. Save the new photo.
    
    // Prepare the photo.
    $context->debug("Debug", "Preparing the Photo...");
    $photo = new Photo();
    $photo->id           =  NULL;
    $photo->user         =& $user;
    $photo->timestamp    =  strtotime($event->date);
    $photo->comments     =  $event->comments;
    $photo->tag          =  $event->tag;
    $photo->image        =  $event->image;
    $photo->imageSubtype =  $event->imageSubtype;

    // Save it locally.
    $context->debug("Debug", "Saving the Photo...");
    $context->local->savePhoto($photo);

    // 3. If this was the first photo, send a FirstPhotoAck message.
    if($user->state->name() == PROTOCOL_STATE_INIT) {
      $context->debug("Debug", "First photo! Sending a FirstPhotoAckMessage...");
      // Prepare a First Photo Ack message
      $msg = new FirstPhotoAckMessage();
      $msg->to   = $user->eMail;
      $msg->lang = $event->lang;

      // Send it by mail
      $context->mailSender->consumeProtocolMessage($msg);

      // Update User's state to COLLECT_PHOTOS.
      $context->debug("Debug", "Updating User's State to COLLECT_PHOTOS");
      $user->state = new StateCollectPhotos();
      $user->state->handler = $context->me;
      $user->state->lang    = $event->lang;
      $context->local->saveUser($user);

    } else {
      $context->debug("Debug", "Not a first photo, not sending anything.");
    }
    
  }

}


interface SaveObservationRequestConsumingPiece {
  public function consumeSaveObservationRequestEvent(SaveObservationRequestEvent $event, ProtocolEventHandler $context);
}

class SaveObservationRequestConsumingPieceWithConfirmation
implements SaveObservationRequestConsumingPiece {

  public function consumeSaveObservationRequestEvent(SaveObservationRequestEvent $event, ProtocolEventHandler $context) {
    $context->debug("Begin", "<h3>Consumes a SaveObservationRequestEvent.</h3>");
    
    // 1. Get the User.
    $context->debug("Debug", "Getting the User...");
    $user = $context->getUser($event);
    
    // If user is not registered.
    if( is_null($user) ) return;

    // Check his state.
    if($user->state->name() != PROTOCOL_STATE_COLLECT_PHOTOS &&
       $user->state->name() != PROTOCOL_STATE_CONFIRM) {
      // Wrong user state!
      return;
    }

    /* 2. Prepare a new Observation for User 
     * (all his fresh Photos are linked to the new Observation) 
     * OR generate a new code for the last one. */

    // Get his last Observation.
    $context->debug("Debug", "Checking if the User has a not confirmed Observation...");
    $obs = $context->local->loadLastObservationOfUser($user);

    if( is_null($obs) ) {
      // There are none not confirmed Observations,
      // we create a new one.
      $context->debug("Debug", "Creating a new Observation for this User...");
    
      // Prepare a new Observation.
      $obs = new Observation();
      $obs->id               =  NULL;
      $obs->user             =& $user;
      $obs->photos           =  array();
      $obs->confirmationCode =  "code_" . time() . "_" . rand();
      $obs->isConfirmed      =  False;
    
      // Create it.
      $obs = $context->local->createObservation($obs);
      assert($obs);

      $context->debug("Debug", "Created Observation.", $obs);
    }
    else {
      // There is a not confirmed Observation.
      // we update it's Confirmation Code.

      $context->debug("Debug", "Generating a new Confirmation Code for the non confirmed Observation...");

      $obs->confirmationCode =  "code_" . time() . "_" . rand();
      $context->local->saveObservation($obs);

      $context->debug("Debug", "Saved Observation with a new Confirmation Code.", $obs);
    }
    
    // 3. Update User's state to CONFIRM.
    $user->state = new StateConfirm();
    $user->state->handler = $context->me;
    $user->state->lang    = $event->lang;
    $context->local->saveUser($user);

    // 4. Send a RequestConfirmation message.
    
    // Prepare a RequestConfirmationMessage.
    $msg = new RequestConfirmationMessage();
    $msg->to               = $user->eMail;
    $msg->lang             = $event->lang;
    $msg->confirmationCode = $obs->confirmationCode;

    // Send it by mail.
    $context->mailSender->consumeProtocolMessage($msg);
  }
}


class SaveObservationRequestConsumingPieceWithoutOfConfirmation
implements SaveObservationRequestConsumingPiece {

  public function consumeSaveObservationRequestEvent(SaveObservationRequestEvent $event, ProtocolEventHandler $context) {
    $context->debug("Begin", "<h3>Consumes a SaveObservationRequestEvent.</h3>");
    
    // 1. Get the User.
    $context->debug("Debug", "Getting the User...");
    $user = $context->getUser($event);
    
    // If user is not registered.
    if( is_null($user) ) return;

    // Check his state.
    if($user->state->name() != PROTOCOL_STATE_COLLECT_PHOTOS) {
      // Wrong user state!
      return;
    }
    
    /* 2. Prepare a new Observation for User 
     * (all his fresh Photos are linked to the new Observation) */

    $context->debug("Debug", "Creating a new Observation for this User...");
    
    // Prepare a new Observation.
    $obs = new Observation();
    $obs->id               =  NULL;
    $obs->user             =& $user;
    $obs->photos           =  array();
    $obs->confirmationCode =  "code_" . time() . "_" . rand();
    $obs->isConfirmed      =  True;
    
    // Create it.
    $obs = $context->local->createObservation($obs);
    assert($obs);

    $context->debug("Debug", "Created Observation.", $obs);

    // 3. Save the Observation on remote DB.
    $context->remote->saveObservation($obs);

    // 4. Update User's state back to INIT.
    $user->state = new StateInit();
    $context->local->saveUser($user);
    
    // 5. Send ConfirmationAck message to the User.
    $msg = new ConfirmationAckMessage();
    $msg->to   = $user->eMail;
    $msg->lang = $event->lang;
    
    // Send it by mail.
    $context->mailSender->consumeProtocolMessage($msg);
  }
}


interface SaveObservationConfirmationConsumingPiece {
  public function consumeSaveObservationConfirmationEvent(SaveObservationConfirmationEvent $event, ProtocolEventHandler $context);
}

class StandardSaveObservationConfirmationConsumingPiece
implements SaveObservationConfirmationConsumingPiece {

  public function consumeSaveObservationConfirmationEvent(SaveObservationConfirmationEvent $event, ProtocolEventHandler $context) {
    $context->debug("Begin", "<h3>Consumes a SaveObservationConfirmationEvent.</h3>");
    
    // 1. Get the User.
    $context->debug("Debug", "Getting the User...");
    $user = $context->getUser($event);
    
    // If user is not registered.
    if( is_null($user) ) return;
    
    // else: User found!

    // Check his state.
    if($user->state->name() != PROTOCOL_STATE_CONFIRM) {
      // Wrong user state!
      $context->debug("Error", "Wrong user state! Should be PROTOCOL_STATE_CONFIRM!");
      return;
    }

    // 2. Get his last Observation.
    $context->debug("Debug", "Getting the User's last Observation...");
    $obs = $context->local->loadLastObservationOfUser($user);

    // Check the last Observation.
    if( is_null($obs) ) {
      // There is no last Observation!
      $context->debug("Error", "There is no last Observation!");
      return;
    }
    
    // 3. Check the Confirmation Code.
    $obs->confirmWith($event->confirmationCode);
    if(! $obs->isConfirmed) {
      // Wrong Confirmation Code!
      $context->debug("Error", "The confirmation code of User's last Observation is different!");
      return;
    }
    
    // Everything is OK!
   
    // Update the Observation locally.
    $context->local->saveObservation($obs);    

    // 4. Save the Observation on remote DB.
    $context->remote->saveObservation($obs);

    // 5. Update User's state back to INIT.
    $user->state = new StateInit();
    $context->local->saveUser($user);
    
    // 6. Send ConfirmationAck message to the User.
    $msg = new ConfirmationAckMessage();
    $msg->to = $user->eMail;
    $msg->lang = $event->lang;
    
    // Send it by mail.
    $context->mailSender->consumeProtocolMessage($msg);
  }

}

class IgnoreSaveObservationConfirmationConsumingPiece
implements SaveObservationConfirmationConsumingPiece {

  public function consumeSaveObservationConfirmationEvent(SaveObservationConfirmationEvent $event, ProtocolEventHandler $context) {
    $context->debug("Debug", "<h3>Consumes a SaveObservationConfirmationEvent and... ignores it!<h3>");
  }

}

class ResetRequestConsumingPiece {

  public function consumeResetRequestEvent(ResetRequestEvent $event, ProtocolEventHandler $context) {
    $context->debug("Begin", "<h3>Consumes a ResetRequestEvent.<h3>");
    
    // 1. Get the User.
    $context->debug("Debug", "Getting the User...");
    $user = $context->getUser($event);
    
    // 2. Delete his fresh Photos.
    $context->debug("Debug", "Deleting fresh Photos of this User...");
    $context->local->deleteFreshPhotosOfUser($user);

    // 3. Delete his not confirmed Observations.
    $context->debug("Debug", "Deleting not confirmed Observations of this User...");
    $context->local->deleteNotConfirmedObservationsOfUser($user);

    // 4. Change his State back to INIT.
    $context->debug("Debug", "Changing User's State back to INIT...");
    $user->state = new StateInit();
    $context->local->saveUser($user);

    // 5. Send a ResetAck message.
    /*
    // Prepare a ResetAckMessage.
    $msg = new ResetAckMessage();
    $msg->to = $user->eMail;
    $msg->lang = $event->lang;
    
    // Send it by mail.
    $context->mailSender->consumeProtocolMessage($msg);
    */
  }

}

?>