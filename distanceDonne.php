<?php
namespace iHerbarium;

function adaptDistance($Lat1,$Long1,$Lat2,$Long2){
  
    if ($Lat1 == 0 || $Long1 == 0 || $Lat2 == 0 || $Long2 == 0 ){
            $result = ' 0000000';
        }
    else {
        $pluriel ='s';
        $distance;
        $dLat1InRad = $Lat1 * (pi() / 180);
        $dLong1InRad = $Long1 * (pi() / 180);
        $dLat2InRad = $Lat2 * (pi() / 180);
        $dLong2InRad = $Long2 * (pi() / 180);
        
        $dLongitude = $dLong2InRad - $dLong1InRad;
        $dLatitude = $dLat2InRad - $dLat1InRad;
        
        // Intermediate result a.
        $a = pow(sin($dLatitude / 2.0), 2.0) + cos($dLat1InRad) * cos($dLat2InRad) * pow(sin($dLongitude / 2.0), 2.0);
        
        // Intermediate result c (great circle distance in Radians).
        $c = 2.0 * atan2(sqrt($a), sqrt(1.0 - $a));
        
        $kEarthRadiusKms = 6376.1;
        $distance = $kEarthRadiusKms * $c;
        
        if (intval($distance) == 0) {
                $distance = intval($distance*1000);
                 if ($distance==1) $pluriel='';
                $result = '&#224; ' .$distance. " m&#232;tre" .$pluriel;
            }
            else {
                $distance = intval(($distance/1000));
                if ($distance==1) $pluriel='';
                $result = '&#224; ' .$distance. " kilom&#232;tre" .$pluriel;
            }
    }

    return $result;
}

?>

