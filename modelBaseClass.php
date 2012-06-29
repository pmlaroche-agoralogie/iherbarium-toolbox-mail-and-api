<?php
namespace iHerbarium;

require_once("myPhpLib.php");

/*
  The ModelBaseClass is designed to be a base class for all data models.
  
  
  It uses the PHP dynamic nature to attain two goals:
  
  + Introduce universal default getter and setter (accessor and mutator) 
  methods for all of class properties without of need to write or generate
  any boilerplate code.
  
  + Automatically pass through these getters and setters even when a given
  property is accessed directly.
  
  
  Effects on it's subclasses:

  + Each subclass public properties are accessed directly (without of 
  passing through getters and setters).
  [This is not a desired behavior, but we cannot do anything about it.]

  + Each subclass protected properties are always accessed through
  getters and setters, even if called directly.
  
  + For a class X with a property "abc" the getter method is called "X->getAbc()"
  and the setter is "X->setAbc(newValue)".

  + Accessing directly "X->foo" is in fact equal to calling "X->getFoo()"
  (in case of reading the property) or "X->setFoo" (in case of writing
  to the property).

  + In order to solve the problematic case of arrays*, an additional method
  has been added "X->addFoo(newValue)" (or "X->addFoo(newValue, index)")
  which adds the "newValue" to property "foos" of X (at given index).
  Of course the property "foos" should be an array.

  TO DO: why is it great, plural names.

  * Problematic case of arrays: TO DO

 */

class ModelBaseClass {
  
  public function __call($methodName, $args) {
    
    if(preg_match('/^(set|get|add)([A-Z])(.*)$/', $methodName, $matches)) {
      $methodType = $matches[1];
      $property = strtolower($matches[2]) . $matches[3];
      
      switch($methodType) {

      case 'get':
	return $this->getProperty($property);

      case 'set':
	$value = $args[0];
	return $this->setProperty($property, $value);

      case 'add':
	$pluralProperty = $this->pluralizeProperty($property);
	$value = $args[0];
	$key = isset($args[1]) ? $args[1] : NULL;	
	//echo "<h3>pp</h3><pre>" . var_export($pluralProperty, true). "</pre>";
	return $this->addToProperty($pluralProperty, $value, $key);

      default:
	throw new StandardMethodException("Standard method " . $methodName . " doesn't exist!");
      }
    }
    
  }

  public function getProperty($property) {

    // Throw exception if the property doesn't exist.
    if( (! isset($this->$property)) && (! property_exists($this, $property)) ) {
      throw new PropertyAccessException("Property " . $property . " doesn't exist!");
    }

    return $this->$property;
  }

  public function setProperty($property, $value) {
    $this->$property = $value;
    return $this;
  }

  public function addToProperty($property, $value, $key = NULL) {
    if( ! is_null($key) ) 
      array_push_associative($this->$property, $value, $key); 
    else 
      array_push($this->$property, $value);

    return $this;
  }

  static protected $pluralizePropertyNameArray =
    array(
	  'child' => 'children'
	  );

  public function pluralizeProperty($property) {
    if(isset(static::$pluralizePropertyNameArray[$property]))
      return static::$pluralizePropertyNameArray[$property];
    else
      return ($property . 's');
  }

  // This functions let us access the protected proprieties as public ones.
  // Used only for compatibility with old code which doesn't use getters and setters.
  public function __get($property) {
    $getterMethodName = 'get' . ucfirst($property);
    return $this->$getterMethodName();
  }

  public function __set($property, $value) {
    $setterMethodName = 'set' . ucfirst($property);
    return $this->$setterMethodName($value);
  }

  // Returns an associative array with object's properties and their values.
  public function toArrayOfProperties() {
    $arrayOfProperties = get_object_vars($this);
    return $arrayOfProperties;
  }

}

class PropertyAccessException extends \Exception {}
class StandardMethodException extends \Exception {}


// A function that converts anything into a tree (nested associative arrays).
function toArrayTree($sth) {

  // Scalar values (also NULLs) and Resources are not changed.
  if(is_null($sth) || is_scalar($sth) || is_resource($sth))
    return $sth;

  // Arrays are mapped recursively.
  if(is_array($sth))
    return
      array_map(
		function($el) { return toArrayTree($el); }, 
		$sth
		);

  // Objects are converted to arrays of properties and then mapped recursively.
  if(is_object($sth)) {

    // Convert the object to an array of properties.
    if(method_exists($sth, "toArrayOfProperties")) {
      // If it has the appropriate method, we use it.
      $arrayOfProperties = $sth->toArrayOfProperties();
    } else {
      // If not - we extract the properties by hand.
      $arrayOfProperties = get_object_vars($sth);
    }

    // Map the array recursively.
    return 
      array_map(
		function($el) { return toArrayTree($el); }, 
		$arrayOfProperties
		);
  }

  // Other types simply don't exist... But just in case we return NULL.
  assert(false);
  return NULL;
}

/*

class ExampleModel
extends ModelBaseClass {
  protected $pr1;
  protected $pr2 = NULL;
  protected $pr3 = "a";
  public $pu1;
  public $pu2 = NULL;
  public $pu3 = "b";

  protected $children = array();

  public function f() {}
}


echo "<h1>AAA</h1>";

$o = new ExampleModel();

echo "<h3>1:</h3><pre>" . var_export($o, true). "</pre>";

$o->addChild("x");
$o->addChild("y", "bonanza");
$o->addChild("yy", "bonanza");
$o->addChild("yyy", "bonanza");
$o->addChild("z");

echo "<h3>2:</h3><pre>" . var_export($o, true). "</pre>";

echo "</br>" . $o->children["bonanza"];


die();

echo "<hr/>";
echo "</br>pr: <pre>" . var_export($o->getPr1(), true). "</pre>";
echo "</br>pr: <pre>" . var_export($o->getPr2(), true). "</pre>";
echo "</br>pr: <pre>" . var_export($o->getPr3(), true). "</pre>";
//echo "</br>pr: <pre>" . var_export($o->getPr4(), true). "</pre>";
echo "</br>pu: <pre>" . var_export($o->getPu1(), true). "</pre>";
echo "</br>pu: <pre>" . var_export($o->getPu2(), true). "</pre>";
echo "</br>pu: <pre>" . var_export($o->getPu3(), true). "</pre>";
//echo "</br>pu: <pre>" . var_export($o->getPu4(), true). "</pre>";

echo "<hr/>";
echo "</br>pr: <pre>" . var_export($o->pr1, true). "</pre>";
echo "</br>pr: <pre>" . var_export($o->pr2, true). "</pre>";
echo "</br>pr: <pre>" . var_export($o->pr3, true). "</pre>";
//echo "</br>pr: <pre>" . var_export($o->pr4, true). "</pre>";
echo "</br>pu: <pre>" . var_export($o->pu1, true). "</pre>";
echo "</br>pu: <pre>" . var_export($o->pu2, true). "</pre>";
echo "</br>pu: <pre>" . var_export($o->pu3, true). "</pre>";
//echo "</br>pu: <pre>" . var_export($o->pu4, true). "</pre>";

$o
->setPr1("pr1")
->setPr2("pr2")
->setPr3("pr3")
->setPr4("pr4");

$o
->setPu1("pu1")
->setPu2("pu2")
->setPu3("pu3")
->setPu4("pu4");

echo "<hr/>";
echo "</br>pr: <pre>" . var_export($o->getPr1(), true). "</pre>";
echo "</br>pr: <pre>" . var_export($o->getPr2(), true). "</pre>";
echo "</br>pr: <pre>" . var_export($o->getPr3(), true). "</pre>";
echo "</br>pr: <pre>" . var_export($o->getPr4(), true). "</pre>";
echo "</br>pu: <pre>" . var_export($o->getPu1(), true). "</pre>";
echo "</br>pu: <pre>" . var_export($o->getPu2(), true). "</pre>";
echo "</br>pu: <pre>" . var_export($o->getPu3(), true). "</pre>";
echo "</br>pu: <pre>" . var_export($o->getPu4(), true). "</pre>";

*/

?>