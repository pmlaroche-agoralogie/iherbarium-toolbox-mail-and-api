<?php
namespace iHerbarium;

require_once("myPhpLib.php");
require_once("debug.php");
require_once("config.php");
require_once("logger.php");

require_once("typoherbariumModel.php");
require_once("dbConnection.php");

require_once("persistentObject.php");
require_once("transferableUserInfo.php");

/*
  
  This script: 

  1. Receives a UserInfoRequest
  (which is sent as JSON through a POST request)

  2. Tries to find a user with a given username in the database.
  Depending on its configuration an the user existence the script
  sends back one of three answers (optionally creating a new user
  on the fly):
  
    - UserExists (with: username and uid)

    - UserJustCreated (with: username, uid and password)
    
    - UserDoesntExist

*/


// Setting up the Debug and Logger modules.
// (It's always necessary for scripts which are
// launched directly as the "main" PHP file.)
Logger::$logDirSetting = "logDirGetUserInfo";
Debug::init("getUid", False); // Attention! If this is set to True, the script will not print the answer correctly!
function me() { return "getUid"; };


// Parameters
//$createUserIfDoesntExist = False;
$createUserIfDoesntExist = True;


// Script

if ($_POST) {
  
  // Remember the POST request.
  $data = (var_export($_POST, true));
  file_put_contents(Config::get("lastPostRequestFile"), $data);
  
  // Get the UserInfoRequest received by POST.
  $requestObj = json_decode($_POST['request']);
  $userInfoRequest = UserInfoRequest::fromStdObj($requestObj);
  Logger::logGetUserInfoReceived($userInfoRequest);

  // Extract informations from UserInfoRequest.
  $username = $userInfoRequest->eMail; // email is used as the username!
  $lang = $userInfoRequest->lang;
  debug("Debug", me(), "Received UserInfoRequest.", $userInfoRequest);
  
  // Connect to database.
  debug("Debug", me(), "Connecting");
  $localTypoherbarium = LocalTypoherbariumDB::get();
  
  // Get the uid.
  debug("Begin", me(), "Getting uid...");
  $uid = $localTypoherbarium->getUserUid($username);
  
  if($uid) {
    // If a user with this username exists...
    debug("Ok", me(), "Got uid!");

    // Prepare the answer...
    $userInfo = new UserExists();
    $userInfo->status   = "UserExists";
    $userInfo->username = $username;
    $userInfo->uid      = $uid;

    debug("Debug", me(), "Prepared UserInfo:", $userInfo);
    
    // Print the answer.
    echo (json_encode($userInfo));
    Logger::logGetUserInfoAnswered($userInfo);

  } 
  else {
    // If a user with this username does not exist.
    debug("Error", me(), "User with this username doesn't exist!");

    if($createUserIfDoesntExist) {
      // Create a new User.
      
      // Generate a cool password for the new user.
      $password = substr(md5($username), 0, 6); // 6 more or less random characters.

      // Create the user.
      $localTypoherbarium->createUser($username, $password, $lang);

      // Respond, that the user was just created.

      // Get the created user's uid.
      $uid = $localTypoherbarium->getUserUid($username);
      assert($uid);

      // Prepare the answer...
      $userInfo = new UserJustCreated();
      $userInfo->status   = "UserJustCreated";
      $userInfo->uid      = $uid;
      $userInfo->username = $username;
      $userInfo->password = $password;

      debug("Debug", me(), "Prepared UserInfo:", $userInfo);
    
      // Print the answer.
      echo (json_encode($userInfo));
      Logger::logGetUserInfoAnswered($userInfo);

    } else {
      // Respond, that the user does not exist.

      // Prepare the answer...
      $userInfo = new UserDoesntExist();
      $userInfo->status = "UserDoesntExist";

      debug("Debug", me(), "Prepared UserInfo:", $userInfo);
    
      // Print the answer.
      echo (json_encode($userInfo));
      Logger::logGetUserInfoAnswered($userInfo);

    }

  }

} 
else {
  debug("Error", me(), "No POST data!");
}


?>