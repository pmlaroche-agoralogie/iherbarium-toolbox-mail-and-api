<?php
namespace iHerbarium;

require_once("myPhpLib.php");

require_once("MDB2.php");

require_once("db.config.php");

interface DBConnectionInterface {
  static public function get($dbId);

  public function query($sqlQuery);
  public function exec($sqlQuery);
  public function quote($string);
  public function lastInsertID($table, $key);
}

class DBConnection {
  // STATIC

  // DBConnection instances
  static private $dbs = array();

  // DSNs (Data Source Name) for available DB connections.
  // Array's format: DB identifier => DSN
  static private function dsns() {

    $dbConfig = getDbConfig();

    return array(
	  // Local storage MySQL Database.
	  /* Tables:
	   * + User
	   * + Photo
	   * + Observation
	   * + ContentTemplate
	   * + MailboxMail
	   */

	  "LocalStorageDevelopment" =>
	  array(
		"phptype"  => "mysql",
		"username" => $dbConfig["USER_TEST"],
		"password" => $dbConfig["PWD_TEST"],
		"hostspec" => "localhost",
		"database" => $dbConfig["DATABASE_LOCAL_DEV"],
		"charset"  => "utf8"
		),

	  "LocalStorage" =>
	  array(
		"phptype"  => "mysql",
		"username" => $dbConfig["USER_TEST"],
		"password" => $dbConfig["PWD_TEST"],
		"hostspec" => "localhost",
		"database" => $dbConfig["DATABASE_LOCAL"],
		"charset"  => "utf8"
		),
	  
	  // Local connection to typoherbarium MySQL Database
	  // on Production server.
	  "LocalTypoherbariumProduction" =>
	  array(
		"phptype"  => "mysql",
		"username" => $dbConfig["USER_PROD"],
		"password" => $dbConfig["PWD_PROD"],
		"hostspec" => "localhost",
		"database" => $dbConfig["DATABASE_PROD"]
		),

	  // Local development copy of herbarium MySQL Database
	  // for Observation Receiver.
	  "LocalTypoherbariumDevelopment" =>
	  array(
		"phptype"  => "mysql",
		"username" => $dbConfig["USER_TEST"],
		"password" => $dbConfig["PWD_TEST"],
		"hostspec" => "localhost",
		"database" => $dbConfig["DATABASE_PROD"]
		),

	  // Local development copy of typoherbarium MySQL Database 
	  // for Balade.
	  "LocalTypoherbariumBaladeTest" =>
	  array(
		"phptype"  => "mysql",
		"username" => $dbConfig["USER_TEST"],
		"password" => $dbConfig["PWD_TEST"],
		"hostspec" => "localhost",
		"database" => $dbConfig["DATABASE_LOCAL"]
		),

	  // Remote connection to typoherbarium MySQL Database 
	  // on application server.
	  // NOT USED!!!
	  "RemoteTypoherbarium" =>
	  array(
		"phptype"  => "mysql",
		"username" => "NA",
		"password" => "NA",
		"hostspec" => "NA",
		"database" => "NA"
		)

	  );

  }
  
  // Get DBConnection with given dbId
  static public function get($dbId) {

    $dsns = self::dsns();

    // Sanity check.
    assert( array_key_exists($dbId, $dsns) );

    // Multi singleton implementation.
    if(! isset(self::$dbs[$dbId]) ) {
      // Get the DSN (Data Source Name).
      $dsn = $dsns[$dbId];
      
      // Prepare and initialize a new DBConnection.
      $db = new self();
      $db->myDbId = $dbId;
      $db->dbConnect($dsn);

      // Save it.
      self::$dbs[$dbId] =& $db;
    }

    return self::$dbs[$dbId];
  }



  // INSTANCE

  private function me() { return (get_called_class() . "[" . $this->myDbId . "]"); }

  private function debug($type, $string, $description = "") {
    debug($type, $this->me(), $string, $description);
  }

  private $myDbId = NULL;

  private $db = NULL;

  private function __construct() {
    //$this->dbConnect(); // No! On "new" we don't have the dsn parameter yet!
  }

  function __destruct() {
    $this->dbDisconnect();
  }

  private function dbConnect($dsn) {
    assert( is_null($this->db) );
    
    $this->debug("Begin", "Connecting to the database...");

    // Attention with printing! It contains database password!
    //$this->debug("Debug", "Data Source Name: <pre>" . var_export($dsn, True) . "</pre>");
    
    // Connect.
    $mdb2 =& \MDB2::connect($dsn);
    if (\PEAR::isError($mdb2)) {
      $this->debug("Error", "Connection failed!", $mdb2->getMessage());
      die();
    }

    // Connected!
    $this->debug("Ok", "Connection successful!");

    // Set options.

    // Option 1: Return rows as objects (stdObj).
    $mdb2->setFetchMode(MDB2_FETCHMODE_OBJECT);

    // Option 2: Don't convert empty strings to NULL.
    $mdb2->setOption(
		     'portability',
		     MDB2_PORTABILITY_ALL ^ MDB2_PORTABILITY_EMPTY_TO_NULL
		     );
    
    // Save connection.
    $this->db =& $mdb2;
  }

  private function dbDisconnect() {
    if($this->db) {
      // Disconnect.
      $this->debug("Begin", "Disconnecting from the database...");
      $this->db->disconnect();

      // Disconnected!
      $this->debug("Ok", "Disconnected!");

      // Remove this connection from saved DB connections.
      unset(self::$dbs[$this->myDbId]);
    }
  }

  public function query($sqlQuery) {
    $this->debug("Debug", "Query", $sqlQuery);

    $result =& $this->db->query($sqlQuery);

    // Always check that result is not an error
    if (\PEAR::isError($result)) {
      $this->debug("Error", $result->getMessage(), $sqlQuery);
      die();
      return NULL;
    }
    else {
      return $result;
    }
  }

  public function exec($sqlQuery) {
    //$this->debug("Debug", "Exec", $sqlQuery);

    $affected =& $this->db->exec($sqlQuery);

    // Always check that result is not an error
    if (\PEAR::isError($affected)) {
      $this->debug("Error", $affected->getMessage(), $sqlQuery);
      die();
      return 0;
    }
    else {
      return $affected;
    }
  }
  
  public function quote($string) {
    return $this->db->quote($string);
  }
  
  public function lastInsertID($table, $key) {
    $this->debug("Debug", "lastInsertID", "($table : $key)");

    $id = $this->db->lastInsertID($table, $key);
   
    if (\PEAR::isError($id)) {
      $this->debug("Error", $id->getMessage(), $sqlQuery);
      die();
      return -1;
    }

    return $id;
  }

  public function numrows($result) {
    //$this->debug("Debug", "Numrows");

    $numrows =& $result->numrows();

    // Always check that result is not an error
    if (\PEAR::isError($numrows)) {
      $this->debug("Error", $numrows->getMessage());
      die();
      return 0;
    }
    else {
      return $numrows;
    }
  }
  
}

?>