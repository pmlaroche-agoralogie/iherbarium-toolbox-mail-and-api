<?php
namespace iHerbarium;

require_once("myPhpLib.php");

class Logger {

  static public $logDirSetting = NULL;
  
  static protected function log($where, $what) {
    $time = date(\DateTime::ISO8601);

    $logDir =Config::get(self::$logDirSetting);
    $logPath = $logDir . $where;
    
    $contents = 
      "<h2>Time: " . $time . "</h2>" .
      "" . $what . "\n";
    
    file_put_contents($logPath, $contents, FILE_APPEND);
  }

  static public function logMailboxReceivedMail(Mailbox $mailbox, ReceivedMail $mail) {
    $what = $mailbox->me() . " fetched a mail. " . $mail->__toString();
    self::log("Mailbox.html", "$what");
  }

  static public function logReceivedMailConsumerConsumed(ReceivedMailConsumer $mailConsumer, ReceivedMail $mail) {
    $what = $mailConsumer->me() . " consumed a mail. " . $mail->__toString();
    self::log("ReceivedMailConsumer.html", "$what");
    
    $from = $mail->from();
    $username = $from->mailbox;
    self::log("ReceivedMailConsumer/" . $username . "_ReceivedMailConsumer.html", "$what");
  }

  static public function logReceivedMailConsumerProduced(ReceivedMailConsumer $mailConsumer, ProtocolEvent $event) {
    $what = $mailConsumer->me() . " produced an Event. " . $event->__toString();
    self::log("ReceivedMailConsumer.html", "$what");
  }

  static public function logProtocolEventConsumerConsumed(ProtocolEventHandler $eventConsumer, ProtocolEvent $event) {
    $what = $eventConsumer->me() . " consumed an event. " . $event->__toString();
    self::log("ProtocolEventConsumer.html", "$what");
  }

  static public function logObservationReceiverReceived(TransferableObservation $obs) {
    $what = "observationReceiver.php received a TransferableObservation. " . $obs->__toString();
    self::log("ObservationReceiver.html", "$what");
  }

  static public function logGetUserInfoReceived(UserInfoRequest $request) {
    $what = "getUserInfo.php received a UserInfoRequest. " . $request->__toString();
    self::log("GetUserInfo.html", "$what");
  }

  static public function logGetUserInfoAnswered(UserInfoAnswer $answer) {
    $what = "getUserInfo.php sent a UserInfoAnswer. " . $answer->__toString();
    self::log("GetUserInfo.html", "$what");
  }


}

?>