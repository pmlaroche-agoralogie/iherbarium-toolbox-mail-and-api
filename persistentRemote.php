<?php
namespace iHerbarium;

require_once("myPhpLib.php");
require_once("dbConnection.php");

require_once("transferableModel.php");
require_once("transferableUserInfo.php");

require_once("exif.php");

//
// The Preparator class goes here for now, 
// but it should be probably reassigned
// somewhere else.
//
class Preparator {

  public static function prepareForTransfer($protocolObs) {

    // Observation
    $obs = new \stdClass();
    $obs->id          = $protocolObs->id;
    $obs->user        = $protocolObs->user->eMail;
    $obs->uid         = NULL;
    $obs->timestamp   = NULL;
    $obs->geolocation = NULL;
    $obs->privacy     = "public";
    $obs->kind        = 1;
    $obs->plantSize   = "";
    $obs->commentary  = "";
    $obs->photos      = array();

    //echo("<pre>" . var_export($obs, True)   . "</pre>");
    
    // Photos
    foreach($protocolObs->photos as $protocolPhoto) {
      $localDir = Config::get("transferablePhotoLocalDir");
      
      // Prepare local name.
      $localFilename = "photo_" . time() . "_" . rand() . ".jpg";

      // Prepare local path.
      $saveToPath = $localDir . $localFilename;

      // Save photo.
      debug("Debug", "prepareForTransfer()", "Writing observation's photo to $saveToPath!");
      file_put_contents($saveToPath, $protocolPhoto->image);

      // Geoloc
      $geoloc = 
      array(
        "latitude"  => 0,
        "longitude" => 0
        );

      $exif = exif_read_data($saveToPath);
      
      if($exif != False)
        $geoloc = Exif::coordinatesFromExif($exif);

      // Photo
      $photo = new \stdClass();
      $photo->obsId            = $protocolObs->id;
      $photo->remoteDir        = Config::get("transferablePhotoRemoteDir");
      $photo->remoteFilename   = $localFilename;
      $photo->localDir         = NULL;
      $photo->localFilename    = NULL;
      $photo->depositTimestamp = $protocolPhoto->timestamp;
      $photo->userTimestamp    = NULL;
      $photo->exifTimestamp    = (array_key_exists('DateTimeOriginal', $exif) ? strtotime($exif['DateTimeOriginal']) : NULL);
      $photo->exifOrientation  = (array_key_exists('Orientation',      $exif) ? $exif['Orientation']                 : NULL);
      $photo->exifGeolocation  = $geoloc;
      $photo->rois             = array();
      
      //echo("<pre>" . var_export($photo, True) . "</pre>");

      // ROI
      if($protocolPhoto->tag) {
        
        // Rectangle
        $rect = new \stdClass();
        $rect->left    = 0.02;
        $rect->top     = 0.02;
        $rect->right   = 0.98;
        $rect->bottom  = 0.98;

        // ROI
        $roi = new \stdClass();
        $roi->rectangle = $rect;
        $roi->tag = $protocolPhoto->tag;

        //echo("<pre>" . var_export($roi, True) . "</pre>");

        array_push($photo->rois, $roi);
      }
      
      // Photo ready
      array_push($obs->photos, $photo);


      // Comments - add the Protocol Photo comments to Observation's comments.
      /*
      if($protocolPhoto->comments) {
        if($obs->commentary) $obs->commentary .= " ";
        $obs->commentary .= $protocolPhoto->comments;
      }
      */
    }

    // Get rough geolocation.
    $obs->geolocation = static::getRoughGeolocation($obs->photos);

    debug("Ok", "PrepareForTransfer()", "Prepared.", var_export($obs, True) );
    return $obs;
  }
  
  protected static function getRoughGeolocation(array $photos) {
    if(count($photos) > 0) {
      $anyPhoto = array_first($photos);
      return $anyPhoto->exifGeolocation;
    }
    else {
      return
      array(
        "latitude"  => 0,
        "longitude" => 0
        );

    }
  }

}


// Remote Storage

interface RemoteStorage {
  public function loadUserInfo(UserInfoRequest $userInfoRequest);
  public function saveObservation(Observation $obs);
}

class RemoteStorageHttp
extends Singleton
implements RemoteStorage {

  // Singleton implementation.
  protected static $instance = NULL;

  protected function me() { return get_called_class(); }

  private function debug($type, $string, $description = "") {
    debug($type, $this->me(), $string, $description);
  }

  // READ / WRITE FUNCTIONS

  public function loadUserInfo(UserInfoRequest $userInfoRequest) {
    $this->debug("Begin", "Load User", $userInfoRequest);

    // Prepare UserInfoRequest for transfer.
    $jsonRequest = json_encode($userInfoRequest);

    // HTTP Post request.
    $url = Config::get("getUserInfoURL");
    $fields = array('request' => $jsonRequest);
    $response = http_post_fields($url, $fields);
    $this->debug("Ok", "Get UserInfoAnswer HTTP Response from $url", "<div style='border : 1px solid black;'>" . var_export($response, True) . "</div>");

    // Parse HTTP Response.
    $parsedResponse = http_parse_message($response);
    //$this->debug("Ok", "Response = " . $response, "<pre>" . var_export($parsedResponse, True) . "</pre>");
    
    // Extract and convert data to UserInfoAnswer object.
    $userInfoObj = json_decode($parsedResponse->body);
    //debug("Debug", "Received Answer ", "<pre>" . var_export($userInfoObj, True) . "</pre>");
    $userInfo = UserInfoAnswer::fromStdObj($userInfoObj);
    
    $this->debug("Ok", "Received a UserInfo.", $userInfo);    
    return $userInfo;
  }

  public function saveObservation(Observation $obs, $url = "") {
    /* with photos! */

    $this->debug("Begin", "Save Observation", $obs);

    // Prepare Observation.
    $transferableObs = Preparator::prepareForTransfer($obs);
    $this->debug("Debug", "Observation prepared for transfer", var_export($transferableObs, True) );

    // Convert it o a JSON in two phases:
    // (if we do it directly, the protected proprieties of
    //  ModelBaseClass objects don't get encoded properly.)

    // 1. Convert it to a tree of assiociative arrays.
    $tree = toArrayTree($transferableObs);
    $this->debug("Debug", "Observation as a tree", "<pre>" . var_export($tree, true) . "</pre>");

    // 2. Encode it as JSON.
    $jsonObs = json_encode($tree);
    $this->debug("Debug", "Observation JSON", $jsonObs);
    
    // HTTP Post request.
    if($url == "") $url = Config::get("observationReceiverURL");
    $fields = array('observation' => $jsonObs);
    $response = http_post_fields($url, $fields);

    $this->debug("Ok", "Save Observation HTTP Response from $url", "<div style='border : 1px solid black;'>" . var_export($response, True) . "</div>");
    
    return;
  }
}

?>