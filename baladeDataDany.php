<?php
namespace iHerbarium;

include("adapteHeures.php");

require_once("myPhpLib.php");
require_once("debug.php");
require_once("config.php");

require_once("transferableModel.php");
require_once("dbConnection.php");

Debug::init("observationReceive", False);
//Config::init("Development");
//Config::init("Production");


class BaladeAnswer {
  public $plante = array(); // List of BaladePlante
}

class BaladePlante {
  public $nom = NULL;
  public $latitude = NULL;
  public $longitude = NULL;
  public $id_user = NULL;
  public $tsdepot = NULL;
  public $ilya = NULL;
  public $nomphoto = NULL;
  public $nomdeposant = NULL;
  public $cas = NULL;
  public $grandephoto = NULL;

  static public function fromRowWithCas($row, $cas) {
    assert( $cas == 'lesmiennes' || $cas == 'tous' );

    $plante = new self();
    $plante->nom         = (isset($row->nom_commun) ? $row->nom_commun : "");
    $plante->latitude    = $row->latitude;
    $plante->longitude   = $row->longitude;
    $plante->id_user     = $row->id_user;
    $plante->tsdepot     = $row->date_depot;
    $plante->ilya        = adaptHeures($row->date_depot);
    $plante->nomphoto    = 'http://www.iherbarium.org/medias/vignettes/' . $row->nom_photo_final;
    $plante->nomdeposant = $row->name;
    $plante->cas         = $cas;
    $plante->grandephoto = 'http://www.iherbarium.fr/medias/big/'. $row->nom_photo_final;
    
    return $plante;
  }

}

class BaladeRequest {
  public $id_user = NULL;
  public $geoloc  = NULL;

  static public function fromPOSTinJSON() {

    // Remember POST data.
    $data = (var_export($_POST, true));
    file_put_contents(Config::get("lastPostRequestFile"), $data);

    // Get the JSON encoded request from POST data.
    $request = json_decode($_POST['request']);

    // Request is a stdObject with maybe id_user and maybe a geolocation.
    assert(isset($request->id_user));


    // Extract info from Request
    
    // id_user
    $id_user = NULL;
    if(isset($request->id_user)) {
      $id_user = $request->id_user;
    }
    
    
    // geoloc
    $geoloc = NULL;
    if(isset($request->geoloc)) {
      $geoloc = $request->geoloc;
      assert(isset($geoloc->latitude));
      assert(isset($geoloc->longitude));
    }


    // Prepare the BaladeRequest.
    $baladeRequest = new self();    
    $baladeRequest->id_user = $id_user;
    $baladeRequest->geoloc = $geoloc;
    
    // Done.
    return $baladeRequest;
  }

  static public function fromGET() {
    // Extract info from Request
    
    // id_user
    $id_user = NULL;
    if(isset($_GET['id_user'])) {
      $id_user = $_GET['id_user'];
    }

    // geoloc
    $geoloc = NULL;
    /*
    if(isset($request['geoloc'])) {
      $geoloc = $request['geoloc'];
    }
    */

    // Prepare the BaladeRequest.
    $baladeRequest = new self();    
    $baladeRequest->id_user = $id_user;
    $baladeRequest->geoloc = $geoloc;

    // Done.
    return $baladeRequest;
  }
}

function me() { return "baladeData"; };

/* Request */

$request = BaladeRequest::fromGET();

/* DB Connection */

$dbName = Config::get("baladeTypoherbariumDatabase");
$db = dbConnection::get($dbName);

/* Get the Answer. */

$answer = new BaladeAnswer();

if($request->id_user) {
  
  // Fetch User's Observations.
  
  $querySelectUserObservations = 
    "SELECT DISTINCT(idobs) AS idobs, latitude, longitude, nom_commun, nom_photo_final, iherba_observations.id_user, iherba_observations.date_depot, name" .
    " FROM `iherba_observations`, iherba_determination, iherba_photos, fe_users" .
    " WHERE iherba_determination.id_obs = iherba_observations.idobs" . 
    " AND iherba_determination.id_obs = iherba_photos.id_obs" .
    //" AND nom_commun != ''" .
    //" AND latitude != 0" . 
    " AND iherba_observations.id_user = fe_users.uid" .
    " AND iherba_observations.id_user = " . $db->quote($request->id_user) .
    " GROUP BY idobs" .
    " ORDER BY iherba_observations.date_depot DESC";

  $result = $db->query($querySelectUserObservations);

  while( $row = $result->fetchRow() ) {
    $plante = BaladePlante::fromRowWithCas($row, 'lesmiennes');
    $answer->plante[] = $plante;
  }

}
  
// Fetch all Observations.

$querySelectAllObservations = 
  "SELECT DISTINCT(idobs) AS idobs, latitude, longitude, nom_commun, nom_photo_final, iherba_observations.id_user, iherba_observations.date_depot, name" .
  " FROM `iherba_observations`, iherba_determination, iherba_photos, fe_users" .
  " WHERE iherba_determination.id_obs = iherba_observations.idobs" . 
  " AND iherba_determination.id_obs = iherba_photos.id_obs" .
  //" AND nom_commun != ''" .
  //" AND latitude != 0" . 
  " AND iherba_observations.id_user = fe_users.uid" .
  " GROUP BY idobs" .
  " ORDER BY iherba_observations.date_depot DESC" .
  " LIMIT 10";


$result = $db->query($querySelectAllObservations);

while( $row = $result->fetchRow() ) {
  $plante = BaladePlante::fromRowWithCas($row, 'tous');
  $answer->plante[] = $plante;
}
          
  
// Return the Answer.

echo (json_encode($answer));  

/*
} else {
  //  debug("Error", me(), "No POST data!");
  debug("Error", me(), "No GET data!");
}
*/