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

function getFileExtension($fileName)
{
   $parts=explode(".",$fileName);
   return $parts[count($parts)-1];
}

$debug = 0;
$mediapath = "fromapi/";
$response['error']="";
$response['responsevalue']="nok";

	
//verify the parameter is given (GET parameters are for debbuging purpose)
if(isset($_GET['information'])) $information = $_GET['information'];
		else if (isset($_POST['information'])) $information = $_POST['information'];
				else $response['error'] = "no 'information' parameter";
if($debug = 1)
	{
	$nomfichier = 'informationdata-'.time().'.txt';
	$debugfile = fopen($mediapath.$nomfichier, 'w');
	fwrite($debugfile, $information);
	fclose($debugfile); 
	}

if($response['error'] == "")
		{
		//verify call parameters structure
		$call_parameters = json_decode($information);
		if(!isset($call_parameters->caller_id) ||  !isset($call_parameters->language)||  !isset($call_parameters->apiversion) || !isset($call_parameters->username) || !isset($call_parameters->md5password ))
			{
			
			$response['error'] = "caller_id,apiversion,username,md5password, language must have value in parameters ";
			}
		else
		{
		  bd_connect();
$_GET = escape_sql_array($_GET);
$_POST = escape_sql_array($_POST);
		$sql_mot_de_passe="SELECT password FROM  `fe_users`  WHERE  `username` = '".$call_parameters->username."' ;";
		$resultat = mysql_query($sql_mot_de_passe) or die ($sql_mot_de_passe);
		if(mysql_num_rows($resultat)<1)
		  $response['error'] = "this username/password does not match any on the server";
		
		if($response['error'] == "")
				{
			      $mot_de_passe = mysql_fetch_row ($resultat);
				if($call_parameters->md5password == md5($mot_de_passe[0]))
				    $response['responsevalue']="ok";
				    else
				    $response['error'] = "this username/password does not match any on the server";
				}
		}	
	}

if($response['error'] == "")unset($response['error'] );
echo json_encode($response);	
?>
