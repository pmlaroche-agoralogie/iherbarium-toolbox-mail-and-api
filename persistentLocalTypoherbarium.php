<?php
namespace iHerbarium;

require_once("myPhpLib.php");
require_once("dbConnection.php");
require_once("typoherbariumModel.php");

require_once("apModel.php");

require_once("persistentInterfaces.php");

class LocalTypoherbariumDB
extends PersistentObjectDB
implements PersistentUserI, 
  PersistentObservationI, PersistentPhotoI, PersistentROII, 
  PersistentTagI, PersistentAnswerI, PersistentAnswersPatternI, 
  PersistentQuestionI, 
  PersistentComparatorI, 
  PersistentTaskI, PersistentGroupI {

  // Singleton implementation.
  protected static $instance = NULL;

  // Database selection.
  protected function dbConnectionId() {
    return Config::get("localTypoherbariumDatabase");
  }

  // Photo and ROI file settings.
  public $mediaSource       = NULL;
  public $photoSource       = NULL;
  public $photoFileVersions = NULL;
  public $roiFileVersions   = NULL;

  function __construct() {
    parent::__construct();
    
    // Config - Media source.
    $this->mediaSource = 
      TypoherbariumFileVersion::make()
      ->setName("source")
      ->setDir(Config::get("filesLocalDir"))
      ->setUrl(Config::get("filesURL"));

    // Config - Photo source.
    $this->photoSource = 
      TypoherbariumFileVersion::make()
      ->setName("source")
      ->setDir(Config::get("filesLocalDir"))
      ->setUrl(Config::get("filesURL"));

    // Config - Photo file versions.
    $this->photoFileVersions = 
      array(
	    "vignette" =>
	    TypoherbariumFileVersion::make()
	    ->setName("vignette")
	    ->setDir(Config::get("vignettesLocalDir"))
	    ->setUrl(Config::get("vignettesURL"))
	    ->setMaxSize(200),
	  
	    "big" =>
	    TypoherbariumFileVersion::make()
	    ->setName("big")
	    ->setDir(Config::get("bigLocalDir"))
	    ->setUrl(Config::get("bigURL"))
	    ->setMaxSize(1024)
	    );

    // Config - ROI file versions.
    $this->roiFileVersions = 
      array(
	    "source" =>
	    TypoherbariumFileVersion::make()
	    ->setName("source")
	    ->setDir(Config::get("roiSourcesLocalDir"))
	    ->setUrl(Config::get("roiSourcesURL"))
	    ->setMaxSize(NULL),

	    "vignette" =>
	    TypoherbariumFileVersion::make()
	    ->setName("vignette")
	    ->setDir(Config::get("roiVignettesLocalDir"))
	    ->setUrl(Config::get("roiVignettesURL"))
	    ->setMaxSize(130)
	    );
  }
  
  
  // USER

  public function getUserUid($username) {

    // Workaround...
    $context = $this;
     
    // Uid Query.
    $uidQuery =
      "SELECT uid" .
      " FROM fe_users" .
      " WHERE username = " . $context->quote($username);

    return
      $context->singleResult(// Query
			     $uidQuery,
			  
			     // If the User exists...
			     function($row) use ($context, $username) {
			       // We fetched the uid!
			       $uid = $row->uid;
			       $context->debug("Ok", "User with username = '$username' exists and has uid = $uid.");
			       return $uid;
			     },
			  
			     // If the User doesn't exist...
			     function() use ($context) {
			       $context->debug("Error", "User with username = '$username' doesn't exist!");
			       return NULL;
			     }); 
  }

  public function createUser($username, $password, $lang, $name = NULL) {
    
    // Workaround...
    $context = $this;

    // Name.
    if(! $name) {
      $usernameExploded = explode("@", $username);
      $name = $usernameExploded[0];
    }
    
    // Language.
    $language = strtoupper($lang);

    $insertUserQuery =
      "INSERT INTO fe_users(uid, pid, tstamp, username, password, usergroup, name, email, language)" .
      " VALUES( NULL " . // Autoincrement uid
      " , " . $context->quote(2) .         // pid
      " , " . $context->quote(time()) .    // tstamp
      " , " . $context->quote($username) . // username
      " , " . $context->quote($password) . // password
      " , " . $context->quote(1) .         // usergroup
      " , " . $context->quote($name) .     // name
      " , " . $context->quote($username) . // email
      " , " . $context->quote($language) . // language
      " )";

    $affectedUser = $context->exec($insertUserQuery);
    
    return;
  }


  // OBSERVATION

  public function saveObservation(TypoherbariumObservation $obs, $uid) {
    
    // Workaround...
    $context = $this;

    // Insert OR Update
    if( ! is_null($obs->id) ) {
      
      // Observation Update.
      $obs = $context->updateObservation($obs, $uid);
      return $obs;

    } else {
      
      // Observation Insert.
      $obs = $context->insertObservation($obs, $uid);
      return $obs;

    }

  }

  private function insertObservation(TypoherbariumObservation $obs, $uid) {
    
    // Workaround...
    $context = $this;

    // We presume, that Observation doesn't exist.

    // Insert the Observation.
    $insertObsQuery = 
      "INSERT INTO" .
      " iherba_observations (idobs, id_user, date_depot, commentaires, genre_obs, latitude, longitude, public, taille_plante)" .
      " VALUES( " . $context->quote($obs->id) .
      " , " . $context->quote($uid) .
      " , now()" .
      " , " . $context->quote($obs->commentary) .
      " , " . $context->quote($obs->kind) .
      " , " . $context->quote($obs->geolocation->latitude) .
      " , " . $context->quote($obs->geolocation->longitude) .
      " , " . $context->quote( ($obs->privacy === "public" ? "oui" : "semi" ) ) .
      " , " . $context->quote($obs->plantSize) . 
      " )";
      
    $affectedObs = $context->exec($insertObsQuery);
    
    // Get the Id.
    $obsId = $context->lastInsertID("iherba_observations", "idobs");
    $obs->id = $obsId;
   
    // Return the inserted Observation.
    return $obs;

  }
  
  private function updateObservation(TypoherbariumObservation $obs, $uid) {

    // Workaround...
    $context = $this;
    
    // Get Observation's Id.
    assert($obs->id);
    $obsId = $obs->id;

    // Check if the Observation alteady exists.
    $obsQuery =
      "SELECT idobs " .
      " FROM iherba_observations" .
      " WHERE idobs = " . $context->quote($obsId);

    return
      $context->singleResult(// Query
			     $obsQuery,
			  
			     // If the Observation exists...
			     function($row) use ($context, $obs, $obsId) {
			       // We fetched the Observation!
			    
			       // Update the Observation.
			       $updateObsQuery = 
				 " UPDATE iherba_observations" .
				 " SET" .
				 "   commentaires = "  . $context->quote($obs->commentary) .
				 " , genre_obs = "     . $context->quote($obs->kind) .
				 " , latitude = "      . $context->quote($obs->geolocation->latitude) .
				 " , longitude = "     . $context->quote($obs->geolocation->longitude) .
				 " , taille_plante = " . $context->quote($obs->plantSize) .
				 " , public = "        . $context->quote( ($obs->privacy === "public" ? "oui" : "semi" ) ) .
				 " WHERE idobs = "     . $context->quote($obsId);
      
			       $affectedObs = $context->exec($updateObsQuery);

			       return $obs;
			     },
			  
			     // If the Observation doesn't exist...
			     function() use ($context, $obsId) {
			       // Observation doesn't exist.
			       $context->debug("Error", "Observation with id = '$obsId' doesn't exist!");
			       return NULL;
			     });
  }

  public function loadObservation($obsId) {

    // Workaround...
    $context = $this;

    // If we are already given the Observation object.
    if($obsId instanceof TypoherbariumObservation)
      return $obsId;

    // Fetch the Observation.
    $obsQuery =
      "SELECT idobs, id_user, date_depot, commentaires, genre_obs, latitude, longitude, public, taille_plante" .
      " FROM iherba_observations" .
      " WHERE idobs = " . $context->quote($obsId);

    return
      $context->singleResult(// Observations' query.
			     $obsQuery,
			  
			     // If the Observation exists...
			     function($row) use ($context, $obsId) {

			       // Prepare the Observation.
			       $obs = new TypoherbariumObservation();
			    
			       $obs
				 ->setId          ($obsId)
				 ->setUser        (NULL) // TODO
				 ->setUid         ($row->id_user)
				 ->setTimestamp   ($row->date_depot)
				 ->setGeolocation (TypoherbariumGeolocation::fromLatitudeAndLongitude($row->latitude, $row->longitude))
				 ->setPrivacy     ($row->public === "oui" ? "public" : "private")
				 ->setKind        ($row->genre_obs)
				 ->setPlantSize   ($row->taille_plante)
				 ->setCommentary  ($row->commentaires);
      
			       // Link Photos.

			       // Fetch id's of Photos which belong to this Observation.
			       $photosQuery = 
				 "SELECT idphotos" .
				 " FROM iherba_photos " .
				 " WHERE id_obs = " . $context->quote($obsId);
			       
			       $context->iterResults(// Photos query.
						     $photosQuery,
						     
						     // For each Photo...
						     function($photoRow) use ($context, &$obs) {
						       $photoId = $photoRow->idphotos;
						       $photo = $context->loadPhoto($photoId);
						       
						       $obs->addPhoto($photo, $photoId);
						     });

            // Link Medias.

            // Fetch id's of Medias which belong to this Observation.
            $mediasQuery = 
            "SELECT idmedia" .
            " FROM iherba_medias " .
            " WHERE id_observation = " . $context->quote($obsId);
             
            $context->iterResults(// Medias query.
              $mediasQuery,
              
              // For each Media...
              function($mediaRow) use ($context, &$obs) {
                $mediaId = $mediaRow->idmedias;
                $media = $context->loadMedia($mediaId);
                
                $obs->addMedia($media, $mediaId);
              });


			       $context->debug("Ok", "Loaded Observation $obsId.", $obs);
			       return $obs;
			     },
			  
			     function() use ($context, $obsId) {
			       $context->debug("Error", "Observation with id = '$obsId' doesn't exist!");
			       return NULL;
			     });
  }

  public function getAllObsIdsForUID($uid) {
    
    // Workaround...
    $context = $this;

    $obsQuery =
      "SELECT idobs " .
      " FROM iherba_observations";

    if($uid)
      $obsQuery .=
	    " WHERE id_user = " . $context->quote($uid);

    $obsIds =
      $context->mapResults(// Query
			   $obsQuery,
			
			   function($row) {
			     return $row->idobs;			 
			   });

    return $obsIds;
  }
  
  public function deleteObservation($obsId) {
    
    // Workaround...
    $context = $this;

    // Emulating function overloading: 
    // If we are already given the TypoherbariumObservation object.
    if($obsId instanceof TypoherbariumObservation) {
      $obs = $obsId;
      $obsId = $obs->id;
    } else {
      $obs = $context->loadObservation($obsId);
    }

    $context->debug("Begin", "Deleting Observation $obsId...");

    // Delete Photos of the Observation.
    array_iter(array($context, "deletePhoto"), $obs->photos);
    
    // Delete Medias of the Observation.
    array_iter(array($context, "deleteMedia"), $obs->medias);

    // Delete Observarion from all Groups.
    $deleteObsFromAllGroupsQuery =
      "DELETE FROM iherba_group_observations" .
      " WHERE ObservationId = " . $context->quote($obsId);

    $affectedGroups = $context->exec($deleteObsFromAllGroupsQuery);

    // Delete the Observation itself.
    $deleteObsQuery =
      "DELETE FROM iherba_observations" . 
      " WHERE idobs = " . $context->quote($obsId);
    
    $obsAffected = $context->exec($deleteObsQuery);

    assert($obsAffected <= 1);

    if($obsAffected > 0) {
      $context->debug("Ok", "Deleted Observation $obsId!");
    } else {
      $context->debug("Error", "Deleting Observation $obsId failed!");
    }
    
  }

  
  // PHOTO

  public function addPhotoToObservation(TypoherbariumPhoto $photo, $obsId, $uid) {
    
    // Workaround...
    $context = $this;
    
    // Link the Photo with the Observation.
    $photo->obsId = $obsId;
    
    // Initial insert to get Photo's id.
    $insertPhotoQuery = "INSERT INTO iherba_photos VALUES()";
    $affectedPhoto = $context->exec($insertPhotoQuery);
    $photoId = $context->lastInsertID("iherba_photos", "idphotos");
    $photo->id = $photoId;

    // Copy original Photo (from "remote" address) and create it's resized versions.
    $photo->localDir      = $context->photoSource->dir;
    $photo->localFilename = $context->buildPhotoFilename($photo);
    $context->createPhotoFiles($photo);

    // Date format helper.
    $formatDate = function($date) {
      return ($date ? date("Y-m-d", $date) : NULL); 
    };

    // Real Photo insert.
    $photoQuery = 
      "UPDATE iherba_photos " .
      " SET" .
      "   id_obs = "            . $context->quote($obsId) .
      " , date_depot = "        . $context->quote($formatDate($photo->depositTimestamp)) .
      " , date_exif = "         . $context->quote($formatDate($photo->exifTimestamp)) .
      " , date_user = "         . $context->quote($formatDate($photo->userTimestamp)) .
      " , latitude_exif = "     . $context->quote($photo->exifGeolocation->latitude) .
      " , longitude_exif = "    . $context->quote($photo->exifGeolocation->longitude) .
      " , nom_photo_initial = " . $context->quote($photo->remoteFilename) .
      " , nom_photo_final = "   . $context->quote($photo->localFilename) .
      " WHERE idphotos = "      . $context->quote($photoId);
    
    $affectedPhoto = $context->exec($photoQuery);
    
    // Photo added, now adding it's ROIs.
    foreach($photo->rois as $roi) {
	
      // Fetch Tag's id
      $tagIdQuery = 
	"SELECT id_tag" .
	" FROM iherba_tag_vocabulary_from_mail" .
	" WHERE text = " . $context->quote($roi->tag);

      $context->singleResult(// Tag's query.
			     $tagIdQuery,
			
			     // If the Tag exists...
			     function($row) use ($context, $roi, $photoId, $uid) {
			       // We fetched the Tag!
			       $tagId = $row->id_tag;
			  
			       $context->addROIToPhoto($roi, $tagId, $photoId, $uid);
			     },
			  
			     // If the Tag doesn't exist...
			     function() use ($context, $roi) {
			       $context->debug("Error", "Tag '$roi->tag' doesn't exist!");
			     });
    }
    
    return $photo;
  }

  private function buildPhotoFilename(TypoherbariumPhoto $photo) {
    
    // Workaround...
    $context = $this;
    
    // Build the filename.
    $baseFilename = 
      "photo_" . $photo->id . 
      "_" . 
      "observation_" . $photo->obsId . 
      ".jpg";

    return $baseFilename;
  }

  private function createPhotoFiles(TypoherbariumPhoto $photo) {
    
    // Workaround...
    $context = $this;

    // Get the ids.
    $photoId = $photo->id;
    $obsId   = $photo->obsId;

    // Prepare base filename.
    $baseFilename = $photo->localFilename;

    // Copy from "remote" source to local hard disk.
    $photo->copyFromRemoteToLocal(
				  $context->photoSource->dir,
				  $baseFilename
				  );

    
    $photo->sourceFile =
      $context->photoSource->instantiate($baseFilename);

    // Prepare all file versions (currently: vignette and big).
    array_iter(
	       function($photoFileVersion) use (&$photo, $baseFilename) {

		 $photo->makeLocalResizedCopy(
					      $photoFileVersion->dir,
					      $baseFilename,
					      $photoFileVersion->maxSize
					      );

		 $photo->addFileVersion(
					$photoFileVersion->instantiate($baseFilename),
					$photoFileVersion->name
					);
	       },
	       $context->photoFileVersions);

  }

  public function loadPhoto($photoId) {

    // Workaround...
    $context = $this;

    // If we are already given the Photo object.
    if($photoId instanceof TypoherbariumPhoto)
      return $photoId;
    
    // Fetch the Photo.
    $photoQuery = 
      "SELECT id_obs, date_depot, date_exif, date_user, latitude_exif, longitude_exif, nom_photo_initial, nom_photo_final " .
      " FROM iherba_photos " .
      " WHERE idphotos = " . $context->quote($photoId);
    
    return
      $context->singleResult(// Photo's query.
			     $photoQuery,
			  
			     // If the Photo exists...
			     function($row) use ($context, $photoId) {
			    
			       // Prepare the Photo.
			       $photo = new TypoherbariumPhoto();

			       // Photo's Id
			       $photo->id = $photoId;

			       // Observation's Id
			       $photo->obsId = $row->id_obs;

			       // Remote Path
			       $photo->remoteDir      = NULL; // TODO: TypoherbariumPhoto shouldn't have this field!
			       $photo->remoteFilename = $row->nom_photo_initial;
      
			       // Local Path (to the source)
			       $photo->localDir       = $context->photoSource->dir;
			       $photo->localFilename  = $row->nom_photo_final;

			       // File Versions
			       $baseFilename = 
				 $photo->localFilename;
			    
			       $photo->sourceFile =
				 $context->photoSource->instantiate($baseFilename);

			       $photo->fileVersions =
				 array_map(
					   function($photoFileVersion) use ($baseFilename) {
					  
					     return $photoFileVersion->instantiate($baseFilename);
					
					   }, $context->photoFileVersions);

			       // Timestamps
			       $photo->depositTimestamp = $row->date_depot;
			       $photo->userTimestamp    = $row->date_user;
  
			       // Exif data
			       $exif = NULL;
			       
			       // A little workaround to stop showing EXIF errors!
			       $oldErrorLevel = error_reporting();
			       error_reporting($oldErrorLevel & ~E_WARNING);
			       $exif = exif_read_data($photo->localPath());
			       error_reporting($oldErrorLevel);

			       if($exif) $photo->exifOrientation = (isset($exif['Orientation']) ? $exif['Orientation'] : NULL);
			       else      $photo->exifOrientation = NULL;

			       $photo->exifTimestamp   = $row->date_exif;
			       $photo->exifGeolocation = TypoherbariumGeolocation::fromLatitudeAndLongitude($row->latitude_exif, $row->longitude_exif);

			       // ROIs
			       $roisQuery = 
				 "SELECT id " .
				 " FROM iherba_roi " .
				 " WHERE id_photo = " . $context->quote($photoId);

			       $photo->rois =
				 $context->mapResults(// Query.
						      $roisQuery,

						      // For each ROI...
						      function($row) use ($context) {
							$roiId = $row->id;
							$roi = $context->loadROI($roiId);
							return $roi;
						      });
      
			       $context->debug("Ok", "Loaded photo $photoId.", $photo);
			       return $photo;
			     },

			     // If the Photo doesn't exist...
			     function() use ($context, $photoId) {
			       $context->debug("Error", "Photo with id = '$photoId' doesn't exist!");
			       return NULL;
			     });
  }
  
  public function deletePhoto($photoId) {
    
    // Workaround...
    $context = $this;
    
    // Emulating function overloading: 
    // If we are already given the TypoherbariumPhoto object.
    if($photoId instanceof TypoherbariumPhoto) {
      $photo = $photoId;
      $photoId = $photo->id;
    } else {
      $photo = $context->loadPhoto($photoId);
    }
    
    $context->debug("Begin", "Deleting Photo $photoId...");

    // Delete all ROIs of this Photo.
    array_iter(array($context, "deleteROI"), $photo->rois);
    
    // Delete all Photo's files on disk.
    $context->deletePhotoFiles($photo);
        
    // Delete the Photo in the database.
    $deletePhotoQuery =
      "DELETE FROM iherba_photos" . 
      " WHERE idphotos = " . $context->quote($photoId);
    
    $photoAffected =& $context->exec($deletePhotoQuery);

    // Check if deleted successfully.
    if($photoAffected > 0) {
      $context->debug("Ok", "Deleted Photo $photoId!");
    } else {
      $context->debug("Error", "Deleting Photo $photoId failed!");
    }

  }

  private function deletePhotoFiles(TypoherbariumPhoto $photo) {
    
    // Workaround...
    $context = $this;
    
    $photoId = $photo->id;

    $context->debug("Begin", "Deleting files of Photo $photoId...");
    
    // Delete source and all versions.
    $context->deletePhotoSourceFile($photo);
    $context->deletePhotoVersionsFiles($photo);
    
    $context->debug("Ok", "Deleted files of Photo $photoId!");

  }

  private function deletePhotoSourceFile(TypoherbariumPhoto $photo) {
    
    // Workaround...
    $context = $this;

    $photoId = $photo->id;

    $context->debug("Begin", "Deleting source file of Photo $photoId...");
        
    // Delete Photo's source file.
    $context->deleteFile($photo->sourceFile->path());
    
    $context->debug("Ok", "Deleted source file of Photo $photoId!");

  }

  private function deletePhotoVersionsFiles(TypoherbariumPhoto $photo) {
    
    // Workaround...
    $context = $this;

    $photoId = $photo->id;

    $context->debug("Begin", "Deleting all files with versions of Photo $photoId...");
        
    // Prepare all file versions (currently: vignette and big).
    $photoVersionsPaths = 
      array_map(
		function($photoFileVersion) {
		  return $photoFileVersion->path();
		},
		$photo->fileVersions);
    
    // Delete each file.
    array_iter(array($context, "deleteFile"), $photoVersionsPaths);
    
    $context->debug("Ok", "Deleted all files with versions of Photo $photoId!");

  }


  // MEDIA

  public function createMedia(TypoherbariumMedia $media) {

    // Workaround...
    $context = $this;
    
    // Initial insert to get Media's id.
    $insertMediaQuery = "INSERT INTO iherba_medias VALUES()";
    $affectedMedia = $context->exec($insertMediaQuery);
    $mediaId = $context->lastInsertID("iherba_medias", "idmedia");
    $media->id = $mediaId;

    // Date format helper.
    $formatDate = function($date) {
      return ($date ? date("Y-m-d", $date) : NULL); 
    };

    // Real Media insert.
    $mediaQuery = 
    "UPDATE iherba_medias " .
    " SET" .
    "   id_obs = "            . $context->quote($obsId) .
    " , date_depot = "        . $context->quote($formatDate($media->depositTimestamp)) .
    " , nom_media_initial = " . $context->quote($media->initialFilename) .
    " , nom_media_final = "   . $context->quote($media->localFilename) .
    " WHERE idmedia = "       . $context->quote($mediaId);
    
    $affectedMedia = $context->exec($mediaQuery);
    
    return $media;
  }

  private function buildMediaFilename(TypoherbariumMedia $media) {

    // Workaround...
    $context = $this;
    
    // Build the filename.
    $baseFilename = 
    "media_" . $media->id . 
    "_" . 
    "observation_" . $media->obsId;

    return $baseFilename;
  }

  public function copyMediaSourceFromRemotePath(TypoherbariumMedia $media, $remotePath) {

    // Workaround...
    $context = $this;
    
    // Prepare the local path.
    $media
      ->setLocalDir      ($context->mediaSource->dir)
      ->setLocalFilename ($context->buildMediaFilename($media))
      ->setSourceFile    ($context->mediaSource->instantiate($baseFilename));

    // Copy from "remote" source to local hard disk.
    $context->debug("Debug", "Copying media from ". $remotePath ." to ". $media->localPath() );
    copy($remotePath, $media->localPath());

    return $media;

  }

  public function loadMedia($mediaId) {

    // Workaround...
    $context = $this;

    // If we are already given the Media object.
    if($mediaId instanceof TypoherbariumMedia)
      return $mediaId;
    
    // Fetch the Media.
    $mediaQuery = 
    "SELECT id_observation, date_depot, nom_media_initial, nom_media_final " .
    " FROM iherba_medias " .
    " WHERE idmedia = " . $context->quote($mediaId);
    
    return
      $context->singleResult(// Media's query.
        $mediaQuery,

        // If the Media exists...
        function($row) use ($context, $mediaId) {

          // Prepare the Media.
          $media = new TypoherbariumMedia();

          // Media's Id
          $media->id = $mediaId;

          // Observation's Id
          $media->obsId = $row->id_observation;

          // Timestamps
          $media->depositTimestamp = $row->date_depot;

          // Initial Filename
          $media->initialFilename = $row->nom_media_initial;

          // Local Path (to the source)
          $media->localDir       = $context->mediaSource->dir;
          $media->localFilename  = $row->nom_media_final;

          // Source File
          $baseFilename          = $media->localFilename;
          $media->sourceFile     = $context->mediaSource->instantiate($baseFilename);


          $context->debug("Ok", "Loaded media $mediaId.", $media);
          return $media;
        },

        // If the Media doesn't exist...
        function() use ($context, $mediaId) {
          $context->debug("Error", "Media with id = '$mediaId' doesn't exist!");
          return NULL;
        }
      );
  }

  public function deleteMedia($mediaId) {

    // Workaround...
    $context = $this;
    
    // Emulating function overloading: 
    // If we are already given the TypoherbariumMedia object.
    if($mediaId instanceof TypoherbariumMedia) {
      $media = $mediaId;
      $mediaId = $media->id;
    } else {
      $media = $context->loadMedia($mediaId);
    }
    
    $context->debug("Begin", "Deleting Media $mediaId...");
    
    // Delete Media's source file on disk.
    $context->deleteMediaSourceFile($media);

    // Delete the Media in the database.
    $deleteMediaQuery =
    "DELETE FROM iherba_medias" . 
    " WHERE idmedia = " . $context->quote($mediaId);
    
    $mediaAffected =& $context->exec($deleteMediaQuery);

    // Check if deleted successfully.
    if($mediaAffected > 0) {
      $context->debug("Ok", "Deleted Media $mediaId!");
    } else {
      $context->debug("Error", "Deleting Media $mediaId failed!");
    }

  }

  private function deleteMediaSourceFile(TypoherbariumMedia $media) {

    // Workaround...
    $context = $this;

    $mediaId = $media->id;

    $context->debug("Begin", "Deleting source file of Media $mediaId...");

    // Delete Media's source file.
    $context->deleteFile($media->sourceFile->path());
    
    $context->debug("Ok", "Deleted source file of Media $mediaId!");

  }


  // ROI

  public function addROIToPhoto($roi, $tagId, $photoId, $uid) {
    
    // Workaround...
    $context = $this;
    
    // Load the Photo.
    $photo = $context->loadPhoto($photoId);

    // Insert ROI
    $insertROIQuery = 
      "INSERT INTO iherba_roi(id_photo, date_decoupe, x1, y1, x2, y2)" .
      " VALUES( " . $context->quote($photoId) .
      " , NOW()"  .
      " , "       . $context->quote($roi->rectangle->left) . 
      " , "       . $context->quote($roi->rectangle->top) . 
      " , "       . $context->quote($roi->rectangle->right) . 
      " , "       . $context->quote($roi->rectangle->bottom) . 
      " )";
      
    $affectedROI = $context->exec($insertROIQuery);
      
    // Get inserted ROI's id.
    $roiId = $context->lastInsertID("iherba_roi", "id");
    $roi->id = $roiId;
    
    // Insert ROI's Tag.
    $insertROITagQuery = 
      "INSERT INTO iherba_roi_tag(id_roi, id_tag, id_user)" .
      " VALUES( " . $context->quote($roiId) .
      " , "       . $context->quote($tagId) .
      " , "       . $context->quote($uid) .
      " )";
      
    $affectedROITag = $context->exec($insertROITagQuery);

    // Create ROI image files.
    $context->createROIFiles($roi, $photo);
        
  }

  public function buildROIFilename($roiId) {
    
    // Workaround...
    $context = $this;

    // Build the filename.
    $baseFilename = 
      "roi_" . $roiId . ".jpg";

    return $baseFilename;
  }

  private function createROIFiles($roi, $photo){
    
    // Workaround...
    $context = $this;
    
    // Get the ROI's Id.
    $roiId = $roi->id;

    // Prepare base filename.
    $baseFilename = 
      $context->buildROIFilename($roiId);
    
    // Create an image file for every version.
    array_iter(
	       function($roiFileVersion) use ($photo, &$roi, $baseFilename) {

		 $photo->makeLocalResizedCopy(
					      $roiFileVersion->dir,
					      $baseFilename,
					      $roiFileVersion->maxSize,
					      $roi->rectangle
					      );

		 $roi->addFileVersion(
				      $roiFileVersion->instantiate($baseFilename),
				      $roiFileVersion->name
				      );

	       },
	       $context->roiFileVersions);

  }

  public function loadROI($roiId) {

    // Context for anonymous functions.
    $context = $this;

    // If we are already given the ROI object.
    if($roiId instanceof TypoherbariumROI)
      return $roiId;

    // Fetch ROI.
    $roiQuery = 
      "SELECT id_photo, date_decoupe, x1, y1, x2, y2 " .
      " FROM iherba_roi " .
      " WHERE id = " . $context->quote($roiId);

    return
      $context->singleResult(// ROI's query.
			     $roiQuery,
			  
			     // If the ROI exists...
			     function($row) use ($context, $roiId) {
			    
			       // We fetched the ROI!
			    
			       // Prepare the ROI.
			       $roi = new TypoherbariumROI();
      
			       // ROI's id
			       $roi->id = $roiId;

			       // Photo's id
			       $roi->photoId = $row->id_photo;

			       // Observation's id

			       $obsQuery =
				 "SELECT id_obs " .
				 "FROM iherba_photos " .
				 "WHERE idphotos = " . $context->quote($roi->photoId);
			    
			       $roi->observationId =
				 $context->singleResult(
							$obsQuery,
							function($row) { return $row->id_obs; },
							function() { return NULL; }
							);

			       // Rectangle
			       $roi->rectangle =
				 ROIRectangle::fromLeftTopRightBottom(
								      $row->x1, // Left
								      $row->y1, // Top
								      $row->x2, // Right
								      $row->y2  // Bottom
								      );

			       // File Versions

			       $baseFilename = 
				 $context->buildROIFilename($roiId);
			    
			       $roi->fileVersions =
				 array_map(
					   function($roiFileVersion) use ($baseFilename) {

					     return $roiFileVersion->instantiate($baseFilename);

					   }, $context->roiFileVersions);
			    
			    
			       // Tags

			       $tagsQuery = 
				 "SELECT id " .
				 " FROM iherba_roi_tag" .
				 " WHERE id_roi = " . $context->quote($roiId);

			       $roi->tags =
				 $context->mapResults(// Query
						      $tagsQuery,

						      // For each Tag...
						      function($tagRow) use ($context) {
							$tagId  = $tagRow->id;
							$tag = $context->loadTag($tagId);
							return $tag;
						      });
      
			    
			       // Tag
			       $roi->tag = 
				 (isset($roi->tags[0]) ? $roi->tags[0] : NULL);
			    

			       // Answers

			       $answersQuery = 
				 "SELECT id " .
				 " FROM iherba_roi_answer" .
				 " WHERE id_roi = " . $context->quote($roiId);

			       $answers =
				 $context->mapResults(// Query
						      $answersQuery,
						   
						      // For each Answer...
						      function($answerRow) use ($context) {
							$answerId = $answerRow->id;
							$answer = $context->loadAnswer($answerId);
							return $answer;
						      });

			       $roi->answers = $answers;

			       // AnswersPatterns
      
			       $answersPatternsQuery = 
				 "SELECT id " .
				 " FROM  iherba_roi_answers_pattern " .
				 " WHERE id_roi = " . $context->quote($roiId);

			       $roi->answersPatterns =
				 $context->mapResults(// Query
						      $answersPatternsQuery,

						      // For each AnswersPattern...
						      function($answersPatternRow) use ($context) {
							$apId = $answersPatternRow->id;
							$ap = $context->loadAnswersPattern($apId);
							return $ap;
						      });

			       $context->debug("Ok", "Loaded ROI $roiId.", $roi);
			       return $roi;
			     },

			     // If the ROI doesn't exist...
			     function() use ($context, $roiId) {
			       $context->debug("Error", "ROI with id = '$roiId' doesn't exist!");
			       return NULL;
			     });

  }

  public function deleteROI($roiId) {
    
    // Workaround...
    $context = $this;

    // Emulating function overloading: 
    // If we are already given the TypoherbariumROI object.
    if($roiId instanceof TypoherbariumROI) {
      $roi = $roiId;
      $roiId = $roi->id;
    } else {
      $roi = $context->loadROI($roiId);
    }

    $context->debug("Begin", "Delete ROI $roiId...");

    // Delete all ROI's files.
    $context->deleteROIFileVersions($roi);

    // Delete all ROI's Tags.
    array_iter(array($context, "deleteTag"), $roi->tags);

    // Delete all ROI's Answers and AnswersPatterns.
    array_iter(array($context, "deleteAnswer"), $roi->answers);
    array_iter(array($context, "deleteAnswersPattern"), $roi->answersPatterns);
    
    // Delete all Question and Comparison Tasks.
    $context->deleteTasksForROI($roi);

    // Delete the ROI.
    $deleteROIQuery =
      "DELETE FROM iherba_roi" . 
      " WHERE id = " . $context->quote($roiId);
    
    $ROIAffected =& $context->exec($deleteROIQuery);

    $context->debug("Ok", "Deleted ROI $roiId!");
    
  }

  public function deleteROIFileVersions($roi) {
    
    // Workaround...
    $context = $this;

    // Array of files with all versions of this ROI (currently: source and vignette).
    $roiVersionsPaths = 
      array_map(
		function($roiFileVersion) {
		  return $roiFileVersion->path();
		},
		$roi->fileVersions);

    // Delete each file...
    array_iter(array($context, "deleteFile"), $roiVersionsPaths);

  }
  
  public function deleteROIsByPhoto($photoId) {
    
    // Workaround...
    $context = $this;

    $context->debug("Begin", "Delete ROIs of Photo $photoId...");

    $photo = $context->loadPhoto($photoId);
    
    array_iter(array($context, "deleteROI"), $photo->rois);
    
    $context->debug("Ok", "Deleted ROIs of Photo $photoId!");

  }


  // TAG

  public function addTagToROI(TypoherbariumTag $tag, $roiId) {
    
    // Workaround...
    $context = $this;

    // TODO
  }

  public function loadTag($tagId) {

    // Workaround...
    $context = $this;

    // If we are already given the Tag object.
    if($tagId instanceof TypoherbariumTag)
      return $tagId;

    $tagQuery = 
      "SELECT id, id_roi, id_tag, id_user" .
      " FROM iherba_roi_tag" .
      " WHERE id = " . $context->quote($tagId);
    
    return
      $context->singleResult(// Tag's query.
			     $tagQuery,
			  
			     // If the Tag exists...
			     function($row) use ($context) {
			    
			       // Prepare the Tag.
			       $tag = new TypoherbariumTag();
			    
			       $tag
				 ->setId($row->id)
				 ->setRoiId($row->id_roi)
				 ->setTagId($row->id_tag)
				 ->setUid($row->id_user);

			       // Get data about this type of Tags (tagId).
			       $tagId = $tag->tagId;

			       $tagQuery = 
				 "SELECT *" .
				 " FROM iherba_tags" .
				 " WHERE id_tag = " . $context->quote($tag->tagId);
			    
			       $tagInfo =
				 $context->singleResult(// Tag's query.
							$tagQuery,
						     
							// If the Tag exists...
							function($row) use ($context) {
							  return $row;
							},
						     
							// If the Tag doesn't exist...
							function() use ($context, $tagId) {
							  $context->debug("Error", "Tag with tagId = '$tagId' doesn't exist!");
							  return NULL;
							});
			    
			       if( ! is_null($tagInfo) ) { 
				 $tag->setKind($tagInfo->id_genre);
			       }
			    
			       return $tag;
			     },
			  
			     // If the Tag doesn't exist...
			     function() use ($context, $tagId) {
			       $context->debug("Error", "Tag with id = '$tagId' doesn't exist!");
			       return NULL;
			     });
  }

  public function deleteTag($tagId) {
    
    // Workaround...
    $context = $this;

    // Emulating function overloading: 
    // If we are already given the TypoherbariumTag object.
    if($tagId instanceof TypoherbariumTag)
      $tagId = $tagId->id;

    $context->debug("Begin", "Delete Tag $tagId...");

    // Delete the Tag.
    $deleteTagQuery =
      "DELETE FROM iherba_roi_tag" . 
      " WHERE id = " . $context->quote($tagId);
    
    $tagAffected =& $context->exec($deleteTagQuery);

    $context->debug("Ok", "Deleted Tag $tagId!");
    
  }

  public function loadTagTranslations() {

    // Workaround...
    $context = $this;

    // Fetch all Tag translations.
    $tagTranslationsQuery = 
      "SELECT * " .
      " FROM iherba_tags_translation";

    $t = array();

    $context->iterResults(// Query
			  $tagTranslationsQuery,

			  // For each Tag's translation.
			  function($tagRow) use ($context, &$t) {
			    $tagId = $tagRow->id_tag;
			    $lang  = $tagRow->id_langue;
			    
			    $tagParams = 
			      array(
				    'text'         => $tagRow->texte,
				    'questionText' => $tagRow->texte_question
				    );
			    
			    $t[$tagId][$lang] = $tagParams;
			  });
			  
    return $t;
			  
  }
  
  // ANSWER

  public function addAnswerToROI(TypoherbariumROIAnswer $answer, $roiId) {
    
    // Workaround...
    $context = $this;

    // TODO
  }
  
  public function loadAnswer($answerId) {

    // Workaround...
    $context = $this;

    // If we are already given the Answer object.
    if($answerId instanceof TypoherbariumROIAnswer)
      return $answerId;

    // Fetch the Answer.
    $answerQuery = 
      "SELECT id, id_roi, QuestionType, id_question, answer, identifiant_internaute AS AskId, ipinternaute, source, referrant " .
      " FROM iherba_roi_answer" .
      " WHERE id = " . $context->quote($answerId);

    return
      $context->singleResult(// Answer's query.
			     $answerQuery,
			  
			     // If the Answer exists...
			     function($row) {
			    
			       $questionType = $row->questiontype;

			       // Prepare the Answer.
			       $answer = new TypoherbariumROIAnswer();
			    
			       $answer
				 ->setId           ($row->id)
				 ->setQuestionType ($row->questiontype)
				 ->setQuestionId   ($row->id_question)
				 ->setRoiId        ($row->id_roi)
				 ->setAnswerValue  ($row->answer)
				 ->setAskId        ($row->askid)
				 ->setInternautIp  ($row->ipinternaute)
				 ->setSource       ($row->source)
				 ->setReferrant    ($row->referrant);

			       return $answer;
			     },
			  
			     // If the Answer doesn't exist...
			     function() use ($context, $answerId) {
			       $context->debug("Error", "Answer with id = '$answerId' doesn't exist!");
			       return NULL;
			     });
  }

  public function saveAnswer(TypoherbariumROIAnswer $answer) {
    
    // Workaround...
    $context = $this;

    // Insert the Answer.
    $insertAnswerQuery = 
      "INSERT INTO" .
      " iherba_roi_answer (id_roi, QuestionType, id_question, id_comparaison, answer, identifiant_internaute, ipinternaute, tsdepot, source, referrant, AskId)" .
      " VALUES( " . $context->quote($answer->roiId) .         // id_roi
      " , "       . $context->quote($answer->questionType) .  // QuestionType
      " , "       . $context->quote($answer->questionId) .    // id_question
      " , "       . $context->quote(0) .                      // id_comparison
      " , "       . $context->quote($answer->answerValue) .   // answer
      " , "       . $context->quote("") .                     // identifiant_internaute
      " , "       . $context->quote($answer->internautIp) .   // ipinternaute
      " , "       . "now()" .                                 // tsdepot
      " , "       . $context->quote($answer->source) .        // source
      " , "       . $context->quote($answer->referrant) .     // referrant
      " , "       . $context->quote($answer->askId) .         // AskId
      " )";
      
    $affectedAnswer = $context->exec($insertAnswerQuery);

    // Get the Id.
    $answerId = $context->lastInsertID("iherba_roi_answer", "id");
    $answer->id = $answerId;
    
    return $answer;
  }

  public function deleteAnswer($answerId) {
    
    // Workaround...
    $context = $this;

    // Emulating function overloading: 
    // If we are already given the TypoherbariumROIAnswer object.
    if($answerId instanceof TypoherbariumROIAnswer)
      $answerId = $answerId->id;

    $context->debug("Begin", "Delete Answer $answerId...");

    // Delete the Answer.
    $deleteAnswerQuery =
      "DELETE FROM iherba_roi_answer" .
      " WHERE id = " . $context->quote($answerId);
    
    $answerAffected =& $context->exec($deleteAnswerQuery);

    $context->debug("Ok", "Deleted Answer $answerId!");
    
  }

  // ANSWERS PATTERN

  public function loadAnswersPattern($apId) {

    // Workaround...
    $context = $this;

    // If we are already given the AnswersPattern object.
    if($apId instanceof TypoherbariumROIAnswersPattern)
      return $apId;

    // Fetch the AnswersPattern.
    $apQuery = 
      "SELECT * " .
      " FROM  iherba_roi_answers_pattern " .
      " WHERE id = " . $context->quote($apId);

    return
      $context->singleResult(// AnswersPattern's query.
			     $apQuery,
			  
			     // If the Ap exists...
			     function($row) {
			    
			       $ap = new TypoherbariumROIAnswersPattern();
    
			       // COPY PROPRIETIES DIRECTLY FROM ROW - DISABLED
			       /*
				 $vars = get_object_vars($row);
				 foreach($vars as $var => $val) {
				 $ap->$var = $var;
				 }
			       */

			       $ap
				 ->setId           ($row->id)           // Id
				 ->setQuestionType ($row->questiontype) // Question Type
				 ->setQuestionId   ($row->id_question)  // Question Id
				 ->setRoiId        ($row->id_roi);      // ROI Id
			    
			       $allAnswersRaw = $row->pattern_answers;

			       if( preg_match('/^(\d+:\d+(\.\d+)?;)+$/', $allAnswersRaw) > 0 ) {
			      
				 // Old format : [answer:probability;]+
			      
				 $answers = array_map(
						      function($answer) {
							$array =
							  explode(":", $answer);
						   
							return
							  array("id" => $array[0],
								"pr" => $array[1]);
						   
						      },
						      explode(";", $allAnswersRaw, -1)
						      );
			      
				 $ap->answers = $answers;
			      
			       } else if ( preg_match('/^a:/', $allAnswersRaw) > 0 ) {
			      
				 // New format : serialized array.
			      
				 $answers = unserialize($allAnswersRaw);
				 if(! $answers) echo $row->id;

				 $ap->answers = $answers;
			      
			       } else {

				 // All Answers not available! Just take 2 best.
			      
				 // Best Answer
				 $ap->addAnswer(
						array("id" => $row->id_answer_most_common, 
						      "pr" => $row->prob_most_common / 100), 
						0);
			      
				 // Second best Answer
				 if($row->prob_just_less > 0) {
				   $ap->addAnswer(
						  array("id" => $row->id_just_less_common, 
							"pr" => $row->prob_just_less / 100),
						  1);
				 }

			       }

			       return $ap;

			     },
			  
			     // If the Ap doesn't exist...
			     function() use ($context, $apId) {
			       $context->debug("Error", "AnswersPattern with id = '$apId' doesn't exist!");
			       return NULL;
			     });
  }

  public function saveAnswersPattern(TypoherbariumROIAnswersPattern $ap) {

    // Workaround...
    $context = $this;

    // Extract two first answers.
    $firstAnswer = $ap->answers[0];
    $secondAnswer = (
		     isset($ap->answers[1]) ? 
		     $ap->answers[1] :
		     array("id" => 0, "pr" => 0)
		     );    

    // Insert the AnswersPattern.
    $insertAPQuery = 
      "INSERT INTO" .
      " iherba_roi_answers_pattern (id_roi, QuestionType, id_question, id_comparaison, id_answer_most_common, prob_most_common, id_just_less_common, prob_just_less, pattern_answers)" .
      " VALUES( " . $context->quote($ap->roiId) .
      " , "       . $context->quote($ap->questionType) .
      " , "       . $context->quote($ap->questionId) .
      " , "       . $context->quote(0) .
      " , "       . $context->quote($firstAnswer["id"]) .
      " , "       . $context->quote($firstAnswer["pr"] * 100) .
      " , "       . $context->quote($secondAnswer["id"]) .
      " , "       . $context->quote($secondAnswer["pr"] * 100) .
      " , "       . $context->quote(serialize($ap->answers)) .
      " )";
      
    $affectedAP = $context->exec($insertAPQuery);

    // Get the Id.
    $apId = $context->lastInsertID("iherba_roi_answers_pattern", "id");
    $ap->id = $apId;
    
    return $ap;
  }

  public function deleteAnswersPattern($apId) {
    
    // Workaround...
    $context = $this;

    // Emulating function overloading: 
    // If we are already given the TypoherbariumROIAnswersPattern object.
    if($apId instanceof TypoherbariumROIAnswersPattern)
      $apId = $apId->id;

    $context->debug("Begin", "Delete AnswersPattern $apId...");

    // Delete the AnswersPattern.
    $deleteAnswersPatternQuery =
      "DELETE FROM iherba_roi_answers_pattern" .
      " WHERE id = " . $context->quote($apId);
    
    $answersPatternAffected =& $context->exec($deleteAnswersPatternQuery);

    $context->debug("Ok", "Deleted AnswersPattern $apId!");
    
  }


  // QUESTION

  public function loadQuestion($qId) {

    // Workaround...
    $context = $this;

    // If we are already given the TypoherbariumROIQuestion object.
    if($qId instanceof TypoherbariumROIQuestionForm)
      return $qId;

    $questionQuery = 
      "SELECT * " .
      " FROM  iherba_question " .
      " WHERE id_question = " . $context->quote($qId) .
      " AND id_langue = 'fr'"; // Default.
    
    return
      $context->singleResult(// Question's Query
			     $questionQuery,
			     
			     // If Question exists...
			     function($qRow) use ($context, &$question) {
			       
			       $question = new TypoherbariumROIQuestion();

			       // Id.
			       $question->setId($qRow->id_question);
			    
			       // Choices.
			       $rawChoices = explode("!", $qRow->textes_reponses);
			     
			       foreach($rawChoices as $answer => $rawChoice) {
				 $question->addChoice($answer, $answer);
			       }
			     
			       // Constraints.
			       $question
				 ->setNecessaryTagId($qRow->id_tag_necessaire)
				 ->setNecessaryQuestionId($qRow->id_question_necessaire)
				 ->setNecessaryAnswer($qRow->id_reponse_necessaire)
				 ->setNecessaryGrid($qRow->grille);

			       return $question; 
			     },
			     
			     // If Question doesn't exist...
			     function() {
			       $context->debug("Error", "Question with id = '$apId' doesn't exist!");
			       return NULL;			       
			     });
  }

  public function loadQuestionTranslations() {

    // Workaround...
    $context = $this;

    $questionQuery = 
      "SELECT * " .
      " FROM iherba_question";

    $imageSrcDir = "../dessins/w130/";

    // Question Translations
    $q = array();

    $context->iterResults(// Query
			  $questionQuery,
			   
			  // For each Question's language version...
			  function($qVersionRow) use ($context, &$q, $imageSrcDir) {
			    $question = new ClosedChoiceQuestion();

			    // Language of this version.
			    $lang = $qVersionRow->id_langue;

			    // Question id and type
			    $qId = $qVersionRow->id_question;
			    $question->setId($qId);
			    $question->setType("ROIQuestion");
			    
			    // Question text.
			    $question->setText($qVersionRow->texte_question);
			    
			    // Choices.
			    $rawChoices   = explode("!", $qVersionRow->textes_reponses);
			    $descriptions = explode("!", $qVersionRow->choice_detail);
			    
			    foreach($rawChoices as $answer => $rawChoice) {
			      $choice = new Choice();
			      
			      // Answer value (I don't know yet if it's useful).
			      $choice->setAnswerValue($answer);

			      // Text or source of an image.			      
			      if((!(strpos($rawChoice, ".jpg") === false)) ||
				 (!(strpos($rawChoice, ".png") === false)) ) {
				$choice
				  ->setImageSrcDir($imageSrcDir)
				  ->setImageSrcFilename($rawChoice);
			      } else {
				$choice->setText($rawChoice);
			      }
			      
			      // Description.
			      if(isset($descriptions[$answer]))
				$choice->setDescription($descriptions[$answer]);

			      // Add to the question.
			      $question->addChoice($choice, $answer);
			    }
			    
			    $q[$qId][$lang] = $question;

			  });
    
    return $q;
  }

  public function loadQuestionsSchema() {
    
    // Workaround...
    $context = $this;

    $schema = array();

    // Create a base of all Questions wrapped in QuestionSchemaNodes.
    $questionQuery = 
      "SELECT * " .
      " FROM  iherba_question " .
      " WHERE disabled = 0" .
      " AND id_langue = 'fr'"; // Default.
    
    $schema = new TypoherbariumQuestionSchema();

    $context->iterResults(// Query
			  $questionQuery,
			   
			  // For each Question's language version...
			  function($qRow) use ($context, &$schema) {
			    $qId = $qRow->id_question;
			    
			    $schema->addQuestion($context->loadQuestion($qId));
			  });
    
    return $schema;
  }

  public function logQuestionAsked(TypoherbariumAskLog $log) {
    
    // Workaround...
    $context = $this;

    // Insert the Log.
    $insertLogQuery = 
      "INSERT INTO" .
      " iherba_log_questions (id_roi, questiontype, id_question, identifiant_internaute, ipinternaute, langue, datequestion)" .
      " VALUES( " . $context->quote($log->context) .
      " , "       . $context->quote($log->questionType) .
      " , "       . $context->quote($log->questionId) .
      " , "       . $context->quote($log->internautId) .
      " , "       . $context->quote($log->internautIp) .
      " , "       . $context->quote($log->lang) .
      " , now()" .
      " )";
      
    $affectedLog = $context->exec($insertLogQuery);

    // Get the Id.
    $logId = $context->lastInsertID("iherba_log_questions", "id_log_questions");
    $log->id = $logId;
    
    return $log;
  }
  
  // ANSWERS PATTERN MODEL

  public function loadTranslation($translationId) {
    
    // Workaround...
    $context = $this;
    
    $translation = array();

    // Get the translation.
    $translationQuery =
      "SELECT * " .
      " FROM iherba_apmodel_answer_translation" .
      " WHERE TranslationId = " . $context->quote($translationId);
    
    $context->iterResults(// Query
			  $translationQuery,
			   
			  function($row) use (&$translation) {
			    $translation[$row->from] = $row->to;
			  });

    return $translation;
  }

  public function loadExceptionsHandling($questionId) {
    
    // Workaround...
    $context = $this;
    $exceptionsHandling = array();

    // Get the exceptionsHandling.
    $exceptionsHandlingQuery =
      "SELECT * " .
      " FROM iherba_apmodel_question_exception_handling" .
      " WHERE QuestionId = " . $context->quote($questionId);

    $context->iterResults(// Query
			  $exceptionsHandlingQuery,
		       
			  function($row) use (&$exceptionsHandling) {
			    $exception = $row->exception;
			    $exceptionsHandling[$exception]["handling"] = $row->exceptionhandling;
			    $exceptionsHandling[$exception]["options"]  = $row->exceptionhandlingoptions;
			  });

    return $exceptionsHandling;
  }

  public function loadProximityMatrix($questionId) {
    
    // Workaround...
    $context = $this;

    $matrix = array();
    
    // Get the matrix.
    $matrixQuery =
      "SELECT * " .
      " FROM iherba_apmodel_proximity_matrix" .
      " WHERE QuestionId = " . $context->quote($questionId);

    $context->iterResults(// Query
			  $matrixQuery,
			   
			  function($row) use (&$matrix) {
			    $matrix[$row->from][$row->to] = $row->distance;
			  });
    
    return $matrix;
  }

  public function loadColorPalette($paletteId) {
    
    // Workaround...
    $context = $this;
    
    $palette = array();
    
    // Get the palette.
    $paletteQuery =
      "SELECT * " .
      " FROM iherba_apmodel_color_rgb" .
      " WHERE Palette = " . $context->quote($paletteId);

    $context->iterResults(// Query
			  $paletteQuery,
		       
			  function($row) use (&$palette) {
			    $palette[$row->color]['R'] = $row->red;
			    $palette[$row->color]['G'] = $row->green;
			    $palette[$row->color]['B'] = $row->blue;
			  });
    
    return $palette;
  }

  public function loadQuestionsOptions() {

    // Workaround...
    $context = $this;    

    $options = array();

    // Get all Questions Options.
    $questionsOptionsQuery =
      "SELECT * " .
      " FROM iherba_apmodel_question_options";

    $context->iterResults(// Query
			  $questionsOptionsQuery,
		       
			  function($row) use ($context, &$options) {
			 
			    $qId = $row->questionid;
			 
			    // AnswerValueType
			    $avType = $row->answervaluetype;
			    $options[$qId]['answerValueType'] = $avType;
			 
			    // AnswerValueTranslation
			    $defaultTranslationId = "Default";
			    $defaultTranslation = $context->loadTranslation($defaultTranslationId);
			    $options[$qId]['answerValueTranslation'][] = $defaultTranslation;
			 
			    if($avType === 'Translate') {
			      $groupTranslationId = $row->answervaluetranslation;
			      $groupTranslation = $context->loadTranslation($groupTranslationId);
			      $options[$qId]['answerValueTranslation'][] = $groupTranslation;
			   
			      $specificTranslationId = $qId;
			      $specificTranslation = $context->loadTranslation($specificTranslationId);
			      $options[$qId]['answerValueTranslation'][] = $specificTranslation;
			    }
			 
			    // DistanceFunction
			    $df = $row->distancefunction;
			    $options[$qId]['distanceFunction'] = $df;
			 
			    // ProximityMatrix
			    if($df === 'ProximityMatrix') {
			      $matrix = $context->loadProximityMatrix($qId);
			   
			      $options[$qId]['proximityMatrix'] = $matrix;
			    }
			 
			    // ExceptionsHandling
			    $eh = $context->loadExceptionsHandling($qId);
			    $options[$qId]['exceptionsHandling'] = $eh;
			 
			    // Weight
			    $options[$qId]['weight'] = $row->weight;
			  });
    
    return $options;
  }

  public function loadTagsOptions() {

    // Workaround...
    $context = $this;    

    $options = array();

    // Get all Tags Options.
    $tagsOptionsQuery =
      "SELECT * " .
      " FROM iherba_apmodel_tag_options";

    $context->iterResults(// Query
			  $tagsOptionsQuery,
		       
			  function($row) use ($context, &$options) {
			 
			    $tagId = $row->tagid;
			 
			    // QuestionsWeight
			    $questionsWeight = $row->questionsweight;
			    $options[$tagId]['QuestionsWeight'] = $questionsWeight;
			    
			    // ComparisonsWeight
			    $comparisonsWeight = $row->comparisonsweight;
			    $options[$tagId]['ComparisonsWeight'] = $comparisonsWeight;
			  });
    
    return $options;
  }

  // TASKS

  public function addTask(TypoherbariumTask $task) {
    
    // Workaround...
    $context = $this;
    
    // Type
    $taskType = $task->getType();

    // Context & ContextType
    switch($taskType) {
    case "ROIQuestion":
    case "ROIComparison":
      // In both cases it's a ROI id.
      $taskContext = $task->getRoiId();
      break;
    case "ComputeObservationSimilarities":
    case "ComparisonsFinished":
    case "AddObservationToDeterminationFlow":
      // In this case it's the Observation's id.
      $taskContext = $task->context->id;
      break;
    }
    
    // Parameters
    switch($taskType) {
    case "ROIQuestion":
    case "ROIComparison":
      // It's a Question id OR it's a list of ROI ids.
      $taskParams = $task->getQuestionId();
      break;
    case "ComputeObservationSimilarities":
    case "ComparisonsFinished":
    case "AddObservationToDeterminationFlow":
      // In this case there are no parameters.
      $taskParams = $task->parameters;
      break;
    }

    // Query
    $insertTaskQuery =
      "INSERT INTO" .
      " iherba_task (Category, Type, ContextType, Context, ParametersType, Parameters, StopCondition, Priority, Protocol)" .
      " VALUES( " . $context->quote($task->getCategory()) .
      " , "       . $context->quote($task->getType()) .
      " , "       . $context->quote($task->getContextType()) .
      " , "       . $context->quote($taskContext) .
      " , "       . $context->quote($task->getParametersType()) .
      " , "       . $context->quote($taskParams) .
      " , "       . ( ($task instanceof TypoherbariumAnswerableTask) ? ($context->quote(serialize($task->stopCondition))) : $context->quote(NULL)) .
      " , "       . $context->quote($task->priority) .
      " , "       . $context->quote($task->protocol) .
      " )";

    $affectedTask = $context->exec($insertTaskQuery);

    $taskId = $context->lastInsertID("iherba_task", "Id");
    $task->id = $taskId;

    return $task;
  }

  public function loadTask($taskId) {

    // Workaround...
    $context = $this;
     
    // Task Query.
    $taskQuery =
      "SELECT * " .
      " FROM iherba_task" .
      " WHERE id = " . $context->quote($taskId);

    return
      $context->singleResult(// Query
			     $taskQuery,
			  
			     // If the Task exists...
			     function($row) use ($context, $taskId) {
			       // We fetched the task!
			       $taskCategory   = $row->category;
			       $taskType       = $row->type;
			       $taskContext    = $row->context;
			       $taskParameters = $row->parameters;

			       $task = NULL;

			       // Context
			       switch($taskType) {
			       case "ROIQuestion":
				 $roiId = $taskContext;
				 $qId   = $taskParameters;

				 $roi = $context->loadROI($roiId);
				 $q   = $context->loadQuestion($qId);

				 $task = 
				   TypoherbariumTask::makeROIQuestionTask($roi, $q)
				   ->setId($taskId);
				 
				 // Stop Condition
				 $task->stopCondition = unserialize($row->stopcondition);
				 
				 // Answers
			      
				 $answersQuery = 
				   "SELECT id " .
				   " FROM iherba_roi_answer" .
				   " WHERE id_roi = "     . $context->quote($roi->id) .
				   " AND id_question = "  . $context->quote($q->id) .
				   " AND QuestionType = " . $context->quote("ROIQuestion");

				 $task->answers =
				   $context->mapResults(// Query
							$answersQuery,
						   
							// For each Answer...
							function($answerRow) use ($context) {
							  $answerId = $answerRow->id;
							  $answer = $context->loadAnswer($answerId);
							  return $answer;
							});
			      
				 // AnswersPatterns
      
				 $answersPatternsQuery = 
				   "SELECT id " .
				   " FROM  iherba_roi_answers_pattern " .
				   " WHERE id_roi = "     . $context->quote($roi->id) .
				   " AND id_question = "  . $context->quote($q->id);

				 $task->answersPatterns =
				   $context->mapResults(// Query
							$answersPatternsQuery,

							// For each AnswersPattern...
							function($answersPatternRow) use ($context) {
							  $apId = $answersPatternRow->id;
							  $ap = $context->loadAnswersPattern($apId);
							  return $ap;
							});
			      
				 break;

			       case "ROIComparison":
				 $roiId = $taskContext;
				 $roisIds = json_decode($taskParameters);

				 $roi  = $context->loadROI($roiId);
				 $rois = array_map(function($roiId) use ($context) { return $context->loadROI($roiId); }, $roisIds);

				 $task = 
				   TypoherbariumTask::makeROIComparisonTask($roi, $rois)
				   ->setId($taskId);


				 // Stop Condition
				 $task->stopCondition = unserialize($row->stopcondition);
				 
				
				 // Answers
			      
				 $answersQuery = 
				   "SELECT id " .
				   " FROM iherba_roi_answer" .
				   " WHERE id_roi = "     . $context->quote($roi->id) .
				   " AND QuestionType = " . $context->quote("ROIComparison");

				 $task->answers =
				   $context->mapResults(// Query
							$answersQuery,
						   
							// For each Answer...
							function($answerRow) use ($context) {
							  $answerId = $answerRow->id;
							  $answer = $context->loadAnswer($answerId);
							  return $answer;
							});
			      
				 // AnswersPatterns
      
				 $answersPatternsQuery = 
				   "SELECT id " .
				   " FROM  iherba_roi_answers_pattern " .
				   " WHERE id_roi = "        . $context->quote($roi->id) .
				   " AND   QuestionType = "  . $context->quote("ROIComparison");

				 $task->answersPatterns =
				   $context->mapResults(// Query
							$answersPatternsQuery,

							// For each AnswersPattern...
							function($answersPatternRow) use ($context) {
							  $apId = $answersPatternRow->id;
							  $ap = $context->loadAnswersPattern($apId);
							  return $ap;
							});
			      
				 break;

			       case "ComputeObservationSimilarities":
				 $obsId = $taskContext;
				 
				 $obs = $context->loadObservation($obsId);
				 
				 $task = 
				   TypoherbariumTask::makeComputeObservationSimilaritiesTask($obs)
				   ->setId($taskId);

				 break;

             case "ComparisonsFinished":
         $obsId = $taskContext;
         
         $obs = $context->loadObservation($obsId);
         
         $task = 
           TypoherbariumTask::makeComparisonsFinishedTask($obs)
           ->setId($taskId);

         break;

             case "AddObservationToDeterminationFlow":
         $obsId = $taskContext;
         
         $obs = $context->loadObservation($obsId);
         
         $task = 
           TypoherbariumTask::makeAddObservationToDeterminationFlowTask($obs)
           ->setId($taskId);

         break;
			       }

			       // Priority
			       $task->setPriority = $row->priority;

			       // Protocol
			       $task->setProtocol = $row->protocol;
					
			       $context->debug("Ok", "Task with id = '$taskId' loaded!.");
			       return $task;
			     },
			  
			     // If the Task doesn't exist...
			     function() use ($context, $taskId) {
			       $context->debug("Error", "Task with id = '$taskId' doesn't exist!");
			       return NULL;
			     }); 
    
  }

  public function loadNextTask($category = "Answerable") {

    // Workaround...
    $context = $this;
     
    // Minimum priority query.
    $minQuery = "SELECT MIN(Priority) AS MinPriority FROM iherba_task";

    $minPriority =
      $context->singleResult(// Query
			     $minQuery,
			  
			     // If the Min exists...
			     function($row) use ($context) {
			       // We fetched the minimum priority!
			       return $row->minpriority;
			     },
			    
			     // If the Min doesn't exist...
			     function() use ($context) {
			       $context->debug("Error", "Minimum priority doesn't exist!");
			       return NULL;
			     });
			
    if( is_null($minPriority) )
      return NULL;
    
    // Task Query.
    $taskQuery =
      "SELECT Id " .
      " FROM iherba_task" .
      " WHERE Priority = " . $context->quote($minPriority) .
      " AND Category = " . $context->quote($category) .
      " ORDER BY RAND()" .
      " LIMIT 1";

    return
      $context->singleResult(// Query
			     $taskQuery,
			  
			     // If the Task exists...
			     function($row) use ($context) {
			       // We fetched the task!
			       return $context->loadTask($row->id);
			     },

			     // If the Task doesn't exist...
			     function() use ($context, $minPriority) {
			       $context->debug("Error", "Task with priority = '$minPriority' doesn't exist!");
			       return NULL;
			     });
  }

  public function loadEqualTask(TypoherbariumTask $task) {
    
    // Workaround...
    $context = $this;
    
    // Task Query.
    $taskQuery =
      "SELECT Id " .
      " FROM iherba_task" .
      " WHERE Type = "         . $context->quote($task->getType()) .
      " AND ContextType = "    . $context->quote($task->getContextType()) .
      " AND Context = "        . $context->quote($task->getRoiId()) .
      " AND ParametersType = " . $context->quote($task->getParametersType()) .
      " AND Parameters = "     . $context->quote($task->getQuestionId());
    
    return
      $context->singleResult(// Query
			     $taskQuery,
			  
			     // If the Task exists...
			     function($row) use ($context) {
			       // We fetched the task!
			       return $context->loadTask($row->id);
			     },
			  
			     // If the Task doesn't exist...
			     function() use ($context) {
			       $context->debug("Ok", "Task equal to the given Task doesn't exist!");
			       return NULL;
			     });
  }

  public function loadTaskByParams($type, $roiId, $questionId = NULL) {

    // Workaround...
    $context = $this;
    
    // Task Query.
    $taskQuery =
      "SELECT Id " .
      " FROM iherba_task" .
      " WHERE Type = " . $context->quote($type) .
      " AND ContextType = " . $context->quote("ROI") .
      " AND Context = " . $context->quote($roiId) .
      ($type === "ROIQuestion" ?
       " AND ParametersType = " . $context->quote("Question") .
       " AND Parameters = " . $context->quote($questionId) :
       "");

    return
      $context->singleResult(// Query
			     $taskQuery,
			  
			     // If the Task exists...
			     function($row) use ($context) {
			       // We fetched the task!
			       return $context->loadTask($row->id);
			     },

			     // If the Task doesn't exist...
			     function() use ($context) {
			       $context->debug("Ok", "Task with given parameters doesn't exist!");
			       return NULL;
			     });
  }


  public function deleteTask($taskId) {
    
    // Workaround...
    $context = $this;

    // Emulating function overloading: 
    // If we are already given the TypoherbariumTask object.
    if($taskId instanceof TypoherbariumTask)
      $taskId = $taskId->id;

    $context->debug("Begin", "Delete Task $taskId...");

    // Delete the Task.
    $deleteTaskQuery =
      "DELETE FROM iherba_task" . 
      " WHERE Id = " . $context->quote($taskId);
    
    $taskAffected =& $context->exec($deleteTaskQuery);

    $context->debug("Ok", "Deleted Task $taskId!");

  }

  public function loadTasksForROI(TypoherbariumROI $roi) {
    
    // Workaround...
    $context = $this;
    
    // Task Query.
    $tasksQuery =
      "SELECT Id " .
      " FROM iherba_task" .
      " WHERE ContextType = " . $context->quote("ROI") .
      " AND Context = "       . $context->quote($roi->id);

    return
      $context->mapResults(// Query
			   $tasksQuery,
			 
			   // For each Task.
			   function($row) use ($context) {
			     $task = $context->loadTask($row->id);
			     return $task;
			   });
  }

  public function deleteTasksForROI(TypoherbariumROI $roi) {
    
    // Workaround...
    $context = $this;

    $context->debug("Begin", "Delete Tasks for ROI $roi->id...");

    // Task Query.
    $tasksQuery =
      "SELECT Id " .
      " FROM iherba_task" .
      " WHERE ContextType = " . $context->quote("ROI") .
      " AND   Context = "     . $context->quote($roi->id);

    $context->iterResults(// Query
			  $tasksQuery,

			  // For each Task.
			  function($row) use ($context) {
			    $task = $context->loadTask($row->id);
			    $context->deleteTask($task);
			  });

    $context->debug("Ok", "Deleted Tasks for ROI $roi->id!");

  }
  
  // GROUP

  // Load basic Groups information as an array of.
  public function loadGroups() {

    // Workaround...
    $context = $this;
    
    // Groups Query.
    $groupsQuery =                                                
      " SELECT * " .
      " FROM iherba_group " .
      " ORDER BY Id ASC";
    
    $groups = array();
    
    $context->iterResults(// Groups query.
			  $groupsQuery,
			  
			  // For each Group...
			  function($groupRow) use ($context, &$groups) {
			    $group = $groupRow;
			    $groups[$group->id] = $group;
			  });
    
    return $groups;
  }

  public function loadGroup($groupId) {
    
    // Workaround...
    $context = $this;
    
    // Group Query.
    $groupQuery =
      "SELECT * " .
      " FROM iherba_group" .
      " WHERE Id = " . $context->quote($groupId);
    
    return
      $context->singleResult(// Query
			     $groupQuery,
			  
			     // If the Group exists...
			     function($row) use ($context) {
			       // We fetched the group!
			    
			       $group = new TypoherbariumGroup();
			    
			       $group
				 ->setId($row->id)
				 ->setName($row->name);
			    
			       // Group's Observations.
			       $obsQuery =
				 "SELECT ObservationId " .
				 " FROM iherba_group_observations" .
				 " WHERE GroupId = " . $context->quote($group->id);
			    
			       $context->iterResults(// Query
						     $obsQuery,
						  
						     // For each Observation...
						     function($row) use ($context, $group) {
						       $obsId = $row->observationid;
						       $obs = $context->loadObservation($obsId);
						    
						       $group->addObservation($obs, $obsId);
						     });

			       // Group's Included Groups.
			       $obsQuery =
				 "SELECT IncludedGroupId " .
				 " FROM iherba_group_includes" .
				 " WHERE IncludingGroupId = " . $context->quote($group->id);
			    
			       $context->iterResults(// Query
						     $obsQuery,
						  
						     // For each Observation...
						     function($row) use ($context, $group) {
						       $includedGroupId = $row->includedgroupid;
						       $includedGroup   = $context->loadGroup($includedGroupId);
						    
						       $group->addIncludedGroup($includedGroup, $includedGroup->id);
						     });

			       return $group;
			     },
			  
			     // If the Group doesn't exist...
			     function() use ($context, $groupId) {
			       $context->debug("Error", "Group with Id = $groupId doesn't exist!");
			       return NULL;
			     });
  }

  public function createGroup(TypoherbariumGroup $group) {

    // Workaround...
    $context = $this;
    
    $insertGroupQuery =
      "INSERT INTO iherba_group(Id, Name)" .
      " VALUES( NULL " .                      // Auto increment Id
      " , " . $context->quote($group->name) . // Name
      " )";
    
    $affectedGroup = $context->exec($insertGroupQuery);

    // Observations
    array_iter(
	       function($obs) use ($context, $group) {
		 
		 $insertObsQuery =
		   "INSERT INTO iherba_group_observations(GroupId, ObservationId)" .
		   " VALUES( " .
		   " , " . $context->quote($group->id) .
		   " , " . $context->quote($obs->id) .
		   " )";
		 
		 $affectedObs = $context->exec($insertObsQuery);
	       },
	       $group->observations);	 
    
    // Get the Id.
    $groupId = $context->lastInsertID("iherba_group", "Id");
    $group->id = $groupId;

    return $group;
  }

  public function deleteGroup(TypoherbariumGroup $group) {
    
    // Workaround...
    $context = $this;

    // Delete links with Observations.
    $deleteGroupObsQuery =
      "DELETE FROM iherba_group_observations" .
      " WHERE GroupId = " . $context->quote($group->id);

    $affectedGroupObs = $context->exec($deleteGroupObsQuery);

    // Delete includes.
    $deleteIncludeQuery =
      "DELETE FROM iherba_group_includes" .
      " WHERE IncludedGroupId = " . $context->quote($group->id);

    $affectedInclude = $context->exec($deleteIncludeQuery);
    
    // Delete the Group.
    $deleteGroupQuery =
      "DELETE FROM iherba_group" .
      " WHERE Id = " . $context->quote($group->id);

    $affectedGroup = $context->exec($deleteGroupQuery);
  }

  public function addObservationToGroup(TypoherbariumObservation $obs, TypoherbariumGroup $group) {
    
    // Workaround...
    $context = $this;
    
    $insertObsQuery =
      "INSERT INTO iherba_group_observations(GroupId, ObservationId)" .
      " VALUES( " . $context->quote($group->id) .
      " , "       . $context->quote($obs->id) .
      " )";

    $affectedObs = $context->exec($insertObsQuery);
    
    $group->addObservation($obs, $obs->id);
    return $group;
  }
  
  public function deleteObservationFromGroup(TypoherbariumObservation $obs, TypoherbariumGroup $group) {
    
    // Workaround...
    $context = $this;
    
    $deleteObsQuery =
      "DELETE FROM iherba_group_observations" .
      " WHERE GroupId = "       . $context->quote($group->id) .
      " AND   ObservationId = " . $context->quote($obs->id);

    $affectedObs = $context->exec($deleteObsQuery);
  }

  public function includeGroupInGroup(TypoherbariumGroup $includedGroup, TypoherbariumGroup $group) {
    
    // Workaround...
    $context = $this;
    
    $insertIncludedGroupQuery =
      "INSERT INTO iherba_group_includes(IncludingGroupId, IncludedGroupId)" .
      " VALUES( " . $context->quote($group->id) .
      " , "       . $context->quote($includedGroup->id) .
      " )";

    $affectedIncludedGroup = $context->exec($insertIncludedGroupQuery);
    
    $group->addIncludedGroup($includedGroup, $includedGroup->id);
    return $group;
  }
  
  public function excludeGroupFromGroup(TypoherbariumGroup $includedGroup, TypoherbariumGroup $group) {
    
    // Workaround...
    $context = $this;
    
    $deleteIncludedGroupQuery =
      "DELETE FROM iherba_group_includes" .
      " WHERE IncludingGroupId = " . $context->quote($group->id) .
      " AND   IncludedgroupId = "  . $context->quote($includedGroup->id);

    $affectedIncludedGroup = $context->exec($deleteIncludedGroupQuery);
  }

  public function loadGroupTranslations() {

    // Workaround...
    $context = $this;

    // Fetch all Group translations.
    $groupTranslationsQuery = 
      "SELECT * " .
      " FROM iherba_group_translation";

    $t = array();

    $context->iterResults(// Query
			  $groupTranslationsQuery,

			  // For each Group's translation.
			  function($row) use ($context, &$t) {
			    $groupId = $row->groupid;
			    $lang    = $row->lang;
			    
			    $groupParams = 
			      array(
				    'name'        => $row->name,
				    'description' => $row->description
				    );
			    
			    $t[$groupId][$lang] = $groupParams;
			  });
    
    return $t;		  
  }

  public function logDeterminationFinished(TypoherbariumObservation $obs, $wasSuccessful, $result, $info) {

    // Workaround...
    $context = $this;
    
    $insertDeterminationLogQuery =
      "INSERT INTO iherba_determination_log(ObservationId, WasSuccessful, Result, Info, Timestamp) " .
      " VALUES( " . $context->quote($obs->id) .
      " , "      . $context->quote($wasSuccessful) .
      " , "      . $context->quote($result) .
      " , "      . $context->quote($info) .
      " , "      . "NOW()" .
      " )";
    
    $affectedDeterminationLog = $context->exec($insertDeterminationLogQuery);
    
  }

  public function createNotification($messageType, $parameters) {

    // Workaround...
    $context = $this;
    
    $instertNotification =
      "INSERT INTO iherba_notification(message_type, parameters) " .
      " VALUES( " . $context->quote($messageType) .
      " , "       . $context->quote($parameters) .
      " )";
    
    $affectedNotification = $context->exec($instertNotification);
    
  }

  public function logQuestionsFinished(TypoherbariumObservation $obs, $topResults) {

    /* 
       $topResults is an array of Observations and detailed results of their comparison
       ordered by descending similarity.

       So:
       
       + $results[$i]['obs'] is the i-th best Observation.

       + $results[$i]['result'] is the detailed result of it's comparison with $obs,
       generated directly by APComparator.
    */

    // Workaround...
    $context = $this;

    $topObservationsIds = array_map(function($result) { return $result['obs']->id; }, $topResults);

    $topObservationsWithResults =
      array_map(
		function($result) { 
		  $obsId = $result['obs']->id;
		  $obsSimilarity = $result['result']['similarity'];
		  return "(" . $obsId . ":" . $obsSimilarity  . ")"; 
		}, 
		$topResults);

    $insertQuestionsFinishedQuery =
      "INSERT INTO iherba_questions_finished_log(ObservationId, TopObservations, TopResults, Timestamp) " .
      " VALUES( " . $context->quote($obs->id) .
      " , "      . $context->quote(implode(",", $topObservationsIds)) .
      " , "      . $context->quote(implode(",", $topObservationsWithResults)) .
      " , "      . "NOW()" .
      " )";
    
    $affectedQuestionsFinished = $context->exec($insertQuestionsFinishedQuery);
    
  }

  public function loadSimilaritySet($obsId) {

    // Workaround...
    $context = $this;

    // SimilaritySet Query.
    $similaritySetQuery =
      "SELECT * " .
      " FROM iherba_similarity_set" .
      " WHERE observation_id = " . $context->quote($obsId);

    return
      $context->singleResult(// Query
           $similaritySetQuery,
        
           // If the Similarity Set exists...
           function($row) use ($context) {
             $t = json_decode($row->weight_for_nearest_common_plants);
             return $t;
           },
        
           // If the Similarity Set doesn't exist...
           function() use ($context) {
             return NULL;
           }); 

  }

}

?>