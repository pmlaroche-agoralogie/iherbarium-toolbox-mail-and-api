<?php
namespace iHerbarium;

require_once("myPhpLib.php");

require_once("debug.php");
require_once("config.php");

require_once("typoherbariumModel.php");
require_once("dbConnection.php");

require_once("persistentObject.php");

Debug::init("myTest", true);

$local = LocalTypoherbariumDB::get();

if(isset($_GET['obsId'])) {
  $obsId = $_GET['obsId'];  
  $obs = $local->loadObservation($obsId);
  
  echo "<p>" . $obs . "</p>";
}




?>