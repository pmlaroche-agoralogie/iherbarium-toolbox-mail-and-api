<?php
namespace iHerbarium;

//phpinfo(); die();

require_once("myPhpLib.php");
require_once("debug.php");
require_once("config.php");
require_once("logger.php");

require_once("mailSystem.php");

Logger::$logDirSetting = "logDirMailSystem";
Debug::init("main");
//Config::init("Development");
//Config::init("Production");


$sys = new IHerbariumMailSystem();
$sys->fetchAndConsumeNewMail();
$sys->checkTimeouts();

?>