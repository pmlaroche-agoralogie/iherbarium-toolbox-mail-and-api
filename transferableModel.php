<?php
namespace iHerbarium;

require_once("myPhpLib.php");
require_once("modelBaseClass.php");

require_once("imageManipulation.php");

class TransferableObservation
extends ModelBaseClass {
  protected $id = NULL;

  // Owner's data.
  protected $user = NULL;
  protected $uid  = NULL;
  
  // Observation meta-data and data.
  protected $timestamp   = NULL;
  protected $geolocation = NULL;
  protected $kind        = NULL;
  protected $plantSize   = NULL;
  protected $commentary  = NULL;
  protected $photos      = array();

  // Observation settings
  protected $privacy = NULL;

  // Debug printing
  protected function debugStringsArray() {
    $lines   = array();
    $lines[] = "id: "          . $this->id;
    $lines[] = "user: "        . $this->user;
    $lines[] = "uid: "         . $this->uid;
    $lines[] = "timestamp: "   . $this->timestamp;
    $lines[] = "geolocation: " . $this->geolocation;
    $lines[] = "privacy: "     . $this->privacy;
    $lines[] = "kind: "        . $this->kind;
    $lines[] = "plantSize: "   . $this->plantSize;
    $lines[] = "commentary: "  . $this->commentary;
    $lines[] = "photos: " . 
      mkString(
	       array_map(function($photo) { return $photo; }, $this->photos),
	       "<p>Photos:<ul><li>", "</li><li>", "</li></ul></p>"
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
	       "<p>TransferableObservation:<ul><li>", "</li><li>", "</li></ul></p>"
	       );
  }

  function __toString() { return $this->debugString(); }

  static public function fromStdObj($obj) {
    $obs = new static();
    
    $obs
      ->setId($obj->id)
      ->setUser($obj->user)
      ->setUid(NULL)
      ->setTimestamp($obj->timestamp)
      ->setGeolocation(TransferableGeolocation::fromStdObj($obj->geolocation))
      ->setPrivacy($obj->privacy)
      ->setKind($obj->kind)
      ->setPlantSize($obj->plantSize)
      ->setCommentary($obj->commentary);
 
    $obs->setPhotos(
		    array_map(
			      function($objPhoto) { 
				return TransferablePhoto::fromStdObj($objPhoto); 
			      },
			      $obj->photos
			      )
		    );
    
    return $obs;
  }

  static public function createFresh() {
    $obs = new static();

    $obs
      ->setId(NULL)
      ->setUser(NULL)
      ->setUid(NULL)
      ->setTimestamp(NULL)
      ->setGeolocation(TransferableGeolocation::unknown())
      ->setKind(1) // First kind by default.
      ->setPlantSize("")
      ->setCommentary("")
      ->setPrivacy("public");
    
    return $obs;
  }

}

class TransferableGeolocation 
extends ModelBaseClass {
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

  private static function parseExifGPSField($exifMeasure, $field) {
    // Field can be present or not (especially seconds).
    if(! isset($exifMeasure[$field]) ) {
      // If the field is not present, it's value is 0.
      return 0;
    }
    else {
      // If the field is present, it has format "value/divisor" eg. "4255/100".
      
      // Extract parts.
      $valueAndDivisor = explode("/", $exifMeasure[$field]);      
      $value   = $valueAndDivisor[0];
      $divisor = $valueAndDivisor[1];
      
      // Compute real value.
      return $value / $divisor;
    }
  }

  private static function exifGPSToDegrees($exifMeasure) {
    $degrees = static::parseExifGPSField($exifMeasure, 0);
    $minutes = static::parseExifGPSField($exifMeasure, 1);
    $seconds = static::parseExifGPSField($exifMeasure, 2);
    return 
      $degrees + 
      ($minutes / 60) + 
      ($seconds / (60 * 60));
  }

  private static function latitudeFromEXIF($exifLatitude, $exifLatitudeRef) {
    $degrees = static::exifGPSToDegrees($exifLatitude);
    switch($exifLatitudeRef) {
    case 'N': return  $degrees;
    case 'S': return -$degrees;
    }
  }

  private static function longitudeFromEXIF($exifLongitude, $exifLongitudeRef) {
    $degrees = static::exifGPSToDegrees($exifLongitude);
    switch($exifLongitudeRef) {
    case 'E': return  $degrees;
    case 'W': return -$degrees;
    }
  }

  public static function fromExif($exif) {
    $geoloc = NULL;
    
    if(isset($exif['GPSLatitude']) &&
       isset($exif['GPSLatitudeRef']) &&
       isset($exif['GPSLongitude']) &&
       isset($exif['GPSLongitudeRef'])
       ) {
      $geoloc = new static();
      
      $geoloc
	->setLatitude (static::latitudeFromExif ($exif['GPSLatitude'],  $exif['GPSLatitudeRef'] ))
	->setLongitude(static::longitudeFromExif($exif['GPSLongitude'], $exif['GPSLongitudeRef']));
    }
    else {
      $geoloc = new static();
      
      $geoloc
	->setLatitude(0)
	->setLongitude(0);
    }
    
    return $geoloc;
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
	       "<p>TransferableGeolocation:<ul><li>", "</li><li>", "</li></ul></p>"
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
extends ModelBaseClass {

  protected $obsId = NULL;

  // File paths
  protected $remoteDir = NULL;
  protected $remoteFilename = NULL;

  protected $localDir = NULL;
  protected $localFilename = NULL;

  // Photo
  protected $depositTimestamp = NULL;
  protected $userTimestamp = NULL;
  
  protected $exifTimestamp = NULL;
  protected $exifOrientation = NULL;
  protected $exifGeolocation = NULL;

  // ROIs
  protected $rois = array();

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
    debug("Debug", "TransferablePhoto", "Copying photo from ". $this->remotePath() ." to ". $this->localPath() );
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
    debug("Begin", "TransferablePhoto", "Making local copy to: ". $destinationPath );
    ImageManipulator::resizeImage($this->localPath(), 
				  $destinationPath, 
				  $maxSize, 
				  $rotationAngle, 
				  $cutRectangle);
  }

  
  // Debug printing
  protected function debugStringsArray() {
    $lines   = array();
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
    
    return $lines;
  }

  final protected function debugString() {
    return 
      mkString(
	       $this->debugStringsArray(),
	       "<p>TransferablePhoto:<ul><li>", "</li><li>", "</li></ul></p>"
	       );
  }

  function __toString() { return $this->debugString(); }
  
  static public function fromStdObj($obj) {
    $photo = new static;
    $photo->obsId            = $obj->obsId;
    $photo->remoteDir        = $obj->remoteDir;
    $photo->remoteFilename   = $obj->remoteFilename;
    $photo->localDir         = $obj->localDir;
    $photo->localFilename    = $obj->localFilename;
    $photo->depositTimestamp = $obj->depositTimestamp;
    $photo->userTimestamp    = $obj->userTimestamp;
    $photo->exifTimestamp    = $obj->exifTimestamp;
    $photo->exifOrientation  = $obj->exifOrientation;

    $photo->exifGeolocation  = 
      TransferableGeolocation::fromStdObj($obj->exifGeolocation);

    $photo->setRois(
		    array_map(
			      function($objROI) { 
				return TransferableROI::fromStdObj($objROI); 
			      },
			      $obj->rois
			      )
		    );
      
    return $photo;
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
extends ModelBaseClass {

  protected $rectangle;
  protected $tag;

  // Debug printing
  protected function debugStringsArray() {
    $lines   = array();
    $lines[] = "rectangle: " . $this->rectangle;
    $lines[] = "tag: " . $this->tag;
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
}



class Preparator {

  protected static function getRoughGeolocation(array $transferablePhotos) {
    if(isset($transferablePhotos[0])) {
      $anyPhoto = $transferablePhotos[0];
      return $anyPhoto->exifGeolocation;
    }
    else {
      return TransferableGeolocation::fromLatitudeAndLongitude(0, 0);
    }
  }

  public static function prepareForTransfer($protocolObs) {
    $obs = new TransferableObservation();
    
    // Observation
    $obs
      ->setId          ($protocolObs->id)
      ->setUser        ($protocolObs->user->eMail)
      ->setUid         (NULL)
      ->setTimestamp   (NULL)
      ->setGeolocation (NULL)
      ->setPrivacy     ("public")
      ->setKind        (1)
      ->setPlantSize   ("")
      ->setCommentary  ("");
    
    // Photos
    foreach($protocolObs->photos as $protocolPhoto) {
      $localDir = Config::get("transferablePhotoLocalDir");
      
      // Prepare local name.
      $localFilename = "photo_" . time() . "_" . rand() . ".jpg";
            
      // Prepare local path.
      $saveToPath = $localDir . $localFilename;

      // Save photo.
      debug("Debug", "prepareForTransfer()", "Writing observation's photo to $saveToPath!");
      file_put_contents($saveToPath, $protocolPhoto->image);

      // Geoloc
      $exif = exif_read_data($saveToPath);
      $geoloc = TransferableGeolocation::fromExif($exif);
      

      // Photo
      $photo = new TransferablePhoto();
      $photo->obsId            = $obs->id;
      $photo->remoteDir        = Config::get("transferablePhotoRemoteDir");
      $photo->remoteFilename   = $localFilename;
      $photo->localDir         = NULL;
      $photo->localFilename    = NULL;
      $photo->depositTimestamp = $protocolPhoto->timestamp;
      $photo->userTimestamp    = NULL;
      $photo->exifTimestamp    = strtotime($exif['DateTimeOriginal']);
      $photo->exifOrientation  = (isset($exif['Orientation']) ? $exif['Orientation'] : NULL);
      $photo->exifGeolocation  = $geoloc;
      $photo->rois             = array();
      
      // ROI
      if($protocolPhoto->tag) {
	$roi = new TransferableROI();
	$rect = new ROIRectangle();
	$rect->setRectangle(0.02, 0.02, 0.98, 0.98);
	$roi->rectangle = $rect;
	$roi->tag = $protocolPhoto->tag;

	$photo->addRoi($roi);
      }
      
      // Photo ready
      $obs->addPhoto($photo);


      // Comments - add the Protocol Photo comments to Observation's comments.
      /*
      if($protocolPhoto->comments) {
	if($obs->commentary) $obs->commentary .= " ";
	$obs->commentary .= $protocolPhoto->comments;
      }
      */
    }

    // Get rough geolocation.
    $obs->geolocation = static::getRoughGeolocation($obs->photos);

    debug("Ok", "PrepareForTransfer()", "Prepared.", $obs);
    return $obs;
  }
}

?>