<?php
namespace iHerbarium;

require_once("myPhpLib.php");

require_once("distanceFunctions.php");
require_once("answerToValueFunctions.php");

require_once("apModelComparator.php");

class APModel {
  
  public $obsId = NULL; public function setObsId($obsId) { $this->obsId = $obsId; return $this; }

  // features : tagId => APFeature 
  public $features  = array(); public function addFeature($feature, $key = NULL) { if($key) $this->features[$key] = $feature; else $this->features[] = $feature; return $this; }

  function __toString() { return "<pre>" . var_export($this, True) . "</pre>"; }

  static public function create(TypoherbariumObservation $obs) {

    // Prepare AnswersPatternModel.
    $model = new APModel();
    
    $model
      ->setObsId($obs->id);
    
    $allObsTagIds =
      array_unique( array_flatten (array_map(
			      function(TypoherbariumROI $roi) { 
				return $roi->getAllTagIds();
			      },
			      $obs->getROIs()
					     )));

    array_iter(
	       function($tagId) use ($obs, &$model) {
		 $model->addFeature(APFeature::create($tagId, $obs),
				    $tagId);
	       }, 
	       $allObsTagIds
	       );
    
    return $model;
  }

}

class APFeature {
  public $tagId = NULL; public function setTagId($tagId) { $this->tagId = $tagId; return $this; }
  
  // instances : roiId => APFeatureInstance
  public $instances = array(); public function addInstance($instance, $key = NULL) { if($key) $this->instances[$key] = $instance; else $this->instances[] = $instance; return $this; }
  

  static public function create($tagId, TypoherbariumObservation $obs) {
    $feature = new static();

    $feature->setTagId($tagId);

    $rois = array_filter(function($roi) use ($tagId) { return $roi->hasTagId($tagId); } , $obs->getROIs() );

    array_iter(
	       function($roi) use (&$feature) {
		 $feature->addInstance(APFeatureInstance::create($roi),
				       $roi->id);
	       }, 
	       $rois);

    return $feature;
  }
}

class APFeatureInstance {
  public $roi   = NULL; public function setRoi($roi) { $this->roi = $roi; return $this; }
  public $roiId = NULL; public function setRoiId($roiId) { $this->roiId = $roiId; return $this; }

  // questionId => APAttribute
  public $attributes  = array(); public function addAttribute($attribute, $key = NULL) { if($key) $this->attributes[$key] = $attribute; else $this->attributes[] = $attribute; return $this; }
  public $comparisons = array(); public function addComparison($comparison, $key = NULL) { if($key) $this->comparisons[$key] = $comparison; else $this->comparisons[] = $comparison; return $this; }

  static public function create(TypoherbariumROI $roi) {
    $featureInstance = new static();

    $featureInstance->setRoi($roi);
    $featureInstance->setRoiId($roi->id);
    
    array_iter(
	       function(TypoherbariumROIAnswersPattern $ap) use (&$featureInstance) {
		 if($ap->questionType == "ROIQuestion") {
		   $featureInstance
		     ->addAttribute(APAttribute::create($ap),
				    $ap->questionId);
		 }

		 if($ap->questionType == "ROIComparison") {
		   $featureInstance
		     ->addComparison(APAttribute::create($ap));
		 }
	       }, 
	       $roi->answersPatterns);

    return $featureInstance;
  }
}

class APAttribute {
  public $questionId     = NULL; public function setQuestionId($questionId) { $this->questionId = $questionId; return $this; }
  public $answersPattern = NULL; public function setAnswersPattern($answersPattern) { $this->answersPattern = $answersPattern; return $this; }

  static public function create(TypoherbariumROIAnswersPattern $ap) {
    $attribute = new static();
    
    $attribute
      ->setQuestionId($ap->questionId)
      ->setAnswersPattern($ap);
    
    return $attribute;
  }
}

?>