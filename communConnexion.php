<?php
namespace iHerbarium;

function db_conform_global($s) 
{ 
    if (!is_array($s)) mysql_real_escape_string($s); 
    function db_conform_array_callback(&$item, $key) { echo $key; $item = db_conform($item); } 
    array_walk($s, 'db_conform_array_callback'); 
    return $s; 
} 


function bd_connect(){
  //$serveur = mysql_connect("localhost","_SHELL_REPLACED_USER_TEST","_SHELL_REPLACED_PWD_TEST"); // TEST
  $serveur = mysql_connect("localhost","_SHELL_REPLACED_USER_PRODUCTION","_SHELL_REPLACED_PWD_PROD"); // PRODUCTION
  if (!$serveur)
    {
      if($debug_level>0)echo mysql_error();
      die('');
    }
  
  //$bd = mysql_select_db('_SHELL_REPLACED_DATABASE_TEST', $serveur); // TEST
  $bd = mysql_select_db('_SHELL_REPLACED_DATABASE_PROD', $serveur); // PRODUCTION
  if (!$bd)
    {
      if($debug_level>0)echo mysql_error();
      die ('');
    }
}

?>