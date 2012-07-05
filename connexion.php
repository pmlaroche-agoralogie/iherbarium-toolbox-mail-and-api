<?php
namespace iHerbarium; 
$debug_level=0;
include("communConnexion.php");
$tableau_observation = array();
$tab_resultat = array();
	bd_connect();
$_GET = escape_sql_array($_GET);	
        $mot_de_passe="select password
	from fe_users where username = ".$_GET['user']. " ";
	$resultat= mysql_query($mot_de_passe)or die ();
	$mot_de_passe = mysql_fetch_row ($resultat); 
	
	// cas o l'utilisateur n'existe pas 
	if (0==$nbresult=mysql_num_rows($resultat)){
		$tab_resultat['id_user']='-1';
	}
	
		// cas o l'utilisateur existe mais c'est trompŽ de mot de passe 
		else if (! ($mot_de_passe[0] == "".$_GET['psw']. "")) {
			$tab_resultat['id_user']='0';
		}
			// cas o l'utilsateur existe et a entrer le bon de mot de passe
			else{
				$id="select uid
				from fe_users where username = ".$_GET['user']. " ";
				$resultatId= mysql_query($id)or die ();
				$id = mysql_fetch_row ($resultatId); 
				$tab_resultat['id_user']=$id[0];
	
			}
			 
	echo (json_encode($tab_resultat));
?>

