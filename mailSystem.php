<?php
namespace iHerbarium;

require_once("myPhpLib.php");
require_once("debug.php");
require_once("config.php");

require_once("mailbox.php");
require_once("receivedMailConsumer.php");
require_once("protocolEventConsumer.php");

class IHerbariumMailSystem {

  private static $me = "MailSystem";
  
  public $mailboxes = NULL;
  
  public $mailHandlers = array();
  
  public $expertEventConsumer = NULL;
  public $depotEventConsumer = NULL;

  function __construct() {

    // EXPERT

    // == 1. Expert MailSender ==
    $expertMailSender = new ExpertMailSender();
    $expertMailSender->mailFormFactory = new MailFormFactory();
    $expertMailSender->templateFactory = new LocalDBContentTemplateFactory();

    // == 2. Expert EventConsumer ==
    $expertEventConsumer = new IHerbariumEventHandler();
    $expertEventConsumer->me = "Expert";

    // Storage
    $expertEventConsumer->local = LocalDB::get();
    $expertEventConsumer->remote = RemoteStorageHttp::get();

    // MailSender
    $expertEventConsumer->mailSender = &$expertMailSender;

    // Pieces
    $expertEventConsumer->pieceNewPhoto                    = new NewPhotoConsumingPiece();
    $expertEventConsumer->pieceSaveObservationRequest      = new SaveObservationRequestConsumingPieceWithoutOfConfirmation();
    $expertEventConsumer->pieceSaveObservationConfirmation = new IgnoreSaveObservationConfirmationConsumingPiece();
    $expertEventConsumer->pieceResetRequest                = new ResetRequestConsumingPiece();

    $this->expertEventConsumer =& $expertEventConsumer; 

    // == 3. Expert MailHandler ==
    $expertMailHandler = new ExpertReceivedMailHandler();
    $expertMailHandler->setEventConsumer($expertEventConsumer);


    // DEPOT

    // == 1. Depot MailSender ==
    $depotMailSender = new DepotMailSender();
    $depotMailSender->mailFormFactory = new MailFormFactory();
    $depotMailSender->templateFactory = new LocalDBContentTemplateFactory();

    // == 2. Depot EventConsumer ==
    $depotEventConsumer = new IHerbariumEventHandler();
    $depotEventConsumer->me = "Depot";

    // Storage
    $depotEventConsumer->local = LocalDB::get();
    $depotEventConsumer->remote = RemoteStorageHttp::get();

    // MailSender
    $depotEventConsumer->mailSender = &$depotMailSender;

    // Pieces
    $depotEventConsumer->pieceNewPhoto                    = new NewPhotoConsumingPiece();
    $depotEventConsumer->pieceSaveObservationRequest      = new SaveObservationRequestConsumingPieceWithoutOfConfirmation();
    $depotEventConsumer->pieceSaveObservationConfirmation = new IgnoreSaveObservationConfirmationConsumingPiece();
    $depotEventConsumer->pieceResetRequest                = new ResetRequestConsumingPiece();

    $this->depotEventConsumer =& $depotEventConsumer;     

    // == 3. Depot MailHandler ==
    $depotMailHandler = new ExpertReceivedMailHandler();
    $depotMailHandler->setEventConsumer($depotEventConsumer);


    // Prepare Mailboxes.
    $this->mailboxes = 
      array(
	    // Expert
	    "Expert Org" => Mailbox::get("expert@iherbarium.org",     $expertMailHandler),
	    "Expert Fr"  => Mailbox::get("expert@iherbarium.fr",      $expertMailHandler),
	    "Expert Es"  => Mailbox::get("expert@iherbarium.es",      $expertMailHandler),
	    "Expert Br"  => Mailbox::get("expert@iherbarium.com.br",  $expertMailHandler),
	    "Expert De"  => Mailbox::get("expert@iherbarium.de",      $expertMailHandler),

	    // Depot
	    "Depot Org"  => Mailbox::get("depot@iherbarium.org",      $depotMailHandler),
	    "Depot Fr"   => Mailbox::get("depot@iherbarium.fr",       $depotMailHandler),
	    "Depot Es"   => Mailbox::get("depot@iherbarium.es",       $depotMailHandler),
	    "Depot Br"   => Mailbox::get("depot@iherbarium.com.br",   $depotMailHandler),
	    "Depot De"   => Mailbox::get("depot@iherbarium.de",       $depotMailHandler)
	    );
    
  }

  public function fetchAndConsumeNewMail() {
    //debug("Begin", self::$me, "<h1>fetchAndConsumeNewMail()</h1>");
    foreach($this->mailboxes as $mailbox) {
      debug("Begin", self::$me, "<h3>Fetching mail from Mailbox $mailbox->mailboxName.</h3>");
      $mailbox->getAllNewMail();
    }
  }

  public function checkTimeouts() {
    // Local Storage
    $local = LocalDB::get();

    // Get list of Timed-Out Users with state COLLECT_PHOTOS.
    $state = PROTOCOL_STATE_COLLECT_PHOTOS;
    $hour = 1 * 60 * 60;
    $timeout = time() - (4 * $hour); 
    $users = $local->getTimedOutUsers($state, $timeout);
    
    // For each user generate a SaveObservationRequest.
    foreach($users as $user) {
      
      // Prepare a SaveObservationRequest.
      $event = new SaveObservationRequestEvent();
      $event->date = date(DATE_RFC822); // TODO: Change this format!
      $event->lang = $user->state->lang;
      $event->from = $user->eMail;

      switch($user->state->handler) {
      case "Expert" : $eventConsumer = $this->expertEventConsumer; break;
      case "Depot"  : $eventConsumer = $this->depotEventConsumer; break;
      }

      $eventConsumer->consumeProtocolEvent($event);

    }
  }

}

?>