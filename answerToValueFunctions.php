<?php
namespace iHerbarium;

require_once("myPhpLib.php");

class AnswerToValueFunctions {
  
  static public function wrap($wrappingFunction, $wrappedFunction) {
    // Wrap the "wrapped function" into the "wrapping function",
    // so the two values are first given to the outer one (wrapping function)
    // and only if it returns NULL, the inner one (wrapped function) is used.
  
    $atvFunction =
      function($a) use ($wrappingFunction, $wrappedFunction) {
      
      $firstAnswer = $wrappingFunction($a);

      if( ! is_null($firstAnswer) )
	return $firstAnswer;
      else {
	$secondAnswer = $wrappedFunction($a);
	return $secondAnswer;
      }

    };

    return $atvFunction;

  }

  static public function get($questionOptions) {

    // AnswerToValue
    switch($questionOptions['answerValueType']) {
      
    case 'Literal' : {
      // This Answer should be used as a Value literally.
      $atvFunction =
	AnswerToValueFunctions::literally();
      
      break;
    }

    case 'Translate' : {
      // This Answer should be converted to Value using the Translation Table.
      $translation =
	array_reduce($questionOptions['answerValueTranslation'], "array_replace", array() );
	
      //echo "<h1>$q</h1><pre>" . var_export($questionOptions, True) . "</pre>";
      //echo "<h1>$q</h1><pre>" . var_export($translation, True) . "</pre>";

      // Function converting the Answer to the Value
      $atvFunction =
	AnswerToValueFunctions::wrap(
				     AnswerToValueFunctions::translate($translation),
				     AnswerToValueFunctions::literally()
				     );
      break;
    }

    default : {

      // AnswerToValue function was not specified - use the default.
      $atvFunction =
	AnswerToValueFunctions::defaultAnswerToValue();
      
      break;
    }
      
    }

    return $atvFunction;
  }

  static public function defaultAnswerToValue() {
    
    // By default we use the Answer as Value literally.
    return static::literally();

  }
  
  static public function literally() {
    
    // Identity.
    $atvFunction=
      function($a) { return $a; };
    
    return $atvFunction;

  }

  static public function translate($translation) {

    // Translate the Answer using the given Translation Table.
    $atvFunction = 
      function($a) use ($translation) {

      // If Answer is in the Translation Table.
      if(isset($translation[$a])) {
	// Then we translate it.
	return $translation[$a];
      } else {
	// If not - we pass.
	return NULL;
      }
    };

    return $atvFunction;

  }

}