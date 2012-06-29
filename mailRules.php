<?php
namespace iHerbarium;
require_once("myPhpLib.php");
require_once("mailConditions.php");
require_once("mailToEvent.php");

interface MailRuleset {
  public function mailToEvents($mail); // returns List of Events
}

class MailRule 
implements MailRuleset {

  private $defaultMatches = True;

  private $mailCondition = NULL;
  private $mailToEvents = array();

  private $nextIfTrue = NULL;
  private $nextIfFalse = NULL;

  function __construct() {
  }

  public function setCondition(ReceivedMailCondition $mailCondition) {
    $this->mailCondition =& $mailCondition;
  }

  public function addMailToEvent(ReceivedMailToEvent $mailToEvent) {
    $this->mailToEvents[] =& $mailToEvent;
  }

  public function setNextIfTrue(MailRuleset $mailRuleset) {
    $this->nextIfTrue =& $mailRuleset;
  }

  public function setNextIfFalse(MailRuleset $mailRuleset) {
    $this->nextIfFalse =& $mailRuleset;
  }

  public function setNextAlways(MailRuleset $mailRuleset) {
    $this->setNextIfTrue($mailRuleset);
    $this->setNextIfFalse($mailRuleset);
  }  

  public function mailToEvents($mail) {

    // Test the condition.
    $matches = self::$defaultMatches;

    if(is_null($this->mailCondition)) {
      $matches = $this->mailCondition->match($mail);
    }
    
    // If condition is satisfied - produce Events.
    $myEvents = array();
    if($matches) {
      foreach($this->mailToEvents as $mailToEvent) {
	$event = $mailToEvent->convertToEvent($mail);
	$myEvents[] = $event;
      }
    }

    // Call next Rule depending on the condition.
    $nextEvents = array();
    if($matches) {
      // If condition is satisfied - call the nextIfTrue Rule.
      if($this->nextIfTrue) {
	$nextEvents = $this->nextIfTrue->mailToEvents($mail);
      }
    } 
    else {
      // If condition is not satisfied - call the nextIfFalse Rule.
      if($this->nextIfFalse) {
	$nextEvents = $this->nextIfFalse->mailToEvents($mail);
      }
    }
  
    return array_merge($myEvents, $nextEvents);
  }
}

?>