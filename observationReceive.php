<?php
namespace iHerbarium;

require_once("myPhpLib.php");
require_once("debug.php");
require_once("config.php");
require_once("logger.php");

require_once("transferableModel.php");
require_once("typoherbariumModel.php");
require_once("dbConnection.php");

require_once("persistentObject.php");

Logger::$logDirSetting = "logDirObservationReceiver";
Debug::init("observationReceive", false);

function me() { return "observationReceive"; };

abstract class ObservationReceiveResult {
  public $result;

  public function printMe() {
    $json = json_encode($this);
    file_put_contents(Config::get("lastObservationReceiveResultFile"), $json);
    echo $json;
  }

  static public function success($obsId) {
    $result = new ObservationReceiveSuccess();
    $result->result = "success";
    $result->obsId  = $obsId;
    $result->printMe();
  }

  static public function error($error) {
    $result = new ObservationReceiveError();
    $result->result = "error";
    $result->error  = $error;
    $result->printMe();
  }
}

class ObservationReceiveSuccess 
extends ObservationReceiveResult {
  public $obsId;
}

class ObservationReceiveError
extends ObservationReceiveResult {
  public $error;
}

if ($_POST) {
  
  // Write all the data send by POST in a file (for debugging).
  $data = (var_export($_POST, true));
  file_put_contents(Config::get("lastPostRequestFile"), $data);

  // Fill the TypoherbariumObservation with the data transfered by POST:
  
  /* 1. Decode the data from the JSON form to the form
     of a generic object (i.e. an object of PHP class StdClass). */
  $obsObj = json_decode($_POST['observation']);
  if( is_null($obsObj) ) {
    // json_decode function error.
    observationReceiveResult::error("json_decode");
    return NULL;
  }
  
  /* 2. Convert our object to the form of TypoherbariumObservation. */
  $obs = TypoherbariumObservation::fromStdObj($obsObj);
  if(! $obs) {
    // Problems in fromStdObj.
    observationReceiveResult::error("fromStdObj");
    return NULL;
  }

  debug("Debug", me(), "Received TypoherbariumObservation", $obs);

  Logger::logObservationReceiverReceived($obs);  

  // Connect to database.
  debug("Debug", me(), "Connecting");
  $localTypoherbarium = LocalTypoherbariumDB::get();
  
  // Get User's uid.
  $uid = $localTypoherbarium->getUserUid($obs->user);
  if(! $uid) {
    // User does not exist.
    observationReceiveResult::error("uid");
    return NULL;
  }

  // Save the received Observation:
  
  // 1. Prepare for saving.
  $obs->id = NULL; // It's a new Observation.

  // 2. Save Observation.
  debug("Begin", me(), "Saving TypoherbariumObservation...");
  $obs = $localTypoherbarium->saveObservation($obs, $uid);
  if( (! $obs) || (! $obs->id) ) {
    // Error during inserting the Observation into the database.
    observationReceiveResult::error("saveObservation");
    return NULL;
  }
  debug("Ok", me(), "TypoherbariumObservation saved.");

  // 3. Save Photos.
  foreach($obs->photos as $photo) {
    $localTypoherbarium->addPhotoToObservation($photo, $obs->id, $uid);
  }

  // Success!
  observationReceiveResult::success($obs->id);
} 
else {
  debug("Error", me(), "No POST data!");
}


?>