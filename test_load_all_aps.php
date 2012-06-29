<?php
namespace iHerbarium;

require_once("myPhpLib.php");

require_once("debug.php");
require_once("config.php");
require_once("logger.php");

Debug::init("iHerbariumAddObservationPlugin", False);

require_once("transferableModel.php");
require_once("typoherbariumModel.php");
require_once("dbConnection.php");

require_once("persistentObject.php");

$local = LocalTypoherbariumDB::get();


$query =
  "SELECT id FROM iherba_roi_answers_pattern";

$local->iterResults($query,

		    function($row) use ($local) {
		      $ap = $local->loadAnswersPattern($row->id);

		      echo "<p>$ap</p>";
		    });

?>