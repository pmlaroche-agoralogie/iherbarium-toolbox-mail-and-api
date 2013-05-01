<?php
namespace iHerbarium;

require_once("myPhpLib.php");

class Exif {

	private static function parseExifGPSField($exifMeasure, $field) {
    	// Field can be present or not (especially seconds).
		if( !array_key_exists($field, $exifMeasure) ) {
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

	public static function coordinatesFromExif($exif) {
		// Returns an array with two keys: "latitude" and "longitude".

  		// Default is (0, 0), which means, that coordinates are unknown.
		$coordinates = array(
			"latitude"  => 0,
			"longitude" => 0
			);


		if(	isset($exif['GPSLatitude']) &&
			isset($exif['GPSLatitudeRef']) &&
			isset($exif['GPSLongitude']) &&
			isset($exif['GPSLongitudeRef'])
			) {

			// If EXIF data are ok:
			$coordinates = array(
				"latitude"  => static::latitudeFromExif  ($exif['GPSLatitude'],  $exif['GPSLatitudeRef'] ),
				"longitude" => static::longitudeFromExif ($exif['GPSLongitude'], $exif['GPSLongitudeRef'])
				);
		}

		return $coordinates;
	}

}

?>