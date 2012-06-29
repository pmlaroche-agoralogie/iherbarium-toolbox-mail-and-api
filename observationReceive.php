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
Debug::init("observationReceive");

function me() { return "observationReceive"; };

if ($_POST) {
  
  // Write all the data send by POST in a file (for debugging).
  $data = (var_export($_POST, true));
  file_put_contents(Config::get("lastPostRequestFile"), $data);

  // Fill the TypoherbariumObservation with the data transfered by POST:
  
  /* 1. Decode the data from the JSON form to the form
     of a generic object (i.e. an object of PHP class StdClass). */
  $obsObj = json_decode($_POST['observation']);
  
  /* 2. Convert our object to the form of TypoherbariumObservation. */
  $obs = TypoherbariumObservation::fromStdObj($obsObj);
  debug("Debug", me(), "Received TypoherbariumObservation", $obs);

  Logger::logObservationReceiverReceived($obs);  

  // Connect to database.
  debug("Debug", me(), "Connecting");
  $localTypoherbarium = LocalTypoherbariumDB::get();
  
  // Get User's uid.
  $uid = $localTypoherbarium->getUserUid($obs->user);
  if(! $uid) return NULL;

  // Save the received Observation:
  
  // 1. Prepare for saving.
  $obs->id = NULL; // It's a new Observation.

  // 2. Save Observation.
  debug("Begin", me(), "Saving TypoherbariumObservation...");
  $obs = $localTypoherbarium->saveObservation($obs, $uid);
  debug("Ok", me(), "TypoherbariumObservation saved.");

  // 3. Save Photos.
  foreach($obs->photos as $photo) {
    $localTypoherbarium->addPhotoToObservation($photo, $obs->id, $uid);
  }


} 
else {
  debug("Error", me(), "No POST data!");
}


?>