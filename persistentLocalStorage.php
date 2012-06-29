<?php
namespace iHerbarium;

require_once("myPhpLib.php");
require_once("dbConnection.php");

interface LocalStorage {
  public function loadUser($eMail);
  public function saveUser(User $user);
  public function getTimedOutUsers($state, $timeout);

  public function savePhoto(Photo $photo);
  public function deleteFreshPhotosOfUser(User $user);

  public function createObservation(Observation $obs);
  public function saveObservation(Observation $obs);
  public function loadLastObservationOfUser(User $user);
  public function deleteNotConfirmedObservationsOfUser(User $user);

  public function getTemplate($msgType, $msgForm, $lang, $part);
}

class LocalDB 
extends PersistentObjectDB
implements LocalStorage {

  // Singleton implementation.
  protected static $instance = NULL;


  // Database selection.
  protected function dbConnectionId() {
    return Config::get("localStorageDatabase");
  }


  public function loadUser($eMail) {
    $this->debug("Begin", "loadUser($eMail)");

    $user = NULL;

    // Get the user from DB
    $query =
      "SELECT eMail, State, CollectPhotosHandler, CollectPhotosLang " .
      "FROM User " .
      "WHERE eMail = " . $this->quote($eMail);

    $result = $this->query($query);
    assert( ! is_null($result) );
    assert($result->numrows() <= 1);

    // Did we get an answer?
    if( ($row = $result->fetchRow()) ) {
      // We fetched the User!

      // Fill a User Model according to fetched local data.
      $user = new User();
      $user->eMail = $row->email;
      
      // A little rough State fetching.
      switch($row->state) {
      
      case PROTOCOL_STATE_NO_STATE:
	assert(False); break;

      case PROTOCOL_STATE_INIT:
	$user->state = new StateInit();
	break;
      
      case PROTOCOL_STATE_COLLECT_PHOTOS:
	$user->state = new StateCollectPhotos();
	$user->state->handler = $row->collectphotoshandler;
	$user->state->lang    = $row->collectphotoslang;
	break;

      case PROTOCOL_STATE_CONFIRM:
	$user->state = new StateConfirm();
	break;

      }

      $this->debug("Ok", "Loaded User.", $user);      
    } 
    else {
      // User doesn't exist.
      $this->debug("Error", "User doesn't exist locally!");
    }

    return $user;
  }

  public function saveUser(User $user) {
    $this->debug("Begin", "Save User", $user);

    $time = time();

    // Insert / update the user from DB
    $query =
      "INSERT INTO User(eMail, State, LastTransitionTimestamp, CollectPhotosHandler, CollectPhotosLang) " .
      "VALUES( " . $this->quote($user->eMail) . 
      " , " . $this->quote($user->state->name()) . 
      " , " . $this->quote($time) . 
      " , " . $this->quote( (isset($user->state->handler)) ? $user->state->handler : NULL) . 
      " , " . $this->quote( (isset($user->state->lang))    ? $user->state->lang    : NULL) . 
      " ) " .
      "ON DUPLICATE KEY UPDATE" .
      " State = " . $this->quote($user->state->name()) .
      " , LastTransitionTimestamp = " . $this->quote($time) .
      " , CollectPhotosHandler = " . $this->quote( (isset($user->state->handler)) ? $user->state->handler : NULL) . 
      " , CollectPhotosLang = "    . $this->quote( (isset($user->state->lang))    ? $user->state->lang    : NULL);
      
    $affected =& $this->exec($query);

    if( $affected >= 0 )
      $this->debug("Ok", "User saved!");
    else
      $this->debug("Error", "Saving User failed!");    
  }

  public function getTimedOutUsers($state, $timeout) {
    $this->debug("Begin", "Get Timed-Out Users");

    // Get Users from DB.
    $query =
      "SELECT eMail" .
      " FROM User" .
      " WHERE State = " . $this->quote($state) .
      " AND LastTransitionTimestamp < " . $this->quote($timeout);

    $result = $this->query($query);
    
    $users = array();
    
    while( ($row = $result->fetchRow()) ) {
      // Load each User.
      $user = $this->loadUser($row->email);
      //$this->debug("Ok", "Got Timed-Out User: ", $user);
      $users[] = $user;
    }
    
    $this->debug("Ok", "Got Timed-Out Users!");

    return $users;
  }

  public function savePhoto(Photo $photo) {
    $this->debug("Begin", "Save Photo", $photo);

    // Insert the Photo to DB.
    $query =
      "INSERT INTO Photo(Id, User, Tag, Comments, Timestamp, Image, ImageSubtype, Observation) " .
      "VALUES( NULL" . // AutoIncrement Id 
      " , " . $this->quote($photo->user->eMail) . 
      " , " . $this->quote($photo->tag) . 
      " , " . $this->quote($photo->comments) . 
      " , " . $this->quote($photo->timestamp) . 
      " , " . $this->quote($photo->image) .
      " , " . $this->quote($photo->imageSubtype) .
      " , NULL " . // Observation Id
      " ) ";
      
    $affected =& $this->exec($query);

    if( $affected == 1)
      $this->debug("Ok", "Photo saved!");
    else
      $this->debug("Error", "Saving Photo failed!");

    /*
    // Get this Photo's Id...
    $this->debug("Begin", "Getting Photo's id...");
    $photoId = $this->db->lastInsertID("Photo", "Id");
    assert($photoId != -1);
    $this->debug("Ok", "Photo's id = $photoId");
    */
  }
  
  public function deleteFreshPhotosOfUser(User $user) {
    $this->debug("Begin", "Delete fresh Photos of User $user->eMail");
    
    // Link all fresh Photos of this User to this Observation
    $photosQuery =
      "DELETE FROM Photo" .
      " WHERE User = " . $this->quote($user->eMail) .
      " AND Observation IS NULL";
    
    $photosAffected =& $this->exec($photosQuery);

    $this->debug("Ok", "Fresh photos of User $user->eMail deleted!");
  }

  private function loadPhotosBelongingToObservation(Observation $obs) {
    // Get all the Photos which belong to this Observation.
    
    // Fetch the Photos.
    $photosQuery =
      "SELECT Id, User, Tag, Comments, Timestamp, Image, ImageSubtype, Observation" .
      " FROM Photo" .
      " WHERE User = " . $this->quote($obs->user->eMail) .
      " AND Observation = " . $this->quote($obs->id);
    
    $result = $this->query($photosQuery);
    assert( ! is_null($result) );
    assert($result->numrows() >= 0);

    // Prepare all fetched photos.
    $photos = array();
    while( ($row = $result->fetchRow()) ) {
      // We've fetched a Photo.
      
      // Fill the photo with fetched data.
      $photo = new Photo();
      $photo->id           =  $row->id;
      $photo->user         =& $obs->user;
      $photo->tag          =  $row->tag;
      $photo->comments     =  $row->comments;
      $photo->timestamp    =  $row->timestamp;
      $photo->image        =  $row->image;
      $photo->imageSubtype =  $row->imagesubtype;

      $photos[] = $photo;
    }
    
    return $photos;
  }

  public function linkFreshPhotosToObservation(Observation $obs) {
    $this->debug("Begin", "Link fresh Photos to Observation");

    // Link all fresh Photos (of appropriate User) to this Observation
    $photosQuery =
      "UPDATE Photo" .
      " SET Observation = " . $this->quote($obs->id) .
      " WHERE User = " . $this->quote($obs->user->eMail) .
      " AND Observation IS NULL";
    
    $photosAffected =& $this->exec($photosQuery);

    if( $photosAffected >= 0)
      $this->debug("Ok", "Fresh Photos linked to the Observation!");
    else
      $this->debug("Error", "Linking Photos failed!");
  }

  public function createObservation(Observation $obs) {
    /* and link all his fresh photos to it! */
    $this->debug("Begin", "Create Observation", $obs);

    // Insert the Observation to DB.
    $obsQuery =
      "INSERT INTO Observation(Id, User, ConfirmationCode, IsConfirmed) " .
      "VALUES( " . "NULL" . // AutoIncrement Id
      " , " . $this->quote($obs->user->eMail) . 
      " , " . $this->quote($obs->confirmationCode) . 
      " , " . $this->quote($obs->isConfirmed) .
      " ) ";
  
    $obsAffected =& $this->exec($obsQuery);

    if( $obsAffected == 1)
      $this->debug("Ok", "New Observation created!");
    else
      $this->debug("Error", "Creating Observation failed!");
    
    // Get this Observation's Id...
    $this->debug("Begin", "Getting Observation's id...");
    $obsId = $this->db->lastInsertID("Observation", "Id");
    assert($obsId != -1);
    $this->debug("Ok", "Observation's id = $obsId");
    $obs->id = $obsId;

    // Link all fresh Photos of this User to this Observation
    $this->linkFreshPhotosToObservation($obs);

    // Now load the Observation's photos.
    $obs->photos = $this->loadPhotosBelongingToObservation($obs);

    return $obs;
  }

  public function saveObservation(Observation $obs) {
    $this->debug("Begin", "Save Observation", $obs);

    // Update the Observation in DB
    $query =
      "UPDATE Observation " .
      " SET User = " . $this->quote($obs->user->eMail) .
      " , ConfirmationCode = " . $this->quote($obs->confirmationCode) .
      " , IsConfirmed = " . $this->quote($obs->isConfirmed) .
      " WHERE Id = " . $this->quote($obs->id);
      
    $affected =& $this->exec($query);

    if( $affected == 1)
      $this->debug("Ok", "Observation saved!");
    else
      $this->debug("Error", "Saving Observation failed!");
  }

  public function loadLastObservationOfUser(User $user) {
    /* to get the confirmation code */

    $this->debug("Begin", "Load last Observation of User", $user);

    $obs = NULL;

    // Fetch the last not confirmed Observation of User.
    $obsQuery =
      "SELECT Id, User, ConfirmationCode, IsConfirmed" .
      " FROM Observation" .
      " WHERE User = " . $this->quote($user->eMail) .
      " AND IsConfirmed = " . $this->quote(False) .
      " ORDER BY Id DESC" .
      " LIMIT 1";
    
    $result = $this->query($obsQuery);
    
    assert( ! is_null($result) );
    assert($result->numrows() <= 1);
    
    // Did we get an answer?
    if( ($row = $result->fetchRow()) ) {
      // We fetched the Observation!
      //$this->debug("Debug", "Observation loaded.", "<pre>" . var_export($row, True) . "</pre>");

      // Fill the Observation Model according to fetched local data.
      $obs = new Observation();
      $obs->id               =  $row->id;
      $obs->user             =& $user;
      $obs->photos           =  array();
      $obs->confirmationCode =  $row->confirmationcode;
      $obs->isConfirmed      =  $row->isconfirmed;

      // Now load the Observation's photos.
      $obs->photos = $this->loadPhotosBelongingToObservation($obs);
      
      $this->debug("Ok", "Last observation of User loaded!");
      
    } 
    else {
      // Observation doesn't exist.
      $this->debug("Error", "Observation doesn't exist!");
    }    

    return $obs;
  }
  
  public function deleteNotConfirmedObservationsOfUser(User $user) {
    $this->debug("Begin", "Delete not confirmed Observations of User $user->eMail");

    // Fetch all not confirmed Observations of User.
    $selectObsQuery =
      "SELECT Id, User, ConfirmationCode, IsConfirmed" .
      " FROM Observation" .
      " WHERE User = " . $this->quote($user->eMail) .
      " AND IsConfirmed = " . $this->quote(False);
    
    $result = $this->query($selectObsQuery);
    
    assert( ! is_null($result) );
    assert($result->numrows() >= 0);
    
    // For each not confirmed observation.
    while( ($row = $result->fetchRow()) ) {
      
      // Get the Observation's Id.
      $obsId = $row->id;

      // Delete the Observation.
      $deleteObsQuery =
	"DELETE FROM Observation" .
	" WHERE Id = " . $this->quote($obsId);
    
      $deletedObs = $this->exec($deleteObsQuery);
    
      // Delete all it's Photos.
      $deletePhotosQuery =
	"DELETE FROM Photo" .
	" WHERE Observation = " . $this->quote($obsId);
    
      $deletedPhotos = $this->exec($deletePhotosQuery);
    }

    $this->debug("Ok", "All not confirmed Observations of User (and corresponding Photos) deleted!");
  }

  public function getTemplate($msgType, $msgForm, $lang, $part) {
    //$this->debug("Begin", "Get Template", "($msgType, $msgForm, $lang, $part)");

    $template = NULL;

    // Get the Template from DB
    $query =
      "SELECT Value" .
      " FROM ContentTemplate" .
      " WHERE MessageType = " . $this->quote($msgType) .
      " AND MessageForm = " . $this->quote($msgForm) .
      " AND Lang = " . $this->quote($lang) .
      " AND Part = " . $this->quote($part);

    $result = $this->query($query);
    
    assert( ! is_null($result) );
    assert($result->numrows() <= 1);

    // Did we get an answer?
    if( ($row = $result->fetchRow()) ) {
      // We fetched the Template!
      //$this->debug("Ok", "Got Template.", $row->value);
      return $row->value;
    } 
    else {
      // Template doesn't exist.
      $this->debug("Error", "Template doesn't exist!", "getTemplate($msgType, $msgForm, $lang, $part)");
      return NULL;
    }

  }
}

?>