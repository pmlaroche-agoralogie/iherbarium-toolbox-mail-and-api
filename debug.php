<?php
namespace iHerbarium;

require_once("myPhpLib.php");
require_once("dbConnection.php");

class Debug {

  static protected $myDebugger = NULL;

  public $echoDebug = True;

  private $whoIsRunning = NULL;
  private $runId = NULL;

  function __construct($whoIsRunning, $echoDebug = True) {
    $this->whoIsRunning = $whoIsRunning;
    $this->runId = time();

    $this->echoDebug = $echoDebug;
    
    // Beginning of HTML document
    if($this->echoDebug) echo "<html><body>\n";
  }

  function __destruct() {
    // End of HTML document
    if($this->echoDebug) echo "\n</html></body>";
  }

  public function format($type, $who, $what, $details = "") {
    return
      $type . 
      "(" . $who . 
      "): " . $what . 
      ($details ? ("</div>\n" . $details . "\n<div>") : "") .
      "\n<br/>\n";
  }


  public function myDebug($type, $who, $what, $details = "") {
    if(! $this->echoDebug) return;
    
    switch($type) {
    case 'Begin' :
      echo "<div>" . $this->format($type, $who, $what, $details) . "</div>";      break;

    case 'Ok'    :
      //echo "<div style='color: green;>" . $this->format($type, $who, $what, $details) . "</div>";
      echo "<div style='color: green;'>" . $this->format($type, $who, $what, $details) . "</div>";
      break;

    case 'Error' :
      echo "<div style='color: red;'>" . $this->format($type, $who, $what, $details) . "</div>";
      break;
      
    case 'Debug' :
    default      :
      echo "<div>" . $this->format("Debug", $who, $what, $details) . "</div>";
      break;

    }

  }

  static public function init($whoIsRunning, $echoDebug = True) {
    self::$myDebugger = new Debug($whoIsRunning, $echoDebug);
  }

  static public function end() {
    
  }

  static public function debug($type, $who, $what, $details = "") {
    if(! self::$myDebugger)
      self::init();

    self::$myDebugger->myDebug($type, $who, $what, $details);
  }

  
}

function debug($type, $who, $what, $details = "") {
  Debug::debug($type, $who, $what, $details);
};

?>