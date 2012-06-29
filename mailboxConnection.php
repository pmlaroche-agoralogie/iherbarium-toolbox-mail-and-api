<?php
namespace iHerbarium;
require_once("myPhpLib.php");
require_once("mimeContent.php");
require_once("mailbox.php");

abstract class MailboxConnection {
  abstract public function connect(MailboxConnectionParameters $connParams);
  abstract public function disconnect();
  abstract public function getMail($uid);
  abstract public function getAllMail();

  // Debug
  abstract public function overview();
}

class IMAPMailboxConnection
extends MailboxConnection {

  public function me() { return("IMAPMailboxConnection"); }
  
  // Connection
  private $imap_stream = NULL;

  private function decode($data, $encoding) {
    //debug("Debug", $this->me(), "Decoding data with encoding=$encoding...");
    switch($encoding) {
      // 7bit, 8bit, binary - no need to decode.
    case ENC7BIT            :
    case ENC8BIT            :
    case ENCBINARY          :
      return $data;

      // base64, quoted_printable - need to decode.
    case ENCBASE64          :
      return imap_base64($data);
    case ENCQUOTEDPRINTABLE :
      return imap_qprint($data);

      // other (unknown) encoding
    case ENCOTHER           :
      return $data;

      // encoding not specified
    default                 : 
      return $data;
    }
  }

  public function connect(MailboxConnectionParameters $connParams) {
    debug("Begin", $this->me(), "Connecting to the mail server...");

    // Connect.
    $this->imap_stream = 
      imap_open(
		$connParams->connectionString(), 
		$connParams->username, 
		$connParams->password
		);
    
    // Check if connected.
    if (! $this->imap_stream) {
      debug("Error", $this->me(),
	    "Connecting failed!<br/>" . $connParams->username . " / " . $connParams->password . " @ " . $connParams->connectionString(),
	    imap_last_error());
      return false;
    }
    else {
      debug("Ok", $this->me(), 
	    "Connected to mail server!<br/>" . $connParams->username . " / " . $connParams->password . " @ " . $connParams->connectionString()
	    );
      return true;
    }
  }

  public function disconnect() {
    debug("Begin", $this->me(), "Disconnecting from the mail server...");

    // Disconnect.
    if($this->imap_stream) { 
      imap_close($this->imap_stream);
      debug("Ok", $this->me(), "Connection to mail server closed!");
    }
    else
      debug("Error", $this->me(), "Trying to close an already closed connection with mail server!");
  }

  private function reconnectIfDisconnected() {
    //debug("Begin", $this->me(), "Verifying connection with the mail server...");

    // If disconnected try to reconnect until success.
    while (! imap_ping($this->imap_stream)) {
      debug("Error", $this->me(), "Connection lost! Reconnecting...");
      $this->connect();
    }
    
    //debug("Ok", $this->me(), "Connection OK!");    
  }

  public function check() {
    $this->reconnectIfDisconnected();

    debug("Begin", $this->me(), "Checking the mailbox...");
    $info = imap_check($this->imap_stream);

    debug("Ok", $this->me(), "Got check info.", "<pre>" . var_export($info, TRUE) . "</pre>");
    return($info);
  }

  public function overview() {
    $this->reconnectIfDisconnected();

    // Prepare command to fetch all msgs.
    $last_msg_no = $this->check()->Nmsgs;
    $fetch_all_msgs = "1:$last_msg_no";

    debug("Begin", $this->me(), "Fetching overview...");
    $overview = imap_fetch_overview($this->imap_stream, $fetch_all_msgs);
    
    if($overview) {
      debug("Ok", $this->me(), "Got overview.", "<pre>" . var_export($overview, TRUE) . "</pre>");
      return($overview);
    } else {
      debug("Error", $this->me(), "Cannot fetch overview!");
    }

    return $overview;
  }

  private function fetchMsgHeaders($uid) {
    $this->reconnectIfDisconnected();

    //debug("Begin", $this->me(), "Fetching headers of msg with uid=$uid ...");
    $headers = imap_rfc822_parse_headers(imap_fetchheader($this->imap_stream, $uid, FT_UID & FT_PREFETCHTEXT));

    if($headers) {
      //      debug("Ok", $this->me(), "Got headers", "<pre>" . var_export($headers, TRUE) . "</pre>");
      return($headers); 
    } else {
      debug("Error", $this->me(), "Cannot fetch headers!");
    }
  }

  private function fetchMsgStructure($uid) {
    $this->reconnectIfDisconnected();

    //debug("Begin", $this->me(), "Fetching structure of msg with uid=$uid ...");
    $structure = imap_fetchstructure($this->imap_stream, $uid, FT_UID);

    if ($structure) {
      //      debug("Ok", $this->me(), "Got structure.", "<pre>" . var_export($structure, TRUE) . "</pre>");
      return($structure);
    } else {
      debug("Error", $this->me(), "Cannot fetch structure!");
    } 
  }

  public function getMail($uid) {
    
    // Fetch requiored info about the mail
    $headers   = $this->fetchMsgHeaders($uid);
    $structure = $this->fetchMsgStructure($uid);

    // Prepare mail
    $mail = new ReceivedMail();
    $mail->uid = $uid;

    // Headers
    $mail->headers = $headers; 
    
    // Get mime contents from parts
    $mail->content = $this->extractPart($mail, $structure, 0);

    // Save attached (and inline) files
    $mail->saveFiles(Config::get("attachmentsDir"));
    
    debug("Ok", $this->me(), "Got mail!", $mail);
    
    return($mail);
  }


  // Extracting MIME content.

  private function fetchMsgPartBody($uid, $partNo) {
    if ($partNo > 0)
      return imap_fetchbody($this->imap_stream, $uid, $partNo, FT_UID);
    else 
      return imap_body($this->imap_stream, $uid, FT_UID);
  }

  private function getParameters($parameters) {
    $parameters_array = array();
    foreach ($parameters as $p) {
      $parameters_array[ strtolower( $p->attribute ) ] = $p->value;
    }
    return $parameters_array;
  }

  private function extractPart(ReceivedMail $mail, $partStructure, $partNo) {
    //debug("Begin", $this->me(), "Part $partNo");
    
    // Initialize content (USELESS).
    $content = NULL;

    // Part's content type, subtype and it's parameters.
    $type    = $partStructure->type;
    $subtype = strtolower( $partStructure->subtype );
    $type_parameters = $partStructure->ifparameters  ? $this->getParameters($partStructure->parameters ) : array();

    // Part's content disposition (if any) and it's parameters.
    $disposition = $partStructure->ifdisposition ? strtolower( $partStructure->disposition ) : "";
    $disposition_parameters  = $partStructure->ifdparameters ? $this->getParameters($partStructure->dparameters) : array();

    // Extract data in function of part's type.
    if($type == TYPEMULTIPART) {
      // Part is of Multipart type (so it's a node of mail-tree), we extract recursively it's sub-parts.
      assert(isset($partStructure->parts));
      $content = new MimeMultipartContent();

      foreach ($partStructure->parts as $subPartNo => $subPartStructure) {
        $content->parts[] = $this->extractPart($mail, $subPartStructure, ($partNo ? ($partNo . '.') : '') . ($subPartNo + 1));
      }
    } else {
      // Part is not of Multipart type (so it's a leaf), we extract it's data in an appropriate way.

      // Fetch it's data.
      $encoded_data = $this->fetchMsgPartBody($mail->uid, $partNo);
      $data = trim( $this->decode($encoded_data, $partStructure->encoding) );

      //
      switch($type) {
        // It's a text.
      case TYPETEXT :
	$content = new MimeTextContent();
	$content->data = $data;
	switch($subtype) {
	case "plain" : $mail->textPlain[] = $data; break;
	case "html"  : $mail->textHtml[]  = $data; break;
	};
	break;
	  
	// It's an Image.
      case TYPEIMAGE       :
	$filename = $type_parameters['name']; // filename!
	$content = new MimeImageContent();
	$content->data = $data;
	$content->filename = $filename;
	break;

	// It's a file.
      case TYPEMESSAGE     :
      case TYPEAPPLICATION :
      case TYPEAUDIO       :
      case TYPEVIDEO       :
      case TYPEOTHER       :
	$filename = $type_parameters['name']; // filename!
	$content = new MimeFileContent();
	$content->data = $data;
	$content->filename = $filename;
	break;
      }
    }

    // Fill the MimeContent.
    $content->type = $type;
    $content->subtype = $subtype;
    $content->type_parameters = $type_parameters;
    $content->disposition = $disposition;
    $content->disposition_parameters = $disposition_parameters;

    return $content;
  }
  
  public function getAllMail($dontGetTheseUids = array()) {
    $allUids = imap_sort($this->imap_stream, SORTARRIVAL, 0);
    debug("Debug", $this->me(), "getAllMail: allUids", mkString($allUids, "(", " ", ")"));

    $filteredUids = array_diff($allUids, $dontGetTheseUids);
    debug("Debug", $this->me(), "getAllMail: filteredUids", mkString($filteredUids, "(", " ", ")"));

    $allMail = array();
    foreach($filteredUids as $uid) {
      $allMail[$uid] = $this->getMail($uid);
    }
    
    return $allMail;
  }
}


/*
// ALTERNATIVE PIECE OF CODE FOR DECODING

for($i=0;$i<256;$i++)
{
  $c1=dechex($i);
  if(strlen($c1)==1){$c1="0".$c1;}
  $c1="=".$c1;
  $myqprinta[]=$c1;
  $myqprintb[]=chr($i);
}

function decode($data,$code)
{ 
  if(!$code)  {return imap_utf7_decode($data);}
  if($code==0){return imap_utf7_decode($data);}
  if($code==1){return imap_utf8($data);}
  if($code==2){return ($data);}
  if($code==3){return imap_base64($data);}
  if($code==4){return imap_qprint(str_replace($myqprinta,$myqprintb,($data)));}
  if($code==5){return ($data);}
}

*/

?>