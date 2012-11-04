<?php
namespace iHerbarium;

require_once("myPhpLib.php");

require_once("debug.php");
require_once("config.php");
require_once("logger.php");

require_once("typoherbariumModel.php");
require_once("dbConnection.php");

require_once("persistentObject.php");

Debug::init("myTest", false);

$local = LocalTypoherbariumDB::get();

$groups = 
  array_map(
	    function($nr) {
	      
	      $group = new TypoherbariumGroup();
	      $group->setName("Test$nr");
	      echo "<h1>New Group $nr</h1><p>" . $group . "</p>";

	      return $group;
	    },
	    array("A" => "A", "B" => "B", "C" => "C"));

// Create
foreach($groups as $group) {
  $local->createGroup($group);
  echo "<h1>Created Group</h1><p>" . $group . "</p>";
}

// Add
$local->addObservationToGroup($local->loadObservation(700), $groups["A"]);
$local->addObservationToGroup($local->loadObservation(701), $groups["A"]);

$local->addObservationToGroup($local->loadObservation(700), $groups["B"]);
$local->addObservationToGroup($local->loadObservation(702), $groups["B"]);
$local->addObservationToGroup($local->loadObservation(704), $groups["B"]);

$local->addObservationToGroup($local->loadObservation(700), $groups["C"]);
$local->addObservationToGroup($local->loadObservation(703), $groups["C"]);
$local->addObservationToGroup($local->loadObservation(704), $groups["C"]);


// Include
$local->includeGroupInGroup($groups["B"], $groups["A"]);
$local->includeGroupInGroup($groups["C"], $groups["A"]);
$local->includeGroupInGroup($groups["C"], $groups["B"]);

// Load
foreach($groups as $group) {
  $group = $local->loadGroup($group->id);
  echo "<h1>Loaded Group</h1><p>" . $group . "</p>";
}

// Delete
foreach($groups as $group) {
  $local->deleteGroup($group);
  $group = $local->loadGroup($group->id);
  echo "<h1>Deleted Group?</h1><p>" . ( is_null($group) ? "OK" : "Error!") . "</p>";
}

// Group Tranlations
$t = $local->loadGroupTranslations();
echo "<h1>Translations: </h1>" . "<pre>" . var_export($t, True) . "</pre>";


die();

$s = new TypoherbariumSkin('en');
$groupAlex = $local->loadGroup(1);
echo "<h1>Loaded Alex's Group</h1><p>" . $groupAlex . "</p>";
echo "<h1>Skin Translations: </h1>" . "<pre>" . var_export($s->group($groupAlex), True) . "</pre>";

// Adding Alex's observations to Alex's group.
if(false) {
  $obsIds = $local->getAllObsIdsForUID(26);
  $obss = array_map(array($local, "loadObservation"), $obsIds);
  
  foreach($obss as $obs) {
    echo "<p>here!</p>";
    $local->addObservationToGroup($obs, $groupAlex);
  }

  echo "<h1>Alex's Group</h1><p>" . $groupAlex . "</p>";
  $groupAlex = $local->loadGroup(1);
  echo "<h1>Loaded Alex's Group</h1><p>" . $groupAlex . "</p>";
}


?>