<?php
namespace iHerbarium;

require_once("myPhpLib.php");
require_once("debug.php");
require_once("config.php");
require_once("logger.php");

require_once("mailbox.php");
require_once("persistentObject.php");
require_once("receivedMailConsumer.php");
require_once("protocolEventConsumer.php");



echo("<h1>test_observationReceive</h1>");

Logger::$logDirSetting = "logDirMailSystem";
Debug::init("test_observationReceive", true);

class TestObservationReceive {

	static function main($email) {

		$local = LocalDB::get();

		$user = $local->loadUser($email);

		$obs = $local->loadLastObservationOfUser($user);

		$remote = RemoteStorageHttp::get();

		$url = "http://wwwtest.iherbarium.net/boiteauxlettres/observationReceive.php";

		$remote->saveObservation($obs, $url);

	}
}

if(isset($_GET['email'])) {
  $email = $_GET['email'];
  echo("<h2>" . $email . "</h2>");
  TestObservationReceive::main($email);
}

?>