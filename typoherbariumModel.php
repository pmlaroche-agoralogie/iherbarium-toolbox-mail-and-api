<?php
namespace iHerbarium;

require_once("myPhpLib.php");

require_once("transferableModel.php");

require_once("fileVersions.php");
require_once("questionSchema.php");

require_once("question.php");
require_once("determinationProtocol.php");
require_once("typoherbariumTask.php");


class TypoherbariumObservation
extends TransferableObservation {

  static public function fromStdObj($obj) {
    $obs = new static();
    
    $obs
      ->setId(          isset($obj->id         ) ? $obj->id         : NULL)
      ->setUser(        isset($obj->user       ) ? $obj->user       : NULL)
      ->setUid(         NULL)
      ->setTimestamp(   isset($obj->timestamp  ) ? $obj->timestamp  : NULL)
      ->setGeolocation( isset($obj->geolocation) ? TypoherbariumGeolocation::fromStdObj($obj->geolocation) : TransferableGeolocation::unknown() )
      ->setPrivacy(     isset($obj->privacy    ) ? $obj->privacy    : "public")
      ->setKind(        isset($obj->kind       ) ? $obj->kind       : 1)
      ->setPlantSize(   isset($obj->plantSize  ) ? $obj->plantSize  : "")
      ->setCommentary(  isset($obj->commentary ) ? $obj->commentary : "");
 
    $obs->setPhotos(
		    array_map(
			      function($objPhoto) { 
				return TypoherbariumPhoto::fromStdObj($objPhoto); 
			      },
			      $obj->photos
			      )
		    );
    
    return $obs;
  }

}

class TypoherbariumPhoto
extends TransferablePhoto {
  protected $id           = NULL;
  protected $sourceFile   = NULL;
  protected $fileVersions = array();

  protected function debugStringsArray() {
    $lines = array();
    $lines[] = "id: "    . $this->id;
    $lines[] = "obsId: " . $this->obsId;
    $lines[] = "sourceFile: "   . "<pre>" . var_export($this->sourceFile, True)   . "</pre>";
    $lines[] = "fileVersions: " . "<pre>" . var_export($this->fileVersions, True) . "</pre>";

    return array_flatten(array($lines, parent::debugStringsArray()));
  }

  static public function fromStdObj($obj) {
    $photo = new static;
    $photo->obsId            = (isset($obj->obsId           ) ? $obj->obsId            : NULL);
    $photo->remoteDir        = $obj->remoteDir;
    $photo->remoteFilename   = $obj->remoteFilename;
    $photo->localDir         = (isset($obj->localDir        ) ? $obj->localDir         : NULL);
    $photo->localFilename    = (isset($obj->localFilename   ) ? $obj->localFilename    : NULL);
    $photo->depositTimestamp = (isset($obj->depositTimestamp) ? $obj->depositTimestamp : NULL);
    $photo->userTimestamp    = (isset($obj->userTimestamp   ) ? $obj->userTimestamp    : NULL);
    $photo->exifTimestamp    = (isset($obj->exifTimestamp   ) ? $obj->exifTimestamp    : NULL);
    $photo->exifOrientation  = (isset($obj->exifOrientation ) ? $obj->exifOrientation  : NULL);

    $photo->exifGeolocation  = 
      TypoherbariumGeolocation::fromStdObj($obj->exifGeolocation);

    $photo->setRois(
		    array_map(
			      function($objROI) { 
				return TypoherbariumROI::fromStdObj($objROI); 
			      },
			      $obj->rois
			      )
		    );
      
    return $photo;
  }

}

class TypoherbariumGeolocation
extends TransferableGeolocation {

}

class TypoherbariumROI
extends TransferableROI {
  protected $id                = NULL;
  protected $photoId           = NULL;
  protected $observationId     = NULL;
  protected $fileVersions      = array();
  protected $tags              = array();
  protected $answers           = array();
  protected $answersPatterns   = array();

  public function getAllTagIds() {
    return
      array_map(function(TypoherbariumTag $tag) { return $tag->tagId; }, $this->tags);
  }

  public function getComparaisonTagIds() {
//PL tag id to be used for generation of comparisons
if((isset($this->tags[0]))&&(($this->tags[0]->tagId ==7)|| ($this->tags[0]->tagId ==2)) || ($this->tags[0]->tagId ==3))
    return
      array_map(function(TypoherbariumTag $tag) { return $tag->tagId; }, $this->tags);
else
 return array();
  }

  public function hasTagId($tagId) {
    return
      array_any(function(TypoherbariumTag $tag) use ($tagId) { return ($tag->tagId == $tagId); }, $this->tags);
  }

  public function hasAnyOfTagIds($tagIds) {
    $context = $this;

    return array_any(
		     function($tagId) use ($context) {
		       return $context->hasTagId($tagId); 
		     },
		     $tagIds
		     );
  }


  protected function debugStringsArray() {
    $beforeLines = array();
    $beforeLines[] = "id: "            . $this->id;
    $beforeLines[] = "photoId: "       . $this->photoId;
    $beforeLines[] = "observationId: " . $this->observationId;


    $afterLines = array();

    $afterLines[] = "fileVersions: " . "<pre>" . var_export($this->fileVersions, True) . "</pre>";

    $afterLines[] = "tags: " .  
      mkString(
	       $this->tags,
	       "<ul><li>", "</li><li>", "</li></ul>"
	       );
    
    $afterLines[] = "answers: " .  
      mkString(
	       $this->answers,
	       "<ul><li>", "</li><li>", "</li></ul>"
	       );
    
    $afterLines[] = "answersPatterns: " .  
      mkString(
	       $this->answersPatterns,
	       "<ul><li>", "</li><li>", "</li></ul>"
	       );
    
    $lines =
      array_flatten(array(
			  $beforeLines,
			  parent::debugStringsArray(),
			  $afterLines
			  ));
    
    return $lines;
  }

}


class TypoherbariumTag 
extends ModelBaseClass {

  protected $id    = NULL;
  protected $tagId = NULL;
  protected $roiId = NULL;
  protected $uid   = NULL;
  protected $kind  = NULL;

  // Debug printing
  protected function debugStringsArray() {
    $lines   = array();
    $lines[] = "id: "     . $this->id;
    $lines[] = "tagId: "  . $this->tagId;
    $lines[] = "roiId: "  . $this->roiId;
    $lines[] = "uid: "    . $this->uid;
    $lines[] = "kind: "   . $this->kind;
    
    return $lines;
  }

  final protected function debugString() {
    return 
      mkString(
	       $this->debugStringsArray(),
	       "<p>TypoherbariumTag:<ul><li>", "</li><li>", "</li></ul></p>"
	       );
  }

  function __toString() { return $this->debugString(); }

}

class TypoherbariumROIAnswer 
extends ModelBaseClass {

  protected $id           = NULL;

  protected $questionType = NULL;
  protected $questionId   = NULL;
  protected $roiId        = NULL;

  protected $answerValue  = NULL;

  protected $askId        = NULL;

  protected $internautIp  = NULL;
  protected $source       = NULL;
  protected $referrant    = NULL;
  

  // Debug printing
  protected function debugStringsArray() {
    $lines   = array();
    $lines[] = "id: "           . $this->id;
    $lines[] = "questionType: " . $this->questionType;
    $lines[] = "questionId: "   . $this->questionId;
    $lines[] = "roiId: "        . $this->roiId;
    $lines[] = "answerValue: "  . $this->answerValue;
    $lines[] = "askId: "        . $this->askId;
    $lines[] = "internautIp: "  . $this->internautIp;
    $lines[] = "source: "       . $this->source;
    $lines[] = "referrant: "    . $this->referrant;
    
    return $lines;
  }

  final protected function debugString() {
    return 
      mkString(
	       $this->debugStringsArray(),
	       "<p>TypoherbariumROIAnswer:<ul><li>", "</li><li>", "</li></ul></p>"
	       );
  }

  function __toString() { return $this->debugString(); }

}


class TypoherbariumROIAnswersPattern
extends ModelBaseClass {
  
  protected $id           = NULL;
  protected $questionType = NULL;
  protected $questionId   = NULL;
  protected $roiId        = NULL;
  protected $answers      = array();

  public function getAnswerForROIId($roiId) {
    return
      array_single(
		   function($answer) use ($roiId) { 
		     return ($roiId == $answer['id']); 
		   }, 
		   $this->answers
		   );
  }

  public function getAnswerParamForROIId($roiId, $param, $default = 0) {
    $answer = $this->getAnswerForROIId($roiId);
    
    if($answer)
      return $answer[$param];
    else
      return $default;
  }
  
  // Debug printing
  protected function debugStringsArray() {
    $lines = array();
    $lines[] = "id: "           . $this->id;
    $lines[] = "questionType: " . $this->questionType;
    $lines[] = "questionId: "   . $this->questionId;
    $lines[] = "roiId: "        . $this->roiId;
    $lines[] = "getBestAnswer(): " . $this->getBestAnswer();
    $lines[] = "answers: " . "<pre>" . var_export($this->answers, True) . "</pre>";
    
    return $lines;
  }

  final protected function debugString() {
    return 
      mkString(
	       $this->debugStringsArray(),
	       "<p>AnswersPattern:<ul><li>", "</li><li>", "</li></ul></p>"
	       );
  }

  function __toString() { return $this->debugString(); }

  public function getFirstAnswer() {
    if(isset($this->answers[0]))
      return $this->answers[0];
    else
      return NULL;
  }

  public function getBestAnswer() {
    if(isset($this->answers[0]))
      return $this->answers[0]["id"];
    else
      return NULL;
  }
  
}

class TypoherbariumROIQuestion 
extends ModelBaseClass {

  protected $id      = NULL;
  protected $choices = array();

  // Constraints
  protected $necessaryTagId      = NULL;
  protected $necessaryQuestionId = NULL;
  protected $necessaryAnswer     = NULL;
  protected $necessaryGrid       = NULL;

  // Debug printing
  protected function debugStringsArray() {
    $lines = array();
    $lines[] = "id: "         . $this->id;
    
    $lines[] = "choices: " .  
      mkString(
	       $this->choices,
	       "<ul><li>", "</li><li>", "</li></ul>"
	       );

    $lines[] = "necessaryTagId: " . $this->necessaryTagId;
    $lines[] = "necessaryQuestionId: " . $this->necessaryQuestionId;
    $lines[] = "necessaryAnswer: " . $this->necessaryAnswer;
    $lines[] = "necessaryGrid: " . $this->necessaryGrid;  

    return $lines;
  }

  final protected function debugString() {
    return 
      mkString(
	       $this->debugStringsArray(),
	       "<p>TypoherbariumROIQuestion:<ul><li>", "</li><li>", "</li></ul></p>"
	       );
  }

  function __toString() { return $this->debugString(); }

}

class TypoherbariumAskLog
extends ModelBaseClass {

  protected $id           = NULL;

  // Question info
  protected $questionType = NULL;
  protected $questionId   = NULL;
  protected $context      = NULL;

  // Asking conditions info
  protected $lang         = NULL;
  protected $internautIp  = NULL;
  protected $internautId  = NULL;
  
  // Debug printing
  protected function debugStringsArray() {
    $lines = array();
    $lines[] = "id: "           . $this->id;
    $lines[] = "questionType: " . $this->questionType;
    $lines[] = "questionId: "   . $this->questionId;
    $lines[] = "context: "      . $this->context;
    $lines[] = "lang: "         . $this->lang;
    $lines[] = "internautIp: "  . $this->internautIp;
    $lines[] = "internautId: "  . $this->internautId;

    return $lines;
  }

  final protected function debugString() {
    return 
      mkString(
	       $this->debugStringsArray(),
	       "<p>TypoherbariumAskLog:<ul><li>", "</li><li>", "</li></ul></p>"
	       );
  }

  function __toString() { return $this->debugString(); }
  
}

class TypoherbariumGroup
extends ModelBaseClass {
  
  protected $id             = NULL;
  protected $name           = NULL;
  protected $observations   = array();
  protected $includedGroups = array();

  public function getAllObservations() {
    $allObservations = $this->observations;
    
    foreach($this->includedGroups as $group) {
      //$allObservations = array_merge($allObservations, $group->getAllObservations());
      $allObservations += $group->getAllObservations();
    };

    return $allObservations;
  }

  // Debug printing
  protected function debugStringsArray() {
    $lines = array();
    $lines[] = "id: "   . $this->id;
    $lines[] = "name: " . $this->name;

    $lines[] = "includedGroups: " .
      mkString(
	       $this->includedGroups,
	       "<ul><li>", "</li><li>", "</li></ul>"
	       );
    
    $lines[] = "observations: " .
      mkString(
	       array_mapi(function($key, $obs) { return $obs->id; }, $this->observations ),
	       "<ul><li>", "</li><li>", "</li></ul>"
	       );
    
    $lines[] = "getAllObservations(): " .
      mkString(
	       array_mapi(function($key, $obs) { return $obs->id; }, $this->getAllObservations()),
	       "<ul><li>", "</li><li>", "</li></ul>"
	       );   
    
 
    return $lines;
  }

  final protected function debugString() {
    return 
      mkString(
	       $this->debugStringsArray(),
	       "<p>TypoherbariumGroup:<ul><li>", "</li><li>", "</li></ul></p>"
	       );
  }

  function __toString() { return $this->debugString(); }
  
}

?>
