<?php
namespace iHerbarium;

require_once("myPhpLib.php");

class ImageManipulator {

  static public function shrink($maxSize, $src_w, $src_h) {
    // Resize if maxSize is set
    // and width or height of image is bigger than it.
    if($maxSize && ( ($src_w > $maxSize) || ($src_h > $maxSize) ) ) {
      // Resizing!
      if($src_w > $src_h) {
	// Width is bigger than height.
	$dst_h = $src_h * ( $maxSize / $src_w );
	$dst_w = $maxSize;
      }
      else {
	// Height is bigger than width.
	$dst_w = $src_w * ( $maxSize / $src_h );
	$dst_h = $maxSize;
      }
    } else {
      // No need to resize - keep the original dimensions.
      $dst_w = $src_w;
      $dst_h = $src_h;
    }

    return (array(0 => $dst_w, 1 => $dst_h)); // Standard PHP format for width/height.
  }
  
  static public function resizeImage($source, $destination, $maxSize = NULL, $rotateAngle = 0, ROIRectangle $rectangle = NULL) {

    if(! $rectangle) {
      $rectangle = ROIRectangle::fromLeftTopRightBottom(0, 0, 1, 1);
    }
    
    //debug("Debug", "ImageManipulator", "Rectangle: ", $rectangle);


    // Get source image's size.
    $dimentions = getimagesize($source);  
    // $dimensions == array { 0 => width, 1 => height }
    $width  = $dimentions[0];
    $height = $dimentions[1];

    //debug("Debug", "ImageManipulator", "Width: $width, Height: $height");
  

    // Compute cut zone: src_x, src_y, src_w, src_h for imagecopyresampled().
    $src_x = ($width  * $rectangle->left  );
    $src_y = ($height * $rectangle->top   );
    $src_w = ($width  * $rectangle->right ) - $src_x;
    $src_h = ($height * $rectangle->bottom) - $src_y;

    //debug("Debug", "ImageManipulator", "$src_x, $src_y, $src_w, $src_h");


    // Compute paste zone: dst_x, dst_y, dst_w, dst_h.
    $dst_x = 0;
    $dst_y = 0;

    // Resize if maxSize is set
    // and width or height of image is bigger than it.
    $dst_w_and_dst_h = self::shrink($maxSize, $src_w, $src_h);
    $dst_w = $dst_w_and_dst_h[0];
    $dst_h = $dst_w_and_dst_h[1];

    //debug("Debug", "ImageManipulator", "$dst_x, $dst_y, $dst_w, $dst_h");
  

    // Resize.
    $imageFromSource = imagecreatefromjpeg($source); // Source image.
    $newImage = imagecreatetruecolor($dst_w, $dst_h); // New blank image.
  

    // Copy resized source to the new image.
    $copyResult = imagecopyresampled($newImage, $imageFromSource, 
				     $dst_x, $dst_y, $src_x, $src_y, 
				     $dst_w, $dst_h, $src_w, $src_h);
    

    // Rotate the image.
    $rotatedImage = imagerotate($newImage, $rotateAngle, 0);
    

    // Save it at destination;
    $jpegResult = imagejpeg($rotatedImage, $destination, 100);


    //debug("Debug", "ImageManipulator", "copyResult: $copyResult, jpegResult: $jpegResult");
    //debug("Debug", "ImageManipulator", "Local copy in: ". $destination);

  }

}


/*

// Some code found on internet, explaining simply what to do if we need to treat "flipped" images one day.
// As cameras usually don't flip images, we dont't really need it here now...

// To implement this we'd need to introduce flipV and flipH parameters
// as addition to already existing rotateAngle.


$ort = $exif['IFD0']['Orientation'];

switch($ort)
{
case 1: // nothing
break;

case 2: // horizontal flip
$image->flipImage($public,1);
break;

case 3: // 180 rotate left
$image->rotateImage($public,180);
break;

case 4: // vertical flip
$image->flipImage($public,2);
break;

case 5: // vertical flip + 90 rotate right
$image->flipImage($public, 2);
$image->rotateImage($public, -90);
break;

case 6: // 90 rotate right
$image->rotateImage($public, -90);
break;

case 7: // horizontal flip + 90 rotate right
$image->flipImage($public,1);   
$image->rotateImage($public, -90);
break;

case 8:    // 90 rotate left
$image->rotateImage($public, 90);
break;
}

*/

?>