<?php
namespace iHerbarium;

require_once("myPhpLib.php");

class DistanceFunctions {
  
  static public function wrap($wrappingFunction, $wrappedFunction) {
    // Wrap the "wrapped function" into the "wrapping function",
    // so the two values are first given to the outer one (wrapping function)
    // and only if it returns NULL, the inner one (wrapped function) is used.
  
    $distanceFunction =
      function($v1, $v2) use ($wrappingFunction, $wrappedFunction) {
      
      $firstAnswer = $wrappingFunction($v1, $v2);

      if(! is_null($firstAnswer))
	return $firstAnswer;
      else {
	$secondAnswer = $wrappedFunction($v1, $v2);
	return $secondAnswer;
      }

    };

    return $distanceFunction;

  }

  static public function get($questionOptions, $palette) {

    switch($questionOptions['distanceFunction']) {
      
    case 'ProximityMatrix' : {
      $matrix = $questionOptions['proximityMatrix'];

      /* The distance function is defined by a Proximity Matrix,
       * falls back to a Discrete Distance if a given distance is
       * not defined in the matrix.
       */
      $distanceFunction =
	DistanceFunctions::wrap(
				DistanceFunctions::proximityMatrix($matrix),
				DistanceFunctions::discreteDistance()
				);

      break;
    }

    case 'ColorSimpleRGBDistance' : {
	
      // The distance function for colors, very simple 3 dimentional manhattan distance.
      $distanceFunction =
	DistanceFunctions::colorSimpleRGBDistance($palette);

      break;
    }

    case 'ColorCompuPhaseRGBDistance' : {
	
      /* An advenced distance function for colors, 
       * distance is defined using a low-cost
       * approximation algorithm from CompuPhase.
       */
      $distanceFunction =
	DistanceFunctions::colorCompuPhaseRGBDistance($palette);
      
      break;
    }
 
    case 'DiscreteDistance' : {

      // The simpliest distance function - reversed Kronecker's delta.
      $distanceFunction = 
	DistanceFunctions::discreteDistance();

      break;
    }

    default : {
      
      // The dstance function was not specified - use the default.
      $distanceFunction = 
	DistanceFunctions::defaultDistance();

      break;
    }

    }

    return $distanceFunction;

  }

  static public function defaultDistance() {
    
    // Default distance function is the discrete distance.
    return static::discreteDistance();
    
  }

  static public function discreteDistance() {
    
    // The simpliest distance function - reversed Kronecker's delta.
    $distanceFunction =
      function($v1, $v2) {
      return ( (strval($v1) === strval($v2)) ? 0 : 1 );
    };

    return $distanceFunction;

  }

  static public function proximityMatrix($matrix) {
    
    // The distance function is defined by a Proximity Matrix.
    $distanceFunction = 
      function($v1, $v2) use ($matrix) {

      if(isset($matrix[$v1][$v2])) {

	// If it is in the matrix.
	$dist = $matrix[$v1][$v2];
	return $dist;
 
      } else if(isset($matrix[$v2][$v1])) {

	// Or it's reverse is in the matrix.
	$dist = $matrix[$v2][$v1];
	return $dist;

      } else {

	// If not.
	return NULL;

      }

    };

    return $distanceFunction;

  }

  static public function colorSimpleRGBDistance($palette) {

    // A basic distance function for colors, very simple 3 dimentional manhattan distance.
    $distanceFunction =
      function($v1, $v2) use ($palette) {
      $colorKeys = array('R' => 'R', 'G' => 'G', 'B' => 'B');
      $rgb1 = $palette[$v1];
      $rgb2 = $palette[$v2];

      // Compute colors deltas.
      $deltas =
      array_map(
		function($colorKey) use ($rgb1, $rgb2) {
		  // Simple Manhattan distance.
		  return ( abs( $rgb1[$colorKey] - $rgb2[$colorKey] ) / 255 ); 
		},
		$colorKeys);

      $colorsDistance = array_sum($deltas) / 3;
	  
      //echo "<pre>" . var_export($componentsDifference, True) . "</pre>";
      //echo "[ Dist($v1, $v2) = $colorsDistance ] ";

      return $colorsDistance;
    };

    return $distanceFunction;

  }

  static public function colorCompuPhaseRGBDistance($palette) {
    
    // An advanced distance function for colors, taken from CompuPhase.
    $distanceFunction = 
	  
      function($v1, $v2) use ($palette) {
      $colorKeys = array('R' => 'R', 'G' => 'G', 'B' => 'B');
      $rgb1 = $palette[$v1];
      $rgb2 = $palette[$v2];
	  
      // Average of Red.
      $rmean = ( $rgb1['R'] + $rgb2['R'] ) / 2;	  
	  
      // Compute colors deltas.
      $delta =
      array_map(
		function($colorKey) use ($rgb1, $rgb2) {
		  return $rgb1[$colorKey] - $rgb2[$colorKey]; 
		},
		$colorKeys);
	  
      // Compute total color distance using the CompuPhase formula.
      $distance =
      sqrt(
	   ( (2 + ( ($rmean      ) / 256) ) * pow($delta['R'], 2) ) +
	   (                              4 * pow($delta['G'], 2) ) +
	   ( (2 + ( (255 - $rmean) / 256) ) * pow($delta['B'], 2) )
	   );
	  
      // Maximum distance (approximation - distance between Black and White).
      $max = 675;

      // Normalize the distance to be between 0 and 1.
      $normalizedDistance = 
      ($distance <= $max) ? ($distance / $max) : 1;

      // DEBUG
      //echo " rmean=$rmean ";
      //echo "<pre>" . var_export($delta, True) . "</pre>";	  
      //echo "[ Dist($v1, $v2) = ($distance) $normalizedDistance ] <br/>";

      return $normalizedDistance;
    };

    return $distanceFunction;

  }


}

?>