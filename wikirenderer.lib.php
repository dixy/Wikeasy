<?php
/**
 * Wikirenderer is a wiki text parser. It can transform a wiki text into xhtml or other formats
 * @package WikiRenderer
 * @author Laurent Jouanneau
 * @copyright 2003-2008 Laurent Jouanneau
 * @link http://wikirenderer.berlios.de
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public 2.1
 * License as published by the Free Software Foundation.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 *
 */
define('WIKIRENDERER_PATH', dirname(__FILE__).'/');
define('WIKIRENDERER_VERSION', '3.1.1');

/**
 * base class to generate output from inline wiki tag
 *
 * this objects are driven by the wiki inline parser
 * @package WikiRenderer
 * @see WikiInlineParser
 */
abstract class WikiTag {

	public $beginTag='';
	public $endTag='';
	public $isTextLineTag=false;
	/**
	 * list of possible separators
	 */
	public $separators=array();

	protected $attribute = array('$$');
	protected $checkWikiWordIn = array('$$');
	protected $contents = array('');
	/**
	 * wiki content of each part of the tag
	 */
	protected $wikiContentArr = array('');
	/**
	 * wiki content of the full tag
	 */
	protected $wikiContent='';
	protected $separatorCount=0;
	protected $currentSeparator=false;
	protected $checkWikiWordFunction=false;
	protected $config = null;

	/**
	* @param WikiRendererConfig $config
	*/
	function __construct($config){
		$this->config = $config;
		$this->checkWikiWordFunction = $config->checkWikiWordFunction;
		if($config->checkWikiWordFunction === null) $this->checkWikiWordIn = array();
		if(count($this->separators)) $this->currentSeparator = $this->separators[0];
	}

	/**
	* called by the inline parser, when it found a new content
	* @param string $wikiContent	the original content in wiki syntax if $parsedContent is given, or a simple string if not
	* @param string $parsedContent the content already parsed (by an other wikitag object), when this wikitag contains other wikitags
	*/
	public function addContent($wikiContent, $parsedContent=false){
		if($parsedContent === false){
			$parsedContent = $this->_doEscape($wikiContent);
			if(count( $this->checkWikiWordIn)
				&& isset($this->attribute[$this->separatorCount])
				&& in_array($this->attribute[$this->separatorCount], $this->checkWikiWordIn)){
				$parsedContent = $this->_findWikiWord($parsedContent);
			}
		}
		$this->contents[$this->separatorCount] .= $parsedContent;
		$this->wikiContentArr[$this->separatorCount] .= $wikiContent;
	}

	/**
	* called by the inline parser, when it found a separator
	*/
	public function addSeparator($token){
		$this->wikiContent.= $this->wikiContentArr[$this->separatorCount];
		$this->separatorCount++;
		if($this->separatorCount > count($this->separators))
			$this->currentSeparator = end($this->separators);
		else
			$this->currentSeparator = $this->separators[$this->separatorCount-1];
		$this->wikiContent .= $this->currentSeparator;
		$this->contents[$this->separatorCount]='';
		$this->wikiContentArr[$this->separatorCount]='';
	}

	/**
	* says if the given token is the current separator of the tag.
	*
	* The tag can support many separator
	* @return string the separator
	*/
	public function isCurrentSeparator($token){
		return ($this->currentSeparator === $token);
	}

	/**
	* return the wiki content of the tag
	* @return string the content
	*/
	public function getWikiContent(){
		return $this->beginTag.$this->wikiContent.$this->wikiContentArr[$this->separatorCount].$this->endTag;
	}

	/**
	* return the generated content of the tag
	* @return string the content
	*/
	public function getContent(){ return $this->contents[0];}

	public function isOtherTagAllowed() {
		if (isset($this->attribute[$this->separatorCount]))
			return ($this->attribute[$this->separatorCount] == '$$');
		else
			return false;
	}

	/**
	* return the generated content of the tag
	* @return string the content
	*/
	public function getBogusContent(){
		$c=$this->beginTag;
		$m= count($this->contents)-1;
		$s= count($this->separators);
		foreach($this->contents as $k=>$v){
			$c.=$v;
			if($k< $m){
				if($k < $s)
					$c.=$this->separators[$k];
				else
					$c.=end($this->separators);
			}
		}

		return $c;
	}

	/**
	* escape a simple string.
	*/
	protected function _doEscape($string){
		return $string;
	}

	protected function _findWikiWord($string){
		if($this->checkWikiWordFunction !== null && preg_match_all("/(?:(?<=\b)|!)[A-Z]\p{Ll}+[A-Z0-9][\p{Ll}\p{Lu}0-9]*/u", $string, $matches)){
			$match = array_unique($matches[0]); // we must have a list without duplicated values, because of str_replace.
			if(is_array($this->checkWikiWordFunction)) {
				$o = $this->checkWikiWordFunction[0];
				$m = $this->checkWikiWordFunction[1];
				$result = $o->$m($match);
			} else {
				$fct=$this->checkWikiWordFunction;
				$result = $fct($match);
			}
			$string= str_replace($match, $result, $string);
		}
		return $string;
	}

}

class WikiTextLineContainer {
	public $tag = null;
	public $allowedTags = array();
	public $pattern = '';
}

/**
 * The parser used to find all inline tag in a single line of text
 * @package WikiRenderer
 * @abstract
 */
class WikiInlineParser {

	public $error=false;
	protected $simpletags=array();
	protected $resultline='';
	protected $str=array();
	protected $config;
	protected $textLineContainers=array();
	protected $currentTextLineContainer = null;

	/**
	* constructor
	* @param WikiRendererConfig $config	a config object
	*/
	function __construct($config ){
		$this->escapeChar = $config->escapeChar;
		$this->config = $config;

		$simpletagPattern = '';
		foreach($config->simpletags as $tag=>$html){
			$simpletagPattern.='|('.preg_quote($tag, '/').')';
		}
		
		$escapePattern = '';
		if($this->escapeChar != '')
			$escapePattern ='|('.preg_quote($this->escapeChar, '/').')';


		foreach($config->textLineContainers as $class=>$tags){
			$c = new WikiTextLineContainer();
			$c->tag = new $class($config);
			$separators = $c->tag->separators;
			
			$tagList = array();
			foreach($tags as $tag) {
				$t = new $tag($config);
				$c->allowedTags[$t->beginTag] = $t;
				$c->pattern .= '|('.preg_quote($t->beginTag, '/').')';
				if($t->beginTag!= $t->endTag)
					$c->pattern .= '|('.preg_quote($t->endTag, '/').')';
				$separators = array_merge($separators, $t->separators);
			}
			$separators= array_unique($separators);
			foreach($separators as $sep){
				$c->pattern .='|('.preg_quote($sep, '/').')';
			}
			$c->pattern .= $simpletagPattern. $escapePattern;
			$c->pattern = '/'.substr($c->pattern,1).'/';

			$this->textLineContainers[$class] = $c;
		}

		$this->simpletags = $config->simpletags;
	}

	/**
	* main function which parse a line of wiki content
	* @param	string	$line	a string containing wiki content, but without line feeds
	* @return	string	the line transformed to the target content 
	*/
	public function parse($line){
		$this->error=false;
		$this->currentTextLineContainer = $this->textLineContainers[$this->config->defaultTextLineContainer];
		$firsttag = clone ($this->currentTextLineContainer->tag);

		$this->str = preg_split($this->currentTextLineContainer->pattern, $line, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		$this->end = count($this->str);

		if($this->end > 1){
			$pos=-1;
			$this->_parse($firsttag, $pos);
			return $firsttag->getContent();
		}else{
			$firsttag->addContent($line);
			return $firsttag->getContent();
		}
	}

	/**
	* core of the parser
	* @return integer new position
	*/
	protected function _parse($tag, $posstart){
		$checkNextTag=true;

		// we analyse each part of the string, 
		for($i=$posstart+1; $i < $this->end; $i++){
			$t=&$this->str[$i];
	
			// is it the escape char ?
			if($this->escapeChar !='' && $t === $this->escapeChar){
				if($checkNextTag){
					$t=''; // yes -> let's ignore the tag
					$checkNextTag=false;
				}else{
					// if we are here, this is because the previous part was the escape char
					$tag->addContent($this->escapeChar);
					$checkNextTag=true;
				}
			// is this a separator ?
			}elseif($tag->isCurrentSeparator($t)){
				$tag->addSeparator($t);
			}elseif($checkNextTag){
				// is there a ended tag
				if($tag->endTag == $t && !$tag->isTextLineTag){
					return $i;

				}elseif(!$tag->isOtherTagAllowed()) {
					$tag->addContent($t);

				// is there a tag which begin something ?
				}elseif( isset($this->currentTextLineContainer->allowedTags[$t]) ){
					$newtag = clone $this->currentTextLineContainer->allowedTags[$t];
					$i=$this->_parse($newtag,$i);
					if($i !== false){
						$tag->addContent($newtag->getWikiContent(), $newtag->getContent());
					}else{
						$i=$this->end;
						$tag->addContent($newtag->getWikiContent(), $newtag->getBogusContent());
					}

				// is there a simple tag ?
				}elseif( isset($this->simpletags[$t])){
					$tag->addContent($t, $this->simpletags[$t]);
				}else{
					$tag->addContent($t);
				}
			}else{
				if(isset($this->currentTextLineContainer->allowedTags[$t]) || isset($this->simpletags[$t]) || $tag->endTag == $t)
					$tag->addContent($t);
				else
					$tag->addContent($this->escapeChar.$t);
				$checkNextTag=true;
			}
	}
	if(!$tag->isTextLineTag ){
		//we didn't find the ended tag, error
		$this->error=true;
		return false;
	}else
		return $this->end;
	}
}

/**
 * base class to parse bloc elements
 * @abstract
 */
abstract class WikiRendererBloc {
	/**
	 * @var string	type of the bloc
	 */
	public $type='';
	/**
	 * @var string	the string inserted at the beginning of the bloc
	 */
	protected $_openTag='';
	/**
	 * @var string	the string inserted at the end of the bloc
	 */
	protected $_closeTag='';
	/**
	* @var boolean	says if the bloc is only on one line
	 * @access private
	 */
	protected $_closeNow=false;
	/**
	 * @var WikiRenderer		reference to the main parser
	 */
	protected $engine=null;
	protected $config=null;
	/**
	 * @var	array		list of elements found by the regular expression
	 */
	protected $_detectMatch=null;
	/**
	 * @var string		regular expression which can detect the bloc
	 */
	protected $regexp='';
	/**
	 * @param WikiRenderer	$wr	l'objet moteur wiki
	 */
	function __construct($wr,$cfg){
		$this->engine = $wr;
		$this->config = $cfg;
	}

	/**
	 * @return string	the string to insert at the beginning of the bloc
	 */
	public function open(){
		return $this->_openTag;
	}

	/**
	 * @return string the string to insert at the end of the bloc
	 */
	public function close(){
		return $this->_closeTag;
	}

	/**
	 * @return boolean says if the bloc can exists only on one line
	 */
	public function closeNow(){
		return $this->_closeNow;
	}

	/**
	 * says if the given line belongs to the bloc
	 * @param string	$string
	 * @return boolean
	 */
	public function detect($string){
		return preg_match($this->regexp, $string, $this->_detectMatch);
	}

	/**
	 * @return string a rendered line	of bloc
	 * @abstract
	 */
	public function getRenderedLine(){
		return $this->_renderInlineTag($this->_detectMatch[1]);
	}

	/**
	 * @param	string	$string a line of wiki
	 * @return	string	the transformed line
	 * @see WikiRendererInline
	 */
	protected function _renderInlineTag($string){
		return $this->engine->inlineParser->parse($string);
	}
}

/**
 * base class for the configuration
 */
abstract class WikiRendererConfig {
	public $defaultTextLineContainer = 'WikiTextLine';
	public $textLineContainers = array('WikiTextLine'=>array(),);
	
	/**
	 * liste des balises de type bloc reconnus par WikiRenderer.
	 */
	public $bloctags = array();
	public $simpletags = array();
	public $checkWikiWordFunction = null;
	public $escapeChar = '\\';

	/**
	 * Called before the wiki text parsing
	 * @param string $text	the wiki text
	 * @return string the wiki text to parse
	 */
	public function onStart($texte){return $texte;}

	/**
	 * called after the parsing. You can add additionnal data to
	 * the result of the parsing
	 */
	public function onParse($finalTexte){return $finalTexte;}
}

/**
 * Main class of WikiRenderenr. You should instantiate like this:
 *		$ctr = new WikiRenderer();
 *		$monTexteXHTML = $ctr->render($montexte);
 */
class WikiRenderer {
	/**
	 * @var	string	contains the final content
	 */
	protected $_newtext;
	
	/**
	 * @var WikiRendererBloc the current opened bloc element
	 */
	protected $_currentBloc=null;
	
	/**
	 * @var array		list of all possible blocs
	 */
	protected $_blocList= array();
	
	/**
	 * @var WikiInlineParser	the parser for inline content
	 */
	public $inlineParser=null;
	
	/**
	 * list of lines which contain an error
	 */
	public $errors=array();
	protected $config=null;
	
	/**
	 * prepare the engine
	 * @param WikiRendererConfig $config	a config object. if it is not present, it uses wr3_to_xhtml rules.
	 */
	function __construct( $config=null){
		$this->config= new wr3_to_xhtml();
		$this->inlineParser = new WikiInlineParser($this->config);
		foreach($this->config->bloctags as $name){
			$this->_blocList[]= new $name($this,$this->config);
		}
	}

	/**
	 * Main method to call to convert a wiki text into an other format, according to the
	 * rules given to the constructor.
	 * @param	string	$text the wiki text to convert
	 * @return	string	the converted text.
	 */
	public function render($text){
		$text = $this->config->onStart($text);
		
		$lignes=preg_split("/\015\012|\015|\012/",$text); // we split the text at all line feeds
		
		$this->_newtext=array();
		$this->errors=array();
		$this->_currentBloc = null;
		
		// we loop over all lines
		foreach($lignes as $num=>$ligne){
			if($this->_currentBloc){
				// a bloc is already open
				if($this->_currentBloc->detect($ligne)){
					$s =$this->_currentBloc->getRenderedLine();
					if($s !== false)
						$this->_newtext[]=$s;
				}else{
					$this->_newtext[count($this->_newtext)-1].=$this->_currentBloc->close();
					$found=false;
					foreach($this->_blocList as $bloc){
						if($bloc->type != $this->_currentBloc->type && $bloc->detect($ligne)){
							$found=true;
							// we open the new bloc

							if($bloc->closeNow()){
								// if we have to close now the bloc, we close.
								$this->_newtext[]=$bloc->open().$bloc->getRenderedLine().$bloc->close();
								$this->_currentBloc = null;
							}else{
								$this->_currentBloc = clone $bloc; // careful ! it MUST be a copy here !
								$this->_newtext[]=$this->_currentBloc->open().$this->_currentBloc->getRenderedLine();
							}
							break;
						}
					}
					if(!$found){
						$this->_newtext[]= $this->inlineParser->parse($ligne);
						$this->_currentBloc = null;
					}
				}
			}else{
				$found=false;
				// no opened bloc, we saw if the line correspond to a bloc
				foreach($this->_blocList as $bloc){
					if($bloc->detect($ligne)){
						$found=true;
						if($bloc->closeNow()){
							$this->_newtext[]=$bloc->open().$bloc->getRenderedLine().$bloc->close();
						}else{
							$this->_currentBloc = clone $bloc; // careful ! it MUST be a copy here !
							$this->_newtext[]=$this->_currentBloc->open().$this->_currentBloc->getRenderedLine();
						}
						break;
					}
				}
				if(!$found){
					$this->_newtext[]= $this->inlineParser->parse($ligne);
				}
			}
			if($this->inlineParser->error){
				$this->errors[$num+1]=$ligne;
			}
		}
		if($this->_currentBloc){
		$this->_newtext[count($this->_newtext)-1].=$this->_currentBloc->close();
		}
		
		return $this->config->onParse(implode("\n",$this->_newtext));
	}

	/**
	 * return the version of WikiRenderer
	 * @access public
	 * @return string	version
	 */
	public function getVersion(){return WIKIRENDERER_VERSION;}
	public function getConfig(){return $this->config;}
}

class WikiTextLine extends WikiTag {public $isTextLineTag=true;}
class WikiHtmlTextLine extends WikiTag {
	public $isTextLineTag=true;
	protected function _doEscape($string){return htmlspecialchars($string);}
}

/**
 * a base class for wiki inline tag, to generate XHTML element.
 * @package WikiRenderer
 */
abstract class WikiTagXhtml extends WikiTag {
	protected $name;
	protected $additionnalAttributes=array();
	
	/**
	 * sometimes, an attribute could not correspond to something in the target format
	 * so we could indicate it.
	 */
	protected $ignoreAttribute = array();
	
	public function getContent(){
		$attr='';
		$cntattr=count($this->attribute);
		$count=($this->separatorCount >= $cntattr?$cntattr-1:$this->separatorCount);
		$content='';
		
		for($i=0;$i<=$count;$i++){
			if(in_array($this->attribute[$i] , $this->ignoreAttribute))
				continue;
			if($this->attribute[$i] != '$$')
				$attr.=' '.$this->attribute[$i].'="'.htmlspecialchars($this->wikiContentArr[$i]).'"';
			else
				$content = $this->contents[$i];
		}
		
		foreach($this->additionnalAttributes as $name=>$value) {
			$attr.=' '.$name.'="'.htmlspecialchars($value).'"';
		}
		
		return '<'.$this->name.$attr.'>'.$content.'</'.$this->name.'>';
	}
	
	protected function _doEscape($string){return htmlspecialchars($string);}
}

class wr3_to_xhtml extends WikiRendererConfig {
	public $defaultTextLineContainer = 'WikiHtmlTextLine';
	public $textLineContainers = array('WikiHtmlTextLine'=> array('wr3xhtml_strong','wr3xhtml_em','wr3xhtml_code','wr3xhtml_q',
				'wr3xhtml_cite','wr3xhtml_acronym','wr3xhtml_link', 'wr3xhtml_image', 'wr3xhtml_ins',
				'wr3xhtml_anchor', 'wr3xhtml_footnote'));

	/**
	 * liste des balises de type bloc reconnus par WikiRenderer.
	 */
	public $bloctags = array('wr3xhtml_notoc', 'wr3xhtml_title', 'wr3xhtml_list', 'wr3xhtml_pre','wr3xhtml_hr',
							'wr3xhtml_blockquote','wr3xhtml_definition','wr3xhtml_table', 'wr3xhtml_p');

	public $simpletags = array('%%%'=>'<br />', '=>' => '→', '<=' => '←');
	
	public $toc = array();
	public $notoc = false;
	public $tocTemplate = '<div class="toc"><div class="toctitle">Sommaire</div>%s</div>';

	// la syntaxe wr3 contient la possibilité de mettre des notes de bas de page
	// celles-ci seront stockées ici, avant leur incorporation é la fin du texte.
	public $footnotes = array();
	public $footnotesId='';
	public $footnotesTemplate = '<div class="footnotes"><h4>Notes</h4>%s</div>';

	/**
	 * methode invoquée avant le parsing
	 */
	public function onStart($texte){
		$this->footnotesId = rand(0,30000);
		$this->footnotes = array(); // on remet à zero les footnotes
		return $texte;
	}

	/**
	 * methode invoquée aprés le parsing
	 */
	public function onParse($finalTexte){
		// on rajoute les notes de bas de pages.
		if(count($this->footnotes)){
			$footnotes = implode("\n",$this->footnotes);
			$finalTexte .= str_replace('%s', $footnotes, $this->footnotesTemplate);
		}
		if(count($this->toc) > 2 && !$this->notoc){
			$res = '';
			$ref_level = $this->toc[0]['level']-1;
			$level = $ref_level;
			foreach ($this->toc as $t)
			{
				if ($t['level']-$level > 1) $level += ($t['level']-$level)-1;
				//if ($level != $t['level'] && $t['level']-$level < 1) $level += ($t['level']-$level)+1;
				if ($t['level'] > $level) $res .= str_repeat('<ol><li>', $t['level'] - $level);
				elseif ($t['level'] < $level) $res .= str_repeat('</li></ol>', -($t['level'] - $level));
				if ($t['level'] <= $level) $res .= '</li><li>';
				$res .= '<a href="#'.art_title2url(clean_title($t['title'])).'">'.$t['title'].'</a>';
				$level = $t['level'];
			}
			
			if ($ref_level - $level < 0)
				$res .= str_repeat('</li></ol>', -($ref_level - $level));
			$finalTexte = str_replace('%s', $res, $this->tocTemplate).$finalTexte;
		}
		return $finalTexte;
	}
}

// ===================================== déclarations des tags inlines

class wr3xhtml_strong extends WikiTagXhtml {
	protected $name='strong';
	public $beginTag='__';
	public $endTag='__';
}

class wr3xhtml_em extends WikiTagXhtml {
	protected $name='em';
	public $beginTag='\'\'';
	public $endTag='\'\'';
}

class wr3xhtml_ins extends WikiTagXhtml {
	protected $name='ins';
	public $beginTag='++';
	public $endTag='++';
}

class wr3xhtml_code extends WikiTagXhtml {
	protected $name='code';
	public $beginTag='@@';
	public $endTag='@@';
}

class wr3xhtml_q extends WikiTagXhtml {
	protected $name='q';
	public $beginTag='^^';
	public $endTag='^^';
	protected $attribute=array('$$','lang','cite');
	public $separators=array('|');
}

class wr3xhtml_cite extends WikiTagXhtml {
	protected $name='cite';
	public $beginTag='{{';
	public $endTag='}}';
	protected $attribute=array('$$','title');
	public $separators=array('|');
}

class wr3xhtml_acronym extends WikiTagXhtml {
	protected $name='acronym';
	public $beginTag='??';
	public $endTag='??';
	protected $attribute=array('$$','title');
	public $separators=array('|');
}

class wr3xhtml_anchor extends WikiTagXhtml {
	protected $name='anchor';
	public $beginTag='~~';
	public $endTag='~~';
	protected $attribute=array('name');
	public $separators=array('|');
	public function getContent(){
		return '<a name="'.htmlspecialchars($this->wikiContentArr[0]).'"></a>';
	}
}

class wr3xhtml_link extends WikiTagXhtml {
	protected $name='a';
	public $beginTag='[[';
	public $endTag=']]';
	protected $attribute=array('$$','href','hreflang','title');
	public $separators=array('|');
	public function getContent(){
		$cntattr=count($this->attribute);
		$cnt=($this->separatorCount + 1 > $cntattr?$cntattr:$this->separatorCount+1);
		if($cnt == 1 ){
			$contents = $this->wikiContentArr[0];
			$href=$contents;
			if(strpos($href,'javascript:')!==false) // for security reason
				$href='#';
			if(strlen($contents) > 40)
				$contents=substr($contents,0,40).'(..)';
			return '<a href="'.htmlspecialchars($href).'">'.htmlspecialchars($contents).'</a>';
		}else{
			if(strpos($this->wikiContentArr[1],'javascript:')!==false) // for security reason
				$this->wikiContentArr[1]='#';
			return parent::getContent();
		}
	}
}

class wr3xhtml_image extends WikiTagXhtml {
	protected $name='image';
	public $beginTag='((';
	public $endTag='))';
	protected $attribute=array('src','alt','align','longdesc');
	public $separators=array('|');

	public function getContent(){
		$contents = $this->wikiContentArr;
		$cnt=count($contents);
		$attribut='';
		if($cnt > 4) $cnt=4;
		switch($cnt){
			case 4:
				$attribut.=' longdesc="'.$contents[3].'"';
			case 3:
				if($contents[2]=='l' ||$contents[2]=='L' || $contents[2]=='g' || $contents[2]=='G')
					$attribut.=' style="float:left;"';
				elseif($contents[2]=='r' ||$contents[2]=='R' || $contents[2]=='d' ||$contents[2]=='D')
					$attribut.=' style="float:right;"';
			case 2:
				$attribut.=' alt="'.$contents[1].'"';
			case 1:
			default:
				$attribut.=' src="'.$contents[0].'"';
				if($cnt == 1) $attribut.=' alt=""';
		}
		return '<img'.$attribut.'/>';
	}
}

class wr3xhtml_footnote extends WikiTagXhtml {
	protected $name='footnote';
	public $beginTag='$$';
	public $endTag='$$';

	public function getContent(){
		$number = count($this->config->footnotes) + 1;
		$id = 'footnote-'.$this->config->footnotesId.'-'.$number;
		$this->config->footnotes[] = "<p>[<a href=\"#rev-$id\" name=\"$id\" id=\"$id\">$number</a>] ".$this->contents[0].'</p>';

		return "<span class=\"footnote-ref\">[<a href=\"#$id\" name=\"rev-$id\" id=\"rev-$id\">$number</a>]</span>";
	}
}

// ===================================== déclaration des différents bloc wiki

class wr3xhtml_notoc extends WikiRendererBloc {
	public $type='notoc';
	protected $regexp='/__NOTOC__/';
	protected $_closeNow=true;
	
	public function getRenderedLine(){$this->config->notoc=true; return '';}
}
/**
 * traite les signes de types liste
 */
class wr3xhtml_list extends WikiRendererBloc {
	public $type='list';
	protected $_previousTag;
	protected $_firstItem;
	protected $_firstTagLen;
	protected $regexp="/^\s*([\*#-]+)(.*)/";

	public function open(){
		$this->_previousTag = $this->_detectMatch[1];
		$this->_firstTagLen = strlen($this->_previousTag);
		$this->_firstItem=true;

		if(substr($this->_previousTag,-1,1) == '#')
			return "<ol>\n";
		else
			return "<ul>\n";
	}
	
	public function close(){
		$t=$this->_previousTag;
		$str='';
	
		for($i=strlen($t); $i >= $this->_firstTagLen; $i--){
			$str.=($t[$i-1]== '#'?"</li></ol>\n":"</li></ul>\n");
		}
		return $str;
	}

	public function getRenderedLine(){
		$t=$this->_previousTag;
		$d=strlen($t) - strlen($this->_detectMatch[1]);
		$str='';

		if($d > 0){ // on remonte d'un ou plusieurs cran dans la hierarchie...
			$l=strlen($this->_detectMatch[1]);
			for($i=strlen($t); $i>$l; $i--){
				$str.=($t[$i-1]== '#'?"</li></ol>\n":"</li></ul>\n");
			}
			$str.="</li>\n<li>";
			$this->_previousTag=substr($this->_previousTag,0,-$d); // pour étre sur...
		}elseif( $d < 0 ){ // un niveau de plus
			$c=substr($this->_detectMatch[1],-1,1);
			$this->_previousTag.=$c;
			$str=($c == '#'?"<ol><li>":"<ul><li>");
		}else{
			$str=($this->_firstItem ? '<li>':"</li>\n<li>");
		}
		$this->_firstItem=false;
		return $str.$this->_renderInlineTag($this->_detectMatch[2]);
	}
}

/**
 * traite les signes de types table
 */
class wr3xhtml_table extends WikiRendererBloc {
	public $type='table';
	protected $regexp="/^\s*\| ?(.*)/";
	protected $_openTag='<table border="1">';
	protected $_closeTag='</table>';
	protected $_colcount=0;
	
	public function open(){
		$this->_colcount=0;
		return $this->_openTag;
	}
	
	public function getRenderedLine(){
		$result=explode(' | ',trim($this->_detectMatch[1]));
		$str='';
		$t='';
	
		if((count($result) != $this->_colcount) && ($this->_colcount!=0))
			$t='</table><table border="1">';
		$this->_colcount=count($result);
	
		for($i=0; $i < $this->_colcount; $i++){
			$str.='<td>'. $this->_renderInlineTag($result[$i]).'</td>';
		}
		$str=$t.'<tr>'.$str.'</tr>';
	
		return $str;
	}
}

/**
 * traite les signes de types hr
 */
class wr3xhtml_hr extends WikiRendererBloc {
	public $type='hr';
	protected $regexp='/^\s*={4,} *$/';
	protected $_closeNow=true;

	public function getRenderedLine(){
		return '<hr />';
	}
}

/**
 * traite les signes de types titre
 */
class wr3xhtml_title extends WikiRendererBloc {
	public $type='title';
	protected $regexp="/^\s*(\!{1,3})(.*)/";
	protected $_closeNow=true;

	protected $_minlevel=2;
	/**
	 * indique le sens dans lequel il faut interpreter le nombre de signe de titre
	 * true -> ! = titre , !! = sous titre, !!! = sous-sous-titre
	 * false-> !!! = titre , !! = sous titre, ! = sous-sous-titre
	 */
	protected $_order=true;

	public function getRenderedLine(){
		if($this->_order)
			$hx= $this->_minlevel + strlen($this->_detectMatch[1])-1;
		else
			$hx= $this->_minlevel + 3-strlen($this->_detectMatch[1]);
		$title = $this->_renderInlineTag($this->_detectMatch[2]);
		$this->config->toc[] = array('title' => $title, 'level' => $hx);
		return '<h'.$hx.' id="'.art_title2url(clean_title($title)).'">'.$title.'</h'.$hx.'>';
	}
}

/**
 * traite les signes de type paragraphe
 */
class wr3xhtml_p extends WikiRendererBloc {
	public $type='p';
	protected $_openTag='<p>';
	protected $_closeTag='</p>';

	public function detect($string){
		if($string=='') return false;
		if(preg_match("/^\s*[\*#\-\!\| \t>;<=].*/",$string)) return false;
		$this->_detectMatch=array($string,$string);
		return true;
	}
}

/**
 * traite les signes de types pre (pour afficher du code..)
 */
class wr3xhtml_pre extends WikiRendererBloc {
	public $type='pre';
	protected $_openTag='<pre>';
	protected $_closeTag='</pre>';
	protected $isOpen = false;

	public function open(){
		$this->isOpen = true;
		return $this->_openTag;
	}

	public function close(){
		$this->isOpen=false;
		return $this->_closeTag;
	}

	public function getRenderedLine(){
		return htmlspecialchars($this->_detectMatch);
	}

	public function detect($string){
		if($this->isOpen){
			if(preg_match('/(.*)<\/code>\s*$/',$string,$m)){
				$this->_detectMatch=$m[1];
				$this->isOpen=false;
			}else{
				$this->_detectMatch=$string;
			}
			return true;
		}else{
			if(preg_match('/^\s*<code>(.*)/',$string,$m)){
				if(preg_match('/(.*)<\/code>\s*$/',$m[1],$m2)){
					$this->_closeNow = true;
					$this->_detectMatch=$m2[1];
				}
				else {
					$this->_closeNow = false;
					$this->_detectMatch=$m[1];
				}
				return true;
			}else{
				return false;
			}
		}
	}
}

/**
 * traite les signes de type blockquote
 */
class wr3xhtml_blockquote extends WikiRendererBloc {
	public $type='bq';
	protected $regexp="/^\s*(\>+)(.*)/";
	
	public function open(){
		$this->_previousTag = $this->_detectMatch[1];
		$this->_firstTagLen = strlen($this->_previousTag);
		$this->_firstLine = true;
		return str_repeat('<blockquote>',$this->_firstTagLen).'<p>';
	}
	
	public function close(){
		return '</p>'.str_repeat('</blockquote>',strlen($this->_previousTag));
	}
	
	public function getRenderedLine(){
		$d=strlen($this->_previousTag) - strlen($this->_detectMatch[1]);
		$str='';
		
		if($d > 0){ // on remonte d'un cran dans la hierarchie...
			$str='</p>'.str_repeat('</blockquote>',$d).'<p>';
			$this->_previousTag=$this->_detectMatch[1];
		}elseif( $d < 0 ){ // un niveau de plus
			$this->_previousTag=$this->_detectMatch[1];
			$str='</p>'.str_repeat('<blockquote>',-$d).'<p>';
		}else{
			if($this->_firstLine)
				$this->_firstLine=false;
			else
				$str='<br />';
		}
		return $str.$this->_renderInlineTag($this->_detectMatch[2]);
	}
}

/**
 * traite les signes de type définitions
 */
class wr3xhtml_definition extends WikiRendererBloc {
	public $type='dfn';
	protected $regexp="/^\s*;(.*) : (.*)/i";
	protected $_openTag='<dl>';
	protected $_closeTag='</dl>';
	
	public function getRenderedLine(){
		$dt=$this->_renderInlineTag($this->_detectMatch[1]);
		$dd=$this->_renderInlineTag($this->_detectMatch[2]);
		return "<dt>$dt</dt>\n<dd>$dd</dd>\n";
	}
}
?>