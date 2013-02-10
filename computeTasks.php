<?php
namespace iHerbarium;

require_once("myPhpLib.php");
require_once("debug.php");
require_once("config.php");

require_once("determinationProtocol.php");

Debug::init("computeTasks.php", false);

// Database...
$local = LocalTypoherbariumDB::get();

// Action
$action = 'DoTasks';

if( isset($_GET['action']) )
  $action = $_GET['action'];

switch($action) {
  
case 'DoTasks' :
  
  // Number of Tasks to perform.
  if( isset($_GET['numberOfTasksToDo']) ) {
    $numberOfTasksToDo = $_GET['numberOfTasksToDo'];
  } else {
    echo "<p>You should specify the number of Tasks to do with a GET parameter 'numberOfTasksToDo'. Setting it as '-1' means 'do all the Tasks'.</p>";
    $numberOfTasksToDo = 0;
  }

  $numberOfTasksToDoDescr = ($numberOfTasksToDo < 0 ? "infinite" : $numberOfTasksToDo);
  echo "<p>Number of Tasks to do: $numberOfTasksToDoDescr</p>";  
  
  // Protocol.
  $protocol = DeterminationProtocol::getProtocol("Standard");
  
  // Initialize the Tasks counter.
  $tasksDone = 0;
  
  while($tasksDone < $numberOfTasksToDo || $numberOfTasksToDo === "-1") {
    
    // Load next computable Task.
    $task = $local->loadNextTask("Computable");
    
    if($task == NULL) {
      echo "<p>No more computable Tasks left!</p>";
      break;
    }

    // Get the Task's type.
    $taskType = $task->getType();
    echo "<p>Performing a $taskType Task...</p>";

    // Perform the Task.
    switch($taskType) {
      
    case "ComputeObservationSimilarities":
      $obs = $task->context;
      $protocol->noMoreQuestions($obs);
      $local->deleteTask($task);
      echo "<p>Computed Observation similarities for Observation $obs->id!</p>";
      break;

    case "ComparisonsFinished":
      $obs = $task->context;
      $protocol->noMoreComparisons($obs);
      $local->deleteTask($task);
      echo "<p>Finalized the determination for Observation $obs->id!</p>";
      break;

    case "AddObservationToDeterminationFlow":
      $obs = $task->context;
      $similaritySet = $local->loadSimilaritySet($obs->id);
      if(is_null($similaritySet)) {
        // If the similarity set doesn't exist it means that we have to do nothing and wait some more. 
        echo "<p>Similarity set for Observation $obs->id doesn't exist yet...</p>";
      } else {
        // If the similarity set exists, we can proceed.
        //echo "<h4>Similarity Set</h4><pre>" . var_export($similaritySet, True) . "</pre>";
        $protocol->addedObservation($obs);
        $local->deleteTask($task);
        echo "<p>Added Observation $obs->id to determination flow!</p>";
      }
      break;
      
    default:
      echo "<p>I don't know how to perform this type of Task!</p>";

    }

    // Increment the counter.
    $tasksDone = $tasksDone + 1;
    
  }

  echo "<p>Performed $tasksDone Task(s)!</p>";
  break;

default:
  echo "<p>Wrong action: $action !</p>";
  break;

}




?>