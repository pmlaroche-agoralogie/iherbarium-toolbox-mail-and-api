<?php
namespace iHerbarium;

require_once("myPhpLib.php");
require_once("dbConnection.php");
require_once("typoherbariumModel.php");

// Local Storage
require_once("persistentLocalStorage.php");

// Remote Storage
require_once("persistentRemote.php");

// Local Typoherbarium
require_once("persistentLocalTypoherbarium.php");


// PersistentObject with Database scaffolding.
abstract class PersistentObjectDB 
extends Singleton {

  protected function me() { return get_called_class(); }

  // Database selection.
  abstract protected function dbConnectionId(); // TO OVERRIDE IN SUBCLASSES!
  
  // Database connection (by DBConnection abstract layer and MDB2).
  protected $db = NULL;

  final protected function dbConnect() {
    assert( is_null($this->db) );
    $this->debug("Debug", "Getting the local database connection...");
    $this->db =& DBConnection::get($this->dbConnectionId());
  }

  final protected function dbDisconnect() {
    assert($this->db);
    $this->debug("Debug", "Disconnecting from the database...");
    $this->db->disconnect();
  }

  protected function __construct() {
    $this->dbConnect();
  }

  function __destruct() {}

  // Shortcuts...
  final public function query($sqlQuery)         { return $this->db->query($sqlQuery);         }
  final public function exec($sqlQuery)          { return $this->db->exec($sqlQuery);          }
  final public function quote($string)           { return $this->db->quote($string);           }
  final public function lastInsertID($tab, $col) { return $this->db->lastInsertID($tab, $col); }
  final public function numrows($result)         { return $this->db->numrows($result);         }

  final public function debug($type, $string, $description = "") {
    debug($type, $this->me(), $string, $description);
  }
  
  // Functional style fetching functions.

  public function singleResult($query, $ifExists, $ifDoesntExist) {
    
    // Execute the query and obtain the result.
    $result = $this->query($query);
      
    // Sanity check - single result or nothing expected!
    assert( ! is_null($result) );
    assert($this->numrows($result) <= 1);
    
    // Did we get an answer?
    if( ($row = $result->fetchRow()) ) {  
      // We fetched the row!
      return $ifExists($row);
    }
    else {
      // There is no row.
      return $ifDoesntExist();
    }
    
  }
  
  public function iterResults($query, $forEachRow) {
    
    // Execute the query and obtain the result.
    $result = $this->query($query);
    
    // Sanity check.
    assert( ! is_null($result) );

    // For each row...
    while( ($row = $result->fetchRow()) ) {
      // We fetched the row!
      $forEachRow($row);
    }
    
  }

  public function mapResults($query, $forEachRow) {
    
    $results = array();

    $this->iterResults($query, function($row) use ($forEachRow, &$results) {
	$result = $forEachRow($row);
	
	if( ( ! is_null($result) ) && (isset($result->id)) )
	  $results[$result->id] = $result;
	else
	  $results[] = $result;

      });
    
    return $results;

  }


  // Filesystem operations.

  public function deleteFile($filePath) {
    debug("Begin", "LocalFilesystem", "Deleting: " . $filePath . " ...");

    if(! file_exists($filePath) ) {
      debug("Error", "LocalFilesystem", "File " . $filePath . " doesn't exist!");
      return;
    }

    if(! is_writable($filePath) ) {
      debug("Error", "LocalFilesystem", "File " . $filePath . " is not writable!");
      return;
    }
		   
    $unlink = unlink($filePath);

    if($unlink)
      debug("Ok", "LocalFilesystem", "File " . $filePath . " deleted!");
    else
      debug("Error", "LocalFilesystem", "Deleting file " . $filePath . " failed!");
  }  

}

?>