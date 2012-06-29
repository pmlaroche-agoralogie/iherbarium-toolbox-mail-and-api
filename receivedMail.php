<?php
namespace iHerbarium;

require_once("mimeContent.php");

/*
TO DO: Address decoding!!!

class Address {
  static public function addressFromImap($imapAddress) {

  public $user; 
  public $host;
  public $personal;
  public $at_domain_source_route;
}
*/

class ReceivedMail {
  public $uid;

  // Raw mail headers
  public $headers;

  // Data extracted from headers

  // Date (obligatory in a mail)
  public function date() { 
    return $this->headers->date; 
  }

  // Subject (not obligatory in a mail!)
  const noSubject = "";
  public function subject() { // 
    if(isset($this->headers->subject)) {
      $decodedSubjectPieces = imap_mime_header_decode($this->headers->subject);
      $subject = $decodedSubjectPieces[0]->text;
      return $subject;
    }
    else
      return self::noSubject;
  }

  // To (not really obligatory in a mail)
  public function to() {
    if(isset($this->headers->to[0]))
      return $this->headers->to[0];
    else
      return NULL;
  }

  // From (obligatory in a mail, but there can be more than one!)
  public function from() {
    return $this->headers->from[0];
  }

  // Content - a MimeContent object.
  public $content = NULL;

  // Content browsing functions
  public function textPlain() { return ""; }
  public function textHtml() { return ""; }
  public function files() { return $this->content->getFiles(); }
  public function images() { return $this->content->getImages(); }
  
  public function saveFiles($dir) { $this->content->saveFiles($dir); }

  // Debug printing
  protected function debugStringsArray() {
    $lines = array();
    $lines[] = "UID: " . $this->uid;
    $lines[] = "DATE: " . $this->date();
    $lines[] = "SUBJECT: " . $this->subject();
    $lines[] = "TO: " . $this->to()->mailbox . "@" .$this->to()->host;
    $lines[] = "FROM: " . $this->from()->mailbox . "@" .$this->from()->host;
    $lines[] = "CONTENT: " . $this->content;
    //    $lines[] = "ALL HEADERS: " . "<pre>" . var_export($this->headers, True) . "</pre>";

    return $lines;
  }

  final protected function debugString() {
    return 
      mkString(
	       $this->debugStringsArray(),
	       "<p>RECEIVED MAIL<ul><li>", "</li><li>", "</li></ul></p>"
      );
  }
  
  function __toString() { return $this->debugString(); }

}

?>