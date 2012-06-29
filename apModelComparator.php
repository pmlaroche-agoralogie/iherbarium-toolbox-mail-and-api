<?php
namespace iHerbarium;

require_once("myPhpLib.php");

require_once("distanceFunctions.php");
require_once("answerToValueFunctions.php");

interface AnswerPatternsComparatorI {
  public function compareAnswersPatterns(TypoherbariumROIAnswersPattern $ap1, TypoherbariumROIAnswersPattern $ap2, $options);
}

class AnswerPatternsComparator 
implements AnswerPatternsComparatorI {

  public function compareAnswersPatterns(TypoherbariumROIAnswersPattern $ap1, TypoherbariumROIAnswersPattern $ap2, $options = array()) {
    
    //echo "<pre>" . var_export($options, True) . "</pre>";

    // Default answer-to-value function - identity.
    if( isset($options['answerToValue']) && $options['answerToValue'] ) {
      $answerToValue = $options['answerToValue'];
    } else {
      $answerToValue = AnswerToValueFunctions::defaultAnswerToValue();
    }

    // Default distance function - discrete comparison.
    if( isset($options['distanceFunction']) && $options['distanceFunction'] ) {
      $distanceFunction = $options['distanceFunction'];
    } else {
      $distanceFunction = DistanceFunctions::defaultDistance();
    }
    
    // Default probablility merging function - simple product.
    if( isset($options['probabilityMergeFunction']) && $options['probabilityMergeFunction'] ) {
      $probabilityMergeFunction = $options['probabilityMergeFunction'];
    } else {
      $probabilityMergeFunction = function($pr1, $pr2) {
	return ($pr1 * $pr2);
      };
    }

    // Get all pairs.
    $allAnswerPairs =
      array_zip($ap1->answers, $ap2->answers);

    // Compute similarity for each pair.
    $answerPairsSimilarity =
      array_mapi(
		 function($answerPairIndex, $answerPair) use ($answerToValue, $distanceFunction, $probabilityMergeFunction) {
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
		   $d  = $distanceFunction($value1, $value2);   // Distance
		   $pr = $probabilityMergeFunction($pr1, $pr2); // Probablility
		   $similarity = (1 - $d) * $pr;                // Similarity

		   //echo "<br/>Answer Pair Distance and Similarity ($value1, $value2 => $d, $similarity)";
		   //echo " types: " . gettype($value1) . " " . gettype($value2);

		   // Prepare the detailed result.
		   $result = array(
				   "header" => "Answers Pair number $answerPairIndex : " . $similarity,

				   // Details of the first answer.
				   "answer1"      => $answer1['id'],
				   "value1"       => $value1,
				   "probability1" => $pr1,

				   // Details of the second answer.
				   "answer2"      => $answer2['id'],
				   "value2"       => $value2,
				   "probability2" => $pr2,
				  
				   // Details of similarity computation.
				   "distance"     => $d,
				   "probability"  => $pr,
				   "similarity"   => $similarity
				   );

		   return $result;
		 },
		 $allAnswerPairs
		 );
    
    // Sum up.
    $totalSimilarity =
      array_sum(array_map(function($result) { return $result["similarity"]; }, $answerPairsSimilarity));

    return array(
		 "header"   => "Answer Patterns Comparison : $totalSimilarity",
		 "similarity" => $totalSimilarity, 
		 "answerPairsSimilarity" => $answerPairsSimilarity + array("header" => "Individual Answer Pairs Comparison", "open" => true));
  }

}


// Attributes Comparator

interface AttributesComparatorI {
  public function compareAttributes(APAttribute $attr1, APAttribute $attr2);
}

class AttributesComparator
implements AttributesComparatorI {

  public $questionOptionsFunction = NULL;  
  public $apComparator = NULL;

  public function __construct(AnswerPatternsComparator $apComparator, $questionOptionsFunction) {
    $this->apComparator = $apComparator;
    $this->questionOptionsFunction = $questionOptionsFunction;
  }

  public function compareAttributes(APAttribute $attr1, APAttribute $attr2) {
  
    // Compare two Attributes of the same type (= the same QuestionId).
    // Method of comparison depends on their type.
    
    // None or one
    if(is_null($attr1) || is_null($attr2) || $attr1->questionId != $attr2->questionId)
      return array('similarity' => 0); 

    // Both
    $qId = $attr1->questionId;
    $questionOptionsFunction = $this->questionOptionsFunction;
    $options = $questionOptionsFunction($qId);
    
    // TEST
    // Test of an alternative probability merging function!
    $options['probabilityMergeFunction'] = function($pr1, $pr2) { return min($pr1, $pr2); };
    
    return
      $this->apComparator->compareAnswersPatterns($attr1->answersPattern, $attr2->answersPattern, $options);
  }

}


// FeatureInstances Comparator

interface FeatureInstanceComparatorI {
  public function compareFeatureInstances(APFeatureInstance $fi1, APFeatureInstance $fi2);
}
  
class SimpleROIComparator
implements FeatureInstanceComparatorI {

  public $attributesCompareFunction = NULL;
  public $attributesComparator = NULL;

  public function compareFeatureInstances(APFeatureInstance $fi1, APFeatureInstance $fi2) {
    
    $attributesComparator = $this->attributesComparator;

    $totalSimilarity = 0;

    foreach($fi1->attributes as $qId => $ap1) {

      if(isset($fi2->attributes[$qId])) {
	$ap2 = $fi2->attributes[$qId];

	$cmp = $attributesComparator->compareAttributes($ap1, $ap2);

	$totalSimilarity += $cmp['similarity'];
      } else {
	$totalSimilarity += 0;
      }
    }

    $avgSimilarity = $totalSimilarity / count($fi1->attributes);
    
    return array("similarity" => $avgSimilarity);
  }

}


class QuestionsWeightedSumFeatureInstanceComparator 
implements FeatureInstanceComparatorI {

  public $attributesCompareFunction = NULL;
  public $attributesComparator = NULL;
  public $weightFunction = NULL;
  
  public function compareFeatureInstances(APFeatureInstance $fi1, APFeatureInstance $fi2) {

    // Attributes

    $attributesComparator = $this->attributesComparator;
    $weightFunction = $this->weightFunction;

    $qIds = array_keys($fi1->attributes);

    $similarities =
    array_map(
	      function($qId) use ($fi1, $fi2, $attributesComparator, $weightFunction) {

		//echo " __ $q __ ";

		// Get two corresponding Attributes.
		$attr1 = NULL;
		if(isset($fi1->attributes[$qId])) 
		  $attr1 = $fi1->attributes[$qId];
		
		$attr2 = NULL;
		if(isset($fi2->attributes[$qId]))
		  $attr2 = $fi2->attributes[$qId];

		if(is_null($attr1) || is_null($attr2))
		  return 0;
		
		// Compare two corresponding Attributes.
		$cmp = $attributesComparator->compareAttributes($attr1, $attr2);
		
		// Weight the answer.
		$similarity = $cmp["similarity"];
		$weightedSimilarity = $weightFunction($qId, $similarity);

		$result = array(
				"header"               => "Attribute with QuestionId $qId : $weightedSimilarity",
				"questionId"           => $qId,
				"attributesComparison" => $cmp,
				"similarity"           => $similarity,
				"weightedSimilarity"   => $weightedSimilarity
				);
		
		return $result;
	      },
	      $qIds);
    
    $totalSimilarity =
      array_sum(array_map(function($result) { return $result["weightedSimilarity"]; }, $similarities));
           
    $result = array(
		    "header" => "Questions Result : $totalSimilarity",
		    "similarity" => $totalSimilarity,
		    "attributesSimilarities" => $similarities + array("header" => "Attribute Similarities", "open" => true)
		    );

      return $result;
  }

}

class ComparisonsFeatureInstanceComparator 
implements FeatureInstanceComparatorI {
  
  public function compareFeatureInstances(APFeatureInstance $fi1, APFeatureInstance $fi2) {

    /* To compute results of comparisons between two FeatureInstances (i.e., two ROIs)
     * in fact we only sum up the comparisons results in one direction. Why?
     *
     * Comparing ROI1 with ROI2 is something different than comparing ROI2 with ROI1,
     * because sometimes the comparison groups (all the other ROIs that were proposed
     * during the comparison task) can be very different for ROI1 and ROI2. So for example
     * in one  direction it can be easy to say that ROI1 is similar to ROI2 (cause for example
     * all the other ROIs in the comparison group are very different from ROI1), but in
     * the other direction the similarity can be less obvious (if there are many similar
     * ROIs in the comparison group).
     *
     * So to compute the result of comparisons we just take all comparisons made for ROI1
     * when ROI2 was in the comparison group (it was one of the possible answers) and
     * we sum them up.
     *
     * NOTE: For now we should have only zero or one comparison results for every pair of
     * ROIs, but this code is prepared (just in case) for the situation when we can have multiple
     * comparison results and multiple answers in one AnswerPattern concerning the same
     * pair of ROIs.
     */
    
    $roiId2 = $fi2->roiId;

    // For all the comparisons made for ROI1.
    $comparisonSimilarities =
      array_map(
		function(APAttribute $comparison) use ($roiId2) {
		  $ap = $comparison->answersPattern;
		  
		  // For each answer in answer pattern.
		  $comparisonResults =
		    array_map(
			      function($apAnswer) use ($roiId2) {
				$roiId1 = $apAnswer["id"];
				
				// If it is an answer for comparison of ROI1 and ROI2
				// (for now there should be only one in each AnswersPattern),
				// we count it in.
				if($roiId1 === $roiId2)
				  return $apAnswer["pr"];
				else
				  return 0;
			      },
			      $ap->answers);

		  // We sum up all the results in this AnswersPattern.
		  return 
		    array_sum($comparisonResults);
		},
		$fi1->comparisons);

    // We sum up all the comparison results.
    $totalComparisonSimilarity = array_sum($comparisonSimilarities);

    $result = array(
		    "header" => "Comparisons Result : $totalComparisonSimilarity",
		    "similarity" => $totalComparisonSimilarity
		    );
           
    return $result;
  }

}

// Features Comparator

interface FeatureComparatorI {
  public function compareFeatures(APFeature $feature1, APFeature $feature2);
}

class WeightedBestFitFeatureComparator
implements FeatureComparatorI {
  
  public $questionsFeatureInstanceComparator = NULL;
  public $comparisonsFeatureInstanceComparator = NULL;
  
  public $tagOptionsFunction = NULL;

  public function comparePairOfFeatureInstances($pair, $tagOptions) {
    
    // Extract Feature Instances from given pair.
    $fi1 = $pair[0];
    $fi2 = $pair[1];
    
    $roiId1 = $fi1->roiId;
    $roiId2 = $fi2->roiId;
    
    // Global components.
    $questionsFeatureInstanceComparator   = $this->questionsFeatureInstanceComparator;
    $comparisonsFeatureInstanceComparator = $this->comparisonsFeatureInstanceComparator;
    		  
    // Questions Similarity.
    if($tagOptions['QuestionsWeight'] > 0) {
      $questionsResult = $questionsFeatureInstanceComparator->compareFeatureInstances($fi1, $fi2);
      $questionsSimilarity = $questionsResult['similarity'];
      $weightedQuestionsSimilarity = $questionsSimilarity * $tagOptions['QuestionsWeight'];		    
    } else {
      $questionsResult = array('similarity' => 0);
      $questionsSimilarity = 0;
      $weightedQuestionsSimilarity = 0;
    }
		  
    // Comparisons Similarity.
    if($tagOptions['ComparisonsWeight'] > 0) {
      $comparisonsResult = $comparisonsFeatureInstanceComparator->compareFeatureInstances($fi1, $fi2);
      $comparisonsSimilarity = $comparisonsResult['similarity'];
      $weightedComparisonsSimilarity = $comparisonsSimilarity * $tagOptions['ComparisonsWeight'];
    } else {
      $comparisonsResult = array('similarity' => 0);
      $comparisonsSimilarity = 0;
      $weightedComparisonsSimilarity = 0;
    }

    // Total result.
    $similarity =
      $weightedQuestionsSimilarity + $weightedComparisonsSimilarity;

    $result = array(
		    "header" => "Feature Instances Pair Comparison (ROIs: $roiId1, $roiId2) : $similarity",

		    "roi1" => array(
				    "header" => "ROI 1",
				    "roiId" => $roiId1,
				    "roiImg" => "<img src='" . $fi1->roi->fileVersions["vignette"]->url(). "'/>"
				    ),
				  
		    "roi2" => array(
				    "header" => "ROI 2",
				    "roiId" => $roiId2,
				    "roiImg" => "<img src='" . $fi2->roi->fileVersions["vignette"]->url() . "'/>"
				    ),
				  
		    "questionsResult" => $questionsResult,
		    "questionsWeight" => $tagOptions['QuestionsWeight'],
		    "weightedQuestionsSimilarity" => $weightedQuestionsSimilarity,
				  
		    "comparisonsResult" => $comparisonsResult,
		    "comparisonsWeight" => $tagOptions['ComparisonsWeight'],
		    "weightedComparisonsSimilarity" => $weightedComparisonsSimilarity,

		    "similarity" => $similarity
		    );

    return $result;
  }

  public function compareFeatures(APFeature $feature1, APFeature $feature2) {
    $questionsFeatureInstanceComparator   = $this->questionsFeatureInstanceComparator;
    $comparisonsFeatureInstanceComparator = $this->comparisonsFeatureInstanceComparator;

    // Prepare Tag options.
    $tagId = $feature1->tagId;
    $tagOptionsFunction = $this->tagOptionsFunction;
    $tagOptions = $tagOptionsFunction($tagId);

    // Prepare a list of all possible pairs of FeatureInstances.
    $allFeatureInstancePairs =
      array_zip($feature1->instances, $feature2->instances);

    // Compare all pairs of FeatureInstances.
    $featureComparator = $this;

    $cmpFeatureInstancePairs = 
      array_map(
		function($pair) use ($featureComparator, $tagOptions) { 
		  return $featureComparator->comparePairOfFeatureInstances($pair, $tagOptions); 
		},
		$allFeatureInstancePairs);

    /*
    // Filter out the Feature Instances with similarity = 0
    $cmpFeatureInstancePairs = array_filter(function($pairResult) { return $pairResult["similarity"] != 0; }, $cmpFeatureInstancePairs);
    */

    // Get the highest similarity (of two best fitting FeatureInstances).
    usort($cmpFeatureInstancePairs, function($r1, $r2) { return -cmp($r1['similarity'], $r2['similarity']); });

    $bestFitSimilarity = array_first($cmpFeatureInstancePairs);

    $similarity = $bestFitSimilarity["similarity"];

    $result = array(
		    "header" => "Feature with tag $tagId : $similarity",
		    "algorithm" => "Best Fit",
		    "featureInstancePairsCount" => count($cmpFeatureInstancePairs),
		    "similarity" => $similarity,
		    "bestFeatureInstancesPair" => array("header" => "Best Pair of Instances (ROIs)", "open" => true, $bestFitSimilarity)
		    );

    if(count($cmpFeatureInstancePairs) > 1)
      $result += array("allFeatureInstancesPairs" => $cmpFeatureInstancePairs + array("header" => "All Pairs of Feature Instances") );

    return $result;
  } 
}

class WeightedBestFitForEachTargetFeatureInstanceFeatureComparator
extends WeightedBestFitFeatureComparator {
  
  public function compareFeatures(APFeature $feature1, APFeature $feature2) {
    $questionsFeatureInstanceComparator   = $this->questionsFeatureInstanceComparator;
    $comparisonsFeatureInstanceComparator = $this->comparisonsFeatureInstanceComparator;

    // Prepare Tag options.
    $tagId = $feature1->tagId;
    $tagOptionsFunction = $this->tagOptionsFunction;
    $tagOptions = $tagOptionsFunction($tagId);

    // Prepare a list of all possible pairs of FeatureInstances.
    $groupsOfInstancePairs =
      array_map(
		function($featureInstance2) use ($feature1) {
		  return array_map(
				   function($featureInstance1) use ($featureInstance2) {
				     return array($featureInstance1, $featureInstance2);
				   },
				   $feature1->instances);
		}, 
		$feature2->instances);

    // Compare all pairs of FeatureInstances.
    $featureComparator = $this;

    $groupsOfComparedInstancePairs = 
      array_map(
		function($featureInstancePairs) use ($featureComparator, $tagOptions) {
		  return array_map(
				   function($pair) use ($featureComparator, $tagOptions) { 
				     return $featureComparator->comparePairOfFeatureInstances($pair, $tagOptions); 
				   },
				   $featureInstancePairs);
		},
		$groupsOfInstancePairs);

    // Get the highest similarity (of two best fitting FeatureInstances) per group.
    foreach($groupsOfComparedInstancePairs as $groupOfComparedInstancePairs) {
      usort($groupOfComparedInstancePairs, function($r1, $r2) { return -cmp($r1['similarity'], $r2['similarity']); });
    }       

    $bestFitSimilarities = 
      array_map(function($group) { return array_first($group); }, $groupsOfComparedInstancePairs);

    $similarity = array_sum( 
			    array_map(
				      function($result) { return $result["similarity"]; }, 
				      $bestFitSimilarities)
			     );

    $result = array(
		    "header" => "Feature with tag $tagId : $similarity",
		    "algorithm" => "Best Fit For Each Target Feature Instance",
		    "featureInstancePairsCount" => count(array_flatten($groupsOfComparedInstancePairs)),
		    "similarity" => $similarity,
		    "bestFeatureInstancesPair" => $bestFitSimilarities + array("header" => "Best Pairs of Instances (ROIs)", "open" => true)
		    );
    
    if(count(array_flatten($groupsOfComparedInstancePairs)) > 1)
      $result += array("allFeatureInstancesPairs" => $groupsOfComparedInstancePairs + array("header" => "All groups of pairs of Feature Instances") );

    return $result;
  } 

}

class WeightedBestFitNotRepeatingFeatureInstanceFeatureComparator
extends WeightedBestFitFeatureComparator {
  
  public function compareFeatures(APFeature $feature1, APFeature $feature2) {
    $questionsFeatureInstanceComparator   = $this->questionsFeatureInstanceComparator;
    $comparisonsFeatureInstanceComparator = $this->comparisonsFeatureInstanceComparator;

    // Prepare Tag options.
    $tagId = $feature1->tagId;
    $tagOptionsFunction = $this->tagOptionsFunction;
    $tagOptions = $tagOptionsFunction($tagId);

    // Prepare a list of all possible pairs of FeatureInstances.
    $allFeatureInstancePairs =
      array_zip($feature1->instances, $feature2->instances);

    // Compare all pairs of FeatureInstances.
    $featureComparator = $this;

    $comparedPairs = 
      array_map(
		function($pair) use ($featureComparator, $tagOptions) { 
		  
		  $comparedPair =
		  array(
			'pair' => $pair, 
			'result' => $featureComparator->comparePairOfFeatureInstances($pair, $tagOptions)
			); 

		  /* 
		   * A "Compared Pair" contains: 
		   * 1. the pair (of Feature Instances) itself
		   * 2. the result of it's comparison.
		   */
		  
		  return $comparedPair;
		  
		},
		$allFeatureInstancePairs);
    
    // Sort all Compared Pairs by descending similarity.
    usort(
	  $comparedPairs, 
	  function($comparedPair1, $comparedPair2) { 
	    return -cmp(
			$comparedPair1['result']['similarity'], 
			$comparedPair2['result']['similarity']); 
	  });

    // Pick the best pairs without repeating.
    $currentComparedPairs = $comparedPairs;
    $bestComparedPairs    = array();
    
    while(count($currentComparedPairs) > 0) { // While there are any Compared Pairs left...

      /* 
       * The variant of this loop: count($currentComparedPairs)
       *
       * It's strictly diminishing, because with every run at least 
       * one pair get's filtered out - the pair that is currently the best.
       */

      /*
      // Little debug printing...
      echo "<br/>Current / best : " . count($currentComparedPairs) . " / " . count($bestComparedPairs);
      */

      // Take the best compared pair left.
      $bestComparedPair = array_first($currentComparedPairs);

      // Add it to the Best Pairs list.
      $bestComparedPairs[] = $bestComparedPair;

      // Filter out all Pairs which contain one of the Feature Instances form the currently chosen Best Pair.
      $currentComparedPairs = 
	array_filter(
		     function($comparedPair) use ($bestComparedPair) {
		       // Extract lists of ROI id's from both Pairs.
		       $comparedPairRoiIds = array_map(function($fi) { return $fi->roiId; }, $comparedPair['pair']);
		       $bestPairRoiIds     = array_map(function($fi) { return $fi->roiId; }, $bestComparedPair['pair']);
		       
		       // Compute the intersection.
		       $intersection       = array_intersect($comparedPairRoiIds, $bestPairRoiIds);
		       
		       /*
		       // Little debug printing...
		       echo "<br/>" . var_export($comparedPairRoiIds, True);
		       echo "<br/>" . var_export($bestPairRoiIds, True);
		       echo "<br/>Intersections: " . count($intersection);
		       */

		       // Find out if the two Pairs have any Feature Instances (ROIs) in common.
		       $doTheyHaveAnyROIsInCommon = ( count($intersection) > 0 );
		       
		       // A Pair stays in the list only if it has no Feature Instances
		       // in common with the current best Pair.
		       return (! $doTheyHaveAnyROIsInCommon);
		     },
		     $currentComparedPairs
		     );

    }

    assert(count($currentComparedPairs) == 0);

    // Process the Compared Pairs into the Results.
    $allResults = 
      array_map(function($comparedPair) { return $comparedPair['result']; }, $comparedPairs);

    $bestResults = 
      array_map(function($comparedPair) { return $comparedPair['result']; }, $bestComparedPairs);

    // Compute the total similarity - a sum of similarity of all best pairs.
    $similarity = array_sum(
			    array_map(
				      function($result) { return $result["similarity"]; }, 
				      $bestResults)
			    );

    $result = array(
		    "header" => "Feature with tag $tagId : $similarity",
		    "algorithm" => "Sum Best Pairs Without Repeating",
		    "featureInstancePairsCount" => count($allResults),
		    "similarity" => $similarity,
		    "bestFeatureInstancesPairs" => $bestResults + array("header" => "Best Pairs of Feature Instances (ROIs)", "open" => true)
		    );

    if(count($allResults) > 1)
      $result += array("allFeatureInstancesPairs" => $allResults + array("header" => "All Pairs of Feature Instances") );

    return $result;
  } 

}

// Models Comparator

interface ModelComparatorI {
  public function compareModels(APModel $model1, APModel $model2);
}

class ModelSimpleComparator
implements ModelComparatorI {

  public $featureComparator = NULL;

  public function compareModels(APModel $model1, APModel $model2) {
    $answer = array();
    
    foreach($model1->features as $featureId => $feature)
      if(isset($model1->features[$featureId]) && isset($model2->features[$featureId]))
	$answer[$featureId] = $this->featureComparator->compareFeatures($model1->features[$featureId], $model2->features[$featureId]);
 
    return $answer;
  }

}

class ModelSumComparator
implements ModelComparatorI {

  public $featureComparator = NULL;

  public function compareModels(APModel $model1, APModel $model2) {
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

    $similarity = array_sum($similarities);
 
    return array(
		 "header" => "Models Comparison : $similarity",
		 "similarity" => $similarity,
		 "featuresSimilarities" => $answer + array("header" => "Features Similarities", "open" => true)
		 );
  }

}

class MyComparator
implements ModelComparatorI {

  public $apComparator              = NULL;
  public $attributesComparator      = NULL;
  public $featureInstanceComparator = NULL;
  public $featureComparator         = NULL;
  public $modelComparator           = NULL;

  public function compareModels(APModel $model1, APModel $model2) {
    return $this->modelComparator->compareModels($model1, $model2);
  }

  function __construct($questionsOptions, $palette, $tagsOptions) {

    // Question Options Function
    $questionOptionsFunction = function($q) use ($questionsOptions, $palette) {

      $options = array();

      if(isset($questionsOptions[$q])) 
	$questionOptions = $questionsOptions[$q];
      else
	$questionOptions = $questionsOptions[0]; // 0 - default.
      
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
	  
	  if($v1 === $exception || $v2 === $exception) {
	    //echo "<h3>EXCEPTION $v1 $v2 $exception</h3>";
	    
	    if($handling["handling"] === "MaxDistance")
	      return 1;
	    
	    if($handling["handling"] === "ConstDistance")
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

    
    // Question Weight
    $qWeight = function($qId) use ($questionsOptions) {
      
      if(isset($questionsOptions[$qId]['weight']))
	return $questionsOptions[$qId]['weight'];
      else
	return 1;
      
    };


    // Tag Options Function
    $tagOptionsFunction = function($tagId) use ($tagsOptions) {

      $options = array();

      $defaultTagOptions =
      array(
	    'QuestionsWeight'   =>   1,
	    'ComparisonsWeight' => 100
	    );

      if(isset($tagsOptions[$tagId]))
	$tagOptions = $tagsOptions[$tagId];
      else
	$tagOptions = array();
      
      //echo "<h1>$tagId</h1><pre>" . var_export($tagOptions, true) . "</pre>";
      
      $options = array_merge($defaultTagOptions, $tagOptions);

      //echo "<pre>" . var_export($options, True) . "</pre>";

      return $options;
    };
    
    // Answers Patterns Comparator
    $apComparator = new AnswerPatternsComparator();
    $this->apComparator = $apComparator;

    // Attribute Comparator
    $attributesComparator = new AttributesComparator($apComparator, $questionOptionsFunction);
    $this->attributesComparator = $attributesComparator;

    // FeatureInstance (ROI) Comparator
    $featureInstanceComparator = new QuestionsWeightedSumFeatureInstanceComparator();
    $featureInstanceComparator->attributesComparator = $attributesComparator;

    // FeatureInstance (ROI) Comparator : Weight Function
    $featureInstanceComparator->weightFunction =
      function($q, $similarity) use ($qWeight) { 
      return $similarity * $qWeight($q);
    };

    $this->featureInstanceComparator = $featureInstanceComparator;
    
    // Feature Comparator
    //$featureComparator = new WeightedBestFitFeatureComparator();
    //$featureComparator = new WeightedBestFitForEachTargetFeatureInstanceFeatureComparator();
    $featureComparator = new WeightedBestFitNotRepeatingFeatureInstanceFeatureComparator();

    $featureComparator->questionsFeatureInstanceComparator = $featureInstanceComparator;
    $featureComparator->comparisonsFeatureInstanceComparator = new ComparisonsFeatureInstanceComparator();
    $featureComparator->tagOptionsFunction = $tagOptionsFunction;

    $this->featureComparator = $featureComparator;

    // Model Comparator
    $modelComparator = new ModelSumComparator();
    $modelComparator->featureComparator = $featureComparator;

    $this->modelComparator = $modelComparator;
  }

}

?>