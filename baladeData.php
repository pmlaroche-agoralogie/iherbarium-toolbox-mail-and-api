<?php // by Dany (with help of Kuba)
namespace iHerbarium;

require_once("adapteHeures.php");
require_once("adapteDistance.php");

require_once("myPhpLib.php");
require_once("debug.php");
require_once("config.php");

require_once("typoherbariumModel.php");
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
  public $nomphoto = NULL;
  public $nomdeposant = NULL;
  public $cas = NULL;
  public $grandephoto = NULL;
  public $distance = NULL;
  public $allPhotos = array();
  

  static public function fromRowWithCas($row, $cas) {
    assert( $cas == 'lesmiennes' || $cas == 'tous' );
  
    // Extract latitude and longitude from GET request.
    $lat = 0;
    $long= 0;
    if(isset($_GET['lat']))  $lat  = $_GET['lat'];
    if(isset($_GET['long'])) $long = $_GET['long'];
  
    // Fill Plante.
    $plante = new self();
    $plante->nom         =  utf8_encode($row->nom_commun);
    $plante->latitude    = $row->latitude;
    $plante->longitude   = $row->longitude;
    $plante->id_user     = $row->id_user;
    $plante->heure       = $row->deposit_timestamp;
    $plante->tsdepot     = adaptHeures($row->deposit_timestamp);
    $plante->nomphoto    = 'http://www.iherbarium.org/medias/vignettes/' . $row->nom_photo_final;
    $plante->nomdeposant = 'par ' .$row->name;
    $plante->cas         = $cas;
    $plante->grandephoto = 'http://www.iherbarium.fr/medias/big/'. $row->nom_photo_final;
    $plante->distance    = adaptDistance($lat, $long, $row->latitude, $row->longitude);
    $plante->allPhotos   = array();
    
    return $plante;
  }

}

class BaladePlantePhoto {
  public $vignetteURL = NULL;
  public $grandeURL   = NULL;

  static public function fromRow($row) {
    $filename = $row->nom_photo_final;

    $photo = new self();
    $photo->vignetteURL  = 'http://www.iherbarium.org/medias/vignettes/' . $filename;
    $photo->grandeURL    = 'http://www.iherbarium.fr/medias/big/'        . $filename;

    return $photo;
  }
}

function me() { return "baladeData"; };

/* Request */

$id_user = NULL;
if(isset($_GET['id_user'])) {
  $id_user = $_GET['id_user'];
}

/* DB Connection */

$dbName = Config::get("baladeTypoherbariumDatabase");
$db = dbConnection::get($dbName);

/* Prepare the Answer. */

$answer = new BaladeAnswer();

if($id_user) {
  
  // Fetch User's Observations.
  
  $querySelectUserObservations = 
    "SELECT DISTINCT(idobs) AS idobs, latitude, longitude, nom_commun, nom_photo_final, iherba_observations.id_user, iherba_observations.deposit_timestamp, name" .
    " FROM `iherba_observations`, iherba_determination, iherba_photos, fe_users" .
    " WHERE iherba_determination.id_obs = iherba_observations.idobs" . 
    " AND iherba_determination.id_obs = iherba_photos.id_obs" .
    //" AND latitude != 0" . 
    " AND iherba_observations.id_user = fe_users.uid" .
    " AND iherba_observations.id_user = " . $db->quote($id_user) .
    " GROUP BY idobs" .
    " ORDER BY iherba_observations.deposit_timestamp DESC";

  $result = $db->query($querySelectUserObservations);

  while( $row = $result->fetchRow() ) {
    $plante = BaladePlante::fromRowWithCas($row, 'lesmiennes');

    // Attach all photos.
    $queryAllPhotos =
      "SELECT nom_photo_final " .
      "FROM iherba_photos " .
      "WHERE id_obs = " . $db->quote($row->idobs);
    
    $allPhotosResult = $db->query($queryAllPhotos);
    
    while( $photoRow = $allPhotosResult->fetchRow() ) {
      $photo = BaladePlantePhoto::fromRow($photoRow);
      $plante->allPhotos[] = $photo;
    }

    // Add Plante to the answer.
    $answer->plante[] = $plante;
  }

}
  
// Fetch all Observations.

$querySelectAllObservations = 
  "SELECT DISTINCT(idobs) AS idobs, latitude, longitude, nom_commun, nom_photo_final, iherba_observations.id_user, iherba_observations.deposit_timestamp, name" .
  " FROM `iherba_observations`, iherba_determination, iherba_photos, fe_users" .
  " WHERE iherba_determination.id_obs = iherba_observations.idobs" . 
  " AND iherba_determination.id_obs = iherba_photos.id_obs" .
  " AND iherba_determination.nom_commun != ''" .
  " AND iherba_determination.nom_commun IS NOT NULL" .
  //" AND latitude != 0" . 
  " AND iherba_observations.id_user = fe_users.uid" .
  " GROUP BY idobs" .
  " ORDER BY iherba_observations.deposit_timestamp DESC" .
  " LIMIT 50";



$result = $db->query($querySelectAllObservations);

while( $row = $result->fetchRow() ) {
  
  $plante = BaladePlante::fromRowWithCas($row, 'tous');

  // Attach all photos.
  $queryAllPhotos =
    "SELECT nom_photo_final " .
    "FROM iherba_photos " .
    "WHERE id_obs = " . $db->quote($row->idobs);
    
  $allPhotosResult = $db->query($queryAllPhotos);
    
  while( $photoRow = $allPhotosResult->fetchRow() ) {
  
    $photo = BaladePlantePhoto::fromRow($photoRow);
    
    $plante->allPhotos[] = $photo;
  }

  // Add Plante to the answer.
  
  $answer->plante[] = $plante;
}
          
  
// Return the Answer.

 
echo (json_encode($answer));  
