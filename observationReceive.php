<?php
namespace iHerbarium;

require_once("myPhpLib.php");
require_once("debug.php");
require_once("config.php");
require_once("logger.php");

require_once("typoherbariumModel.php");
require_once("dbConnection.php");
require_once("persistentObject.php");

require_once("determinationProtocol.php");


// Setting up the Debug and Logger modules.
// (It's always necessary for scripts which are
// launched directly as the "main" PHP file.)
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




// Script

file_put_contents(Config::get("lastPostRequestFile")."__", serialize($_POST));
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

  // 4. Save Medias.
  if(isset($obsObj->medias))
  foreach($obsObj->medias as $mediaObj) {

    /*
    media:
    
      required fields:
      + remoteDir
      + remoteFilename

      optional fields:
      + depositTimestamp

    */

    // Check requirements.
    if( !isset($mediaObj->remoteDir ) ||
        !isset($mediaObj->remoteFilename ) ) 
      continue;

    // Create a corresponding TypoherbariumMedia object.
    $media = new TypoherbariumMedia();
    $media
      ->setDepositTimestamp(isset($mediaObj->depositTimestamp) ? $mediaObj->depositTimestamp : NULL)
      ->setInitialFilename($mediaObj->remoteFilename);    

    // Link the Media with the Observation.
    $media->obsId = $obs->id;

    // Copy the Media source file from remote address to local hard drive.
    $remotePath = $mediaObj->$remoteDir . $mediaObj->$remoteFilename;
    $media = $localTypoherbarium->copyMediaSourceFromRemotePath($media, $remotePath);

    // Finally: insert the Media into the database.
    $media = $localTypoherbarium->createMedia($media);

  }

  // Success!
  observationReceiveResult::success($obs->id);

  // Notify the Determination Protocol
  $p = DeterminationProtocol::getProtocol("Standard");
  $reObs = $localTypoherbarium->loadObservation($obs->id);
  $p->addedObservation($reObs);

} 
else {
  debug("Error", me(), "No POST data!");
}


?>
