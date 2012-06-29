<?php
namespace iHerbarium;
require_once("myPhpLib.php");
require_once("mimeContent.php");
require_once("receivedMail.php");
require_once("protocolEvent.php");
require_once("protocolEventConsumer.php");
require_once("mailRules.php");


interface ReceivedMailConsumer {
  public function me();
  public function consumeMail(ReceivedMail $mail);
}

class ExpertReceivedMailHandler 
implements ReceivedMailConsumer {

  public function me() { return "ExpertReceivedMailHandler"; }

  protected function debug($type, $string, $description = "") {
    debug($type, $this->me(), $string, $description);
  }

  // Producing

  private $eventConsumer = NULL;

  public function setEventConsumer(ProtocolEventConsumer $eventConsumer) {
    $this->eventConsumer =& $eventConsumer;
  }

  private function produceEvent(ProtocolEvent $event) {
    if(isset($this->eventConsumer)) {
      Logger::logReceivedMailConsumerProduced($this, $event);
      $this->eventConsumer->consumeProtocolEvent($event);
    }
    else
      $this->debug("Error", "eventConsumer is not set!");
  }

  // Consuming

  /* Save words:
   * + English   : save, end
   * + French    : sauv, sauve, sauver, fin
   * + Spanish   : ahorrar, ahorra, final
   * + Portugese : salva, salvar
   * + German    : spart, sparren, end
   */

  private $savePattern = '/(save|end|sauv|sauve|sauver|fin|ahorrar|ahorra|final|salva|salvar|spart|sparren)/i';
  private $confirmationCodePattern = '/\|\|(?P<confirmationCode>code_\d+_\d+)\|\|/';  

  private $keywords = 
    array(
	  // Save
	  "save", "end", 
	  "sauv", "sauve", "sauver", "fin",
	  "ahorrar", "ahorra", "final",
	  "salva", "salvar",
	  "spart", "sparren",

	  // Reset
	  "reset");
  
  public function consumeMail(ReceivedMail $mail) {
    Logger::logReceivedMailConsumerConsumed($this, $mail);
    
    // Mail Rules - implemented but not used for now.

    //$resetRule = new MailRule();
    $resetCondition = new SimplePatternCondition("reset", MatchMechanism::get("Equal"), MailFieldExtractor::get("Subject"));
    //$resetRule->setCondition($resetCondition);
    //$resetRule->addMailToEvent(new ReceivedMailToResetRequest());

    //$confirmationRule = new MailRule();
    $confirmationCondition = new SimplePatternCondition($this->confirmationCodePattern, MatchMechanism::get("Regex"), MailFieldExtractor::get("Subject"));
    //$confirmationRule->setCondition($confirmationCondition);
    //$confirmationRule->addMailToEvent(new ReceivedMailToSaveObservationConfirmation());   
    
    
    // Try to recognise what does this Mail is supposed to mean 
    // and produce an adequate Event(s).

    // Conditions
    $hasAnyPhotosCondition = new SimplePatternCondition(0, MatchMechanism::get("GreaterThan"), MailFieldExtractor::get("ImageCount"));
    $saveCondition = new SimplePatternCondition($this->savePattern, MatchMechanism::get("Regex"), MailFieldExtractor::get("Subject"));
    
    // Recognition.
    if($resetCondition->match($mail)) {
      // Mail is a Reset Request.
      $mailToEvent = new ReceivedMailToResetRequest();
      $event = $mailToEvent->convertToEvent($mail);
      
      // Produce the event.
      $this->produceEvent($event);
      return;
    }
    elseif( $confirmationCondition->match($mail) ) {
      // Mail is a Save Observation Request.
      $mailToEvent = new ReceivedMailToSaveObservationConfirmation();
      $event = $mailToEvent->convertToEvent($mail);

      // Produce the event.
      $this->produceEvent($event);
      return;
    } 

    if( $hasAnyPhotosCondition->match($mail) ) {
      // Mail has a New Photo.
      $mailToEvent = new ReceivedMailToNewPhotoNoKeywords($this->keywords);
      $event = $mailToEvent->convertToEvent($mail);

      // Produce the event.
      $this->produceEvent($event);
    }
    
    if( $saveCondition->match($mail) ) {
      // Mail is a Save Observation Request.
      $mailToEvent = new ReceivedMailToSaveObservationRequest();
      $event = $mailToEvent->convertToEvent($mail);
      
      // Produce the event.
      $this->produceEvent($event);
    }

  }
}

?>