<?php
namespace iHerbarium;

require_once("myPhpLib.php");

require_once("debug.php");
require_once("config.php");

require_once("typoherbariumModel.php");
require_once("persistentObject.php");


/* This interface should be rewritten. */
interface DeterminationProtocolI {
  public function addedROI(TypoherbariumROI $roi);
  public function modifiedROI(TypoherbariumROI $roi);

  public function addedObservation(TypoherbariumObservation $obs);
  public function modifiedObservation(TypoherbariumObservation $obs);

  public function answerReceived(TypoherbariumROIAnswer $answer);
}

/*
 * The DeterminationProtocol class is a mess. It is however the center piece of the determination
 * system: it coordinates all the important objects (observations, questions, tasks etc.),
 * effectively making the determination process happen.
 * 
 * It was supposed to be event-driven, so that each of its exposed methods reacts to some
 * external event happening (like "an observation was added", "a ROI was modified", "an answer
 * was received" etc.), implementing neatly the designed deterimination algorithm.
 *
 * However it got cluttered with many many similarily named methods (some of which are just
 * internal functions and some represent external exents), it contains a lot too much concrete
 * logic for handling some stuff (like the completely bloated noMoreQuestions method) and,
 * last but not least, it brings together many of the little shortcomings located in different parts
 * of the rest of the code (which is far from being perfect), so it needs to work around all of them
 * (look for example at what's going on in the addTasks method).
 * 
 * Thus, the DeterminationProtocol class is really messy and it would be great if one day somebody
 * cleaned it up at least a little bit :)
 */
class DeterminationProtocol 
implements DeterminationProtocolI {

  public $schema = NULL;

  // Task options
  public $taskPriority            = 0;
  public $questionStopCondition   = NULL;
  public $comparisonStopCondition = NULL;

  static public function getProtocol() {

    $local = LocalTypoherbariumDB::get();

    // Stop Conditions

    
    // Stop after at least 4 Answers if the best answer has at least 75% probability,
    // otherwise stop after 9 answers
    // or the first one is accepted if the user who made it is conencted (the only way to have probability of 1 (>thus > 0.99))
    //     if a user is not conencted, it is possible if the first three answers are the same
    $questionStopCondition =
      OrStopCondition::make()
      ->setStopConditions(
			  array(
				AndStopCondition::make()
				->setStopConditions(
						    array(
							  EnoughAnswersStopCondition::make()->setEnoughAnswers(4),
							  FirstAnswerVeryProbableStopCondition::make()->setAcceptableProbability(0.75)
							  )
						    ),
				EnoughAnswersStopCondition::make()->setEnoughAnswers(9),
				AndStopCondition::make()
                                ->setStopConditions(
                                                    array(
                                                          EnoughAnswersStopCondition::make()->setEnoughAnswers(1),
                                                          FirstAnswerVeryProbableStopCondition::make()->setAcceptableProbability(0.99)
                                                          )
                                                    )
				)
			  );
   
    
    // Stop after 1 Answer.
  /*  $questionStopCondition = 
      EnoughAnswersStopCondition::make()->setEnoughAnswers(1);
   */ 
    /*
    // Stop after 12 Answers.
    $comparisonStopCondition =
      EnoughAnswersStopCondition::make()->setEnoughAnswers(12);
    */

    // Stop after each choice has been asked at least 4 times.
    $comparisonStopCondition =
      AllChoicesAskedEnoughTimesStopCondition::make()->setEnoughTimes(2);

    // Schema
    $schema = $local->loadQuestionsSchema();
    //echo "<p>" . $schema . "</p>";
    
    // DeterminationProtocol
    $p = new DeterminationProtocol();
    $p->schema = $schema;
    $p->questionStopCondition   = $questionStopCondition;
    $p->comparisonStopCondition = $comparisonStopCondition;
    $p->taskPriority = 0;
    
    return $p;

  }

  private function questionTaskStopCondition() {
    return $this->questionStopCondition;
  }

  private function comparisonTaskStopCondition() {
    return $this->comparisonStopCondition;
  }

  public function generateQuestionTasksForROI(TypoherbariumROI $roi) {
    // Generate all questions available for this ROI in it's current state.

    // 1. Load the question schema.
    $schema = $this->schema;

    // 2. Apply our questions schema to the given ROI.
    $schemaInContext = $schema->inContext($roi);
    
    // 3. Filter and extract all askable questions.
    $questionsToAsk = $schemaInContext->getGoodQuestions();
    
    // 4. Build tasks of asking these questions in context of the given ROI.
    $stopCondition = $this->questionTaskStopCondition();
    $priority = $this->taskPriority;

    $tasks = array_map(
		       function($question) use ($roi, $stopCondition, $priority) {
			 return
			 TypoherbariumTask::makeROIQuestionTask($roi, $question)
			 ->setStopCondition($stopCondition)
			 ->setProtocol('Standard')
			 ->setPriority($priority);
		       }, 
		       $questionsToAsk);
    
    return $tasks;
  }

  public function generateQuestionTasksForObservation(TypoherbariumObservation $obs) {
    $tasks = array_flatten( array_map( array($this, "generateQuestionTasksForROI"), $obs->getROIs() ) );
    return $tasks;
  }

  public function addTasks($tasks) {

    $local = LocalTypoherbariumDB::get();

    foreach($tasks as $task) {
      // Check if the same Task is not already in the Task Pool.
      $previouslyExistingTask = $local->loadEqualTask($task);
      
      // If there is already another version of this Task in the Task Pool, 
      // we delete it (because it may have a different Stop Condition!).
      if(! is_null($previouslyExistingTask))
	$local->deleteTask($previouslyExistingTask);
  
      // We add the new Task to the Task Pool.
      $local->addTask($task);
    }

    foreach($tasks as $task) {

      // Now we have to reload each Task (to load all Answers connected with it)
      // and check if it's not already finished...
      $task = $local->loadEqualTask($task);

      // If it still exists and it's finished we should call taskCompleted($task)
      if( ! is_null($task) && 
	  $task->isFinished() ) {
	$this->taskCompleted($task);
      }
    }

  }

  public function addedROI(TypoherbariumROI $roi) {

    $local = LocalTypoherbariumDB::get();

    // 1. Generate all Question Tasks for this ROI.
    $tasks = $this->generateQuestionTasksForROI($roi);

    // 2. Add all these Tasks to the Task Pool.
    $this->addTasks($tasks);
    
  }

  public function modifiedROI(TypoherbariumROI $roi) {
    return $this->addedROI($roi);
  }

  protected function isObsFitForDetermination(TypoherbariumObservation $obs) {

    // Is this Observation good enough to be added to the 
    // flow of the Observations which will be identified?

    $local = LocalTypoherbariumDB::get();
    $results = $this->getSimilarObservations($obs);

    // Preliminary check : One or no results.
    echo "<p>Preliminary check of the similarity set: Are there only 0 or 1 results?</p>";
    
    $countResults = count($results);
    if($countResults <= 1) {

      echo "<p>Preliminary check: FAILED. Determination aborted with TooSmallReferenceGroup notification.</p>";
      $local->logDeterminationFinished($obs, false, "TooSmallReferenceGroup", "Reference group size = $countResults.");

      $parameters = array(
        "obsId"              => $obs->id,
        "owner"              => $obs->uid,
        "verdict"            => "TooSmallReferenceGroup",
        "referenceGroupSize" => $countResults
      );

      $local->createNotification("expert-system-say", json_encode($parameters));
      
      return false;
    }

    echo "<p>Preliminary check: OK.</p>";

    return true;

  }

  public function addedObservation(TypoherbariumObservation $obs) {

    // Is the newly added Observation good enough for determination?
    if( $this->isObsFitForDetermination($obs) ) {

      // The Observation is fit for determination!

      // If we an Observation was added, we should all it's ROIs.
      array_iter( array($this, "addedROI"), $obs->getROIs() );

    } else {

      // The Observation is not fit for determination!

    }

  }

  public function modifiedObservation(TypoherbariumObservation $obs) {
    return $this->addedObservation($obs);
  }

  public function answerReceived(TypoherbariumROIAnswer $answer) {
    
    $local = LocalTypoherbariumDB::get();

    // 1. Get the corresponding Task.
    $task = $local->loadTaskByParams($answer->questionType, 
				     $answer->roiId, 
				     $answer->questionId);
    
    if(is_null($task))
      return;

    // 2. Check if it's finished.
    if( $task->isFinished() ) {
      // If it's finished : 

      //echo "<p>" . "Task Finished!" . "</p>";
      
      // 3. Call taskCompleted($task).
      return $this->taskCompleted($task);

    } else {
      
      // 4. If it's not finished : do nothing.

    }
    
  }

  public function taskCompleted(TypoherbariumTask $task) {

    $local = LocalTypoherbariumDB::get();

    assert( $task->isFinished() );

    // 1. Delete the task from the Task Pool.
    $local->deleteTask($task);

    // 2. Create an AnswersPattern and put it where it belongs
    $ap = $task->createAnswersPattern();
    $local->saveAnswersPattern($ap);

    switch($task->getType()) {
    case "ROIQuestion":
      
      // 3. If this was a ROIQuestion Task...
      return $this->questionTaskCompleted($task);

    case "ROIComparison":
      
      // 3. If this was a ROIComparison Task...
      return $this->comparisonTaskCompleted($task);
    }
  }

  public function questionTaskCompleted(TypoherbariumROIQuestionTask $task) {

    $local = LocalTypoherbariumDB::get();

    // 1. Get the ROI.
    $roi = $local->loadROI($task->context->id);

    // 2. Get Questions for it (which are available and without a definitive answer yet).
    $questionTasks = $this->generateQuestionTasksForROI($roi);

    if( count($questionTasks) > 0 ) {

      //echo "<p>More Question Tasks for this ROI!</p>";

      // 3. If there are any - add them to the task pool.
      $this->addTasks($questionTasks);
      
    } else {
    
      // 4. If there are no more questions for whole Observation : call noMoreQuestions($obs)

      //echo "<p>No more Question Tasks for ROI!</p>";
      
      $obs = $local->loadObservation($roi->observationId);
      $allQuestionTasks = $this->generateQuestionTasksForObservation($obs);

      if( count($allQuestionTasks) == 0 ) {
	//echo "<p>No more Question Tasks for Observation!</p>";
	//$this->noMoreQuestions($obs);
	
	
	$noMoreQuestionsTask =
	  TypoherbariumTask::makeComputeObservationSimilaritiesTask($obs)
	  ->setProtocol('Standard');

	//echo "<p>$noMoreQuestionsTask<p>";

	$local->addTask($noMoreQuestionsTask);
	
      }

    }
  }
  
  private function printResults($results) {

    $obsLines = array_mapi(function($index, $result) {
      $obs   = $result['obs'];
      $obsId = $obs->id;
      $similarity = $result['result']['similarity'];
      $weight = $result['weight'];
      $weightedSimilarity = $result['weightedSimilarity'];
      return "( $index ) Observation $obsId: similarity = $similarity , weight = $weight , weightedSimilarity = $weightedSimilarity";
    }, $results);

    echo mkString($obsLines, "<p>Results:</p><ul><li>", "</li><li>", "</li></ul>");

  }

  public function noMoreQuestions(TypoherbariumObservation $obs) {
    
    $local = LocalTypoherbariumDB::get();

    // 1. Generate list of most similar observations using the AnswersPatternsModel.

    $results = $this->getSimilarObservations($obs);

    /* 
       $results is an array of Observations and detailed results of their comparison
       ordered by descending similarity.

       So:
       
       + $results[$i]['obs'] is the i-th best Observation.

       + $results[$i]['result'] is the detailed result of it's comparison with $obs,
       generated directly by APComparator.

       + $results[$i]['weightedSimilarity'] is the weighted similarity.
    */

    $countResultsToLog = 20;

    $resultsToLog = array_slice($results, 0, $countResultsToLog);
    //$obssToLog = array_map(function($result) { return $result['obs']; }, $resultsToLog);

    $local->logQuestionsFinished($obs, $resultsToLog);
    
    
    // 2. Use some smart algorithm to determine which of the Observations
    // (from the list of most similar Observations) should be compared.

    // Test if the Observation is in a group and abort if it is.
    // TODO: Erase this.
    
    $findItQuery =
      "SELECT COUNT(*) AS howmany" .
      " FROM iherba_group_observations" .
      " WHERE ObservationId = " . $local->quote($obs->id);

    $howMany =
      $local->singleResult($findItQuery,
			   function($row) { return $row->howmany; },
			   function()     { return 0; }
			   );

    if($howMany > 0) {
      echo "<p>In group: we are not generating comparisons.</p>";
      return;
    }
    echo "<p>Not in group: we generate comparisons.</p>";


    // Case 0 : One or no results.

    /* As this is already checked before (in the preliminary check)
     * we could probably delete the "Case 0" part here. But better safe than
     * sorry, hence we leave it here and just check this second time. */

    echo "<p>Case 0: Are there only 0 or 1 results ?</p>";
    
    $countResults = count($results);
    if($countResults <= 1) {
      echo "<p>Case 0: YES (this is very strange, as the preliminary check should have found it before!)</p>";
      $local->logDeterminationFinished($obs, false, "TooSmallReferenceGroup", "Reference group size = $countResults.");

      $parameters = array(
        "obsId"              => $obs->id,
        "owner"              => $obs->uid,
        "verdict"            => "TooSmallReferenceGroup",
        "referenceGroupSize" => $countResults
      );

      $local->createNotification("expert-system-say", json_encode($parameters));
      
      return;
    }

    echo "<p>Case 0: NO</p>";

    // Case A : Maybe the top result is super good ?

    $ratioTopSecondNeeded = 0.8;
    
    $topScore    = $results[0]['weightedSimilarity'];
    $secondScore = $results[1]['weightedSimilarity'];

    echo "<p>Case A: Maybe the top result is super good ?</p>";
    echo "<p>Case A: ($ratioTopSecondNeeded * $topScore) > $secondScore</p>";

    if( $secondScore < ($topScore * $ratioTopSecondNeeded) ) {
      echo "<p>Case A: YES</p>";

      $topObs = $results[0]['obs'];
      $topObsId = $topObs->id;

      $local->logDeterminationFinished($obs, true, "NoComparisonsNeeded", "The best observation = $topObsId. Top score = $topScore, second score = $secondScore.");

      $parameters = array(
        "obsId"       => $obs->id,
        "owner"       => $obs->uid,
        "verdict"     => "NoComparisonsNeeded",
        "topObsId"    => $topObsId,
        "topScore"    => $topScore,
        "secondScore" => $secondScore
      );

      $local->createNotification("expert-system-say", json_encode($parameters));
      
      return;
    }
    echo "<p>Case A: NO</p>";
    

    // Case B : Too many observations in top 5% ?

    $margin = 0.8;
    $marginInPercents = (1 - $margin) * 100;

    $minScore = $margin * $topScore;

    $topResults =
      array_filter(
		   function($result) use ($minScore) { 
		     return $result['weightedSimilarity'] > $minScore;
		   },
		   $results);

    $topObss = array_map(function($result) { return $result["obs"]; }, $topResults);

    $countObsToCompare = count($topObss);


    echo "<p>Test B: Too many observations in top " . $marginInPercents . "% ?</p>";
    echo "<p>Test B: countObsToCompare = $countObsToCompare</p>";

    if( $countObsToCompare > 10 ) {
      echo "<p>Test B: YES</p>";

      $local->logDeterminationFinished($obs, false, "TooMuchComparisonsNedded", "There is $countObsToCompare observations in the top " . $marginInPercents . "%");

      $parameters = array(
        "obsId"               => $obs->id,
        "owner"               => $obs->uid,
        "verdict"             => "TooMuchComparisonsNedded",
        "marginInPercents"    => $marginInPercents,
        "numberOfObsInMargin" => $countObsToCompare
      );

      $local->createNotification("expert-system-say", json_encode($parameters));

      return;
    }
    echo "<p>Test B: NO</p>";


    // Case C : Otherwise - lets prepare a nice list of observations to make comparisons.
    echo "<p>Case C: We generate some nice comparisons.</p>";

    $minCountObsToCompare = 3;
    $countObsToCompare = max($countObsToCompare, $minCountObsToCompare);

    $topResults = array_slice($results, 0, $countObsToCompare);
    $topObss = array_map(function($result) { return $result["obs"]; }, $topResults);
    
    
    // 3. Add adequate comparisons to the Task Pool

    $roisLists = 
      $this->generateROIsToCompare($obs, $topObss);
    
    $content =
      array_mapi(function($roiId, $roisList) {
	  $roi =  $roisList["roi"];
	  $rois = $roisList["rois"];
	  $roisIds = array_map(function(TypoherbariumROI $roi2) { return $roi2->id; }, $rois);
	
	  return 
	    mkString($roisIds, "<p>Roi $roiId : ", " , ", " </p>");
	
	}, $roisLists);
    
    echo mkString($content, "<ul><li>", "</li><li>", "</li></ul>");

    $filteredRoisLists =
      array_filter(function($roisList) {
	  return count( $roisList["rois"] ) >= 3;
	}, $roisLists);

    $stopCondition = $this->comparisonTaskStopCondition();
    
    $tasks = 
      array_map(
		function($roisList) use ($stopCondition) {
		  $roi =  $roisList["roi"];
		  $rois = $roisList["rois"];
		  
		  $task = 
		  TypoherbariumTask::makeROIComparisonTask($roi, $rois)
		  ->setStopCondition($stopCondition)
		  ->setProtocol("Standard");
		  
		  return $task;
		}, $filteredRoisLists);
    
    $this->addTasks($tasks);
    
  }

  public function comparisonTaskCompleted(TypoherbariumROIComparisonTask $task) {

    $local = LocalTypoherbariumDB::get();

    // 1. Get the ROI.
    $roi = $local->loadROI($task->context->id);
    $obs = $local->loadObservation($roi->observationId);
    
    // 2. Check if all comparisons from the Task Pool concerning this Observation have been answered
    $tasksLeft =    
      array_flatten(
		    array_map(
			      function(TypoherbariumROI $roi) use ($local) { return $local->loadTasksForROI($roi); }, $obs->getROIs()
			      )
		    );

    $countTasksLeft = count($tasksLeft);
    
    if($countTasksLeft == 0) {

        $comparisonsFinishedTask =
          TypoherbariumTask::makeComparisonsFinishedTask($obs)
          ->setProtocol('Standard');

        //echo "<p>$comparisonsFinishedTask<p>";

        $local->addTask($comparisonsFinishedTask);
    }
    
    // 1. Rebuild and recompare the models using new informations
    // 2. Decide if should compare more
    // 3. If yes - add new comparisons to the Task Pool
    // 4. If not - alarm the expert that you've reached some kind of conclusion
  }

  public function noMoreComparisons(TypoherbariumObservation $obs) {
    
    $local = LocalTypoherbariumDB::get();

    $maxResults = 20;

    $results = $this->getSimilarObservations($obs);
    $topResults = array_slice($results, 0, $maxResults);

    $resultsStrings =
  array_map(function($result) { return "(" . $result['obs']->id . " : " .  $result['weightedSimilarity'] . "),"; }, $topResults);

    $local->logDeterminationFinished($obs, true, "ComparisonsFinished", mkString($resultsStrings, "Results: ", " ", ""));

    $resultsArray =
      array_map(function($result) { return array(
        "obsId"      => $result['obs']->id,
        "similarity" => $result['weightedSimilarity']);
      }, $topResults);

    $parameters = array(
      "obsId"   => $obs->id,
      "owner"   => $obs->uid,
      "verdict" => "ComparisonsFinished",
      "results" => $resultsArray
    );

    $local->createNotification("expert-system-say", json_encode($parameters));

  }
  
  public function getComparator() {
    $local = LocalTypoherbariumDB::get();

    $questionsOptions = $local->loadQuestionsOptions();
    $palette          = $local->loadColorPalette("Basic");
    $tagsOptions      = $local->loadTagsOptions();

    //echo "<h4>QuestionsOptions</h4><pre>" . var_export($questionsOptions, True) . "</pre>";
    //echo "<h4>Palette</h4><pre>" . var_export($palette, True) . "</pre>";
    //echo "<h4>TagsOptions</h4><pre>" . var_export($tagsOptions, True) . "</pre>";

    $comparator = new MyComparator($questionsOptions, $palette, $tagsOptions);
    
    return $comparator;
  }

  protected function getReferenceObservations(TypoherbariumObservation $obs) {
    $local = LocalTypoherbariumDB::get();

    $similaritySet = $local->loadSimilaritySet($obs->id);

    return $similaritySet;
  }

  public function getSimilarObservations(TypoherbariumObservation $obs) {

    $local = LocalTypoherbariumDB::get();

    // Model
    $model = APModel::create($obs);
    //echo "<h4>Model</h4><pre>" . var_export($model, True) . "</pre>";
    
    $comparator = $this->getComparator();
    
    $similaritySet = $this->getReferenceObservations($obs);

    $observationIds     = array();
    $observationWeights = array();
    foreach($similaritySet as $index => $obsIdAndWeight) {
      $id     = $obsIdAndWeight->id;
      $weight = $obsIdAndWeight->weight;
      $observationIds[$id]     = $id;
      $observationWeights[$id] = $weight;
    }

    //echo "<h4>observationWeights</h4><pre>" . var_export($observationWeights, True) . "</pre>";
    //echo "<h4>observationIds</h4><pre>" . var_export($observationIds, True) . "</pre>";

    $observations =
      array_map(function($obsId) use ($local) { 
        return $local->loadObservation($obsId);
      }, $observationIds);

    // Models
    $models = array_map(function($obs) { return APModel::create($obs); }, $observations);
    //$echo "<h4>Models</h4><pre>" . var_export($models, True) . "</pre>";


    // Comparing results
    $cmpResults = 
      array_map(
		function($model2) use ($comparator, $model) {
		  $cmpModels = $comparator->compareModels($model, $model2);
		  return $cmpModels;
		}, $models);

    //echo "<h4>CmpModels</h4><pre>" . var_export($cmpResults, True) . "</pre>";

    $results = array();
    foreach($cmpResults as $obsId => $cmpResult) {

      $weight = $observationWeights[$obsId];
      $weightedSimilarity = $weight  * $cmpResult['similarity'];

      $results[] = array(
        "obs"                => $observations[$obsId], 
        "result"             => $cmpResult,
	"weight"             => $weight,
        "weightedSimilarity" => $weightedSimilarity
      );

    };

    echo "<h4>results</h4>"; //<pre>" . var_export($results, True) . "</pre>";
    $this->printResults($results);

    // Sort by weighted similarity.
    uasort($results, 
     function($r1, $r2) { 
       return - cmp(
        $r1['weightedSimilarity'],
        $r2['weightedSimilarity']
        ); 
     } );

    $orderedResults = array();
    foreach($results as $index => $result) {
      $orderedResults[] = $result;
    }
    

    echo "<h4>orderedResults</h4>"; //<pre>" . var_export($orderedResults, True) . "</pre>";
    $this->printResults($orderedResults);

    return $orderedResults;
  }

  public function generateComparisonsForObservation(TypoherbariumObservation $obs, $max = 5) {
//PL
$max =6;
    $results = 
      $this->getSimilarObservations($obs);

    $topResults = array_slice($results, 0, $max);
    $topObss = array_map(function($result) { return $result["obs"]; }, $topResults);

    $roisLists = 
      $this->generateROIsToCompare($obs, $topObss);
    
    $content =
      array_mapi(function($roiId, $roisList) {
	  $roi =  $roisList["roi"];
	  $rois = $roisList["rois"];
	  $roisIds = array_map(function(TypoherbariumROI $roi2) { return $roi2->id; }, $rois);
	
	  return 
	    mkString($roisIds, "<p>Roi $roiId : ", " , ", " </p>");
	
	}, $roisLists);
    
    echo mkString($content, "<ul><li>", "</li><li>", "</li></ul>");


    $stopCondition = $this->comparisonTaskStopCondition();
    
    $tasks = 
      array_map(
		function($roisList) use ($stopCondition) {
		  $roi =  $roisList["roi"];
		  $rois = $roisList["rois"];
		  
		  $task = 
		  TypoherbariumTask::makeROIComparisonTask($roi, $rois)
		  ->setStopCondition($stopCondition)
		  ->setProtocol("Standard");
		  
		  return $task;
		}, $roisLists);

    return $tasks;
  }


  public function generateROIsToCompareForTwoObservations(TypoherbariumObservation $obs1, TypoherbariumObservation $obs2) {
    
    $getRoisWithAtLeastOneCommonTag =
      function($roi1, $rois2) {
      return
      array_filter(
		   function($roi2) use ($roi1) {
		     return $roi2->hasAnyOfTagIds( $roi1->getComparaisonTagIds() );
		   },
		   $rois2
		   );
    };

    $comparator = $this->getComparator();

    $compareTwoRois =
      function($roi1, $roi2) use ($comparator) {
      $fi1 = APFeatureInstance::create($roi1);
      $fi2 = APFeatureInstance::create($roi2);
      
      return
      $comparator->featureInstanceComparator->compareFeatureInstances($fi1, $fi2);
    };
  
    $chooseMostSimilarRoi =
      function($roi1, $rois2) use ($compareTwoRois) {
      
      $cmpResults =
      array_map(function($roi2) use ($roi1, $compareTwoRois) { 
	  return array(
		       "roi"        => $roi2, 
		       "similarity" => $compareTwoRois($roi1, $roi2) 
		       ); 
	}, $rois2);
      
      // Get the highest similarity (of two best fitting FeatureInstances).
      usort($cmpResults, 
	    function($r1, $r2) { 
	      return -cmp($r1['similarity'], $r2['similarity']);
	    } 
	    );
      
      if(count($cmpResults) > 0) {
	$best = array_first($cmpResults);
	return $best["roi"];
      }
      else
	return NULL;
    };

    // Get all ROIs of Observation.
    $rois1 = $obs1->getROIs();

    // For each ROI of the first Observation choose the best ROI from the second Observation
    $roisPairs =
      array_map(
		function($roi1) use ($getRoisWithAtLeastOneCommonTag, $chooseMostSimilarRoi, $obs2) {
		  $rois2 = $getRoisWithAtLeastOneCommonTag($roi1, $obs2->getROIs());
		  $roi2 = $chooseMostSimilarRoi($roi1, $rois2);

		  return array("roi1" => $roi1,
			       "roi2" => $roi2);
		},
		$rois1);

    return $roisPairs;
  }


  public function generateROIsToCompare(TypoherbariumObservation $obs1, $obsToCompareWith) {

    $roisLists = array();

    foreach($obsToCompareWith as $obs2) {
      $roisPairs = $this->generateROIsToCompareForTwoObservations($obs1, $obs2);
      
      foreach($roisPairs as $roisPair) {
	$roi1 = $roisPair["roi1"];
	$roi2 = $roisPair["roi2"];

	if(! is_null($roi2)) {
	  
	  $roisLists[$roi1->id]["roi"] = $roi1;
	  $roisLists[$roi1->id]["rois"][] = $roi2;

	}
      }
    }

    return $roisLists;
  }

}


?>
