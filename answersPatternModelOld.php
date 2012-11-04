<?php
namespace iHerbarium;

require_once("myPhpLib.php");

require_once("distanceFunctions.php");
require_once("answerToValueFunctions.php");

class AnswersPatternModel {
  
  public $obsId     = NULL;
  public $features  = NULL;

  function __toString() { return "<pre>" . var_export($this, True) . "</pre>"; }

  static public function create(TypoherbariumObservation $obs) {

    // Prepare AnswersPatternModel.
    $model = new AnswersPatternModel();
    $model->obsId = $obs->id;
    $model->features  = array();
    
    $rois = $obs->getROIs();

    array_iter( function($roi) use (&$model) {
	
	// Get an array of all AnswerPatterns (with keys being their questionIds).
	$aps = array();
	foreach($roi->answersPatterns as $ap) {
	  if($ap->questionType == "ROIQuestion")
	    $aps[$ap->questionId] = $ap;
	  
	  /*
	  if($ap->questionType == "ROIComparison")
	    $aps["ROIComparison"][] = $ap;
	  */
	}
	
	// Attach the AnswerPatterns to each Tag the ROI has.
	foreach($roi->tags as $tag) {
	  $tagId = $tag->tagId;
	  $model->features[$tagId][$roi->id] = $aps;
	}
      }, $rois);

    return $model;
  }

}

class AnswerPatternsComparator {

  public function compareAnswersPatterns($ap1, $ap2, $options = array()) {
    
    //echo "<pre>" . var_export($options, True) . "</pre>";

    // Default answer-to-value function - identity.
    if( isset($options['answerToValue']) && $options['answerToValue'] ) {
      $answerToValue = $options['answerToValue'];
    } else {
      $answerToValue = function($a) { return $a; };
    }

    // Default distance function - discrete comparison.
    if( isset($options['distanceFunction']) ) {
      $distanceFunction = $options['distanceFunction'];
    } else {
      $distanceFunction = function($v1, $v2) {
	return ( ($v1 == $v2) ? 0 : 1 );
      };
    }
    
    // Default probablility merging function - simple product.
    if( isset($options['prMergeFunction']) ) {
      $prMergeFunction = $options['prMergeFunction'];
    } else {
      $prMergeFunction = function($pr1, $pr2) {
	return ($pr1 * $pr2);
      };
    }

    // Get all pairs.
    $allAnswerPairs =
      array_zip($ap1->answers, $ap2->answers);

    // Compute for each pair.
    $answerPairsSimilarity =
      array_map(
		function($answerPair) use ($answerToValue, $distanceFunction, $prMergeFunction) {
		  // Extract answers.
		  $answer1 = $answerPair[0];
		  $answer2 = $answerPair[1];

		  // Convert to values.
		  $value1 = $answerToValue($answer1['id']);
		  $value2 = $answerToValue($answer2['id']);

		  // Get probabilities.
		  $pr1 = $answer1['pr'];
		  $pr2 = $answer2['pr'];

		  // Compute their similarity.
		  $d  = $distanceFunction($value1, $value2);
		  $pr = $prMergeFunction($pr1, $pr2);
		  $similarity = (1 - $d) * $pr;

		  //echo "($value1, $value2 => $d, $similarity) ";
		  
		  return $similarity;
		},
		$allAnswerPairs
		);
    
    // Sum up.
    $totalSimilarity =
      array_sum($answerPairsSimilarity);

    return array("similarity" => $totalSimilarity);
  }

}

abstract class ROIComparator {
  abstract public function compareROIs($roi1, $roi2);
}
  
class SimpleROIComparator
extends ROIComparator {

  public $attributesCompareFunction = NULL;

  public function compareROIs($roi1, $roi2) {
    
    $attributesCompareFunction = $this->attributesCompareFunction;

    $totalSimilarity = 0;

    foreach($roi1 as $q => $ap1) {
      if($q == "ROIComparison")
	continue;

      if(isset($roi2[$q])) {
	$ap2 = $roi2[$q];
	$cmp = $attributesCompareFunction($q, $ap1, $ap2);

	$totalSimilarity += $cmp['similarity'];
      } else {
	$totalSimilarity += 0;
      }
    }

    $avgSimilarity = $totalSimilarity / count($roi1);
    
    return array("similarity" => $avgSimilarity);
  }

}


class SmartROIComparator 
extends ROIComparator {

  public $attributesCompareFunction = NULL;
  public $weightFunction = NULL;
  
  public function compareROIs($roi1, $roi2) {

    $attributesCompareFunction = $this->attributesCompareFunction;
    $weightFunction = $this->weightFunction;

    $qs = array_keys($roi1);

    $similarities =
    array_map(
	      function($q) use ($roi1, $roi2, $attributesCompareFunction, $weightFunction) {

		//echo " __ $q __ ";

		// Get two corresponding AnswerPatterns.
		$ap1 = NULL;
		if(isset($roi1[$q])) 
		  $ap1 = $roi1[$q];
		
		$ap2 = NULL;
		if(isset($roi2[$q]))
		  $ap2 = $roi2[$q];
		
		// Compare two corresponding AnswersPatterns.
		$cmp = $attributesCompareFunction($q, $ap1, $ap2);
		
		// Weight the answer.
		$similarity = $cmp['similarity'];
		$weightedSimilarity = $weightFunction($q, $similarity);
	
		return $weightedSimilarity;
	      },
	      $qs);
    
    $totalSimilarity = array_sum($similarities);
           
    return array("similarity" => $totalSimilarity);
  }

}


abstract class FeatureComparator {
  abstract public function compareFeatures($feature1, $feature2);
}
  
class BestFitFeatureComparator
extends FeatureComparator {
  
  public $roiComparator = NULL;

  public function compareFeatures($feature1, $feature2) {
    $roiComparator = $this->roiComparator;

    $allRoiPairs =
      array_zip($feature1, $feature2);

    // Compare all pairs of ROIs.
    $cmpRoiPairs = 
      array_map(
		function($pair) use ($roiComparator) {
		  $roi1 = $pair[0];
		  $roi2 = $pair[1];
		  return $roiComparator->compareROIs($roi1, $roi2);
		}, $allRoiPairs);

    // Get the highest similarity (of two best fitting ROIs).
    usort($cmpRoiPairs, 
	  function($r1, $r2) { 
	    return -cmp($r1['similarity'], $r2['similarity']); 
	  } 
	  );

    $bestFitSimilarity = array_first($cmpRoiPairs);
        
    return array('similarity' => $bestFitSimilarity['similarity']);
  } 
}


abstract class ModelComparator {
  abstract public function compareModels($model1, $model2);
}

class ModelSimpleComparator
extends ModelComparator {

  //public $apComparator      = NULL;
  //public $attrComparator    = NULL;
  //public $roiComparator     = NULL;
  public $featureComparator = NULL;

  public function compareModels($model1, $model2) {
    $answer = array();
    
    foreach($model1->features as $featureId => $feature)
      if(isset($model1->features[$featureId]) && isset($model2->features[$featureId]))
	$answer[$featureId] = $this->featureComparator->compareFeatures($model1->features[$featureId], $model2->features[$featureId]);
 
    return $answer;
  }

}

class ModelSumComparator
extends ModelComparator {

  //public $apComparator      = NULL;
  //public $attrComparator    = NULL;
  //public $roiComparator     = NULL;
  public $featureComparator = NULL;

  public function compareModels($model1, $model2) {
    $answer = array();
    
    foreach($model1->features as $featureId => $feature)
      if(isset($model1->features[$featureId]) && isset($model2->features[$featureId]))
	$answer[$featureId] = $this->featureComparator->compareFeatures($model1->features[$featureId], $model2->features[$featureId]);

    $similarities = 
      array_map(
		function($featureAnswer) { 
		  return $featureAnswer['similarity']; 
		},
		$answer
		);

    $sum = array_sum($similarities);
 
    return array(
		 'similarity' => $sum, 
		 'details' => $similarities // TODO: Make it more elegant and complete!
		 );
  }

}

class MyComparator
extends ModelComparator {

  public $modelComparator = NULL;

  function __construct($questionsOptions, $palette) {

    // Answers Patterns Comparator
    $apComparator = new AnswerPatternsComparator();
    $this->apComparator = $apComparator;

    // ROI Comparator
    $roiComparator = new SmartROIComparator($this);

    // Question Weight
    $qWeight = function($q) use ($questionsOptions) {
      $questionOptions = $questionsOptions[$q];
      
      if(isset($questionOptions['weight']))
	return $questionOptions['weight'];
      else
	return 1;
    };
    
    // ROI Comparator - Weight Function
    $roiComparator->weightFunction =
      function($q, $similarity) use ($qWeight) { 
      return $similarity * $qWeight($q);
    };

    // Question Options Function
    $qOpts = function($q) use ($questionsOptions, $palette) {

      $options = array();

      $questionOptions = $questionsOptions[$q];
      
      //echo "<h1>$q</h1><pre>" . var_export($questionOptions, True) . "</pre>";
      
      // AnswerToValue Function.
      $atvFunction = AnswerToValueFunctions::get($questionOptions);
      $options['answerToValue'] = $atvFunction;

      // Exceptions handling options.
      $exceptionsHandling = $questionOptions['exceptionsHandling'];

      // Function to wrap the Distance Function for Exception Handling
      $exceptionHandlingFunction =
      function($v1, $v2) use ($exceptionsHandling) {
	//echo "<h3>CHECK $v1 $v2</h3>";
	foreach($exceptionsHandling as $exception => $handling) {
	  
	  if($v1 == $exception || $v2 == $exception) {
	    //echo "<h3>EXCEPTION $v1 $v2 $exception</h3>";
	    
	    if($handling["handling"] == "MaxDistance")
	      return 1;
	    
	    if($handling["handling"] == "ConstDistance")
	      return $handling["options"];
	  }
	  
	}

	return NULL;
      };
      
      // DistanceFunction
      $distanceFunction =
      DistanceFunctions::get($questionOptions, $palette);
      
      // Wrap the distance function in exception handling.
      $options['distanceFunction'] =
      DistanceFunctions::wrap(
			      $exceptionHandlingFunction,
			      $distanceFunction
			      );
      
      //echo "<pre>" . var_export($options, True) . "</pre>";
      
      return $options;
    };

    // Attributes Compare Function
    $roiComparator->attributesCompareFunction = 
      function($q, $ap1, $ap2) use ($apComparator, $qOpts) {
      
      // Compare two Attributes of the same type (= the same QuestionId).
      // Method of comparison depends on their type.

      // None or one
      if($ap1 == NULL || $ap2 == NULL)
	return array('similarity' => 0);
      
      // Both
      $options = $qOpts($q);

      // Test of an alternative probability merging function!
      $options['prMergeFunction'] = function($pr1, $pr2) {return min($pr1, $pr2);};
      
      return 
      $apComparator->compareAnswersPatterns($ap1,
					    $ap2,
					    $options
					    );
    };
    
    // Feature Comparator
    $featureComparator = new BestFitFeatureComparator($this);
    $featureComparator->roiComparator = $roiComparator;

    $this->featureComparator = $featureComparator;

    // Model Comparator
    $modelComparator = new ModelSumComparator();
    $modelComparator->featureComparator = $featureComparator;

    $this->modelComparator = $modelComparator;
  }

  public function compareModels($model1, $model2) {
    return $this->modelComparator->compareModels($model1, $model2);
  }

}

?>