<?php

/*
 Receive call to a from a smartphone or other servers, in order to create a new account

the call has one parameter, a json encoded string named information

the information must contain :
caller_id -> unique identification of the caller, if possible, is a string
apiversion -> for now, must be 1 is an integer
username : a string
password : a string
language : a string (value in fr, en, pt, de, it, br, es)
checksum : a hash code made from a private key (one by caller_id) and other parameters

the information can also have
first name : a string
last name : a string
avatar : an URL of a picture (already uploaded with apipost_data.php)

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
	$nomfichier = 'fromapi/informationdata-'.time().'.txt';
	$debugfile = fopen($nomfichier, 'w');
	fwrite($debugfile, "\n POST = : ".print_r($_POST,true));
 	fwrite($debugfile, "\n GET = : ".print_r($_GET,true));
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
		  $response['error'] = "this username already exists on the server.";
		  
	       //compute a valid chcsum which has to be computed on the mobile app
	       /*$privatekey['iphonev1']="xxxxxxxxxxxxxxxxxxxxx";//to be replaced by the real key, not public...
	       if ($call_parameters->caller_id!='iphonev1')
		  $response['error'] = "this username already exists on the server";
		  
	       $goodchecksum = md5($privatekey[$call_parameters->caller_id].$call_parameters->username);
	       */
	       if($response['error'] == "")
				{
				 // Create a new User.
				 if($call_parameters->password=="")
				    {// Generate a cool password.
				    $password = substr(md5("myhiddensalt".$call_parameters->username), 0, 6);
				    }
				 else
				    $password = $call_parameters->password;
				 
				 if(isset($call_parameters->lastname) )
				    $mylastname = $call_parameters->lastname;
				    else
				    $mylastname = "";
				 if(isset($call_parameters->avatar) )
				    $myavatar = $call_parameters->avatar;
				    else
				    $myavatar = "";
				 
				   
				 if(isset($call_parameters->firstname) && ($call_parameters->firstname != ""))
				    {
				       $myfirstname = $call_parameters->firstname;
				       $myname = $myfirstname. " ".$mylastname;
				    }
				    else
				    {
				       $myfirstname = "";
				       $myname = $mylastname;
				    }
				    
				 if($myname == "" )
				   {
				    $left = strpos($call_parameters->username,"@");
				    if($left <1)
				       $left=strlen($call_parameters->username);
				    $myname = substr($call_parameters->username,0,$left);
				    }
				 // Create the user.
				 //$localTypoherbarium->createUser($call_parameters->username, $password, $call_parameters->language);
				 $sql_create_account="insert  INTO `fe_users`  (`username`,`email`, `password`, `name`, `language`,`first_name`,`last_name`,`www`,`pid`,`usergroup`) values ( '".$call_parameters->username."','".$call_parameters->username."','".$password."','".$myname."','".$call_parameters->language."','".$myfirstname."','".$mylastname."','".$myavatar."',2,'1' );";
				 
				 $resultat = mysql_query($sql_create_account) or die ();
				 $id_user = mysql_insert_id();
				 $sql_notification = "INSERT INTO `typoherbarium`.`iherba_notification` (`id_notification`, `ts_creation`, `message_type`, `preferred_language`, `parameters`, `preferred_media`)
				 VALUES (NULL, CURRENT_TIMESTAMP, 'account-open', 'fr', '".'{"user":"'.$id_user.'"}'."', 'mail');";
				 $resultat = mysql_query($sql_notification) or die ();
				 
				 $response['responsevalue']="ok";
				 $response['newpassword']=$password;
				}
		}	
	}

if($response['error'] == "")unset($response['error'] );
echo json_encode($response);
if($debug = 1)
	{
	$nomfichier = 'fromapi/_return_informationdata-'.time().'.txt';
	$debugfile = fopen($nomfichier, 'w');
	fwrite($debugfile, json_encode($response));
	fclose($debugfile); 
	}
?>
