<?php
namespace iHerbarium;

require_once("myPhpLib.php");

/* Task Stop Conditions (to describe and check when the Task is finished) */

abstract class TaskStopCondition {
  abstract public function isFinished($task);
  abstract public function type();

  // Debug printing
  protected function debugStringsArray() {
    $lines = array();
    $lines[] = "type: " . $this->type(); 
    return $lines;
  }

  final protected function debugString() {
    return 
      mkString(
	       $this->debugStringsArray(),
	       "<p>StopCondition:<ul><li>", "</li><li>", "</li></ul></p>"
	       );
  }

  function __toString() { return $this->debugString(); }

  static public function make() { return new static(); }
}

/* Stop the Task after enough Answers have been received. */
class EnoughAnswersStopCondition
extends TaskStopCondition {
  public function type() { return "EnoughAnswers"; }

  public $enoughAnswers = 0; public function setEnoughAnswers($enoughAnswers) { $this->enoughAnswers = $enoughAnswers; return $this; }

  public function isFinished($task) {
    if($this->enoughAnswers <= 0) return false;
      
    $answersCount = count($task->answers);
    
    return ($answersCount >= $this->enoughAnswers);
  }

  // Debug printing
  protected function debugStringsArray() {
    $lines   = parent::debugStringsArray();
    $lines[] = "enoughAnwers: " . $this->enoughAnswers; 
    return $lines;
  }

}

/* Stop the Task if the probability of the most probable Answer is big enough. */
class FirstAnswerVeryProbableStopCondition
extends TaskStopCondition {
  public function type() { return "FirstAnswerVeryProbable"; }
  
  public $acceptableProbability = 0; public function setAcceptableProbability($acceptableProbability) { $this->acceptableProbability = $acceptableProbability; return $this; }

  public function isFinished($task) {
    if($this->acceptableProbability <= 0) return false;
    
    $firstAnswer = $task->createAnswersPattern()->getFirstAnswer();
    if( is_null($firstAnswer) ) return false;

    $firstAnswerProbability = $firstAnswer["pr"];
    return ($firstAnswerProbability >= $this->acceptableProbability);
  }

  // Debug printing
  protected function debugStringsArray() {
    $lines   = parent::debugStringsArray();
    $lines[] = "acceptableProbability: " . $this->acceptableProbability; 
    return $lines;
  }
  
}

/* Stop the Task if every choice has been asked enough times. */
class AllChoicesAskedEnoughTimesStopCondition
extends TaskStopCondition {
  public function type() { return "AllChoicesAskedEnoughTimes"; }
  
  public $enoughTimes = 0; public function setEnoughTimes($enoughTimes) { $this->enoughTimes = $enoughTimes; return $this; }

  public function isFinished($task) {
    $enoughTimes = $this->enoughTimes;
    if($enoughTimes <= 0) return false;
  
    $rois = $task->parameters;
    $ap = $task->createAnswersPattern();
    
    return
      array_all(
		function(TypoherbariumROI $roi) use ($enoughTimes, $ap) { 
		  return ($ap->getAnswerParamForROIId($roi->id, "asked", 0) >= $enoughTimes); 
		}, 
		$rois 		
		);
  }

  // Debug printing
  protected function debugStringsArray() {
    $lines   = parent::debugStringsArray();
    $lines[] = "enoughTimes: " . $this->enoughTimes; 
    return $lines;
  }
  
}


/* StopCondition dependent on a list of sub-conditions. */
abstract class AggregateStopCondition
extends TaskStopCondition {
  public $stopConditions = array(); public function setStopConditions($stopConditions) { $this->stopConditions = $stopConditions; return $this; }
  
  abstract protected function aggregateFunction($callback, $theArray);
  
  public function isFinished($task) {
    return $this->aggregateFunction(
				    function($stopCondition) use ($task) { 
				      return $stopCondition->isFinished($task); 
				    }, 
				    $this->stopConditions
				    );
  }
  
  // Debug printing
  protected function debugStringsArray() {
    $lines   = parent::debugStringsArray();

    $lines[] = "sub Stop Conditions: " .
      mkString(
	       $this->stopConditions,
	       "<ul><li>", "</li><li>", "</li></ul>"
	       );

    return $lines;
  }

}

/* Stop the Task if all sub-conditions have been satisfied. */
class AndStopCondition
extends AggregateStopCondition {
  public function type() { return "And"; }

  protected function aggregateFunction($callback, $theArray) {
    return array_all($callback, $theArray);
  }

}

/* Stop the Task if any sub-condition has been satisfied. */
class OrStopCondition
extends AggregateStopCondition {
  public function type() { return "Or"; }

  protected function aggregateFunction($callback, $theArray) {
    return array_any($callback, $theArray);
  }
  
}

?>
