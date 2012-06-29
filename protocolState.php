<?php
namespace iHerbarium;
require_once("myPhpLib.php");

define('PROTOCOL_STATE_NO_STATE', 0);
define('PROTOCOL_STATE_INIT', 1);
define('PROTOCOL_STATE_COLLECT_PHOTOS', 2);
define('PROTOCOL_STATE_CONFIRM', 3);      

function protocolStateArray($state) {
  $arr = array(
	       PROTOCOL_STATE_NO_STATE => 'PROTOCOL_STATE_NO_STATE',
	       PROTOCOL_STATE_INIT => 'PROTOCOL_STATE_INIT',
	       PROTOCOL_STATE_COLLECT_PHOTOS => 'PROTOCOL_STATE_COLLECT_PHOTOS',
	       PROTOCOL_STATE_CONFIRM => 'PROTOCOL_STATE_CONFIRM'
	       );

  return $arr[$state];
}

?>