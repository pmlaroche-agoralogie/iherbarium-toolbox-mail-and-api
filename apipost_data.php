<?php

/*
 Receive call to post_data.php from a smartphone or other servers, in order to store on this server the binary data
 NB : the details of the observation are send via another call

the call has two parameters, a json encoded string named information, and the binarydata to store, in a binarydata parameter

the information must contain :
caller_id -> unique identification of the caller, if possible, is a string
apiversion -> for now, must be 1 is an integer
filename -> filename on the calling plateform is a string

mediatype type of media in the binary data part,is a string
value :
flatimg -> filename must end with jpg or png
3dimg -> filename must end with mpo
video -> filename must end with avi or mpg
sound -> filename must end with mp3 or raw

encoding encoding used for binarydata, is a string
value :
raw -> binary data is stored as is
base64 -> base64_decode is applied before storing


the script respond a json string, containing
either
error -> an error text, giving hints about the problem
either
serverfilename -> file name on the server, to be used when calling sending information on observation
ttl -> time to live (in hour, integer) ; after this delay the file on the server has perhaps been deleted (futur use)

*/

function getFileExtension($fileName)
{
   $parts=explode(".",$fileName);
   return $parts[count($parts)-1];
}
$authorized_ext = array('jpeg','jpg','png','mpo','avi','mpg','raw','mp3');

$debug = 0;
$mediapath = "fromapi/";
$response['error']="";

	
//verify the two parameters are given (GET parameters are for debbuging purpose)
if(isset($_GET['information'])) $information = $_GET['information'];
		else if (isset($_POST['information'])) $information = $_POST['information'];
				else $response['error'] = "no 'information' parameter";
if(isset($_GET['binarydata'])) $binarydata  = $_GET['binarydata'];
		else if (isset($_POST['binarydata'])) $binarydata  = $_POST['binarydata'];
				else $response['error'] = "no 'binarydata' data";

if($debug = 1)
	{
	$nomfichier = str_replace(' ','','paramdata-'.microtime().'.txt');
	$debugfile = fopen($mediapath.$nomfichier, 'w');
	fwrite($debugfile, $information."\n".$binarydata);
	fclose($debugfile); 
	}

if($response['error'] == "")
		{
		//verify call parameters structure
		$call_parameters = json_decode($information);
		if(!isset($call_parameters->caller_id) || !isset($call_parameters->filename)|| !isset($call_parameters->apiversion) || !isset($call_parameters->mediatype) || !isset($call_parameters->encoding ))
			{
			
			$response['error'] = "caller_id,filename,apiversion,mediatype,encoding must have value in parameters ";
			}
		else
		{
		// verify extension of filename are correct
		$call_parameters->filename = strtolower($call_parameters->filename);
		$thextension = getFileExtension($call_parameters->filename);
		if(!in_array($thextension,$authorized_ext))
			$response['error'] = "Extension not authorized";
		if ($call_parameters->encoding =="base64")
				{
				$binarydata = base64_decode(chunk_split($binarydata));
				if($binarydata===false)$response['error'] = "not base 64 data";
				}
				
		if($response['error'] == "")
				{
				$servername = "bindata".str_replace(" ","",substr(microtime(),3)).$call_parameters->filename;
				$fp = fopen($mediapath.$servername, 'w');
				fwrite($fp, $binarydata);
				fclose($fp);
				$response['serverfilename'] = "http://apimedia.iherbarium.net/fromapi/".$servername;
				$response['ttl'] = 72;
				}
		}	
	}

if($response['error'] == "")unset($response['error'] );
echo json_encode($response);	
file_put_contents ($mediapath."last_response", json_encode($response));
?>
