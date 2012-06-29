<?php
namespace iHerbarium;
require_once("myPhpLib.php");

interface ReceivedMailCondition {
  public function match(ReceivedMail $mail); // returns boolean
}

class SimplePatternCondition
implements ReceivedMailCondition {
  
  protected $pattern = NULL;

  protected $mailFieldExtractor = NULL;
  protected $matchMechanism = NULL;

  protected function matchValue($value) {
    return $this->matchMechanism->matchPatternWithValue($this->pattern, $value);
  }
  
  protected function extractValue(ReceivedMail $mail) {
    return $this->mailFieldExtractor->extractField($mail);
  }

  function __construct($pattern, 
		       MatchMechanism $matchMechanism, 
		       MailFieldExtractor $mailFieldExtractor) {
    assert( (! is_null($pattern)) );
    assert( (! is_null($matchMechanism)) );
    assert( (! is_null($mailFieldExtractor)) );
    
    $this->pattern = $pattern;
    $this->matchMechanism = $matchMechanism;
    $this->mailFieldExtractor = $mailFieldExtractor;
  }

  public function match(ReceivedMail $mail) {
    $value = $this->extractValue($mail);
    
    if( $this->matchValue($value) )
      return True;
    else
      return False;
  }
  
}

/* MailFieldExtractors */

abstract class MailFieldExtractor {

  static public function get($which) {
    switch($which) {
    case 'Subject'   : return new SubjectExtractor();
    case 'ImageCount': return new ImageCountExtractor();
    }
  }

  abstract public function extractField(ReceivedMail $mail);
}

class SubjectExtractor
extends MailFieldExtractor {
  public function extractField(ReceivedMail $mail) {
    return $mail->subject();
  }
}

class ImageCountExtractor
extends MailFieldExtractor {
  public function extractField(ReceivedMail $mail) {
    $images =& $mail->images();
    return count($images);
  }
}


/* MatchMechanisms */

abstract class MatchMechanism {

  static public function get($which) {
    switch($which) {
    case 'Equal':          return new EqualMatchMechanism();
    case 'FirstWordEqual': return new FirstWordEqualMatchMechanism();
    case 'Regex':          return new RegexMatchMechanism();
    case 'GreaterThan':    return new GreaterThanMatchMechanism();
    }
  }

  abstract public function matchPatternWithValue($pattern, $value);
}

class EqualMatchMechanism
extends MatchMechanism {
  public function matchPatternWithValue($pattern, $value) {
    return ( strtolower($pattern) == strtolower($value) );
  }
}


class FirstWordEqualMatchMechanism
extends MatchMechanism {
  public function matchPatternWithValue($pattern, $value) {
     $words = explode(" ", ltrim($value));
     return ( strtolower($pattern) == strtolower($subjectWords[0]) );
  }
}

class RegexMatchMechanism
extends MatchMechanism {
  public function matchPatternWithValue($pattern, $value) {
    return ( preg_match($pattern, $value) > 0 ) ;
  }
}

class GreaterThanMatchMechanism
extends MatchMechanism {
  public function matchPatternWithValue($pattern, $value) {
    return ( $value > $pattern );
  }
}

?>