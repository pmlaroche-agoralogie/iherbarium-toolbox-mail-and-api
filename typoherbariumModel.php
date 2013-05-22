<?php
namespace iHerbarium;

require_once("myPhpLib.php");
require_once("modelBaseClass.php");

require_once("exif.php");
require_once("imageManipulation.php");

require_once("fileVersions.php");
require_once("questionSchema.php");

require_once("question.php");
require_once("determinationProtocol.php");
require_once("typoherbariumTask.php");

class TransferableObservation
extends ModelBaseClass { /* Included only for backward compatibility reasons. */ }

class TypoherbariumObservation
extends TransferableObservation {

  protected $id = NULL;

  // Owner's data.
  protected $user = NULL;
  protected $uid  = NULL;
  
  // Observation meta-data and data.
  protected $timestamp     = NULL;
  protected $geolocation   = NULL;
  protected $kind          = NULL;
  protected $plantSize     = NULL;
  protected $commentary    = NULL;
  protected $address       = NULL;
  protected $miscellaneous = NULL;
  protected $photos        = array();
  protected $medias        = array();

  // Observation settings
  protected $privacy = NULL;

  // Debug printing
  protected function debugStringsArray() {
    $lines   = array();
    $lines[] = "id: "            . $this->id;
    $lines[] = "user: "          . $this->user;
    $lines[] = "uid: "           . $this->uid;
    $lines[] = "timestamp: "     . $this->timestamp;
    $lines[] = "geolocation: "   . $this->geolocation;
    $lines[] = "privacy: "       . $this->privacy;
    $lines[] = "kind: "          . $this->kind;
    $lines[] = "plantSize: "     . $this->plantSize;
    $lines[] = "commentary: "    . $this->commentary;
    $lines[] = "address: "       . $this->address;
    $lines[] = "miscellaneous: " . "<pre>" . var_export($this->miscellaneous, True)   . "</pre>";

    $lines[] = "photos: " . 
    mkString(
     array_map(function($photo) { return $photo; }, $this->photos),
     "<p>Photos:<ul><li>", "</li><li>", "</li></ul></p>"
     );

    $lines[] = "media: " . 
    mkString(
     array_map(function($media) { return $media; }, $this->medias),
     "<p>Media:<ul><li>", "</li><li>", "</li></ul></p>"
     );

    return $lines;
  }

  public function getROIs() {
    return array_flatten( array_map(function($photo) { return $photo->rois; }, $this->photos) );
  }

  final protected function debugString() {
    return 
    mkString(
     $this->debugStringsArray(),
     "<p>TypoherbariumObservation:<ul><li>", "</li><li>", "</li></ul></p>"
     );
  }

  function __toString() { return $this->debugString(); }

  // createFresh
  static public function createFresh() {
    $obs = new static();

    $obs
    ->setId(NULL)
    ->setUser(NULL)
    ->setUid(NULL)
    ->setTimestamp(NULL)
    ->setGeolocation(TypoherbariumGeolocation::unknown())
    ->setKind(1) // First kind by default.
    ->setPlantSize("")
    ->setCommentary("")
    ->setAddress("")
    ->setMiscellaneous(NULL)
    ->setPrivacy("public");

    return $obs;
  }

  // fromStdObj
  static public function fromStdObj($obj) {
    $obs = new static();

    $obs
    ->setId(          isset($obj->id         ) ? $obj->id         : NULL)
    ->setUser(        isset($obj->user       ) ? $obj->user       : NULL)
    ->setUid(         NULL)
    ->setTimestamp(   isset($obj->timestamp  ) ? $obj->timestamp  : NULL)
    ->setGeolocation( isset($obj->geolocation) ? TypoherbariumGeolocation::fromStdObj($obj->geolocation) : TypoherbariumGeolocation::unknown() )
    ->setPrivacy(     isset($obj->privacy    ) ? $obj->privacy    : "public")
    ->setKind(        isset($obj->kind       ) ? $obj->kind       : 1)
    ->setPlantSize(   isset($obj->plantSize  ) ? $obj->plantSize  : "")
    ->setCommentary(  isset($obj->commentary ) ? $obj->commentary : "")
    ->setAddress(  isset($obj->address ) ? $obj->address : "")
    ->setMiscellaneous(  isset($obj->miscellaneous ) ? $obj->miscellaneous : NULL);

    $obs->setPhotos(
      array_map(
       function($objPhoto) { 
        return TypoherbariumPhoto::fromStdObj($objPhoto); 
      },
      $obj->photos
      )
    );

    /*
    $obs->setMedias(
      array_map(
       function($objMedia) { 
        return TypoherbariumMedia::fromStdObj($objMedia); 
      },
      $obj->medias
      )
    );
    */

    return $obs;
  }

}



class TransferableGeolocation
extends ModelBaseClass { /* Included only for backward compatibility reasons. */ }

class TypoherbariumGeolocation 
extends TransferableGeolocation {

  protected $latitude  = NULL;
  protected $longitude = NULL;

  public function isKnown() {
    // Geolocation is unknown by our convention 
    // if both Latitude and Longitude are equal to 0.
    return (
      $this->latitude  != 0 
      ||
      $this->longitude != 0
      ); 
  }

  // Debug printing
  protected function debugStringsArray() {
    $lines   = array();
    $lines[] = "latitude: "  . $this->latitude;
    $lines[] = "longitude: " . $this->longitude;
    return $lines;
  }

  final protected function debugString() {
    return 
    mkString(
     $this->debugStringsArray(),
     "<p>TypoherbariumGeolocation:<ul><li>", "</li><li>", "</li></ul></p>"
     );
  }

  function __toString() { return $this->debugString(); }

  public static function fromLatitudeAndLongitude($latitude, $longitude) {
    $geoloc = new static();

    $geoloc
    ->setLatitude($latitude)
    ->setLongitude($longitude);

    return $geoloc;
  }

  public static function fromCoordinates($coordinates) {
    $geoloc = static::unknown();

    if(array_key_exists("latitude", $coordinates))
      $geoloc->setLatitude($coordinates["latitude"]);

    if(array_key_exists("longitude", $coordinates))
      $geoloc->setLongitude($coordinates["longitude"]);

    return $geoloc;
  }

  public static function fromExif($exif) {
    return static::fromCoordinates( Exif::coordinatesFromExif($exif) );
  }

  public static function unknown() {
    return static::fromLatitudeAndLongitude(0, 0);
  }

  static public function fromStdObj($obj) {
    $geoloc = new static();

    $geoloc
    ->setLatitude($obj->latitude)
    ->setLongitude($obj->longitude);

    return $geoloc;
  }

}



class TransferablePhoto
extends ModelBaseClass { /* Included only for backward compatibility reasons. */ }

class TypoherbariumPhoto
extends TransferablePhoto {

  protected $id    = NULL;
  protected $obsId = NULL;

  // File paths
  protected $remoteDir      = NULL;
  protected $remoteFilename = NULL;

  protected $localDir      = NULL;
  protected $localFilename = NULL;

  protected $sourceFile   = NULL;
  protected $fileVersions = array();

  // Photo
  protected $depositTimestamp = NULL;
  protected $userTimestamp    = NULL;

  protected $exifTimestamp   = NULL;
  protected $exifOrientation = NULL;
  protected $exifGeolocation = NULL;

  // ROIs
  protected $rois = array();


  // Debug printing
  protected function debugStringsArray() {
    $lines   = array();

    $lines[] = "id: "    . $this->id;
    $lines[] = "obsId: " . $this->obsId;

    $lines[] = "remotePath: " . $this->remotePath();

    if($this->remotePath()) 
      $lines[] = "remotePreview: <img height=150px width=150px src='" . $this->remotePath() . "'/>";

    $lines[] = "localPath: " . $this->localPath();
    $lines[] = "depositTimestamp: " . $this->depositTimestamp;
    $lines[] = "userTimestamp: " . $this->userTimestamp;
    $lines[] = "exifTimestamp: " . $this->exifTimestamp;
    $lines[] = "exifOrientation: " . $this->exifOrientation;
    $lines[] = "exifGeolocation: " . $this->exifGeolocation;
    $lines[] = "ROIs:" . 
    mkString(
     array_map(function($roi) { return $roi; }, $this->rois),
     "<p>ROIs:<ul><li>", "</li><li>", "</li></ul></p>"
     );

    $lines[] = "sourceFile: "   . "<pre>" . var_export($this->sourceFile, True)   . "</pre>";
    $lines[] = "fileVersions: " . "<pre>" . var_export($this->fileVersions, True) . "</pre>";


    return $lines;
  }

  final protected function debugString() {
    return 
    mkString(
     $this->debugStringsArray(),
     "<p>TypoherbariumPhoto:<ul><li>", "</li><li>", "</li></ul></p>"
     );
  }

  function __toString() { return $this->debugString(); }


  static public function fromStdObj($obj) {
    $photo = new static;
    $photo->obsId            = (isset($obj->obsId           ) ? $obj->obsId            : NULL);
    $photo->remoteDir        = $obj->remoteDir;
    $photo->remoteFilename   = $obj->remoteFilename;
    $photo->localDir         = (isset($obj->localDir        ) ? $obj->localDir         : NULL);
    $photo->localFilename    = (isset($obj->localFilename   ) ? $obj->localFilename    : NULL);
    $photo->depositTimestamp = (isset($obj->depositTimestamp) ? $obj->depositTimestamp : NULL);
    $photo->userTimestamp    = (isset($obj->userTimestamp   ) ? $obj->userTimestamp    : NULL);
    $photo->exifTimestamp    = (isset($obj->exifTimestamp   ) ? $obj->exifTimestamp    : NULL);
    $photo->exifOrientation  = (isset($obj->exifOrientation ) ? $obj->exifOrientation  : NULL);

    $photo->exifGeolocation  = 
    TypoherbariumGeolocation::fromStdObj($obj->exifGeolocation);

    $photo->setRois(
      array_map(
       function($objROI) { 
        return TypoherbariumROI::fromStdObj($objROI); 
      },
      $obj->rois
      )
      );

    return $photo;
  }


  public function remotePath() {
    return $this->remoteDir . $this->remoteFilename;
  }

  public function localPath() {
    return $this->localDir . $this->localFilename;
  }

  // Get rotation in degrees (anti-clockwise) from exif orientation.
  public function rotationAngle() {
    $rotationAngle = 0;

    if($this->exifOrientation) {
      switch($this->exifOrientation) {
        case 1: $rotationAngle = 0; break;
        case 8: $rotationAngle = 90; break;
        case 3: $rotationAngle = 180; break;
        case 6: $rotationAngle = 270; break;
      }
    }


    return $rotationAngle;
  }

  public function copyFromRemoteToLocal($localDir, $localFilename) {
    $this->localDir = $localDir;
    $this->localFilename = $localFilename;
    debug("Debug", "TypoherbariumPhoto", "Copying photo from ". $this->remotePath() ." to ". $this->localPath() );
    copy($this->remotePath(), $this->localPath());
  }

  public function makeLocalResizedCopy($destinationDir, $destinationFilename, 
   $maxSize = NULL,
   ROIRectangle $cutRectangle = NULL) {

    assert($this->localDir && $this->localFilename);

    // Get rotation.
    $rotationAngle = $this->rotationAngle();

    // Resize and rotate.
    $destinationPath = $destinationDir . $destinationFilename;
    debug("Begin", "TypoherbariumPhoto", "Making local copy to: ". $destinationPath );
    ImageManipulator::resizeImage($this->localPath(), 
      $destinationPath, 
      $maxSize, 
      $rotationAngle, 
      $cutRectangle);
  }

}



class ROIRectangle 
extends ModelBaseClass {

  protected $left;
  protected $top;
  protected $right;
  protected $bottom;

  public function rotate($angle) {
      // Rotates anti-clockwise
    $angle = $angle % 360;
    assert(($angle == 0) || ($angle == 90) || ($angle == 180) || ($angle == 270));

      // Compute values after rotation.

    switch($angle) {

      case 0  :
      $left   = $this->left;
      $top    = $this->top;
      $right  = $this->right;
      $bottom = $this->bottom;
      break;

      case 90 :
      $left   =     $this->top;
      $top    = 1 - $this->right;
      $right  =     $this->bottom;
      $bottom = 1 - $this->left;
      break;

      case 180 :
      $left   = 1 - $this->right;
      $top    = 1 - $this->bottom;
      $right  = 1 - $this->left;
      $bottom = 1 - $this->top;
      break;

      case 270 :
      $left   = 1 - $this->bottom;
      $top    =     $this->left;
      $right  = 1 - $this->top;
      $bottom =     $this->right;
      break;

      default :
      assert(False);
    }

      // Set new values.
    $this->left   = $left;
    $this->top    = $top;
    $this->right  = $right;
    $this->bottom = $bottom;

  }

  public static function fromLeftTopRightBottom($left, $top, $right, $bottom) {
    $rect = new static;
    $rect->setRectangle($left, $top, $right, $bottom);
    return $rect;
  }

  public function setRectangle($left, $top, $right, $bottom) {
    $this->left    = $left;
    $this->top     = $top;
    $this->right   = $right;
    $this->bottom  = $bottom;
  }

    // Debug printing
  protected function debugStringsArray() {
    $lines   = array();
    $lines[] = "left: " . $this->left;
    $lines[] = "top: " . $this->top;
    $lines[] = "right: " . $this->right;
    $lines[] = "bottom: " . $this->bottom;
    return $lines;
  }

  final protected function debugString() {
    return 
    mkString(
     $this->debugStringsArray(),
     "<p>ROIRectangle:<ul><li>", "</li><li>", "</li></ul></p>"
     );
  }

  function __toString() { return $this->debugString(); }

  static public function fromStdObj($obj) {
    $rect = new static();
    $rect->left    = $obj->left;
    $rect->top     = $obj->top;
    $rect->right   = $obj->right;
    $rect->bottom  = $obj->bottom;
    return $rect;
  }

  static public function fromStdObjRectangleAndArea($rectangle, $areaWidth, $areaHeight) {
    assert(isset($rectangle->left));
    assert(isset($rectangle->right));
    assert(isset($rectangle->width));
    assert(isset($rectangle->height));

    $left    = $rectangle->left / $areaWidth;
    $top     = $rectangle->top  / $areaHeight;
    $right   = ($rectangle->left + $rectangle->width ) / $areaWidth;
    $bottom  = ($rectangle->top  + $rectangle->height) / $areaHeight;

    return static::fromLeftTopRightBottom($left, $top, $right, $bottom);
  }
}



class TransferableROI
extends ModelBaseClass { /* Included only for backward compatibility reasons. */ }

class TypoherbariumROI
extends TransferableROI {

  protected $id                = NULL;
  protected $photoId           = NULL;
  protected $observationId     = NULL;
  protected $fileVersions      = array();
  protected $tags              = array();
  protected $answers           = array();
  protected $answersPatterns   = array();

  protected $rectangle;
  protected $tag;

  // Debug printing
  protected function debugStringsArray() {
    $lines   = array();

    // before lines
    $lines[] = "id: "            . $this->id;
    $lines[] = "photoId: "       . $this->photoId;
    $lines[] = "observationId: " . $this->observationId;

    // parent lines
    $lines[] = "rectangle: " . $this->rectangle;
    $lines[] = "tag: " . $this->tag;

    // after lines
    $lines[] = "fileVersions: " . "<pre>" . var_export($this->fileVersions, True) . "</pre>";

    $lines[] = "tags: " .  
    mkString(
      $this->tags,
      "<ul><li>", "</li><li>", "</li></ul>"
      );

    $lines[] = "answers: " .  
    mkString(
      $this->answers,
      "<ul><li>", "</li><li>", "</li></ul>"
      );

    $lines[] = "answersPatterns: " .  
    mkString(
      $this->answersPatterns,
      "<ul><li>", "</li><li>", "</li></ul>"
      );

    return $lines;
  }

  final protected function debugString() {
    return 
    mkString(
     $this->debugStringsArray(),
     "<p>ROI:<ul><li>", "</li><li>", "</li></ul></p>"
     );
  }

  function __toString() { return $this->debugString(); }

  static public function fromStdObj($obj) {
    $roi = new static();
    $roi->rectangle = ROIRectangle::fromStdObj($obj->rectangle);
    $roi->tag       = $obj->tag;
    return $roi;
  }

  public function getAllTagIds() {
    return
    array_map(function(TypoherbariumTag $tag) { return $tag->tagId; }, $this->tags);
  }

  public function getComparaisonTagIds() {
    //PL tag id to be used for generation of comparisons
    if((isset($this->tags[0]))&&(($this->tags[0]->tagId ==7)|| ($this->tags[0]->tagId ==2)) || ($this->tags[0]->tagId ==3))
      return array_map(function(TypoherbariumTag $tag) { return $tag->tagId; }, $this->tags);
    else
      return array();
  }

  public function hasTagId($tagId) {
    return
    array_any(function(TypoherbariumTag $tag) use ($tagId) { return ($tag->tagId == $tagId); }, $this->tags);
  }

  public function hasAnyOfTagIds($tagIds) {
    $context = $this;

    return array_any(
     function($tagId) use ($context) {
       return $context->hasTagId($tagId); 
     },
     $tagIds
     );
  }

}



class TypoherbariumTag 
extends ModelBaseClass {

  protected $id    = NULL;
  protected $tagId = NULL;
  protected $roiId = NULL;
  protected $uid   = NULL;
  protected $kind  = NULL;

  // Debug printing
  protected function debugStringsArray() {
    $lines   = array();
    $lines[] = "id: "     . $this->id;
    $lines[] = "tagId: "  . $this->tagId;
    $lines[] = "roiId: "  . $this->roiId;
    $lines[] = "uid: "    . $this->uid;
    $lines[] = "kind: "   . $this->kind;
    
    return $lines;
  }

  final protected function debugString() {
    return 
    mkString(
      $this->debugStringsArray(),
      "<p>TypoherbariumTag:<ul><li>", "</li><li>", "</li></ul></p>"
      );
  }

  function __toString() { return $this->debugString(); }

}



class TypoherbariumROIAnswer 
extends ModelBaseClass {

  protected $id           = NULL;

  protected $questionType = NULL;
  protected $questionId   = NULL;
  protected $roiId        = NULL;

  protected $answerValue  = NULL;

  protected $askId        = NULL;

  protected $internautIp  = NULL;
  protected $source       = NULL;
  protected $referrant    = NULL;
  

  // Debug printing
  protected function debugStringsArray() {
    $lines   = array();
    $lines[] = "id: "           . $this->id;
    $lines[] = "questionType: " . $this->questionType;
    $lines[] = "questionId: "   . $this->questionId;
    $lines[] = "roiId: "        . $this->roiId;
    $lines[] = "answerValue: "  . $this->answerValue;
    $lines[] = "askId: "        . $this->askId;
    $lines[] = "internautIp: "  . $this->internautIp;
    $lines[] = "source: "       . $this->source;
    $lines[] = "referrant: "    . $this->referrant;
    
    return $lines;
  }

  final protected function debugString() {
    return 
    mkString(
      $this->debugStringsArray(),
      "<p>TypoherbariumROIAnswer:<ul><li>", "</li><li>", "</li></ul></p>"
      );
  }

  function __toString() { return $this->debugString(); }

}



class TypoherbariumROIAnswersPattern
extends ModelBaseClass {

  protected $id           = NULL;
  protected $questionType = NULL;
  protected $questionId   = NULL;
  protected $roiId        = NULL;
  protected $answers      = array();

  public function getAnswerForROIId($roiId) {
    return
    array_single(
     function($answer) use ($roiId) { 
       return ($roiId == $answer['id']); 
     }, 
     $this->answers
     );
  }

  public function getAnswerParamForROIId($roiId, $param, $default = 0) {
    $answer = $this->getAnswerForROIId($roiId);
    
    if($answer)
      return $answer[$param];
    else
      return $default;
  }
  
  // Debug printing
  protected function debugStringsArray() {
    $lines = array();
    $lines[] = "id: "           . $this->id;
    $lines[] = "questionType: " . $this->questionType;
    $lines[] = "questionId: "   . $this->questionId;
    $lines[] = "roiId: "        . $this->roiId;
    $lines[] = "getBestAnswer(): " . $this->getBestAnswer();
    $lines[] = "answers: " . "<pre>" . var_export($this->answers, True) . "</pre>";
    
    return $lines;
  }

  final protected function debugString() {
    return 
    mkString(
      $this->debugStringsArray(),
      "<p>AnswersPattern:<ul><li>", "</li><li>", "</li></ul></p>"
      );
  }

  function __toString() { return $this->debugString(); }

  public function getFirstAnswer() {
    if(isset($this->answers[0]))
      return $this->answers[0];
    else
      return NULL;
  }

  public function getBestAnswer() {
    if(isset($this->answers[0]))
      return $this->answers[0]["id"];
    else
      return NULL;
  }
  
}



class TypoherbariumROIQuestion 
extends ModelBaseClass {

  protected $id      = NULL;
  protected $choices = array();

  // Constraints
  protected $necessaryTagId      = NULL;
  protected $necessaryQuestionId = NULL;
  protected $necessaryAnswer     = NULL;
  protected $necessaryGrid       = NULL;

  // Debug printing
  protected function debugStringsArray() {
    $lines = array();
    $lines[] = "id: "         . $this->id;
    
    $lines[] = "choices: " .  
    mkString(
      $this->choices,
      "<ul><li>", "</li><li>", "</li></ul>"
      );

    $lines[] = "necessaryTagId: " . $this->necessaryTagId;
    $lines[] = "necessaryQuestionId: " . $this->necessaryQuestionId;
    $lines[] = "necessaryAnswer: " . $this->necessaryAnswer;
    $lines[] = "necessaryGrid: " . $this->necessaryGrid;  

    return $lines;
  }

  final protected function debugString() {
    return 
    mkString(
      $this->debugStringsArray(),
      "<p>TypoherbariumROIQuestion:<ul><li>", "</li><li>", "</li></ul></p>"
      );
  }

  function __toString() { return $this->debugString(); }

}



class TypoherbariumAskLog
extends ModelBaseClass {

  protected $id           = NULL;

  // Question info
  protected $questionType = NULL;
  protected $questionId   = NULL;
  protected $context      = NULL;

  // Asking conditions info
  protected $lang         = NULL;
  protected $internautIp  = NULL;
  protected $internautId  = NULL;
  
  // Debug printing
  protected function debugStringsArray() {
    $lines = array();
    $lines[] = "id: "           . $this->id;
    $lines[] = "questionType: " . $this->questionType;
    $lines[] = "questionId: "   . $this->questionId;
    $lines[] = "context: "      . $this->context;
    $lines[] = "lang: "         . $this->lang;
    $lines[] = "internautIp: "  . $this->internautIp;
    $lines[] = "internautId: "  . $this->internautId;

    return $lines;
  }

  final protected function debugString() {
    return 
    mkString(
      $this->debugStringsArray(),
      "<p>TypoherbariumAskLog:<ul><li>", "</li><li>", "</li></ul></p>"
      );
  }

  function __toString() { return $this->debugString(); }
  
}



class TypoherbariumGroup
extends ModelBaseClass {

  protected $id             = NULL;
  protected $name           = NULL;
  protected $observations   = array();
  protected $includedGroups = array();

  public function getAllObservations() {
    $allObservations = $this->observations;
    
    foreach($this->includedGroups as $group) {
      //$allObservations = array_merge($allObservations, $group->getAllObservations());
      $allObservations += $group->getAllObservations();
    };

    return $allObservations;
  }

  // Debug printing
  protected function debugStringsArray() {
    $lines = array();
    $lines[] = "id: "   . $this->id;
    $lines[] = "name: " . $this->name;

    $lines[] = "includedGroups: " .
    mkString(
      $this->includedGroups,
      "<ul><li>", "</li><li>", "</li></ul>"
      );
    
    $lines[] = "observations: " .
    mkString(
      array_mapi(function($key, $obs) { return $obs->id; }, $this->observations ),
      "<ul><li>", "</li><li>", "</li></ul>"
      );
    
    $lines[] = "getAllObservations(): " .
    mkString(
      array_mapi(function($key, $obs) { return $obs->id; }, $this->getAllObservations()),
      "<ul><li>", "</li><li>", "</li></ul>"
      );   
    

    return $lines;
  }

  final protected function debugString() {
    return 
    mkString(
      $this->debugStringsArray(),
      "<p>TypoherbariumGroup:<ul><li>", "</li><li>", "</li></ul></p>"
      );
  }

  function __toString() { return $this->debugString(); }
  
}




class TypoherbariumMedia
extends ModelBaseClass {

  protected $id    = NULL;
  protected $obsId = NULL;

  // Timestamp
  protected $depositTimestamp = NULL;

  // File paths
  protected $localDir        = NULL;
  protected $localFilename   = NULL;
  
  protected $initialFilename = NULL;

  protected $sourceFile      = NULL;


  // Debug printing
  protected function debugStringsArray() {
    $lines   = array();

    $lines[] = "id: "    . $this->id;
    $lines[] = "obsId: " . $this->obsId;

    $lines[] = "depositTimestamp: " . $this->depositTimestamp;    

    $lines[] = "localPath: "       . $this->localPath();
    $lines[] = "initialFilename: " . $this->initialFilename();

    return $lines;
  }

  final protected function debugString() {
    return 
    mkString(
     $this->debugStringsArray(),
     "<p>TypoherbariumMedia:<ul><li>", "</li><li>", "</li></ul></p>"
     );
  }

  function __toString() { return $this->debugString(); }


  static public function fromStdObj($obj) {
    $media = new static;
    $media->id               = (isset($obj->id              ) ? $obj->id               : NULL);
    $media->obsId            = (isset($obj->obsId           ) ? $obj->obsId            : NULL);
    $media->depositTimestamp = (isset($obj->depositTimestamp) ? $obj->depositTimestamp : NULL);
    $media->initialFilename  = (isset($obj->initialFilename ) ? $obj->initialFilename  : NULL);
    $media->localDir         = (isset($obj->localDir        ) ? $obj->localDir         : NULL);
    $media->localFilename    = (isset($obj->localFilename   ) ? $obj->localFilename    : NULL);

    return $media;
  }

  public function localPath() {
    return $this->localDir . $this->localFilename;
  }

}

?>
