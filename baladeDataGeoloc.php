<?php // by Dany (with help of Kuba)
namespace iHerbarium;
require_once("distance.php");
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

// centered on pere Lachaise if no parameter
$latitude = 48.8894;
$longitude = 2.3924;

if(isset($_POST['lat'])) {
  $latitude = $_POST['lat'];
}
else {
  if(isset($_GET['lat'])) {
      $latitude = $_GET['lat'];
    }
}

if(isset($_POST['long'])) {
  $longitude = $_POST['long'];
}
else{
  if(isset($_GET['long'])) {
    $latitude = $_GET['long'];
  }
}
$latitude = str_replace(",",".",$latitude);
$longitude = str_replace(",",".",$longitude);

//sql injection protection
if(!is_numeric($latitude) || !is_numeric($longitude))die();

if(($latitude==0) &&($longitude==0)){$latitude = 48.8894; $longitude = 2.3924;} //no gps passed

$ecart = 0.001;
$limit=0;

while($limit < 30){
  // Fetch all Observations.
    $ecart *= 2;
    
    $borneMinLatitude=round(($latitude-$ecart), 2 ) ;
    $borneMaxLatitude=round(($latitude+$ecart), 2);
    $borneMinLongitude=round(($longitude-$ecart), 2 ) ;
    $borneMaxLongitude=round(($longitude+$ecart), 2);
   
  
$querySelectAllObservations = 
  "SELECT DISTINCT(idobs) AS idobs, latitude, longitude, nom_commun, nom_photo_final, iherba_observations.id_user, iherba_observations.deposit_timestamp, name" .
  " FROM `iherba_observations`, iherba_determination, iherba_photos, fe_users" .
  " WHERE iherba_determination.id_obs = iherba_observations.idobs" . 
  " AND iherba_determination.id_obs = iherba_photos.id_obs" .
  " AND iherba_determination.nom_commun != ''" .
  " AND iherba_determination.nom_commun IS NOT NULL" .
  " AND iherba_observations.id_user = fe_users.uid" .
  " AND latitude BETWEEN $borneMinLatitude AND $borneMaxLatitude" .
  " AND longitude BETWEEN $borneMinLongitude AND $borneMaxLongitude" .
  " GROUP BY idobs" .
  " ORDER BY iherba_observations.deposit_timestamp DESC" .
  " LIMIT 30";


  $result = $db->query($querySelectAllObservations);
  $limit = $result->numRows();  
}


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
