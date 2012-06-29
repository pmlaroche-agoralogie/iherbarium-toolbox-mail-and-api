<?php
namespace iHerbarium;

//echo ENC7BIT . ENC8BIT . ENCBINARY . ENCBASE64 . ENCQUOTEDPRINTABLE . ENCOTHER;

function mkString($arrayOfStrings, $start, $sep, $end) {
  return $start . implode($sep, $arrayOfStrings) . $end;
}

function array_mapi($callback, $theArray) {
  $result = array();
  foreach($theArray as $key => $value) {
    $result[$key] = $callback($key, $value);
  }
  return $result;
}

function array_iteri($callback, $theArray) {
  foreach($theArray as $key => $value) {
    $callback($key, $value);
  }
}

function array_first($array) {
  $values = array_values($array);
  return $values[0];
}

function array_flatten($arrayOfArrays) {
  $flattenedArray = array();
  foreach($arrayOfArrays as $arr) {
    $flattenedArray = array_merge($flattenedArray, $arr);
  }
  return $flattenedArray;
}

function array_iter($callback, $theArray) {
  foreach($theArray as $el) {
    call_user_func($callback, $el);
  }
}

function array_filter($callback, $theArray) {
  return \array_filter($theArray, $callback);
}

function array_filteri($callback, $theArray) {
  $result = array();
  foreach($theArray as $key => $value) {
    if( $callback($key, $value) )
      $result[$key] = $value;     
  }
  return $result;
}

function array_all($callback, $theArray) {
  foreach($theArray as $el) {
    if ( !( call_user_func($callback, $el) ) ) return false;
  }
  return true;
}

function array_any($callback, $theArray) {
  foreach($theArray as $el) {
    if ( call_user_func($callback, $el) ) return true;
  }
  return false;
}

function array_single($callback, $theArray) {
  foreach($theArray as $el) {
    if ( call_user_func($callback, $el) ) return $el;
  }
  return NULL;
}

function array_zip($array1, $array2) {

  return
        array_flatten(
		  
		  array_map(
			    function($el1) use ($array2) {
			      return
				array_map(
					  function($el2) use ($el1) {
					    //return var_export($el1, True) . " " . var_export($el2, True);
					    $result = array($el1, $el2);
					    return $result;
					    //return call_user_func_array($callback, array($el1, $el2));
					  }, 
					  $array2
					  );
			    },
			    $array1)
    
	  );

}

function cmp($v1, $v2) {
  if($v1 == $v2) {
    return 0;
  } else {
    if($v1 < $v2)
      return -1;
    else
      return  1;
  }
}

function array_push_associative(&$array, $value, $key) {
  $array[$key] = $value;
}

function ifNull($value, $ifNull) { 
  return is_null($value) ? $ifNull : $value; 
}

function filenamesFromDir($dir) {

  // Check.
  if(! file_exists($dir) ) return array();
  
  // Directory handle.
  $dh  = opendir($dir);

  // All filenames (hopefully in order of creation).
  $allFilenames = array();
  while (false !== ($filename = readdir($dh))) {
    $allFilenames[] = $filename;
  }
	
  // All good filenames (with '.' and '..' filtered out).
  $goodFilenames = 
    array_filter(
		 function($filename) { 
		   return (! in_array($filename, array('.', '..'))); 
		 }, 
		 $allFilenames
		 );
	
  return $goodFilenames;
}

function deleteDirWithFiles($dir) {

  // Check.
  if(! file_exists($dir) ) return;
  if(! is_writable($dir) ) return;
  
  // All filenames.
  $allFilenames = scandir($dir);
  
  // All good filenames (with '.' and '..' filtered out).
  $filenames = 
    array_filter(
		 function($filename) { 
		   return (! in_array($filename, array('.', '..'))); 
		 }, 
		 $allFilenames
		 );
  
  // Delete files.
  array_iter('unlink', 
	     array_map(
		       function($filename) use ($dir) {
			 return ($dir . $filename);
		       },
		       $filenames)
	     );

  // Delete directory.
  rmdir($dir);
}


abstract class Singleton {
 
  // Singleton instance of this class. // WE HAVE TO OVERRIDE IT IN EVERY SUBCLASS...
  protected static $instance = NULL;

  static protected function newSelf() { return new static(); }

  final static public function get() {
    // Singleton implementation.
    if( is_null(static::$instance) ) {
      static::$instance = static::newSelf();
    }
    
    /*
    debug("Debug", "Singleton", 
	  "The called class : " . get_called_class() .
	  " returned class : " . get_class(static::$instance));
    */

    return static::$instance;
  }


  // Protected constructors to forbid "new".
  protected function __construct() {}
  protected function __clone() {}

}


// Fill object with given stdClass object.
function fillFromStdObj($obj, $stdObj) {
    $vars = get_object_vars($stdObj);
    foreach($vars as $var => $val) {
      $obj->$var = $val;
    }
    return $obj;
}


// Some HTML Templates

function viewAsOptionFunction($valueFieldFun, $textFieldFun) {
  
  $viewFunction =
    function($row) use ($valueFieldFun, $textFieldFun) {
    return '<option value="' . $valueFieldFun($row) . '">' . $textFieldFun($row) . '</option>';
  };
  
  return $viewFunction;
  
}

function viewArrayAsSelect($selectId, $valueFieldFun, $textFieldFun, $array) {
  $lines = array();
  
  // Display Rows as Select choice.
  
  // Beginning
  $lines[] = '<select id="' . $selectId . '" name="' . $selectId . '">';
  
  // Options
  $viewAsOptionFunction = viewAsOptionFunction($valueFieldFun, $textFieldFun);

  $options = array_map($viewAsOptionFunction, $array);
  
  $lines[] = implode("\n", $options);
  
  // End
  $lines[] = '</select>';
  
  $content = implode("\n", $lines);

  return $content;
}

function extractArrayFieldFunction ($fieldName) { 
  return function($array) use ($fieldName) { return $array[$fieldName]; };
}

function extractObjectFieldFunction ($fieldName) { 
  return function($object) use ($fieldName) { return $object->$fieldName; };
}

?>