<?php
namespace iHerbarium;

require_once("myPhpLib.php");
require_once("dbConnection.php");

require_once("transferableUserInfo.php");

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

  public function saveObservation(Observation $obs) {
    /* with photos! */
    
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
    $url = Config::get("observationReceiverURL");
    $fields = array('observation' => $jsonObs);
    $response = http_post_fields($url, $fields);

    $this->debug("Ok", "Save Observation HTTP Response from $url", "<div style='border : 1px solid black;'>" . var_export($response, True) . "</div>");
    
    return;
  }
}

?>