<?php

/*
 Receive call to a from a smartphone or other servers, in order to createa new account

the call has one parameter, a json encoded string named information

the information must contain :
caller_id -> unique identification of the caller, if possible, is a string
apiversion -> for now, must be 1 is an integer
username : a string
password : a string
language : a string (value in fr, en, pt, de, it, br, es)

the information can also have
first name : a string
last name : a string
avatar : an URL of a picture (already upload with apipost_data.php)

the script respond a json string, containing
responsevalue -> ok or nok
error -> an error text, giving hints about the problem

if the password field is empty, it create a new random password, and return a newpassword value in the response
if the password is non-empty, it contains a password in clear, which is used
*/

namespace iHerbarium;

include("communConnexion.php");


$debug = 0;
$response['error']="";
$response['responsevalue']="nok";

bd_connect();


//verify the parameter is given (GET parameters are for debbuging purpose)
if(isset($_GET['information'])) $information = $_GET['information'];
		else if (isset($_POST['information'])) $information = $_POST['information'];
				else $response['error'] = "no 'information' parameter";
if($debug = 1)
	{
	$nomfichier = 'informationdata-'.time().'.txt';
	$debugfile = fopen($nomfichier, 'w');
	fwrite($debugfile, $information);
	fclose($debugfile); 
	}

if($response['error'] == "")
		{
		//verify call parameters structure
		$call_parameters = escape_sql_array(json_decode($information));
		if(!isset($call_parameters->caller_id) ||  !isset($call_parameters->language)||  !isset($call_parameters->apiversion) || !isset($call_parameters->username) || !isset($call_parameters->password ))
			{
			//print_r($information);echo "param";print_r($call_parameters);
			$response['error'] = "caller_id,apiversion,username,password, language must have value in parameters ";
			}
		else
		{
		 

		$sql_mot_de_passe="SELECT password FROM  `fe_users`  WHERE  `username` = '".$call_parameters->username."' ;";
		$resultat = mysql_query($sql_mot_de_passe) or die ($sql_mot_de_passe);
		if(mysql_num_rows($resultat)>0)
		  $response['error'] = "this username already exists on the server";
		
		if($response['error'] == "")
				{
				 // Create a new User.
				 if($call_parameters->password=="")
				    {// Generate a cool password.
				    $password = substr(md5($call_parameters->username), 0, 6);
				    }
				 else
				    $password = $call_parameters->password;
				  
				  if(isset($call_parameters->name) )
				    $name = $call_parameters->name;
				    else
				    {
				    $left = strpos($call_parameters->username,"@");
				    if($left <1)
				       $left=strlen($call_parameters->username);
				    $name = substr($call_parameters->username,0,$left);
				    }
				 // Create the user.
				 //$localTypoherbarium->createUser($call_parameters->username, $password, $call_parameters->language);
				 $sql_create_account="insert  INTO `fe_users`  (`username`,`email`, `password`, `name`, `language`,`pid`,`usergroup`) values ( '".$call_parameters->username."','".$call_parameters->username."','".$password."','".$name."','".$call_parameters->language."',2,'1' );";
				 
				 $resultat = mysql_query($sql_create_account) or die ($sql_create_account);
		
				 $response['responsevalue']="ok";
				 $response['newpassword']=$password;
				}
		}	
	}

if($response['error'] == "")unset($response['error'] );
echo json_encode($response);	
?>
