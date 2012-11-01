<?php
namespace iHerbarium;
require_once("myPhpLib.php");
require_once("mailboxConnection.php");

require_once("mailbox.config.php");

class Mailbox {

  public function me() { 
    return("Mailbox[" . $this->mailboxName . "]"); 
  }

  public $mailboxName = NULL;

  public $connection = NULL;
  public $isConnected = false;
  
  private $params = NULL;

  private $mailConsumer = NULL;

  private function produceMail(ReceivedMail $mail) {
    if($this->mailConsumer)
      $this->mailConsumer->consumeMail($mail);
  }

  public function setMailConsumer(ReceivedMailConsumer $mailConsumer) {
    $this->mailConsumer =& $mailConsumer;
  }

  public static function get($mailboxName, ReceivedMailConsumer $mailConsumer) {
    $mailbox = new Mailbox($mailboxName);
    $mailbox->setMailConsumer($mailConsumer);
    return $mailbox;
  }
  
  private function connect() {
    if(! $this->isConnected) {
      if($this->connection->connect($this->params))
	$this->isConnected = true;
    }

    //$this->connection->overview();
  }

  private function disconnect() {
    if($this->isConnected) {
      $this->connection->disconnect();
      $this->isConnected = false;
    }
  }

  private function __construct($mailboxName) {
    $this->mailboxName = $mailboxName;
    $this->connection = new IMAPMailboxConnection();
    $this->params = MailboxConnectionParameters::get($mailboxName);    
  }

  function __destruct() {
    $this->disconnect();
  }

  public function getMail($uid) {
    $this->connect();
    if($this->isConnected) {
      $mail = $this->connection->getMail($uid);
    }
    $this->disconnect();

    $this->produceMail($mail);
  }

  public function getAllNewMail() {
    // Get a list of already fetched Mail.
    $consumedUids = $this->getAlreadyFetchedUids();

    $this->connect();
    if($this->isConnected) {
      // Fetch all new Mail.
      $allNewMail = $this->connection->getAllMail($consumedUids);
      $this->disconnect();
    
      // Mark all fetched Mail as already fetched and log them.
      foreach($allNewMail as $mail) {
	Logger::logMailboxReceivedMail($this, $mail);
	$this->rememberFetchedUid($mail->uid);
      }
      
      // Pass all fetched Mail to MailConsumer.
      foreach($allNewMail as $mail) {
	$this->produceMail($mail);
      }
    }
  }

  public function getAlreadyFetchedUids() {
    $db = DBConnection::get(Config::get("localStorageDatabase"));

    // Fetch the already fetch mail UIDs for this Mailbox.
    $results = 
      $db->query(
		 "SELECT Uid" . 
		 " FROM MailboxMail" .
		 " WHERE MailboxName = " . $db->quote($this->mailboxName) .
		 " AND Consumed IS NOT NULL");

    // Fill an array with already fetched mail UIDs.
    $fetchedUids = array();
    while( ($row = $results->fetchRow()) ) { 
      $fetchedUids[] = $row->uid;
    }

    return $fetchedUids;
  }
  
  public function rememberFetchedUid($uid) {
    $consumed = time();

    $db = DBConnection::get(Config::get("localStorageDatabase"));

    // Insert/Update the Mail
    $db->exec(
	      "INSERT INTO MailboxMail(Uid, MailboxName, Consumed)" .
	      " VALUES( " . $db->quote($uid) . 
	      " , " . $db->quote($this->mailboxName) .
	      " , " . $db->quote($consumed) .
	      " ) ON DUPLICATE KEY UPDATE Consumed = " . $db->quote($consumed)
	      );
  }

  public function rememberFetchedUids(array $uids) {
    array_iter(function($uid) { $this->rememberFetchedUid($uid); }, $uids);
  }

}

class MailboxConnectionParameters {
  
  private $mailboxName = NULL;

  // Mailbox connection parameters
  public $username  = NULL;
  public $password  = NULL;
  
  private $host     = NULL;
  private $port     = NULL;
  private $protocol = NULL;
  private $options  = NULL;

  public function connectionString() {
    $option_slash = create_function('$option', 'return "/$option";');

    // Result: "{host:port/protocol[option1/option2.../optionN}"
    return 
      "{" . $this->host .
      ":" . $this->port .
      "/" . $this->protocol .
      implode("", array_map($option_slash, $this->options ) ) .
      "}";
  }

  private function __construct() {
    // Some defaults for a POP3 server.
    $this->port        = "110";
    $this->protocol    = "pop3";
    $this->options     = array("novalidate-cert");
  }

  public static function get($mailboxName) {
  
    $mailboxConfig = getMailboxConfig();

    switch($mailboxName) {

      /* International : iherbarium.org */
    case "expert@iherbarium.org":
      $params = new static();
      $params->mailboxName = $mailboxName;
      $params->username    = "expert@iherbarium.org";
      $params->password    = $mailboxConfig["PWD_EXPERT_ORG"];
      $params->host        = "pop3.iherbarium.net";
      return $params;
      
    case "depot@iherbarium.org":
      $params = new static();
      $params->mailboxName = $mailboxName;
      $params->username    = "depot@iherbarium.org";
      $params->password    = $mailboxConfig["PWD_DEPOT_ORG"];
      $params->host        = "pop3.iherbarium.net";
      return $params;

      /* France : iherbarium.fr */
    case "expert@iherbarium.fr":
      $params = new static();
      $params->mailboxName = $mailboxName;
      $params->username    = "expert@iherbarium.fr";
      $params->password    = $mailboxConfig["PWD_EXPERT_FR"];
      $params->host        = "pop3.iherbarium.net";
      return $params;
      
    case "depot@iherbarium.fr":
      $params = new static();
      $params->mailboxName = $mailboxName;
      $params->username    = "depot@iherbarium.fr";
      $params->password    = $mailboxConfig["PWD_DEPOT_FR"];
      $params->host        = "pop3.iherbarium.net";
      return $params;
      
      /* Spain : iherbarium.es */
    case "expert@iherbarium.es":
      $params = new static();
      $params->mailboxName = $mailboxName;
      $params->username    = "expert@iherbarium.es";
      $params->password    = $mailboxConfig["PWD_EXPERT_ES"];
      $params->host        = "pop3.iherbarium.net";
      return $params;
      
    case "depot@iherbarium.es":
      $params = new static();
      $params->mailboxName = $mailboxName;
      $params->username    = "depot@iherbarium.es";
      $params->password    = $mailboxConfig["PWD_DEPOT_ES"];
      $params->host        = "pop3.iherbarium.net";
      return $params;

      /* Brasil : iherbarium.com.br */
    case "expert@iherbarium.com.br":
      $params = new static();
      $params->mailboxName = $mailboxName;
      $params->username    = "expert@iherbarium.com.br";
      $params->password    = $mailboxConfig["PWD_EXPERT_BR"];
      $params->host        = "pop3.iherbarium.net";
      return $params;
      
    case "depot@iherbarium.com.br":
      $params = new static();
      $params->mailboxName = $mailboxName;
      $params->username    = "depot@iherbarium.com.br";
      $params->password    = $mailboxConfig["PWD_DEPOT_BR"];
      $params->host        = "pop3.iherbarium.net";
      return $params;

      /* Germany : iherbarium.de */
    case "expert@iherbarium.de":
      $params = new static();
      $params->mailboxName = $mailboxName;
      $params->username    = "expert@iherbarium.de";
      $params->password    = $mailboxConfig["PWD_EXPERT_DE"];
      $params->host        = "pop3.iherbarium.net";
      return $params;
      
    case "depot@iherbarium.de":
      $params = new static();
      $params->mailboxName = $mailboxName;
      $params->username    = "depot@iherbarium.de";
      $params->password    = $mailboxConfig["PWD_DEPOT_DE"];
      $params->host        = "pop3.iherbarium.net";
      return $params;

    default :
      return NULL;
    }
    
  }

}

?>