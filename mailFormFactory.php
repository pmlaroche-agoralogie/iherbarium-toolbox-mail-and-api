<?php
namespace iHerbarium;

require_once("myPhpLib.php");

include("Mail.php");
include("Mail/mime.php");

abstract class MailForm {
  /* Set meta-data */
  abstract public function addToSubject($string);
  abstract public function setFrom($from);
  abstract public function setTo($to);
  
  /* Set data */
  abstract public function addHtmlText($string);
  abstract public function addPlainText($string);

  /* Add files */
  abstract public function addInlineImage($image);
  abstract public function addAttachedImage($image);

  /* Compile and send */
  abstract public function send();
}

class NoAttachmentsMailForm
extends MailForm {

  private function me() { return "MailForm"; }

  private $subject = "";
  private $to = null;
  private $headers = array();

  private $plainText = "";
  private $htmlText = "";

  public function addToSubject($string) {
    $this->subject .= $string;
  }

  public function setFrom($from) {
    $this->headers['From'] = $from;
  }

  public function setTo($to) {
    $this->to = $to;
  }
  
  public function addHtmlText($string) {
    $this->htmlText .= $string;
  }

  public function addPlainText($string) {
    $this->plainText .= $string;
  }

  public function addInlineImage($image) {

  }
  
  public function addAttachedImage($image) {

  }
  
  public function send() {
    assert(isset($this->to));

    debug("Begin", $this->me(), "Sending a Mail...");    

    $crlf = "\n";

    $params = array();
    $params['eol'] = $crlf;
    $params['head_charset'] = "utf8"; // = "iso-8859-1";
    $params['text_charset'] = "utf8"; // = "iso-8859-1";
    $params['html_charset'] = "utf8"; // = "iso-8859-1".
    
    $mime = new \Mail_mime($params);

    $mime->setTXTBody($this->plainText);
    $mime->setHTMLBody($this->htmlText);

    $this->headers['Subject'] = $this->subject;
    $additionalHeaders = $this->headers;

    // Prepare 'To:'.
    $to = $this->to;

    // Prepare headers and content.
    // (Do not ever try to call these lines in reverse order!)
    $body = $mime->get();
    $hdrs = $mime->headers($additionalHeaders);

    // Send mail.
    $mail =& \Mail::factory('mail');
    $mailError = False;
 
    // SWITCHED ON
    $mailError = $mail->send($this->to, $hdrs, $body);

    if($mailError == True)
      debug("Ok", $this->me(), "Mail to [$to] successfuly sent!");
    else
      debug("Error", $this->me(), "Sending a mail to [$to] failed!");

    return $mailError;
  }
}

abstract class AbstractMailFormFactory {
  abstract public function newMailForm();
}

class MailFormFactory {
  public function newMailForm() {
    return new NoAttachmentsMailForm();
  }
}

/*
debug("a");
$text = 'Text version of email';
$html = '<html><body>HTML version of email</body></html>';
$file = '/home/expert1/htdocs/tmpuploads/photo.JPG';
$crlf = "\n";
$hdrs = array(
              'From'    => 'expert@iherbarium.fr',
              'Subject' => 'Test mime message'
              );

$mime = new \Mail_mime($crlf);

$mime->setTXTBody($text);
$mime->setHTMLBody($html);
$mime->addAttachment($file, 'image/jpg');

debug("b");
//do not ever try to call these lines in reverse order
$body = $mime->get();
$hdrs = $mime->headers($hdrs);

debug("c");
$mail =& \Mail::factory('mail');
$mailError = $mail->send('expert@iherbarium.fr', $hdrs, $body);
*/
