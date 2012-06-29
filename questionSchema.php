<?php
namespace iHerbarium;

require_once("myPhpLib.php");


/*
interface QuestionSchema {
  
}

abstract class AskingCondition {
  public function check();
}
*/


class TypoherbariumQuestionSchema {

  public $questionNodes = array(); public function addQuestionNode($questionNode, $key = NULL) { if($key) $this->questionNodes[$key] = $questionNode; else $this->questionNodes[] = $questionNode; return $this; }

  public function addQuestion(TypoherbariumROIQuestion $question) {
    $questionNode = 
      new TypoherbariumQuestionSchemaNode($this, $question);

    $this->addQuestionNode($questionNode, $question->id);
  }

  public function inContext(TypoherbariumROI $roi) {
    return 
      new TypoherbariumQuestionSchemaInContext($this, $roi);
  }
  
  
  // Debug printing
  protected function debugStringsArray() {
    $lines = array();
    $lines[] = "questionNodes: " .
      mkString(
	       $this->questionNodes,
	       "<ul><li>", "</li><li>", "</li></ul>"
	       );

    return $lines;
  }

  final protected function debugString() {
    return 
      mkString(
	       $this->debugStringsArray(),
	       "<p>TypoherbariumQuestionSchema:<ul><li>", "</li><li>", "</li></ul></p>"
	       );
  }

  function __toString() { return $this->debugString(); }

}

class TypoherbariumQuestionSchemaInContext {

  public $mySchema      =    NULL; public function setMySchema($mySchema) { $this->mySchema = $mySchema; return $this; }
  public $questionNodes = array(); public function addQuestionNode($questionNode, $key = NULL) { if($key) $this->questionNodes[$key] = $questionNode; else $this->questionNodes[] = $questionNode; return $this; }

  public function getQuestionNodes() {
    return $this->questionNodes;
  }

  public function getQuestionNode($qId) {
    return $this->questionNodes[$qId];
  }

  public function getGoodQuestions() {

    return
      array_map(
		function($questionNode) { return $questionNode->mySchemaNode->question; },
		array_filter(
			     function($questionNode) { return $questionNode->checkConditions() && (! $questionNode->hasDefiniteAnswer() ); }, 
			     $this->getQuestionNodes()
			     )
		);
  }

  function __construct(TypoherbariumQuestionSchema $mySchema, TypoherbariumROI $roi) {
    $this->setMySchema($mySchema);
    
    $schemaInContext = $this;

    $this
      ->questionNodes =
      array_map(
		function($questionNode) use ($roi, $schemaInContext) { 
		  return $questionNode->inContext($schemaInContext, $roi); 
		}, 
		$mySchema->questionNodes);

  }

  
  // Debug printing
  protected function debugStringsArray() {
    $lines = array();
    $lines[] = "questionNodes: " .
      mkString(
	       $this->questionNodes,
	       "<ul><li>", "</li><li>", "</li></ul>"
	       );

    return $lines;
  }

  final protected function debugString() {
    return 
      mkString(
	       $this->debugStringsArray(),
	       "<p>TypoherbariumQuestionSchemaInContext:<ul><li>", "</li><li>", "</li></ul></p>"
	       );
  }

  function __toString() { return $this->debugString(); }

}

class TypoherbariumQuestionSchemaNode {
  
  /* workaround to get nested class behavior... */
  public $mySchema = NULL; public function setMySchema($mySchema) { $this->mySchema = $mySchema; return $this; }
  public $question = NULL; public function setQuestion(TypoherbariumROIQuestion $question) { $this->question = $question; return $this; }

  function __construct(TypoherbariumQuestionSchema $schema, TypoherbariumROIQuestion $question) {
    $this
      ->setMySchema($schema)
      ->setQuestion($question);
  }

  public function inContext(TypoherbariumQuestionSchemaInContext $schemaInContext, TypoherbariumROI $roi) {
    return
      new TypoherbariumQuestionSchemaNodeInContext($schemaInContext, $this, $roi);
  }


  // Debug printing
  protected function debugStringsArray() {
    $lines = array();
    $lines[] = "question: " . $this->question;
    return $lines;
  }

  final protected function debugString() {
    return 
      mkString(
	       $this->debugStringsArray(),
	       "<p>TypoherbariumQuestionSchemaNode:<ul><li>", "</li><li>", "</li></ul></p>"
	       );
  }

  function __toString() { return $this->debugString(); }

}

class TypoherbariumQuestionSchemaNodeInContext {
  
  public $mySchemaInContext = NULL; public function setMySchemaInContext($mySchemaInContext) { $this->mySchemaInContext = $mySchemaInContext; return $this; }
  public $mySchemaNode      = NULL; public function setMySchemaNode($mySchemaNode) { $this->mySchemaNode = $mySchemaNode; return $this; }
  public $roi               = NULL; public function setRoi($roi) { $this->roi = $roi; return $this; }

  function __construct(TypoherbariumQuestionSchemaInContext $schemaInContext, TypoherbariumQuestionSchemaNode $schemaNode, TypoherbariumROI $roi) {
    $this
      ->setMySchemaInContext($schemaInContext)
      ->setMySchemaNode($schemaNode)
      ->setRoi($roi);
  }

  public function getAnswers() {
    $question = $this->mySchemaNode->question;

    return
      array_filter( 
		   function($answer) use ($question) { 
		     return ($answer->questionId == $question->id); 
		   }, 
		   $this->roi->answers);
  }

  public function getAnswersPatterns() {
    $question = $this->mySchemaNode->question;
    
    return
      array_filter( 
		   function($ap) use ($question) { 
		     return ($ap->questionId == $question->id);
		   },
		   $this->roi->answersPatterns);
  }

  public function hasDefiniteAnswer() {
    $aps = $this->getAnswersPatterns();
    
    if(count($aps) > 0)
      return true;
    else
      return false;
  }
  
  public function getDefiniteAnswer() {
    $aps = $this->getAnswersPatterns();

    if(count($aps) > 0) {
      $ap = array_first($aps);
      return $ap->getBestAnswer();
    } 
    else 
      return NULL;
  }  

  public function checkTagCondition($necessaryTagId) {
    return 
      array_any(
		function(TypoherbariumTag $tag) use ($necessaryTagId) { 
		  return ($tag->tagId == $necessaryTagId);
		},
		$this->roi->tags);
  }

  public function checkQuestionAnswerCondition($necessaryQuestionId, $necessaryAnswerValue) {
    $qNode = $this->mySchemaInContext->questionNodes[$necessaryQuestionId]; // simulating class nesting
    
    return
      ( $qNode->hasDefiniteAnswer() && 
	($qNode->getDefiniteAnswer() == $necessaryAnswerValue) );
  }

  public function checkConditions() {
    $q = $this->mySchemaNode->question; // simulating class nesting
    
    // Initially - OK!
    $ok = true;

    // Check NecessaryTagCondition.
    if($q->necessaryTagId != 0) {
      $ok = $this->checkTagCondition($q->necessaryTagId); // ? "[Tag OK]" : "[Tag Wrong]");
    }
    
    if(!$ok) return false;

    // Check NecessaryQuestionAnswerCondition.
    if($q->necessaryQuestionId != 0) {
      $ok = $this->checkQuestionAnswerCondition($q->necessaryQuestionId, $q->necessaryAnswer); // ? "[A&Q OK]" : "[A&Q Wrong]");
    }
    
    if(!$ok) return false;

    // All checks done - it's ok!
    return $ok;
  }

  public function shouldBeAsked() {
    return
      (! $this->hasDefiniteAnswer() ) && $this->checkConditions();
  }
  
  // Debug printing
  protected function debugStringsArray() {
    $lines = array();
    $lines[] = "context (= roi): " . $this->roi->id;
    $lines[] = "myNode: " . $this->mySchemaNode;
    
    $lines[] = "answers: " .  
      mkString(
	       $this->getAnswers(),
	       "<ul><li>", "</li><li>", "</li></ul>"
	       );    

    $lines[] = "answersPatterns: " .  
      mkString(
	       $this->getAnswersPatterns(),
	       "<ul><li>", "</li><li>", "</li></ul>"
	       );

    $lines[] = "hasDefiniteAnswer(): " . ( $this->hasDefiniteAnswer() ? "YES" : "NO" );
    $lines[] = "getDefiniteAnswer(): " . $this->getDefiniteAnswer();

    $lines[] = "checkConditions(): "   . ( $this->checkConditions() ? "YES" : "NO" );
	
    return $lines;
  }

  final protected function debugString() {
    return 
      mkString(
	       $this->debugStringsArray(),
	       "<p>TypoherbariumQuestionSchemaNodeInContext:<ul><li>", "</li><li>", "</li></ul></p>"
	       );
  }

  function __toString() { return $this->debugString(); }

}

?>