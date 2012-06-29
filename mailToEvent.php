<?php
namespace iHerbarium;
require_once("myPhpLib.php");

interface EventFiller {
  public function fillEvent(ReceivedMail $mail, ProtocolEvent $event);
}

abstract class ChainableEventFiller
implements EventFiller {
  protected $next = NULL;

  public function __construct() {}

  abstract protected function myFillEvent(ReceivedMail $mail, ProtocolEvent $event);

  final public function chain(ChainableEventFiller $next) {
    // Check we already have something chained.
    if($this->next)
      // If we have - we pass the next EventFiller recursively down.
      $this->next->chain($next);
    else
      // If not - we chain the next EventFiller to us.
      $this->next =& $next;
    
    return $this;
  }

  final public function fillEvent(ReceivedMail $mail, ProtocolEvent $event) {
    // Fill the event myself.
    $filledEvent = $this->myFillEvent($mail, $event);
    
    // Pass the event to the chained EventFiller (if it's set).
    if(is_null($this->next))
      return $filledEvent;
    else
      return $this->next->fillEvent($mail, $filledEvent);
  }
}

class DateFiller
extends ChainableEventFiller {
  protected function myFillEvent(ReceivedMail $mail, ProtocolEvent $event) {    
    // Date
    $event->date = $mail->date();

    return $event;
  }
}

class FromFiller
extends ChainableEventFiller {
  public function myFillEvent(ReceivedMail $mail, ProtocolEvent $event) {    
    // From
    $from = $mail->from();
    $event->from = $from->mailbox . "@" . $from->host;

    return $event;
  }
}

class LangFiller
extends ChainableEventFiller {
  public function myFillEvent(ReceivedMail $mail, ProtocolEvent $event) {    
    // Lang
    $to = $mail->to();

    switch( $to->host ) {
    case 'iherbarium.fr'     : $event->lang = 'fr'; break;
    case 'iherbarium.es'     : $event->lang = 'es'; break;
    case 'iherbarium.com.br' : $event->lang = 'pt'; break;
    case 'iherbarium.de'     : $event->lang = 'de'; break;
    case 'iherbarium.org'    : 
    case 'iherbarium.net'    : 
    default                  : $event->lang = 'en'; break;
    }

    return $event;
  }
}

class DateFromLangFiller
extends ChainableEventFiller {
  public function myFillEvent(ReceivedMail $mail, ProtocolEvent $event) {    

    // Prepare 3 Fillers
    $dateFiller = new DateFiller();
    $fromFiller = new FromFiller();
    $langFiller = new LangFiller();

    // Chain them together.
    $fromFiller->chain($langFiller);
    $dateFiller->chain($fromFiller);
    
    // We have our private chained (Date -> From -> Lang) Filler.
    $dateFromLangFiller =& $dateFiller;
    
    // Fill.
    return $dateFromLangFiller->fillEvent($mail, $event);
  }
}

class ConfirmationCodeFiller
extends ChainableEventFiller {
  
  private $confirmationCodePattern = '/\|\|(?P<confirmationCode>code_\d+_\d+)\|\|/';  

  public function myFillEvent(ReceivedMail $mail, 
			      ProtocolEvent /*SaveObservationConfirmationEvent*/ $event) {
    // Confirmation Code
    preg_match($this->confirmationCodePattern, $mail->subject(), $confirmationCodeRegexMatches);
    $confirmationCode = $confirmationCodeRegexMatches['confirmationCode'];
    $event->confirmationCode = $confirmationCode;
    
    debug("Debug", "ConfirmationCodeFiller" , "ConfirmationCode extracted = " . $confirmationCode);

    return $event;
  }
}

class TagFiller
extends ChainableEventFiller {
  public function myFillEvent(ReceivedMail $mail, 
			      ProtocolEvent /*NewPhotoEvent*/ $event) {

    // Tag = first word of Subject
    $subjectWords = explode(" ", ltrim($mail->subject()));
    $tag = strtolower( $subjectWords[0] );
    $event->tag = $tag;

    return $event;
  }
}

class SmartTagFiller
extends ChainableEventFiller {
  public $splitPattern = NULL;

  public function __construct($splitPattern = "/ /") {
    parent::__construct();
    $this->splitPattern = $splitPattern;
  }

  public function myFillEvent(ReceivedMail $mail, 
			      ProtocolEvent /*NewPhotoEvent*/ $event) {

    // Tag = first word of Subject
    $subjectWords = preg_split($this->splitPattern, 
			       ltrim($mail->subject()),
			       NULL, 
			       PREG_SPLIT_NO_EMPTY);

    /*
    debug("Debug", "SmartTagFiller", 
	  "splitPattern: " . $this->splitPattern .
	  " subject: " . ltrim($mail->subject()),
	  "<pre>" . var_export($subjectWords, True) . "</pre>");
    */

    if(isset($subjectWords[0]))
      $tag = strtolower( $subjectWords[0] );
    else
      $tag = "";

    $event->tag = $tag;

    return $event;
  }
}

class CommentFiller
extends ChainableEventFiller {
  public $filterOutPatterns = NULL;

  public function __construct($filterOutPatterns = array()) {
    parent::__construct();
    $this->filterOutPatterns = $filterOutPatterns;
  }

  public function myFillEvent(ReceivedMail $mail, 
			      ProtocolEvent /*NewPhotoEvent*/ $event) {
    
    $comments = preg_replace($this->filterOutPatterns, "", $mail->subject());

    $event->comments = $comments;

    return $event;
  }
}

class ImageFiller
extends ChainableEventFiller {
  public function myFillEvent(ReceivedMail $mail, 
			      ProtocolEvent /*NewPhotoEvent*/ $event) {
    // Image = first attached file (inline or attachment)
    $images = $mail->images();
    $imageFile = $images[0];
    $event->image = $imageFile->data;
    $event->imageSubtype = $imageFile->subtype;

    return $event;
  }
}

interface ReceivedMailToEvent {
  public function convertToEvent(ReceivedMail $mail);
}

class ReceivedMailToResetRequest
implements ReceivedMailToEvent {
  public function convertToEvent(ReceivedMail $mail) {
    // Prepare chain of Fillers.
    $filler = new DateFromLangFiller();

    // Fill the Event.    
    $event = $filler->fillEvent($mail, new ResetRequestEvent());
    return $event;
  }
}

class ReceivedMailToSaveObservationConfirmation
implements ReceivedMailToEvent {
  public function convertToEvent(ReceivedMail $mail) {
    // Prepare chain of Fillers.
    $filler = new DateFromLangFiller();
    $filler->chain(new ConfirmationCodeFiller()); 
    
    // Fill the Event.
    $event = $filler->fillEvent($mail, new SaveObservationConfirmationEvent());
    return $event;
  }
}

class ReceivedMailToNewPhoto
implements ReceivedMailToEvent {
  public function convertToEvent(ReceivedMail $mail) {
    
    // Prepare chain of Fillers.
    $filler = new DateFromLangFiller();
    $filler->chain(new TagFiller());
    $filler->chain(new ImageFiller());
    
    // Fill the Event.
    $event = $filler->fillEvent($mail, new NewPhotoEvent());
    return $event;
  }
}

class ReceivedMailToNewPhotoNoKeywords
extends ReceivedMailToNewPhoto {
  public $keywordPatterns = NULL;
  public $splitPattern = NULL;

  public function __construct($keywords = array()) {
    //parent::__constructor();
    
    $this->keywordPatterns = 
      array_map(function($keyword) { return ("/" . $keyword . "/"); }, $keywords);

    // Add space to split words.
    $keywordsAndSpace = $keywords;
    $keywordsAndSpace[] = " ";

    // Create the split pattern.
    $keywordsPattern = implode("|", $keywordsAndSpace);
    $splitPattern = "/(" . $keywordsPattern . ")/i";
    debug("Debug", "ReceivedMailToNewPhotoNoKeywords", $splitPattern);

    $this->splitPattern = $splitPattern;
  }

  public function convertToEvent(ReceivedMail $mail) {
    
    // Prepare chain of Fillers.
    $filler = new DateFromLangFiller();
    $filler->chain(new SmartTagFiller($this->splitPattern));
    $filler->chain(new CommentFiller($this->keywordPatterns));
    $filler->chain(new ImageFiller());
    
    // Fill the Event.
    $event = $filler->fillEvent($mail, new NewPhotoEvent());
    return $event;
  }
}

class ReceivedMailToSaveObservationRequest
implements ReceivedMailToEvent {
  public function convertToEvent(ReceivedMail $mail) {
    // Prepare chain of Fillers.
    $filler = new DateFromLangFiller();
    
    // Fill the Event.
    $event = $filler->fillEvent($mail, new SaveObservationRequestEvent());
    return $event;
  }
}


?>