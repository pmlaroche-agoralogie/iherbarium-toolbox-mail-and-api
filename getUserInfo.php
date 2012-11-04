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

Logger::$logDirSetting = "logDirGetUserInfo";
Debug::init("getUid", False);
//Config::init("Development");
//Config::init("Production");

//$createUserIfDoesntExist = False;
$createUserIfDoesntExist = True;

function me() { return "getUid"; };

if ($_POST) {
  
  // Remember
  $data = (var_export($_POST, true));
  file_put_contents(Config::get("lastPostRequestFile"), $data);
  
  // Get the UserInfoRequest received by POST.
  $requestObj = json_decode($_POST['request']);
  $userInfoRequest = UserInfoRequest::fromStdObj($requestObj);
  Logger::logGetUserInfoReceived($userInfoRequest);

  // Extract informations from UserInfoRequest.
  $username = $userInfoRequest->eMail;
  $lang = $userInfoRequest->lang;
  debug("Debug", me(), "Received UserInfoRequest.", $userInfoRequest);
  
  // Connect to database.
  debug("Debug", me(), "Connecting");
  $localTypoherbarium = LocalTypoherbariumDB::get();
  
  // Getting uid...
  debug("Begin", me(), "Getting uid...");
  $uid = $localTypoherbarium->getUserUid($username);
  
  if($uid) {
    debug("Ok", me(), "Got uid!");

    $userInfo = new UserExists();
    $userInfo->status = "UserExists";
    $userInfo->username = $username;
    $userInfo->uid = $uid;

    debug("Debug", me(), "Prepared UserInfo:", $userInfo);
    
    echo (json_encode($userInfo));
    Logger::logGetUserInfoAnswered($userInfo);

  } 
  else {
    debug("Error", me(), "User with this username doesn't exist!");

    if($createUserIfDoesntExist) {
      // Create a new User.
      
      // Generate a cool password.
      $password = "oompaloompas";
      $password = substr(md5($username), 0, 6);

      // Create the user.
      $localTypoherbarium->createUser($username, $password, $lang);

      // Respond - user just created.
      $uid = $localTypoherbarium->getUserUid($username);
      assert($uid);
      //$uid = 999; // As we know that the above will be NULL.

      $userInfo = new UserJustCreated();
      $userInfo->status = "UserJustCreated";
      $userInfo->uid = $uid;
      $userInfo->username = $username;
      $userInfo->password = $password;

      debug("Debug", me(), "Prepared UserInfo:", $userInfo);
    
      echo (json_encode($userInfo));
      Logger::logGetUserInfoAnswered($userInfo);

    } else {
      // Respond - user doesn't exist.

      $userInfo = new UserDoesntExist();
      $userInfo->status = "UserDoesntExist";

      debug("Debug", me(), "Prepared UserInfo:", $userInfo);
    
      echo (json_encode($userInfo));
      Logger::logGetUserInfoAnswered($userInfo);

    }

  }

} 
else {
  debug("Error", me(), "No POST data!");
}


?>