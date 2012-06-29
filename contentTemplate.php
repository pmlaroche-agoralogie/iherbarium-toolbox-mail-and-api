<?php
namespace iHerbarium;

require_once("myPhpLib.php");

// Interfaces

interface ContentTemplateFactory {
  public function getTemplate($msgType, $msgForm, $lang);
}

interface ContentTemplate {
  public function getContent($part);
  public function ask($part);
}


// LocalDB factory and template

class LocalDBContentTemplateFactory
implements ContentTemplateFactory {
  
  public function getTemplate($msgType, $msgForm, $lang) {
    $template = new LocalDBContentTemplate();
    $template->msgType = $msgType;
    $template->msgForm = $msgForm;
    $template->lang    = $lang;
    return $template;
  }
}

class LocalDBContentTemplate
implements ContentTemplate {

  // Local database connection.
  private $local = NULL;
  
  function __construct() {
    $this->local = LocalDB::get();
  }

  // Template description.
  public $msgType;
  public $msgForm;
  public $lang;

  // Get Template content.
  public function getContent($part) {
    $template = 
      $this->local->getTemplate(
				$this->msgType, 
				$this->msgForm, 
				$this->lang, 
				$part
				);

    if( is_null($template) )
      return "TEMPLATE[" . $part . "]";
    else
      return $template;
  }

  // Shortcut...
  public function ask($part) {
    return $this->getContent($part);
  }

}