<?php
namespace iHerbarium;

require_once("myPhpLib.php");


/* CHOICES */

interface ChoiceI {
  public function hasImage();
  public function getText();
  public function getImageSrc();
  public function getImageSrcFilename();
  public function getDescription();
  public function getAnswerValue();
}

class Choice 
implements ChoiceI {
  private $text             = NULL; public function setText($text) { $this->text = $text; return $this; }
  private $imageSrcDir      = "";   public function setImageSrcDir($imageSrcDir) { $this->imageSrcDir = $imageSrcDir; return $this; }
  private $imageSrcFilename = NULL; public function setImageSrcFilename($imageSrcFilename) { $this->imageSrcFilename = $imageSrcFilename; return $this; }
  private $description      = NULL; public function setDescription($description) { $this->description = $description; return $this; }
  private $answerValue      = NULL; public function setAnswerValue($answerValue) { $this->answerValue = $answerValue; return $this; }

  public function hasImage()            { return (isset($this->imageSrcFilename) && 
						  $this->imageSrcFilename); }
  public function getText()             { return $this->text; }
  public function getImageSrc()         { return $this->imageSrcDir . $this->imageSrcFilename; }
  public function getImageSrcFilename() { return $this->imageSrcFilename; }
  public function getDescription()      { return $this->description; }
  public function getAnswerValue()      { return $this->answerValue; }

  static public function create() {
    return new static();
  }
}

class ROIChoice
implements ChoiceI {

  public $roi = NULL;

  function __construct(TypoherbariumROI $roi) {
    $this->roi = $roi;
  }

  public function hasImage()            { return true; }
  public function getText()             { return "ROI " . $this->roi->id; }
  public function getImageSrc()         { return $this->roi->fileVersions["vignette"]->url(); }
  public function getImageSrcFilename() { return $this->roi->fileVersions["vignette"]->filename; }
  public function getDescription()      { return "ROI " . $this->roi->id;; }
  public function getAnswerValue()      { return $this->roi->id; }
}


/* QUESTIONS */

interface QuestionI {
  public function getId();      /* this doesn't have to be a database id, but some kind of reference */
  public function getType();
  public function getText();    /* returns string */
  public function getAskLog();   /* identifier of the question in the asked question log; null if question wasn't asked yet */
}

interface ClosedChoiceQuestionI
extends QuestionI {
  public function getChoices(); /* returns array of Choice objects */
}

class ClosedChoiceQuestion
implements ClosedChoiceQuestionI {
  private $id      = NULL;    public function setId($id) { $this->id = $id; return $this; }
  private $type    = NULL;    public function setType($type) { $this->type = $type; return $this; }
  private $text    = NULL;    public function setText($text) { $this->text = $text; return $this; }
  private $choices = array(); public function addChoice($choice, $key = NULL) { if($key) $this->choices[$key] = $choice; else $this->choices[] = $choice; return $this; } public function setChoices($choices) { $this->choices = $choices; return $this; }
  private $askLog   = NULL;   public function setAskLog($askLog) { $this->askLog = $askLog; return $this; }

  public function getId()      { return $this->id; }
  public function getType()    { return $this->type; }
  public function getText()    { return $this->text; }
  public function getChoices() { return $this->choices; }
  public function getAskLog()  { return $this->askLog; }  
}

class TypoherbariumClosedChoiceQuestion
implements ClosedChoiceQuestionI {

  private $askLog = NULL; public function setAskLog($askLog) { $this->askLog = $askLog; return $this; }
  
  public $ccq = NULL; // ccq : ClosedChoiceQuestion

  function __construct(TypoherbariumROIQuestion $q, TypoherbariumROIQuestionSkinI $s) {
    $this->ccq = $s->question($q);
  }

  public function getId()      { return $this->ccq->getId(); }
  public function getType()    { return $this->ccq->getType(); }
  public function getText()    { return $this->ccq->getText(); }
  public function getChoices() { return $this->ccq->getChoices(); }
  public function getAskLog()  { return $this->askLog; }
}

class ComparisonClosedChoiceQuestion
extends ClosedChoiceQuestion {

  private $askLog = NULL; public function setAskLog($askLog) { $this->askLog = $askLog; return $this; }
  
  private $rois;
  private $s;

  function __construct($rois, TypoherbariumComparisonSkinI $s) {
    $this->rois = $rois;
    $this->s = $s;
  }
  
  public function getId()      { return json_encode(array_map(function($roi) { return $roi->id; }, $this->rois)); }
  public function getType()    { return "ROIComparison"; }
  public function getText()    { return $this->s->comparisonText(); }
  public function getChoices() { 
    return array_map(
		     function(TypoherbariumROI $roi) { 
		       return new ROIChoice($roi); 
		     }, $this->rois); 
  }
  public function getAskLog()   { return $this->askLog; }
		     
}

interface TypoherbariumROIQuestionSkinI {
  public function question(TypoherbariumROIQuestion $question);
}

interface TypoherbariumComparisonSkinI {
  public function comparisonText();
}

interface TypoherbariumROIQuestionViewSkinI {
  public function helpToIdentify();
  public function abuse();
  public function exception();
  public function illustrationFor();
}

interface TypoherbariumGroupSkinI {
  public function group($group);
}

class TypoherbariumSkin
implements TypoherbariumROIQuestionSkinI, TypoherbariumROIQuestionViewSkinI, TypoherbariumComparisonSkinI, TypoherbariumGroupSkinI {
  
  public $lang;
  private $translations;
  
  private $fallbackLang;

  function __construct($lang, $fallbackLang = 'fr') {
    $this->lang = $lang;
    $this->fallbackLang = 'fr';
    $this->init();
  }

  protected function init() {
    
    $local = LocalTypoherbariumDB::get();

    $this->translations =
      array(
	    'helpToIdentify' =>
	    array(
		  'fr' => "Merci de nous aider &agrave; identifier :",
		  'en' => "Please help us to identify this picture:",
		  'de' => "Danke für ihre Mitthilfe, diese Abbildungen zu identifizierenen:"
		  ),

	    'abuse' =>
	    array(
		  'fr' => "Avertir le mod&eacute;rateur", 
		  'en' => "Report Abuse", 
		  'de' => "Report Abuse", 
		  'pt' => "Report Abuse"
		  ),
	    
	    'exception' =>
	    array(
		  'fr' => "Question non adapt&eacute;e &agrave; la photo", 
		  'en' => "Not a meaningful question for this picture", 
		  'de' => "i don't know", 
		  'pt' => "i don't know"
		  ),

	    'illustrationFor' =>
	    array(
		  'fr' => "dessin pour", 
		  'en' => "illustration for"
		  ),

	    'tag' => $local->loadTagTranslations(),
	    
	    'question' => $local->loadQuestionTranslations(),

	    'comparisonText' =>
	    array(
		  'fr' => "Quelle photo ci-dessous est la plus proche de la photo ci-dessus ?", 
		  'en' => "Which one of the photos below is the most similar to the photo above"
		  ),

	    'group' => $local->loadGroupTranslations()

	    );
    
  }
  
  private function getTranslation($what) {
    // Is the desired translation available?
    if(isset($what[$this->lang]))
      // Yup.
      return $what[$this->lang];
    else if(isset($what[$this->fallbackLang]))
      // No. Give a standard "backup translation" (french?) if it exists.
      return $what[$this->fallbackLang];
    else
      // If even the "backup translation" doesn't exist, give nothing.
      return "";
  }

  public function helpToIdentify() {
    return 
      $this->getTranslation($this->translations['helpToIdentify']);
  }

  public function abuse() {
    return 
      $this->getTranslation($this->translations['abuse']);
  }

  public function exception() {
    return 
      $this->getTranslation($this->translations['exception']);
  }

  public function illustrationFor() {
    return 
      $this->getTranslation($this->translations['illustrationFor']);
  }
  
  public function tag(TypoherbariumTag $tag) {
    return (object) 
      $this->getTranslation($this->translations['tag'][$tag->tagId]);
  }

  public function question(TypoherbariumROIQuestion $question) {
    return (object) 
      $this->getTranslation($this->translations['question'][$question->id]);
  }

  public function comparisonText() {
    return 
      $this->getTranslation($this->translations['comparisonText']);
  }

  public function group($group) {
    return (object)
      $this->getTranslation($this->translations['group'][$group->id]);
  }

  
}


/* QuestionView */

class QuestionView {

  private $defaultOptions = NULL;

  function __construct() {
    $this->defaultOptions =
      array(
	    'lang' => "fr",
	    'sourceanswer' => "network",
	    'referrant' => "",
	    'choice99asImage' => true
	    );

  }
  
  public function viewQuestion(ClosedChoiceQuestionI $q, TypoherbariumROI $context, TypoherbariumROIQuestionViewSkinI $x, $options = array()) {

    $options = array_merge($this->defaultOptions, $options);
    if($_SERVER['REMOTE_ADDR']=='94.23.195.65')
	$xmlgeneration = 1; else $xmlgeneration = 0;
    $content = '';

    // iHerbarium logo.
    if($xmlgeneration==0)
	$content .= 
      '<a href="/index.php?id=accueil" target="_blank">' .
      '<img src="/interface/logoiherbarium_135.jpg" border=0 />' .
      '</a><br>';
    
    // "Please help to identify"
    if($xmlgeneration==0)
$content .= 
      '<a href="/index.php?id=accueil" target="_blank">' . 
      '<span style="font-size:small;">' .
      $x->helpToIdentify() .
      '</span></a><br>';


    $roi =& $context;

    // ROI's image.
    if($xmlgeneration==0)
	$content .=
      '<a href="/scripts/large.php?name=' . ($roi->fileVersions["source"]->filename) . '" target="_blank">' .
      '<img src="' . $roi->fileVersions["vignette"]->url() . '" border="0"></a>' . "<br/>\n";
	else
	{
	$content .= '<image>'.$roi->fileVersions["vignette"]->url().'</image>';
        $content .= '<id>'. $q->getAskLog()->id.'</id>';

	}

    // Abuse warning.
if($xmlgeneration==0)
    $content .=
      '<font size="-2">' .
      '<a href="/collaborative/rapport.php?id_roi=' . $roi->id . '">' . $x->abuse() . '</a>' . 
      '</font>' .
      "<br/><br/>\n";
			
    // Question text.
if($xmlgeneration==0)
    $content .= $q->getText() . "<br/>\n";
else
	$content .= '<texte><![CDATA['.$q->getText()  .']]></texte>';
    
    // Choices.
    $choices =  $q->getChoices();
    
    foreach ($choices as $choice) {
      $content .= $this->viewChoice($choice, $x, $q->getAskLog()->id);
    }

    // Choice 99...

    if($options['choice99asImage']) {
      $choice99 = 
	Choice::create()
	->setAnswerValue      (99)
	->setImageSrcDir      ("../dessins/w130/")
	->setImageSrcFilename ("99_nsp.jpg")
	->setDescription      ($x->exception());
    } else {
      $choice99 =
	Choice::create()
	->setAnswerValue (99)
	->setText        ($x->exception())
	->setDescription ($x->exception());
    }
   if($xmlgeneration==0) 
    $content .= $this->viewChoice($choice99, $x, $q->getAskLog()->id) ."<br/>\n";
	else
	$content .= $this->viewChoice($choice99, $x, $q->getAskLog()->id) ;
   
// Referrant //added by PLAROCHE to follow the initial referrer of the question
    if(isset($_POST['referrant']))
      $referrant = $_POST['referrant'];
    else {
      if(isset($_SERVER['HTTP_REFERER']))
	$referrant = $_SERVER['HTTP_REFERER'];
      else
	$referrant = "";
    }
if($xmlgeneration==0) 
    $content .=
      '<form  id="questionForm' . $q->getAskLog()->id . '" method="post" action="?thisIsAnswer=1" enctype="multipart/form-data">' .
      '<input type="hidden" name="questionType" value="' . $q->getType() . '" />' .
      '<input type="hidden" name="questionId"   value=\'' . $q->getId() . '\' />' .
      '<input type="hidden" name="context"      value="' . $roi->id . '">' .
      '<input type="hidden" name="askId"        value="' . $q->getAskLog()->id . '" />' .
     '<input type="hidden" name="referrant" 	value="'.$referrant.'">'.      
      '<input type="hidden" name="answerValue"  value="noAnswer" id="hiddenAnswer' . $q->getAskLog()->id . '" />' .

      '</form>';

    return $content;
    
  }


  private function choiceElementAttributes($choice, $askId) {
    return
      ' ' .
      'style="cursor: pointer;" ' .
      'onClick="' .
      'document.getElementById(\'hiddenAnswer' . $askId . '\').value=' . $choice->getAnswerValue() . ';' .
      'document.getElementById(\'questionForm' . $askId . '\').submit();"' .
      ' ';
  }


  private function viewChoice(ChoiceI $choice, TypoherbariumSkin $x, $askId) {

/* ELGG TEST if($_SERVER['REMOTE_ADDR']=='94.23.195.65')
        $xmlgeneration = 1; else $xmlgeneration = 0;*/

    $content = "";

    if( $choice->hasImage() ) {
if($xmlgeneration == 0)
      $content .=
	// <div>
	'<div class="question-image">' .
	  
	// <div>
	'<div' . $this->choiceElementAttributes($choice, $askId) . '>' .
	  
	// <img/>
	'<img class="questionpic" ' .
	'alt="' . $x->illustrationFor() . ' ' . $choice->getDescription() . '" ' .
	'src="' . $choice->getImageSrc() .
	'">' .

	// </div>
	'</div>'.

	// <a/>
	'<a href="' . '/scripts/licence.php?name=' . $choice->getImageSrcFilename() . '" target="_blank">' .
	'<img class="questionclip" src="/collaborative/Magnify-clip-tiny_bottom-right-white-gradient.png">' .
	'</a>' .
	  
	// </div>
	'</div>' . 

        // line break
	"\n";
else
$content .=
        '<choix>' .
        '<id>'. $choice->getAnswerValue().  '</id>' .
        '<image> ' . $choice->getImageSrc().'</image>'.
        '<texte>'  . '</texte> ' .
        "</choix>\n";
	
    } else {
if($xmlgeneration == 0)
      $content .=
	// <div>
	'<div class="question-texte" style="background:yellow; margin-bottom: 12px">' .
	  
	// <div/>
	'<div' . $this->choiceElementAttributes($choice, $askId) . '>' . 
	$choice->getText() .
	'</div>' .
	  
	// </div>
	'</div>' .

	// line break
	"\n";
else 
$content .=
        '<choix>' .
        '<id>'. $choice->getAnswerValue().  '</id>' .
        '<image> ' . '</image>'.
        '<texte><![CDATA['  . $choice->getText() . ']]></texte> ' .
        "</choix>\n";

    

    }
      
    return $content;    
  }

}


/* AnswerHandler */

class AnswerHandler {

  public function receiveAnswer() {
    
    // Import vars

    // About asked question
    $questionType = $_POST['questionType'];
    $questionId   = $_POST['questionId'];
    $context      = $_POST['context'];

    // Reference to asking the question
    $askId        = $_POST['askId'];

    // Answer value
    $answerValue  = $_POST['answerValue'];


    // Additional information

    // IP
    $ip = $_SERVER['REMOTE_ADDR'];

    // Source
    if(isset($_GET['answeringUser']))
      $source = $_GET['answeringUser'];
    else                             
      $source = "network"; 

    // Referrant
    if(isset($_POST['referrant']))
      $referrant = $_POST['referrant'];
    else {
      if(isset($_SERVER['HTTP_REFERER']))
	$referrant = $_SERVER['HTTP_REFERER'];
      else
	$referrant = "";
    }
    
    // Prepare the Answer
    $answer = new TypoherbariumROIAnswer();

    $answer
      ->setQuestionType($questionType)
      ->setQuestionId  ($questionId)
      ->setRoiId       ($context)
      ->setAnswerValue ($answerValue)
      ->setAskId       ($askId)
      ->setInternautIp ($ip)
      ->setSource      ($source)
      ->setReferrant   ($referrant);

    return $answer;
  }

}

?>
