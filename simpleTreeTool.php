<?php
namespace iHerbarium;

require_once("myPhpLib.php");

interface TreeNodeI {
  public function getId();
  public function getHeader();
  public function getChildren();
  
  public function isOpen();
}

class TreeNode
implements TreeNodeI {

  protected $id       = NULL;     public function setId($id) { $this->id = $id; return $this; }
  protected $header   = NULL;     public function setHeader($header) { $this->header = $header; return $this; }
  protected $children = array();  public function addChild($child, $key = NULL) { if($key) $this->children[$key] = $child; else $this->children[] = $child; return $this; }
  protected $isOpen   = NULL;     public function setIsOpen($isOpen) { $this->isOpen = $isOpen; return $this; }

  
  public function getId()       { return $this->id; }
  public function getHeader()   { return $this->header; }
  public function getChildren() { return $this->children; }
  public function isOpen()      { return $this->isOpen; }

}

class TreeToHTML {
  
  public function headerId($id)  { return $id . "Header";  }
  public function contentId($id) { return $id . "Content"; }

  public function toHTML(TreeNodeI $t) {
    $treeToHTML = $this;
    
    $lines = array();
    
    $lines[] =
      "<div " . 
      "id='" . $this->headerId($t->getId()) . "' " .

      "onClick=\"treeToggle('" . 
      $this->headerId($t->getId()) . "', '" . 
      $this->contentId($t->getId()) . "')\" " .

      "style='font-weight: bold; cursor: pointer;' " .
      ">";
    $lines[] = $t->getHeader();
    $lines[] = "</div>";
    
    $display =
      ($t->isOpen() ? "" : "none");

    $lines[] = "<ul id='" . $this->contentId($t->getId()) . "' style='display: " . $display . ";'>";

    $children = array_mapi(
			   function($name, $child) use ($treeToHTML) {
			     if($child instanceof TreeNodeI)
			       return "<li>" . $treeToHTML->toHTML($child) . "</li>";
			     else
			       return "<li>" . $name . " : " . $child . "</li>";
			  },
			  $t->getChildren());

    $lines = array_merge($lines, $children);

    $lines[] = "</ul>";
    
    return implode("\n", $lines);

  }

}

class TreeTool {
  
  static $lastIdSuffix = 1;
  static $idPrefix = "TreeNode";

  static public function getNewId() {
    $id = static::$idPrefix . static::$lastIdSuffix;
    static::$lastIdSuffix = static::$lastIdSuffix + 1;
    return $id;
  }


  public function toTree($something) {

    if(is_array($something)) {
      
      $id = static::getNewId();

      // Extract header.
      if(isset($something["header"])) {
	$header = $something["header"];
      } else {
	$header = "LIST";
      }

      // Extract information is the node open.
      if(isset($something["open"])) {
	$isOpen = $something["open"];
      } else {
	$isOpen = false;
      }
      
      // Process recursively all elements of array.
      $parts = array_map(
			 // Call recursively toTree()
			 array($this, "toTree"),

			 // Filter out all options.
			 array_diff_key($something, 
					array("header" => NULL, 
					      "open" => NULL)));
      
      // Prepare the Tree Node.
      $treeNode = new TreeNode();
      $treeNode
	->setId($id)
	->setHeader($header)
	->setIsOpen($isOpen);

      foreach($parts as $name => $part) {
	$treeNode->addChild($part, $name);
      }

      return $treeNode;

    } else {

      // This is a value.
      return $something;

    }

  }

}


/*echo "
<script type=\"text/javascript\">
  function treeToggle(header, content) {
  var el = document.getElementById(content);
  el.style.display = (el.style.display != 'none' ? 'none' : '' );
}
</script>
";
*/
/*
$t2 = new TreeNode();
$t2->id = "Id2";
$t2->header = "TreeNode2";
$t2->children = array("a", "b", "c");

$t1 = new TreeNode();
$t1->id = "Id1";
$t1->header = "TreeNode1";
$t1->children = array($t2);

$tt = new TreeTool();
$tth = new TreeToHTML();

echo $tth->toHTML($t1);

$something1 = 
  array(
	"header" => "sth1", "aaa", "bbb", "ccc", array("header" => "nested1", "ddd", "eee", "fff")
	);

echo $tth->toHTML($tt->toTree($something1));
*/

function arrayToHTML($sth) {
  $tt = new TreeTool();
  $tth = new TreeToHTML();
  return $tth->toHTML($tt->toTree($sth));
}

?>
