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

	protected function me() { return "testscript"; }

	private function debug($type, $string, $description = "") {
		debug($type, $this->me(), $string, $description);
	}

	public function saveObservation(Observation $obs) {    
		$this->debug("Begin", "Save Observation", $obs);

    // Prepare Observation.
		$transferableObs = Preparator::prepareForTransfer($obs);
		$this->debug("Debug", "Observation prepared for transfer", $transferableObs);

    // Convert it o a JSON in two phases:
    // (if we do it directly, the protected proprieties of
    //  ModelBaseClass objects don't get encoded properly.)

    // 1. Convert it to a tree of assiociative arrays.
		$tree = toArrayTree($transferableObs);
		$this->debug("Debug", "Observation as a tree", var_export($tree, true));

    // 2. Encode it as JSON.
		$jsonObs = json_encode($tree);
		$this->debug("Debug", "Observation JSON", $jsonObs);

    // HTTP Post request.
		//$url = Config::get("observationReceiverURL");
		$url = "http://wwwtest.iherbarium.net/boiteauxlettres/observationReceive.php";
		$fields = array('observation' => $jsonObs);
		$response = http_post_fields($url, $fields);

		$this->debug("Ok", "Save Observation HTTP Response from $url", "<div style='border : 1px solid black;'>" . var_export($response, True) . "</div>");

		return;
	}

	static function main($email) {

		$local = LocalDB::get();

		$user = $local->loadUser($email);

		$obs = $local->loadLastObservationOfUser($user);

		$remote = new static();

		$remote->saveObservation($obs);

	}
}

if(isset($_GET['email'])) {
  $email = $_GET['email'];
  echo("<h2>" . $email . "</h2>");
  TestObservationReceive::main($email);
}

?>