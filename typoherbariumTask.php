<?php
namespace iHerbarium;

require_once("myPhpLib.php");
require_once("taskStopCondition.php");

abstract class TypoherbariumTask {
  public $id         = NULL; public function setId($id) { $this->id = $id; return $this; }

  /*
   * Fields description:
   *
   * Parameters (defining the task):
   *
   * + Category     decides which kind of task is it (answerable / computable)
   *                and therefore who should perform the task (i.e. should it 
   *                be answered by an user or computed by a machine).
   *
   * + Type         describes exactly what task is it (i.e. what should be done).
   *
   * + Context      is the main paramater, it defines the object of
   *                the task (e.g. a ROI or an Observation). What is
   *                it exactly depends on the type of the task.
   *
   * + Parameters   contains all the precise details about how the task should
   *                be performed. As with context, what is it exactly depends
   *                on the type of the task (e.g. for the ROI comparison tasks
   *                context is a ROI and parameters are a list of ROIs).
   *                
   *                (Note: In fact the is no real difference in meaning between
   *                the context and other "parameter". Normally we would probably 
   *                have just one field: parameters. But context was added as
   *                a separate field in order to make it easier to treat in 
   *                SQL queries. As it is just a single object, it does not need
   *                to be serialized in any way and fits neatly in a single column,
   *                which is easily searchable.)
   *
   * Meta-parameters (information about how to handle the task):
   *
   * + Priority     indicates, how the task is important in order to
   *                let us select most important tasks.
   *                Because of my strange idea at the time, the smaller
   *                the priority, the more important the task.
   *
   * + Protocol     is a useless field, should be deleted...
   *
   */

  abstract public function getCategory();
  abstract public function getType();
  abstract public function getContextType();
  abstract public function getParametersType();

  public $context    = NULL; public function setContext($context) { $this->context = $context; return $this; }
  public $parameters = NULL; public function setParameters($parameters) { $this->parameters = $parameters; return $this; }

  public $priority   = 0;    public function setPriority($priority) { $this->priority = $priority; return $this; }
  public $protocol   = NULL; public function setProtocol($protocol) { $this->protocol = $protocol; return $this; }  

    
  // Debug printing
  protected function debugStringsArray() {
    $lines = array();
    $lines[] = "id: "             . ($this->id ? $this->id : "NULL");
    $lines[] = "category: "       . $this->getCategory();
    $lines[] = "type: "           . $this->getType();
    $lines[] = "contextType: "    . $this->getContextType();
    $lines[] = "parametersType: " . $this->getParametersType();
    $lines[] = "priority: "       . $this->priority;
    $lines[] = "protocol: "       . $this->protocol;
    return $lines;
  }

  final protected function debugString() {
    return 
      mkString(
	       $this->debugStringsArray(),
	       "<p>TypoherbariumTask:<ul><li>", "</li><li>", "</li></ul></p>"
	       );
  }

  function __toString() { return $this->debugString(); }


  static public function makeROIQuestionTask(TypoherbariumROI $roi, TypoherbariumROIQuestion $q) {

    $task = new TypoherbariumROIQuestionTask();

    $task
      ->setContext($roi)
      ->setParameters($q);
    
    return $task;
  }

  static public function makeROIComparisonTask(TypoherbariumROI $roi, $rois) {

    $task = new TypoherbariumROIComparisonTask();

    $task
      ->setContext($roi)
      ->setParameters($rois);
    
    return $task;
  }

  static public function makeComputeObservationSimilaritiesTask(TypoherbariumObservation $obs) {

    $task = new TypoherbariumComputeObservationSimilaritiesTask();

    $task
      ->setContext($obs)
      ->setParameters(NULL);
    
    return $task;
  }

  static public function makeComparisonsFinishedTask(TypoherbariumObservation $obs) {

    $task = new TypoherbariumComparisonsFinishedTask();

    $task
      ->setContext($obs)
      ->setParameters(NULL);
    
    return $task;
  }

  static public function makeAddObservationToDeterminationFlowTask(TypoherbariumObservation $obs) {

    $task = new TypoherbariumAddObservationToDeterminationFlowTask();

    $task
      ->setContext($obs)
      ->setParameters(NULL);
    
    return $task;
  }

}

/* Answerable tasks can be answered by users. */
abstract class TypoherbariumAnswerableTask
extends TypoherbariumTask {

  public function getCategory() { return "Answerable"; }

  public $stopCondition = NULL; public function setStopCondition($stopCondition) { $this->stopCondition = $stopCondition; return $this; }

  public $answers         = array();
  public $answersPatterns = array();

  public function isFinished() { 
    if($this->stopCondition)
      return $this->stopCondition->isFinished($this); 
    else
      return false;
  }

  // Debug printing
  protected function debugStringsArray() {
    $lines   = parent::debugStringsArray();
  
    $lines[] = "stopCondition is fulfilled? : " . 
      ($this->stopCondition ? 
       ($this->isFinished() ? "YES" : "NO") : 
       "No stop condition...");
    
    $lines[] = "stopCondition: " . ($this->stopCondition ? $this->stopCondition : "NULL");

    $lines[] = "answers: " .  
      mkString(
	       $this->answers,
	       "<ul><li>", "</li><li>", "</li></ul>"
	       );

    $lines[] = "answersPatterns: " .  
      mkString(
	       $this->answersPatterns,
	       "<ul><li>", "</li><li>", "</li></ul>"
	       );
    
    return $lines;
  }

}

class TypoherbariumROIQuestionTask
extends TypoherbariumAnswerableTask {

  public function getType()     { return "ROIQuestion"; }

  public function getContextType()    { return "ROI"; }
  public function getParametersType() { return "Question"; }

  public function getQuestionId() {
    return $this->parameters->id;
  }

  public function getRoiId() {
    return $this->context->id;
  }

  public function makeClosedChoiceQuestion(TypoherbariumROIQuestionSkinI $skin) {
    $ccq = new TypoherbariumClosedChoiceQuestion($this->parameters, $skin);
    return $ccq;
  }

  public function createAnswersPattern() {

    $ap = new TypoherbariumROIAnswersPattern();
    
    // Fill the basic fields.
    $ap
      ->setQuestionType ("ROIQuestion")
      ->setQuestionId   ($this->getQuestionId()) // Question Id
      ->setRoiId        ($this->getRoiId());     // ROI Id

    // Extract AnswerValues from Answers.
    $unsortedAnswers =
      array_map(function($answer) { return $answer->answerValue; }, $this->answers);
    // Count each AnswerValue.
    $answersScore    = array();
    $allAnswersCount =0;
    foreach($this->answers as $key => $value)
	{
	  // give more wieght to known people
	if(is_numeric($value->source)){$weight=10; } else $weight=1;
	if( isset($answersScore[$value->answerValue]) )
                   $answersScore[$value->answerValue] += $weight;
                 else
                   $answersScore[$value->answerValue] = $weight;
	$allAnswersCount += $weight;
	}
    // Reformat
    $formattedAnswers =
      array_mapi(
		 function($answerValue, $answerTimes) use ($allAnswersCount) {
			global $connecte;
		   $id = $answerValue;
		   $askedTimes = $allAnswersCount;
		   $chosenTimes = $answerTimes;

		   return
		   array(
			 "id"     => $answerValue,
			 "chosen" => $chosenTimes,
			 "asked"  => $askedTimes,
			 // no probability given if only to unknown persons have given answsers
			 "pr"     => ($askedTimes > 2) ? ($chosenTimes / $askedTimes) : 0 ,
			 "score"  => $chosenTimes - $askedTimes
			 );
		 },
		 $answersScore);
    
    // Sort
    $sortedAnswers = $formattedAnswers;
    usort($sortedAnswers, function($a1, $a2) { return cmp($a1["pr"], $a2["pr"]); });
    $sortedAnswers = array_reverse($sortedAnswers);

    $ap->answers = $sortedAnswers;

    return $ap;
  }

  // Debug printing
  protected function debugStringsArray() {
    $lines   = parent::debugStringsArray();
    $lines[] = "context: ROI " . $this->getRoiId();
    $lines[] = "parameters: TypoherbariumROIQuestion " . $this->getQuestionId();
    $lines[] = "createAnswersPattern(): " . $this->createAnswersPattern();
    
    return $lines;
  }

}


class TypoherbariumROIComparisonTask
extends TypoherbariumAnswerableTask {

  public function getType()     { return "ROIComparison"; }

  public function getContextType()    { return "ROI"; }
  public function getParametersType() { return "ROIs"; }

  public function getRoisIds() {
    return array_map(function($roi) { return $roi->id; }, $this->parameters);
  }

  public function getQuestionId() {
    $raw = $this->getRoisIds();
    sort($raw);
    return json_encode($raw);
  }

  public function getRoiId() {
    return $this->context->id;
  }

  
  public $choiceStrategy = NULL;

  function __construct() {
    //PLAROCHE 
    $this->choiceLimit = 6;
    //$this->choiceStrategy = new RandomChoice();
    $this->choiceStrategy = new MinAnswersChoice();
  }

  public function makeClosedChoiceQuestion(TypoherbariumComparisonSkinI $skin) {

    $chosenRois = $this->choiceStrategy->choose($this);
    
    $ccq = new ComparisonClosedChoiceQuestion($chosenRois, $skin);
    return $ccq;
  }

  // Debug printing
  protected function debugStringsArray() {
    $lines   = parent::debugStringsArray();
    $lines[] = "context: ROI " . $this->context->id;

    $lines[] = "parameters (ROIs to compare): " .
      mkString(
	       array_map(function($roi) { return $roi->id; }, $this->parameters),
	       "[", " , ", "]"
	       );
    $lines[] = "createAnswersPattern(): " . $this->createAnswersPattern();
    
    return $lines;
  }

  
  public function createAnswersPattern() {
    $ap = new TypoherbariumROIAnswersPattern();
    
    // Fill the basic fields.
    $ap
      ->setQuestionType ("ROIComparison")
      ->setQuestionId   ($this->getQuestionId()) // Question Id
      ->setRoiId        ($this->getRoiId());     // ROI Id

    // Count the statistics of AnswerValues.
    $task = $this;
    
    $possibleAnswers = array_unique( $this->getRoisIds() );
    
    $unsortedAnswers =
      array_map(
		function($answerValue) use ($task) {
		  $id = $answerValue;
		  
		  $chosenTimes =
		    count( array_filter(
					function($answer) use ($answerValue) {
					  return ($answer->answerValue == $answerValue);
					}, 
					$task->answers) );

		  $askedTimes =
		    count( array_filter(
					function($answer) use ($answerValue) {
					  return in_array($answerValue, json_decode($answer->questionId));
					}, 
					$task->answers) );
		  
		  return
		  array(
			"id"     => $id,
			"chosen" => $chosenTimes,
			"asked"  => $askedTimes,
			"pr"     => (($askedTimes > 0) ? ($chosenTimes / $askedTimes) : 0),
			"score"  => $chosenTimes - $askedTimes
			);
		  
		},
		$possibleAnswers);

    // Sort
    $sortedAnswers = $unsortedAnswers;
    usort($sortedAnswers, function($a1, $a2) { return cmp($a1["pr"], $a2["pr"]); });
    $sortedAnswers = array_reverse($sortedAnswers);

    $ap->answers = $sortedAnswers;

    return $ap;
  }

}


/* Comparison Choice Strategies (to choose a few ROIs from many) */

interface ChoiceStrategyI {
  public function choose(TypoherbariumROIComparisonTask $task);
}

class RandomChoice
implements ChoiceStrategyI {
  public function choose(TypoherbariumROIComparisonTask $task) {
    $limit = $task->choiceLimit;
    $rois = $task->parameters;
    
    // Shuffle ROIs array and choose ROIs from the beginning.
    shuffle($rois);
    $chosenRois = array_slice($rois, 0, $limit);
    
    return $chosenRois;
  }
}

class MinAnswersChoice
implements ChoiceStrategyI {
  public function choose(TypoherbariumROIComparisonTask $task) {
    $limit = $task->choiceLimit;
    $rois = $task->parameters;

    $ap = $task->createAnswersPattern();

    $roisToString = 
      function($roisArray) use ($ap) { 
      return mkString(
		      array_map(
				function($roi) use ($ap) { 
				  $asked = $ap->getAnswerParamForROIId($roi->id, "asked", 0);

				  return "(" . $roi->id . " : " . $asked . ")";
				}, 
				$roisArray), 

		      "[", " ", "]");
    };
    
    // echo "<p>" . $roisToString($rois) . "</p>";

    // Group ROIs by their Answers number.

    $roisByAnswersNumber = array();
    foreach($rois as $roi) {
      $asked = $ap->getAnswerParamForROIId($roi->id, "asked", 0);
      $roisByAnswersNumber[$asked][] = $roi;
    }

    // Order the groups in ascending order of number of answers.
    ksort($roisByAnswersNumber);
    
    // Shuffle ROIs in each group.
    foreach(array_keys($roisByAnswersNumber) as $answersNumber) {
      shuffle($roisByAnswersNumber[$answersNumber]);
    }

    // Flatten them back to a single array.
    $arrangedRois = array_flatten($roisByAnswersNumber);

    // echo "<p>" . $roisToString($arrangedRois) . "</p>";

    // Choose ROIs from the beginning.
    $chosenRois = array_slice($arrangedRois, 0, $limit);

    // echo "<p>" . $roisToString($chosenRois) . "</p>";
    
    return $chosenRois;
  }
}


abstract class TypoherbariumComputableTask
extends TypoherbariumTask {
  
  public function getCategory() { return "Computable"; }

}

class TypoherbariumComputeObservationSimilaritiesTask
extends TypoherbariumComputableTask {

  public function getType()           { return "ComputeObservationSimilarities"; }

  public function getContextType()    { return "Observation"; }
  public function getParametersType() { return "ComputationParameters"; }
  
}

class TypoherbariumComparisonsFinishedTask
extends TypoherbariumComputableTask {

  public function getType()           { return "ComparisonsFinished"; }

  public function getContextType()    { return "Observation"; }
  public function getParametersType() { return "ComputationParameters"; }
  
}

class TypoherbariumAddObservationToDeterminationFlowTask
extends TypoherbariumComputableTask {

  public function getType()           { return "AddObservationToDeterminationFlow"; }

  public function getContextType()    { return "Observation"; }
  public function getParametersType() { return "ComputationParameters"; }
  
}

?>
