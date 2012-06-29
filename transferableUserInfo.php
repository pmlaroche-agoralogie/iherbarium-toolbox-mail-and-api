<?php
namespace iHerbarium;

require_once("myPhpLib.php");
require_once("debug.php");
require_once("config.php");

class UserInfoRequest {
  public $eMail;
  public $lang;

  // Debug printing
  protected function debugStringsArray() {
    $lines   = array();
    $lines[] = "eMail: " . $this->eMail;
    $lines[] = "lang: " . $this->lang;
    return $lines;
  }

  final protected function debugString() {
    return 
      mkString(
	       $this->debugStringsArray(),
	       "<p>UserInfoRequest:<ul><li>", "</li><li>", "</li></ul></p>"
	       );
  }

  function __toString() { return $this->debugString(); }

  static public function fromStdObj($obj) {
    $request = new self();
    $request->eMail = $obj->eMail;
    $request->lang  = $obj->lang;
    return $request;
  }

}

abstract class UserInfoAnswer {
  public $status;

  abstract protected function type();  

  // Debug printing
  protected function debugStringsArray() {
    $lines   = array();
    $lines[] = "type: " . $this->type();
    $lines[] = "status: " . $this->status;
    return $lines;
  }

  final protected function debugString() {
    return 
      mkString(
	       $this->debugStringsArray(),
	       "<p>UserInfoAnswer:<ul><li>", "</li><li>", "</li></ul></p>"
	       );
  }

  function __toString() { return $this->debugString(); }
  

  static public function fromStdObj($obj) {
    assert(isset($obj->status));
    assert($obj->status == "UserExists" || 
	   $obj->status == "UserJustCreated" || 
	   $obj->status == "UserDoesntExist");
    
    $userInfo = NULL;
    switch($obj->status) {
    case "UserExists" :      
    case "UserJustCreated" : $userInfo = UserExists::fromStdObj($obj); break;
    case "UserDoesntExist" : $userInfo = UserDoesntExist::fromStdObj($obj); break;
    default : assert(False); return NULL;
    }

    $userInfo->status = $obj->status;
    return $userInfo;
  }

}

class UserExists
extends UserInfoAnswer {
  public $username;
  public $uid;

  protected function type() { return __CLASS__; }

  // Debug printing
  protected function debugStringsArray() {
    $lines   = parent::debugStringsArray();
    $lines[] = "username: " . $this->username;
    $lines[] = "uid: " . $this->uid;
    return $lines;
  }

  static public function fromStdObj($obj) {
    assert(isset($obj->status));
    assert($obj->status == "UserExists" || $obj->status == "UserJustCreated");

    $userInfo = NULL;
    switch($obj->status) {
    case "UserExists" :      $userInfo = new self(); break;
    case "UserJustCreated" : $userInfo = UserJustCreated::fromStdObj($obj); break;
    default : assert(False); return NULL;
    }

    $userInfo->username = $obj->username;
    $userInfo->uid      = $obj->uid;
    return $userInfo;
  }

}

class UserJustCreated
extends UserExists {
  public $password;

  protected function type() { return __CLASS__; }

  // Debug printing
  protected function debugStringsArray() {
    $lines   = parent::debugStringsArray();
    $lines[] = "password: " . $this->password;
    return $lines;
  }

  static public function fromStdObj($obj) {
    assert(isset($obj->status));
    assert($obj->status == "UserJustCreated");

    $userInfo = new self();
    $userInfo->password = $obj->password;
    return $userInfo;
  }

}

class UserDoesntExist 
extends UserInfoAnswer {

  protected function type() { return __CLASS__; }

  static public function fromStdObj($obj) {
    assert(isset($obj->status));
    assert($obj->status == "UserDoesntExist");

    $userInfo = new self();
    return $userInfo;
  }

}


?>