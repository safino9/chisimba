<?php
/* -------------------- LANGUAGE CLASS ----------------*/
/*Description of file
  *This is a Language class 
  *@author Prince Mbekwa, Paul Scott
  *@copyright (c) 200-2004 University of the Western Cape
  *@Version 0.1
  *@author  Prince Mbekwa
*/

/**
*Description of the class
* Language class for Chisimba. Provides language translation methods,
* the main one being to call the PEAR language translation object for a particular
* language items which are then converted to any translated language .
*
*/
class language extends dbTable {
	/**
	 * New form object
	 *
	 * @var $objNewForm
	 */
    private $objNewForm = null;
    /**
     * Dropdown object
     *
     * @var objDropdown
     */
    private $objDropdown = null;
    /**
     * Buttons object
     *
     * @var objButtons
     */
    private $objButtons = null;
    /**
     * Config object
     *
     * @var objConfig
     */
    public $objConfig =  null;
    /**
     * Language Translation2 object
     *
     * @var lang object
     */
    public $lang = null;
    /**
     * The global error callback for altconfig errors
     *
     * @access public
     * @var string
    */
    public $_errorCallback;
    /**
    * abstractList array of text items and their abstracts
    * @access public
    * @var object
    */

    public $abstractList;

    /**
    * Constructor method for the language class
    */
    public function init()
    {
    	try {
	    	parent::init('tbl_languagelist');
	        $this->objConfig = $this->getObject('altconfig','config');
	        $this->lang = $this->newObject('languageConfig','language');
	        $this->lang = &$this->lang->setup();
	        $this->loadClass('form', 'htmlelements');
	        $this->loadClass('dropdown', 'htmlelements');
	        $this->loadClass('button', 'htmlelements');
	          
	        $this->objAbstract =& $this -> getObject('systext_facet', 'systext');
	        $this->abstractList = $this->objAbstract->getSession('systext');
    	}catch (Exception $e){
    		$this->errorCallback ($this->languageText('word_caught_exception').$e->getMessage());
    		 exit();
    	}
    }

    /**
    * Method to return the language text when passed the language
    * text code. It looks up the language text for the current
    * language in the database.
    * @access public
    * @param string $itemName : The language code for the item to be
    * looked up
    * @param string $modulename : The module name that owns the string
    */

    public function languageText($itemName,$modulename='system',$default = false)

    {
    	try {
		    	//$abstractList = $this -> objAbstract -> getSession('systext');
		         $notFound = TRUE;
		        $arrName = explode("_", $itemName);

		        if(isset($arrName[2])){

		            if($arrName[1] == "context"){

		                $check = array_key_exists($arrName[2], $this->abstractList);

		                if($check){

		                    $notFound = FALSE;

		                    return trim($this->abstractList[$arrName[2]]);

		                }

		            }

		        }

		        if($notFound){
				$var = $this->currentLanguage();
				$var = strtolower($var);
                $line = $this->lang->get($itemName, $modulename, "{$var}");

 		        if (strcmp($line,$itemName)) {
                	$found = true;

		        } else {
		        	$found = false;
		        }
                if (($line!=null)&&($line!=$itemName)) {
                    return stripslashes($line);
                } else {
                    if ($default != false) {
                        return $default;
                    } else if ($itemName == 'error_languageitemmissing') { // test to prevent recursive loop
                            return "This language item is missing";
                    } else {
                    	// fetch a string not translated into Italian (test fallback language)
						return $this->lang->get('error_languageitemmissing','system',"{$var}").": $itemName from $modulename";
                        //return ($this->lang->get('error_languageitemmissing') . ":" . $itemName);
                    }
                }

        	}
    	}catch (Exception $e){

    		$this->errorCallback ($this->languageText('word_caught_exception').$e->getMessage());
    		 exit();
    	}
    }

    /**
    * Method to return a language element with a [-STRING-] tag replaced
    * with some text. This allows you to create a translatable element
    * with pseudotags, as we had in KEWL.
    *
    * @example TEXT: mod_mymodule_greet|Time and user sensitive greeting
    * |Hello [-FULLNAME-], Good [-GREETINGTIME-], I hope you are fine this great [-DAYOFTHEWEEK-].
    *
    * This allows this to appear in text as Hello Derek Keats,
    * Good evening, I hope you are fine this great Friday.
    *
    * Call it as follows:
    *    $str="mod_mymodule_greet";
    *    $arrOfRep= array(
    *       'FULLNAME'=> $userFullname,
    *       'GREETINGTIME'=> $time,
    *       'DAYOFTHEWEEK'=> $DOW );
    *    $formTitle=$this->objLanguage->code2Txt($str, $arrOfRep );
    *
    * Note that it is not case sensitive, and you can add as many elements as you like.
    *
    * @param string $str the language text code.
    * @param string modulename
    * @param array $arrOfRep An associative array of [-TAG-], replacement pairs
    * @return string $ret The array parsed
    * @access public
    * @author Jonathan Abrahams, Derek Keats
    */
    public function code2Txt($str,$modulename = "system",$arrOfRep=NULL)
    {
    	try {
	        $ret=$this->languageText($str,"{$modulename}");
	        //$abstractList = $this->objAbstract->getSession('systext');
			foreach($this->abstractList as $textItem => $abstractText){
	            $ret = preg_replace($this -> _match($textItem), $abstractText, $ret);
	        }
	        // Process other tags
	        if( $arrOfRep!=NULL ) {
	            foreach( $arrOfRep as $tag=>$rep ) {
	                $ret = preg_replace($this->_match($tag), $rep, $ret);
	            }
	        }
	        return $ret;
    	}catch (Exception $e){
    		$this->errorCallback ($this->languageText('word_caught_exception') .$e->getMessage());
    		 exit();
    	}
    }

    /**
     * Method to replace tagged strings with abstracted text. Similar to code2Txt only this
     * method works on strings (from the database) rather than language elements.
     *
     * @param string $str the string to search for abstraction tags
     * @return the abstracted string
     */
    public function abstractText($str) {
    	$ret = $str;
    	//$abstractList = $this->objAbstract->getSession('systext');
    	foreach($this->abstractList as $textItem => $abstractText){
	    	$ret = preg_replace($this -> _match($textItem), $abstractText, $ret);
	    }
	    return $ret;
    }
    
    /**
    * Method to return Language list
    * @access public
    * @return array
    */
    public function languagelist()
    {
    	try {
       	 $sql = "Select languageName from tbl_languagelist";
       	 return $this->getArray($sql);
    	}catch (Exception $e){
    		$this->errorCallback ($this->languageText('word_caught_exception').$e->getMessage());
    		 exit();
    	}
    }

    /**
    * Method to render Language Chooser on the
    * index page.
    * Construct a form and populate it with all available
    * language translations for selection
    * @access public
    * @return form
    */
    public function putlanguageChooser()
    {
    	try {
        //$ret = $this->languageText("phrase_languagelist",'language') . ":<br />\n";
        $script = $_SERVER['PHP_SELF'];
        $objNewForm = new form('languageCheck', $script);
        $objDropdown = new dropdown('Languages');
        $objDropdown->extra = "onchange =\"document.forms['languageCheck'].submit();\"";
        $results = $this->languagelist();
        foreach ($results as $list) {
            foreach($list as $key) {
                $objDropdown->addOption($key, $key);
            }
        }
        $objNewForm->addToForm($this->languageText("phrase_languagelist") . ":<br />\n");
        $objNewForm->addToForm($objDropdown->show());
        $ret = $objNewForm->show();
        return $ret;
    	}catch (Exception $e){
    		$this->errorCallback ($this->languageText('word_caught_exception').$e->getMessage());
    		 exit();
    	}
    }

    /**
    * Method to return the default language
    * @access public
    * @return default site language
    */
    public function currentLanguage()
    {
    	try {
        if (isset($_POST['Languages'])) {
            $_SESSION["language"] = $_POST['Languages'];
            $var = $_POST['Languages'];
            $this->lang->setLang("{$var}");
        } else {
            if (isset($_SESSION["language"])) {
                $var = $_SESSION["language"];
                $this->lang->setLang("{$var}");
            } else {
                $this->objConfig = &$this->getObject('altconfig','config');
                $var = $this->objConfig->getdefaultLanguageAbbrev();
                $this->lang->setLang("{$var}");
            }
        }
        return $var;
    	}catch (Exception $e){
    		$this->errorCallback ($this->languageText('word_caught_exception').$e->getMessage());
    		 exit();
    	}
    }

    /**
    * Method to create code2Txt match expression.
    * @access private
    * @param String The tag
    * @return String The regular expression with tag
    */
    private function _match( $tag )
    {
        return "/\[\-".$tag."\-\]/isU";
    }

    /**
     * Check for system properties
     *
     * @param string $code
     * @param string $itemName
     * @return TRUE/FALSE
     */

    public function valueExists($code,$item)
    {
    	 $line = $this->lang->get($item, '', 'en');
                if ($line != null) {
                    return TRUE;
                } else {
                	return FALSE;
                }


    }
    /**
    * The error callback function, defers to configured error handler
    *
    * @param string $error
    * @return void
    * @access public
    */
    public function errorCallback($exception)
    {
    	echo customException::cleanUp($exception);
    }

} #end of class

?>