<?PHP
/* -------------------- LANGUAGE CONFIG CLASS ----------------*/


require_once 'Translation2.php';
define('TABLE_PREFIX', 'tbl_');

/**
 * Language Config class for chisimba. Provides language setup properties,
 * the main one being to call the PEAR Translation2 object and setup
 * all language table layouts.
 * Setup all locales
 * Allow MDB2 to take over language Item maintainance
 *
 * @copyright (c) 2006 University of the Western Cape AVOIR
 * @Version 0.1
 * @author  Prince Mbekwa <pmbekwa@uwc.ac.za>
 *
 */
class languageConfig extends object
{
	/**
     * Public variable to hold the new language config object
     * @access public
     * @var string
     */
    public $lang;

    /**
     * Public variable to hold the site config object
     * @access private
     * @var string
     */
    private $_siteConf;

    /**
     * The global error callback for altconfig errors
     *
     * @access public
     * @var string
     */
    public $_errorCallback;

	/**
     * Constructor for the languageConf class.
     *
     */

	public function init(){}

	/**
     * Setup for the languageConf class.
     * tell Translation2 about our db-tables structure,
     * setup primary language
     * setup the group of module strings we want to fetch from
     * add a Lang decorator to provide a fallback language
     * add another Lang decorator to provide another fallback language,
	 * in case some strings are not translated in all languages that exist in KINKY
	 *
	 * @param void
	 * @return void
	 * @access public
     */
	public function setup()
	{
		try {
			//Define table properties so that MDB2 knows about them
			$params = array(
			'langs_avail_table' => TABLE_PREFIX.'langs_avail',
			'lang_id_col'     => 'id',
			'lang_name_col'   => 'name',
			'lang_meta_col'   => 'meta',
			'lang_errmsg_col' => 'error_text',
			'strings_tables'  => array(
								'en' => TABLE_PREFIX.'en',

									),
			'string_id_col'      => 'id',
			'string_page_id_col' => 'pageID',
			'string_text_col'    => '%s'  //'%s' will be replaced by the lang code
			);
			$driver = 'MDB2';

			//instantiate class
			$this->_siteConf = $this->getObject('altconfig','config');
			$dsn = $this->_parseDSN($this->_siteConf->getDsn());

			$this->lang =& Translation2::factory($driver, $dsn, $params);
			if (PEAR::isError($this->lang)) {
				throw new Exception('Could not load Translation class');
			}
			// set primary language
			if(!is_object($this->lang)) throw new Exception('Translation class not loaded');
			$this->lang->setLang("en");

			// set the group of strings you want to fetch from
			$this->lang->setPageID('defaultGroup');
			$this->caller = $this->moduleName;
			// add a Lang decorator to provide a fallback language
			$this->lang =& $this->lang->getDecorator('Lang');
			$this->lang->setOption('fallbackLang', 'en');
			// add a default text decorator to deal with empty strings
			$this->lang = & $this->lang->getDecorator('DefaultText');
			// replace the empty string with its stringID

			// use a custom fallback text
			return $this->lang;
		}catch (Exception $e){
			$this->errorCallback ('Caught exception: '.$e->getMessage());
    		exit();

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
    	$this->_errorCallback = new ErrorException($exception,1,1,'languageConfig_class_inc.php');
        return $this->_errorCallback;
    }

    /**
     * Method to parse the DSN from a string style DSN to an array for portability reasons
     *
     * @access private
     * @param string $dsn
     * @return void
     * @TODO get the port settings too!
     */
    private function _parseDSN($dsn)
    {
    	$parsed = NULL;
    	$arr = NULL;
    	if (is_array($dsn)) {
    		$dsn = array_merge($parsed, $dsn);
    		return $dsn;
    	}
    	//find the protocol
    	if (($pos = strpos($dsn, '://')) !== false) {
    		$str = substr($dsn, 0, $pos);
    		$dsn = substr($dsn, $pos + 3);
    	} else {
    		$str = $dsn;
    		$dsn = null;
    	}
    	if (preg_match('|^(.+?)\((.*?)\)$|', $str, $arr)) {
    		$parsed['phptype']  = $arr[1];
    		$parsed['phptype'] = !$arr[2] ? $arr[1] : $arr[2];
    	} else {
    		$parsed['phptype']  = $str;
    		$parsed['phptype'] = $str;
    	}

    	if (!count($dsn)) {
    		return $parsed;
    	}
    	// Get (if found): username and password
    	if (($at = strrpos($dsn,'@')) !== false) {
    		$str = substr($dsn, 0, $at);
    		$dsn = substr($dsn, $at + 1);
    		if (($pos = strpos($str, ':')) !== false) {
    			$parsed['username'] = rawurldecode(substr($str, 0, $pos));
    			$parsed['password'] = rawurldecode(substr($str, $pos + 1));
    		} else {
    			$parsed['username'] = rawurldecode($str);
    		}
    	}
    	//server
    	if (($col = strrpos($dsn,':')) !== false) {
    		$strcol = substr($dsn, 0, $col);
    		$dsn = substr($dsn, $col + 1);
    		if (($pos = strpos($strcol, '+')) !== false) {
    			$parsed['hostspec'] = rawurldecode(substr($strcol, 0, $pos));
    		} else {
    			$parsed['hostspec'] = rawurldecode($strcol);
    		}
    	}

    	//now we are left with the port and databsource so we can just explode the string and clobber the arrays together
    	$pm = explode("/",$dsn);
    	$parsed['hostspec'] = $pm[0];
    	$parsed['database'] = $pm[1];
    	$dsn = NULL;

    	$parsed['hostspec'] = str_replace("+","/",$parsed['hostspec']);

    	return $parsed;
    }
}
?>