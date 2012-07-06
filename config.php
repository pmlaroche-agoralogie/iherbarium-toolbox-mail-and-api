<?php
namespace iHerbarium;
require_once("myPhpLib.php");

class Config {

  static private $config = NULL;

  static private $configs =
    array(
	  /* In these settings both Mail System and Observation Receiver
	   * are on the same machine - agoralogie1 */
	  "Development" =>
	  array(
		// Logger Config
		"logDirMailSystem"          => "/home/iherbariumtest/htdocs/boiteauxlettres/logs/",
		"logDirObservationReceiver" => "/home/iherbariumtest/htdocs/boiteauxlettres/logs/",
		"logDirObservationDelete"   => "/home/iherbariumtest/htdocs/boiteauxlettres/logs/",
		"logDirGetUserInfo"         => "/home/iherbariumtest/htdocs/boiteauxlettres/logs/",

		// Mail System Config
		"attachmentsDir"             => "/home/iherbariumtest/htdocs/boiteauxlettres/attachedFiles/",
		"attachmentsURL"             => "http://test.iherbarium.fr/boiteauxlettres/attachedFiles/",
		"transferablePhotoLocalDir"  => "/home/iherbariumtest/htdocs/boiteauxlettres/data/",
		"transferablePhotoRemoteDir" => "http://test.iherbarium.fr/boiteauxlettres/data/",
		"observationReceiverURL"     => "http://test.iherbarium.fr/boiteauxlettres/observationReceive.php",
		"getUserInfoURL"             => "http://test.iherbarium.fr/getUserInfo.php",
		"localStorageDatabase"       => "LocalStorageDevelopment",

		// File Paths
		"filesLocalDir"        => "/home/iherbariumtest/htdocs/medias/sources/",

		"vignettesLocalDir"    => "/home/iherbariumtest/htdocs/medias/vignettes/",
		"bigLocalDir"          => "/home/iherbariumtest/htdocs/medias/big/",

		"roiSourcesLocalDir"   => "/home/iherbariumtest/htdocs/medias/roi/",
		"roiVignettesLocalDir" => "/home/iherbariumtest/htdocs/medias/roi_vignettes/",

		"lastPostRequestFile"  => "/home/iherbariumtest/htdocs/boiteauxlettres/data/lastPost.txt",
		"lastObservationReceiveResultFile" => "/home/iherbariumtest/htdocs/boiteauxlettres/data/lastObservationReceiveResult.txt",

		// URLs
		"filesURL"            => "/medias/sources/",

		"vignettesURL"        => "/medias/vignettes/",
		"bigURL"              => "/medias/big/",

		"roiSourcesURL"       => "/medias/roi/",
		"roiVignettesURL"     => "/medias/roi_vignettes/",

		"lastPostRequestURL"  => "/boiteauxlettres/data/lastPost.txt",

		// Databases
		"localTypoherbariumDatabase"               => "LocalTypoherbariumDevelopment",
		"observationReceiverTypoherbariumDatabase" => "LocalTypoherbariumDevelopment",
		"observationDeleteTypoherbariumDatabase"   => "LocalTypoherbariumDevelopment",

		// Balade Config
		"baladeTypoherbariumDatabase" => "LocalTypoherbariumDevelopment",

		// Plugins config
		"pluginsTypoherbariumDatabase"   => "LocalTypoherbariumProduction"
		),
	  
	  /* In these settings: 
	     + Mail System is on agoralogie1
	     + Observation Receiver script is on the application server (at address www.agoralogie.fr). */
	  "Production" =>
	  array(
		// Logger Config
		"logDirMailSystem"          => "/home/expert1/htdocs/boiteauxlettres/logs/",
		"logDirObservationReceiver" => "/home/ftpiherbarium/htdocs/boiteauxlettres/logs/",
		"logDirObservationDelete"   => "/home/ftpiherbarium/htdocs/boiteauxlettres/logs/",
		"logDirGetUserInfo"         => "/home/ftpiherbarium/htdocs/boiteauxlettres/logs/",

		// Mail System Config
		"attachmentsDir"             => "/home/expert1/htdocs/boiteauxlettres/attachedFiles/",
		"attachmentsURL"             => "http://bal.iherbarium.net/attachedFiles/",
		"transferablePhotoLocalDir"  => "/home/expert1/htdocs/boiteauxlettres/data/",
		"transferablePhotoRemoteDir" => "http://bal.iherbarium.net/data/",
		"observationReceiverURL"     => "http://www.iherbarium.fr/boiteauxlettres/observationReceive.php",
		"getUserInfoURL"             => "http://www.iherbarium.fr/boiteauxlettres/getUserInfo.php",
		"localStorageDatabase"       => "LocalStorage",

		// File Paths
		"filesLocalDir"        => "/home/ftpiherbarium/htdocs/medias/sources/",

		"vignettesLocalDir"    => "/home/ftpiherbarium/htdocs/medias/vignettes/",
		"bigLocalDir"          => "/home/ftpiherbarium/htdocs/medias/big/",

		"roiSourcesLocalDir"   => "/home/ftpiherbarium/htdocs/medias/roi/",
		"roiVignettesLocalDir" => "/home/ftpiherbarium/htdocs/medias/roi_vignettes/",

		"lastPostRequestFile"  => "/home/ftpiherbarium/htdocs/lastPost.txt",
		"lastObservationReceiveResultFile" => "/home/ftpiherbarium/htdocs/lastObservationReceiveResult.txt",

		// URLs
		"filesURL"            => "/medias/sources/",

		"vignettesURL"        => "/medias/vignettes/",
		"bigURL"              => "/medias/big/",

		"roiSourcesURL"       => "/medias/roi/",
		"roiVignettesURL"     => "/medias/roi_vignettes/",

		"lastPostRequestURL"  => "/boiteauxlettres/data/lastPost.txt",

		// Databases
		"localTypoherbariumDatabase"               => "LocalTypoherbariumProduction",
		"observationReceiverTypoherbariumDatabase" => "LocalTypoherbariumProduction",
		"observationDeleteTypoherbariumDatabase"   => "LocalTypoherbariumProduction",

		// Balade Config
		"baladeTypoherbariumDatabase" => "LocalTypoherbariumProduction",

		// Plugins config
		"pluginsTypoherbariumDatabase"   => "LocalTypoherbariumProduction"
		)
	  );
  
  static public function init($config) {
    // Sanity check.
    assert( array_key_exists($config, self::$configs) );
    assert( is_null(self::$config) );

    self::$config = $config;
  }

  static public function get($setting) {
    // Sanity check.
    assert(! is_null(self::$config));
    //debug("Debug", "Config", "config = " . self::$config . ", getting setting " . $setting);
    assert( array_key_exists($setting, self::$configs[self::$config]) );
    
    // Fetch the setting.
    return self::$configs[self::$config][$setting];
  }

}

// Init
require_once("configInit.php");

?>