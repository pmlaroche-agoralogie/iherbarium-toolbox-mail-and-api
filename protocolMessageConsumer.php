<?php
namespace iHerbarium;

require_once("myPhpLib.php");

// ProtocolMessage Consumers

interface ProtocolMessageConsumer {
  public function consumeProtocolMessage(ProtocolMessage $msg);
}

// ProtocolMessage Senders

abstract class ProtocolMessageSender
implements ProtocolMessageConsumer {
  final public function consumeProtocolMessage(ProtocolMessage $msg) {
    //debug("Debug", "ProtocolMessageConsumer", "Consumes a message.", $msg);
    $msg->getConsumedBy($this);
  }

  abstract public function consumeYouAreNotRegisteredMessage(YouAreNotRegisteredMessage $msg);
  abstract public function consumeYouHaveBeenRegisteredMessage(YouHaveBeenRegisteredMessage $msg);
  abstract public function consumeFirstPhotoAckMessage(FirstPhotoAckMessage $msg);
  abstract public function consumeRequestConfirmationMessage(RequestConfirmationMessage $msg);
  abstract public function consumeConfirmationAckMessage(ConfirmationAckMessage $msg);
}

// iHerbariumMailSender  

abstract class IHerbariumMailSender
extends ProtocolMessageSender
implements ProtocolMessageConsumer {

  protected function me() { return "IHerbariumMailSender"; }

  protected function debug($type, $string, $description = "") {
    debug($type, $this->me(), $string, $description);
  }


  public $mailFormFactory = NULL;
  public $templateFactory = NULL;
  
  abstract protected function sender($msg);

  protected function simpleMail($msg, $subject, $plainText, $htmlText) {

    $from = $this->sender($msg);
    $to = $msg->to;

    // Prepare the Template.
    $t = $this->templateFactory->getTemplate($msg->type(), 'Mail', $msg->lang);

    // Create the Mail.
    $mail = $this->mailFormFactory->newMailForm();

    // Fill the Mail.
    $mail->setFrom($from);
    $mail->setTo($to);
    $mail->addToSubject($t->ask($subject));
    $mail->addPlainText($t->ask($plainText));
    $mail->addHtmlText($t->ask($htmlText));

    return($mail);
  }

  protected function send($mail) {
    $this->debug("Debug", "Sending mail.", "<pre>" . var_export($mail, TRUE) . "</pre>");
    $mail->send();
  }

}

class ExpertMailSender
extends IHerbariumMailSender {
  
  protected function me() { return "ExpertMailSender"; }

  protected function sender($msg) {
    switch($msg->lang) {
    case "fr" : return "expert@iherbarium.fr";
    case "es" : return "expert@iherbarium.es";
    case "pt" : return "expert@iherbarium.com.br";
    case "de" : return "expert@iherbarium.de";
    default   : return "expert@iherbarium.org";
    }
  }

  public function consumeYouAreNotRegisteredMessage(YouAreNotRegisteredMessage $msg) {
    $this->debug("Debug", "Consumes a YouAreNotRegisteredMessage.", $msg);
    $mail = $this->simpleMail($msg, "Subject", "PlainText", "HtmlText");

    $this->debug("Debug", "Sending mail.", "<pre>" . var_export($mail, TRUE) . "</pre>");
    $mail->send();
  }

  public function consumeYouHaveBeenRegisteredMessage(YouHaveBeenRegisteredMessage $msg) {
    $this->debug("Debug", "Consumes a YouHaveBeenRegisteredMessage.", $msg);
    $mail = $this->simpleMail($msg, "Subject", "PlainText", "HtmlText");


    // Prepare the Template.
    $t = $this->templateFactory->getTemplate($msg->type(), 'Mail', $msg->lang);

    $beforeUsername = $t->ask("BeforeUsername");
    $mail->addPlainText("\n" . $beforeUsername   . ": " . $msg->username);
    $mail->addHtmlText("<br/>" . $beforeUsername . ": " . $msg->username);

    $beforePassword = $t->ask("BeforePassword");
    $mail->addPlainText("\n"   . $beforePassword . ": " . $msg->password);
    $mail->addHtmlText("<br/>" . $beforePassword . ": " . $msg->password);

    $mail->addHtmlText("</body></html>");    


    $this->send($mail);
  }

  public function consumeFirstPhotoAckMessage(FirstPhotoAckMessage $msg) {
    $this->debug("Debug", "Consumes a FirstPhotoAckMessage.", $msg);
    $mail = $this->simpleMail($msg, "Subject", "PlainText", "Expert_HtmlText");
    
    $this->send($mail);
  }


  public function consumeRequestConfirmationMessage(RequestConfirmationMessage $msg) {
    $this->debug("Debug", "Consumes a RequestConfirmationMessage.", $msg);
    $mail = $this->simpleMail($msg, "Subject", "PlainText", "HtmlText");
    
    $this->send($mail);

  }

  public function consumeConfirmationAckMessage(ConfirmationAckMessage $msg) {
    $this->debug("Debug", "Consumes a ConfirmationAckMessage.", $msg);
    $mail = $this->simpleMail($msg, "Subject", "PlainText", "HtmlText");
    
    $this->send($mail);
  }

}


class DepotMailSender
extends IHerbariumMailSender {

  protected function me() { return "DepotMailSender"; }
  
  protected function sender($msg) {
    switch($msg->lang) {
    case "fr" : return "depot@iherbarium.fr";
    case "es" : return "depot@iherbarium.es";
    case "pt" : return "depot@iherbarium.com.br";
    case "de" : return "depot@iherbarium.de";
    default   : return "depot@iherbarium.org";
    }
  }

  public function consumeYouAreNotRegisteredMessage(YouAreNotRegisteredMessage $msg) {
    $this->debug("Debug", "Consumes a YouAreNotRegisteredMessage.", $msg);
    $mail = $this->simpleMail($msg, "Subject", "PlainText", "HtmlText");

    $this->send($mail);
  }

  public function consumeYouHaveBeenRegisteredMessage(YouHaveBeenRegisteredMessage $msg) {
    $this->debug("Debug", "Consumes a YouHaveBeenRegisteredMessage.", $msg);
    $mail = $this->simpleMail($msg, "Subject", "PlainText", "HtmlText");


    // Prepare the Template.
    $t = $this->templateFactory->getTemplate($msg->type(), 'Mail', $msg->lang);

    $beforeUsername = $t->ask("BeforeUsername");
    $mail->addPlainText("\n"     . $beforeUsername . ": " . $msg->username);
    $mail->addHtmlText("\n<br/>" . $beforeUsername . ": " . $msg->username);

    $beforePassword = $t->ask("BeforePassword");
    $mail->addPlainText("\n"     . $beforePassword . ": " . $msg->password);
    $mail->addHtmlText("\n<br/>" . $beforePassword . ": " . $msg->password);

    $mail->addHtmlText("\n</body></html>");    


    $this->send($mail);
  }

  public function consumeFirstPhotoAckMessage(FirstPhotoAckMessage $msg) {
    $this->debug("Debug", "Consumes a FirstPhotoAckMessage.", $msg);
    $mail = $this->simpleMail($msg, "Subject", "PlainText", "Depot_HtmlText");
    
    $this->send($mail);
  }

  public function consumeRequestConfirmationMessage(RequestConfirmationMessage $msg) {
    $this->debug("Debug", "Consumes a RequestConfirmationMessage.", $msg);
    $mail = $this->simpleMail($msg, "Subject", "PlainText", "HtmlText");
    
    $this->send($mail);

  }

  public function consumeConfirmationAckMessage(ConfirmationAckMessage $msg) {
    $this->debug("Debug", "Consumes a ConfirmationAckMessage.", $msg);
    $mail = $this->simpleMail($msg, "Subject", "PlainText", "HtmlText");
    
    $this->send($mail);
  }

}


?>