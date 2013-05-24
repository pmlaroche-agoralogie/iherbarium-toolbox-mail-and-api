<?php

/*
 Receive call to api_verify_username_password.php from a smartphone or other servers, in order to verify a login/password

the call has one parameter, a json encoded string named information

the information must contain :
caller_id -> unique identification of the caller, if possible, is a string
apiversion -> for now, must be 1 is an integer
username : a string
md5password : a string
language : a string (value in fr, en, pt, de, it, br, es)

the script respond a json string, containing
responsevalue -> ok or nok
error -> an error text, giving hints about the problem

*/

namespace iHerbarium;

include("communConnexion.php");

function get_string_language_sql($identifiant,$chosen_language)
{
  bd_connect();
  
  $sql_list_translate =
    "SELECT * FROM  `ih_translation` 
    WHERE  `label` LIKE  '$identifiant' and lang LIKE '$chosen_language%'";
    
  $result_translate=mysql_query ($sql_list_translate) or die ();
  
  if(mysql_num_rows($result_translate)==0)
    {
      $sql_list_translate =
      "SELECT * FROM  `ih_translation` 
      WHERE  `label` LIKE  '$identifiant' and lang LIKE 'en%'";
      $result_translate=mysql_query ($sql_list_translate) or die ();
    }
  if(mysql_num_rows($result_translate)==0)
    {//identifiant not found
    return $identifiant;
    }
    else
    {
    $ligne = mysql_fetch_array($result_translate);
    return $ligne['translated_text'];
    }
}


$debug = 0;
$mediapath = "fromapi/";
$response['error']="";
$response['responsevalue']="nok";

	
//verify the parameter is given (GET parameters are for debbuging purpose)
if(isset($_GET['information'])) $information = $_GET['information'];
		else if (isset($_POST['information'])) $information = $_POST['information'];
				else $response['error'] = "no 'information' parameter";
if($debug == 1)
	{
	$nomfichier = 'informationdata-'.time().'.txt';
	$debugfile = fopen($mediapath.$nomfichier, 'w');
	fwrite($debugfile, $information);
	fclose($debugfile); 
	}

if($response['error'] == "")
   {
     bd_connect();
   //verify call parameters structure
   $information= $_GET['information'];
   $call_parameters = json_decode($information);
   $call_parameters=escape_sql_array($call_parameters);
   if(!isset($call_parameters->caller_id) ||  !isset($call_parameters->language)||  !isset($call_parameters->apiversion) || !isset($call_parameters->username) )
	   {
	   
	   $response['error'] = "caller_id,apiversion,username, language must have value in parameters ";
	   }
   else
   {
     
   $sql_mot_de_passe="SELECT password,email FROM  `fe_users`  WHERE  `username` = '".$call_parameters->username."' ;";
   $resultat = mysql_query($sql_mot_de_passe) or die ($sql_mot_de_passe);
   if(mysql_num_rows($resultat)<1)
     $response['error'] = "this username does not match any on the server";
   
   
   if($response['error'] == "")
		   {
		 $ligne = mysql_fetch_row ($resultat);
		$mot_de_passe = $ligne[0];
		 $title = get_string_language_sql("mail_ask_for_password_title",$call_parameters->language);
		 $body = get_string_language_sql("mail_ask_for_password_body",$call_parameters->language);
		 $body = str_replace('%s$1',$mot_de_passe,$body);
		 mail($ligne[1],$title,$body );
		 $response['responsevalue']="ok";
		   }
   }
}

if($response['error'] == "")unset($response['error'] );
echo json_encode($response);	
?>
