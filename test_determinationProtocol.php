<?php
namespace iHerbarium;

if (isset($_COOKIE['idcollaboratif'])) {
  $gateau = $_COOKIE['idcollaboratif'];
} else { 
  $gateau = time()."-".rand(1000,9900);
  setcookie('idcollaboratif',$gateau,time()+888777,"/",'.iherbarium.org'); //,'.iherbarium.org'
}

echo '<head>';
echo '<link rel="stylesheet" type="text/css" href="/collaborative/questionstyle.css" media="all" />';
echo '</head>';

require_once("myPhpLib.php");

require_once("debug.php");
require_once("config.php");
require_once("logger.php");

require_once("typoherbariumModel.php");
require_once("dbConnection.php");

require_once("persistentObject.php");

Debug::init("myTest", false);

$local = LocalTypoherbariumDB::get();


// TASK CONDITION TESTS


$stopCondition =
  OrStopCondition::make()
  ->setStopConditions(
		      array(
			    AndStopCondition::make()
			    ->setStopConditions(
						array(
						      EnoughAnswersStopCondition::make()->setEnoughAnswers(4),
						      FirstAnswerVeryProbableStopCondition::make()->setAcceptableProbability(0.8)
 						      )
						),
			    EnoughAnswersStopCondition::make()->setEnoughAnswers(9)
			    )
		      );

$comparisonStopCondition =
  EnoughAnswersStopCondition::make()->setEnoughAnswers(12);

/*
echo "<p>" . $taskCondition . "</p>";

$serialized = serialize($taskCondition);
echo "<p>" . $serialized . "</p>";

$unserialized = unserialize($serialized);
echo "<p>" . $unserialized . "</p>";

die();
*/


// Schema
$schema = $local->loadQuestionsSchema();
//echo "<p>" . $schema . "</p>";

// Protocol
$p = new Protocol();
$p->schema = $schema;
$p->stopCondition = $stopCondition;


if(isset($_GET['thisIsAnswer'])) {

  echo '<p><a href="?nextQuestion=1">Next Task</a></p>';

  $ah = new AnswerHandler();
  $answer = $ah->receiveAnswer();
  $local->saveAnswer($answer);
  echo "<p>" . $answer . "</p>";

  $p->answerReceived($answer);

  echo '<p><a href="?nextQuestion=1">Next Task</a></p>';

  die();
}

// Skin
$s = new TypoherbariumSkin('fr');

// View
$qv = new QuestionView();

// Test APModel
if(isset($_GET['cmp1'])) {  
  $obsId1 = $_GET['cmp1'];
  
  $obsId2 = NULL;
  if(isset($_GET['cmp2']))
    $obsId2 = $_GET['cmp2'];

  $obsId1 = 860;
  //  $obsId2 = 241;
  //  $obsId3 = 457;

  $obs1 = $local->loadObservation($obsId1);
  //$obs2 = $local->loadObservation($obsId2);
  //$obs3 = $local->loadObservation($obsId3);

  $obss = $p->getSimilarObservations($obs1, 10);
  
  $obssIds = array_map(function(TypoherbariumObservation $obs2) { return $obs2->id; }, $obss);
  echo mkString($obssIds, "<p>Obss : ", " , ", " </p>");
  
  //$roisLists = $p->generateROIsToCompare($obs1, array($obs2, $obs3));
  $roisLists = $p->generateROIsToCompare($obs1, $obss);
  
  $content =
    array_mapi(function($roiId, $roisList) {
	$roi =  $roisList["roi"];
	$rois = $roisList["rois"];
	$roisIds = array_map(function(TypoherbariumROI $roi2) { return $roi2->id; }, $rois);
	
	return 
	  mkString($roisIds, "<p>Roi $roiId : ", " , ", " </p>");
	
      }, $roisLists);
    
  echo mkString($content, "<ul><li>", "</li><li>", "</li></ul>");
  
  $tasks = 
    array_map(
	      function($roisList) use ($comparisonStopCondition) {
		$roi =  $roisList["roi"];
		$rois = $roisList["rois"];
		
		$ctask = 
		  TypoherbariumTask::makeROIComparisonTask($roi, $rois)
		  ->setStopCondition($comparisonStopCondition);
				
		return $ctask;
	      }, $roisLists);

  array_iter(
	     array($local, "addTask"),
	     $tasks
	     );

  die();
}

// Test APModel
if(isset($_GET['apModel1'])) {  
  $obsId1 = $_GET['apModel1'];
  $obsId2 = $_GET['apModel2'];

  $obs1 = $local->loadObservation($obsId1);
  $obs2 = $local->loadObservation($obsId2);
  $model1 = APModel::create($obs1);
  $model2 = APModel::create($obs2);  

  echo "<p>" . $model1 . "</p>";
  echo "<p>" . $model2 . "</p>";

  $cmp = $p->getComparator();
  $results = $cmp->compareModels($model1, $model2);

  echo "<pre>" . var_export($results, true) . "</pre>";

  die();
}

// Add Observation
if(isset($_GET['addObs'])) {
  $obsId = $_GET['addObs'];  
  $obs = $local->loadObservation($obsId);

  $p->addedObservation($obs);

  /*
  echo "<p>" . "Added Tasks:" . "</p>";

  array_iter(
	     function(TypoherbariumTask $task) {
	       echo ("<p>" . $task . "</p>");
	     },
	     $tasks);
  */

  die();
}

if(isset($_GET['nextQuestion'])) {
  $task = $local->loadNextTask();
  if( is_null($task) ) {
    echo "<p>No Tasks left!</p>";
    die();
  }
    
  //echo "<p>" . $task . "</p>";  

  // Extract ROI.
  $roi = $task->context;

  $taskCcq = $task->makeClosedChoiceQuestion($s);
  
  // Ask Log.
  $ask = new TypoherbariumAskLog();
  
  $ask
    ->setQuestionType($taskCcq->getType())
    ->setQuestionId($taskCcq->getId())
    ->setContext($roi->id)
    ->setLang($s->lang)
    ->setInternautIp($_SERVER['REMOTE_ADDR'])
    ->setInternautId($gateau);
  
  $ask = $local->logQuestionAsked($ask);
  //echo "<p>" . $ask . "</p>";


  // ClosedChoiceQuestion.
  $taskCcq->setAskLog($ask);
  

  // View.
  $content = '<div>' . $qv->viewQuestion($taskCcq, $roi, $s) . '</div>';
  echo $content;

  die();
}

if(isset($_GET['cmpTest1'])) {

  $r = $local->loadROI(1178);
  $rrr = array_map(
		   array($local, "loadROI"), 
		   array(1179, 897, 908, 926)
		   );
  echo "<p>" . $r . "</p>";

  $ctask = 
    TypoherbariumTask::makeROIComparisonTask($r, $rrr)
    ->setStopCondition($stopCondition);

  $local->addTask($ctask);

  echo "<p>Added test Comparison Task...</p>";
  
  die();
}

echo "<p>No action...</p>";
die();

//$obs = $local->loadObservation(1357);
//$obs = $local->loadObservation(860);

$r = $local->loadROI(1176);
$rrr = array_map(
		 array($local, "loadROI"), 
		 array(1177, 897, 908, 926)
		 );
echo "<p>" . $r . "</p>";


/* cmp start */

$ctask = TypoherbariumTask::makeROIComparisonTask($r, $rrr);
$local->addTask($ctask);
die();

//echo "<pre>" . var_export($ctask, True) . "</pre>";
//echo "<p>" . $ctask . "</p>";

/*
$ctask = $local->addTask($ctask);
//echo "<pre>" . var_export($ctask, True) . "</pre>";
echo "<p>" . $ctask . "</p>";

$ctask = $local->loadTask($ctask->id);
//echo "<pre>" . var_export($ctask, True) . "</pre>";
echo "<p>" . $ctask . "</p>";


//$ctask = $local->loadTask(179);
echo "<p>" . $ctask . "</p>";
*/

/* cmp end */

//$tasks = $p->generateComparisonsForObservation($obs);
//$tasks = $p->generateTasksForROI($r);
//$tasks = $p->generateTasksForObservation($obs);
//$tasks = array($ctask);
$tasks = array($local->loadNextTask());


foreach($tasks as $task) {
  //$task->setStopCondition($taskCondition);
  //$local->addTask($task);
  //echo $task;
    
  $qtask = $local->loadTask($task->id);
  //echo "<pre>" . var_export($qtask, True) . "</pre>";
  //echo "<p>" . $qtask . "</p>";

  
  if($qtask->isFinished()) {
    // $local->saveAnswersPattern($qtask->createAnswersPattern());
  }
  
  
  $taskR = $qtask->context;
  $taskCcq = $qtask->makeClosedChoiceQuestion($s);
  
  $qask = new TypoherbariumAskLog();
  
  $qask
    ->setQuestionType($taskCcq->getType())
    ->setQuestionId($taskCcq->getId())
    ->setContext($taskR->id)
    ->setLang($s->lang)
    ->setInternautIp($_SERVER['REMOTE_ADDR'])
    ->setInternautId($gateau);
  
  $qask = $local->logQuestionAsked($qask);
  //echo "<p>" . $qask . "</p>";
  
  $taskCcq->setAskLog($qask);
  
  $content = '<div>' . $qv->viewQuestion($taskCcq, $taskR, $s) . '</div>';
  echo $content;
}

die();

$schemaInContext = $schema->inContext($r);
//echo "<p>" . $schemaInContext . "</p>";


$goodQ = $schemaInContext->getGoodQuestions();
/*
foreach($goodQ as $q) {
  echo "<p>" . $q . "</p>";
}
*/

$q = $local->loadQuestion(707);
echo "<p>" . $q . "</p>";

$qt = $local->loadQuestionTranslations();
//echo "<pre>" . var_export($qt, True) . "</pre>";
echo "<pre>" . var_export($qt[$q->id], True) . "</pre>";

$t = $local->loadTag(847);
echo "<p>" . $t . "</p>";

$tt = $local->loadTagTranslations();
//echo "<pre>" . var_export($tt, True) . "</pre>";
echo "<pre>" . var_export($tt[$t->tagId], True) . "</pre>";

//echo "<p> ABUSE " . $s->abuse() . "</p>";
echo "<p> TAGNAME " . $s->tag($t)->text . "</p>";
//echo "<p> Q TEXT " .  $s->question($q)->getText() . "</p>";
//echo "<p> Q CHOICES " . "<pre>" . var_export($s->question($q)->getChoices(), True) . "</pre>" . "</p>";

?>