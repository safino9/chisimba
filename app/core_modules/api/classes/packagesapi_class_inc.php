<?php
/**
 * Packages interface class
 *
 * XML-RPC (Remote Procedure call) class
 *
 * PHP version 5
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the
 * Free Software Foundation, Inc.,
 * 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 * @category  Chisimba
 * @package   api
 * @author    Paul Scott <pscott@uwc.ac.za>
 * @copyright 2007 Paul Scott
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt The GNU General Public License
 * @version   $Id$
 * @link      http://avoir.uwc.ac.za
 * @see       core
 */
// security check - must be included in all scripts
if (!
/**
 * Description for $GLOBALS
 * @global entry point $GLOBALS['kewl_entry_point_run']
 * @name   $kewl_entry_point_run
 */
$GLOBALS['kewl_entry_point_run']) {
    die("You cannot view this page directly");
}
// end security check


/**
 * Packages XML-RPC Class
 *
 * Class to provide Chisimba Packages XML-RPC functionality
 *
 * @category  Chisimba
 * @package   api
 * @author    Paul Scott <pscott@uwc.ac.za>
 * @copyright 2007 Paul Scott
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt The GNU General Public License
 * @version   Release: @package_version@
 * @link      http://avoir.uwc.ac.za
 * @see       core
 */
class packagesapi extends object
{

    /**
     * init method
     *
     * Standard Chisimba init method
     *
     * @return void
     * @access public
     */
    public function init()
    {
        try {
            $this->objConfig = $this->getObject('altconfig', 'config');
            $this->objLanguage = $this->getObject('language', 'language');
            $this->objUser = $this->getObject('user', 'security');
            $this->objCatalogueConfig = $this->getObject('catalogueconfig','modulecatalogue');
            $this->objModules = $this->getObject('modules', 'modulecatalogue');
        }
        catch (customException $e)
        {
            customException::cleanUp();
            exit;
        }
    }



    /**
     * Method to grab a specified module as a zipfile
     *
     * @param string $module
     * @return string - base64 encoded string of the zipfile
     */
    public function getModuleZip($module)
    {
        //grab the module name
        $mod = $module->getParam(0);
        // lets check to see if this module has dependencies...
        $depends = $this->objCatalogueConfig->getModuleDeps($mod->scalarval());
        $depends = explode(',', $depends);
        foreach($depends as $dep)
        {
            // get 2nd level deps as well
            $dep2 = $this->objCatalogueConfig->getModuleDeps($dep);
            $dep2 = explode(',', $dep2);
            $depos[] = $dep2;

        }
        //log_debug($depos);
        foreach($depos as $d2)
        {
            $depends = array_merge($d2, $depends);
        }
        $depends = array_filter($depends);
        //log_debug($depends);
        // Recursively download the dependencies
        // generate a list of paths to zip up
        foreach($depends as $paths)
        {
            $paths = trim($paths);
            //log_debug("Grabbing path: $paths");
            $path = $this->objConfig->getModulePath().$paths.'/';
            if(file_exists($path))
            {
                //log_debug("Found $paths in regular modules");
                $d12[] = $this->objConfig->getModulePath().$paths.'/';
                // continue;
            }
            elseif(file_exists($this->objConfig->getsiteRootPath().'core_modules/'.$paths.'/'))
            {
                //log_debug("Found $paths in core modules");
                //$dep[] = $this->objConfig->getsiteRootPath().'core_modules/'.$paths.'/';
                $d12[] = FALSE;
                //continue;
            }
            else {
                //log_debug("No $paths Found!");
                $d12[] = FALSE;
                //continue;
            }
        }
        // add the actual module path in there too
        $path = $this->objConfig->getModulePath().$mod->scalarval().'/';
        $d12[] = $path;
        //log_debug($d12);
        foreach($d12 as $deps)
        {
            if(substr($deps, -2) == '//')
            {
                unset($deps);
            }
            //check for core_modules and unset that too
            if(!isset($deps)) {
                $deps = NULL;
            }

            if(preg_match("/core_modules/i", $deps))
            {
                unset($deps);
            }
            $depe[] = $deps;

        }
        if(!isset($depe)) {
            $depe = array();
        }
        if(is_array($depe)) {
            $depe = array_filter($depe);
        }
        $filepath = $this->objConfig->getModulePath().$mod->scalarval().'.zip';
        if(!file_exists($path))
        {
            // try the core modules....
            $path = $this->objConfig->getsiteRootPath().'core_modules/'.$mod->scalarval().'/';
            $filepath = $this->objConfig->getsiteRootPath().'core_modules/'.$mod->scalarval().'.zip';
            $zipfile = $this->makeZip($path, $filepath);
            $filetosend = file_get_contents($zipfile);
            $filetosend = base64_encode($filetosend);
            $val = new XML_RPC_Value($filetosend, 'string');
            //unlink($filepath);
            if($this->objModules->checkIfRegistered('remotepopularity'))
            {
                $objDbPop = $this->getObject('dbpopularity', 'remotepopularity');
                $recarr = array();
                $recarr['ip'] = $_SERVER['REMOTE_ADDR'];
                $recarr['module_name'] = $mod->scalarval();
                $objDbPop->addRecord($recarr);
            }
            else {
                log_debug("grabbing a core module -> ".$mod->scalarval());
                log_debug("Sent ".$mod->scalarval()." to client at ".$_SERVER['REMOTE_ADDR']);
            }
            return new XML_RPC_Response($val);
            // Ooops, couldn't open the file so return an error message.
            return new XML_RPC_Response(0, $XML_RPC_erruser+1, $this->objLanguage->languageText("mod_packages_fileerr", "packages"));
        }
        //zip up the module(s)
        $filetosend = $this->zipDependencies($depe, $mod->scalarval());
        $val = new XML_RPC_Value($filetosend, 'string');
        if($this->objModules->checkIfRegistered('remotepopularity'))
        {
            $objDbPop = $this->getObject('dbpopularity', 'remotepopularity');
            $recarr = array();
            $recarr['ip'] = $_SERVER['REMOTE_ADDR'];
            $recarr['module_name'] = $mod->scalarval();
            $objDbPop->addRecord($recarr);
        }
        else {
            log_debug("Sent ".$mod->scalarval()." to client at ".$_SERVER['REMOTE_ADDR']);
        }
        return new XML_RPC_Response($val);
        // Ooops, couldn't open the file so return an error message.
        return new XML_RPC_Response(0, $XML_RPC_erruser+1, $this->objLanguage->languageText("mod_packages_fileerr", "packages"));
    }

    public function zipDependencies($modulesarr, $mod)
    {
        log_debug("THE MODULE IS:   ".$mod);
        //log_debug($modulesarr);
        $filepath = $this->objConfig->getModulePath().$mod.'.zip';
        
        $zipfile = $this->makeSysZip($modulesarr, $filepath);
        //log_debug($zipfile);
        $filetosend = file_get_contents($zipfile);
        $filetosend = base64_encode($filetosend);
        return $filetosend;
    }

    /**
     * Method to grab a specified set of modules as a zip file
     *
     * @param array $module
     * @return string - base64 encoded string of the zipfile
     */
    public function getMultiModuleZip($module)
    {
        //grab the module array
        $mod = $module->getParam(0);
        $mod = $mod->scalarval();
        log_debug($mod);
        $path = $this->objConfig->getModulePath();
        //zip up the module
        //$objZip = $this->getObject('wzip', 'utilities');
        //$zipfile = $objZip->addArchive($path, $filepath, $this->objConfig->getModulePath());
        $zipfile = $this->makeZip($path, $filepath);
        $filetosend = file_get_contents($zipfile);
        $filetosend = base64_encode($filetosend);
        $val = new XML_RPC_Value($filetosend, 'string');
        unlink($filepath);
        if($this->objModules->checkIfRegistered('remotepopularity'))
        {
            $objDbPop = $this->getObject('dbpopularity', 'remotepopularity');
            $recarr = array();
            $recarr['ip'] = $_SERVER['REMOTE_ADDR'];
            $recarr['module_name'] = $mod->scalarval();
            $objDbPop->addRecord($recarr);
        }
        else {
            log_debug("Sent ".$mod->scalarval()." to client at ".$_SERVER['REMOTE_ADDR']);
        }
        return new XML_RPC_Response($val);
        // Ooops, couldn't open the file so return an error message.
        return new XML_RPC_Response(0, $XML_RPC_erruser+1, $this->objLanguage->languageText("mod_packages_fileerr", "packages"));
    }


    /**
     * Method to grab a specified module's description
     *
     * @param string $module
     * @return string
     */
    public function getModuleDescription($module)
    {
        //grab the module name
        $mod = $module->getParam(0);
        $name = $this->objCatalogueConfig->getModuleName($mod->scalarval());
        $desc = $this->objCatalogueConfig->getModuleDescription($mod->scalarval());
        $data[0] = new XML_RPC_Value((string)$name[0],'string');
        $data[1] = new XML_RPC_Value((string)$desc[0],'string');
        $val = new XML_RPC_Value($data, 'array');
        return new XML_RPC_Response($val);
        // Ooops, couldn't open the file so return an error message.
        return new XML_RPC_Response(0, $XML_RPC_erruser+1, $this->objLanguage->languageText("mod_packages_fileerr", "packages"));
    }

    /**
     * Method to delete a module zipfile from the server
     *
     * @param void
     * @return void
     */
    public function deleteModZip()
    {
        chdir($this->objConfig->getModulePath());
        foreach(glob('*.zip') as $files)
        {
            log_debug("cleaning up: ".$files);
            unlink($files);
        }
    }

    /**
     * Method to return an XML-RPC message
     *
     * @param string $message
     * @return XML-RPC response object
     */
    public function getMessage($message)
    {
        $message = $message->getParam(0);
        return new XML_RPC_Response($message);
    }

    /**
     * Method to return a list of available modules on the RPC server
     *
     * @param void
     * @return XML-RPC Response object (string)
     */
    public function getModuleList()
    {
        $dataDir = $this->objConfig->getModulePath();
        try {
              $dir  = new DirectoryIterator($dataDir);
            foreach ($dir as $file)
              {
                  if($file->isDir())
                  {
                    $fileName[] = new XML_RPC_Value($file->getFilename(), 'string');
                  }
              }
        }
        catch (customException $e)
        {
            customException::cleanUp();
        }
        $val = new XML_RPC_Value($fileName, 'array');
        return new XML_RPC_Response($val);

    }

    public function getModuleDetails() {
        $this->objCatalogueConfig->writeCatalogue();
        $mArray = $this->objCatalogueConfig->getModuleDetails();
        $data = array();
        foreach ($mArray as $mod) {
           $det[0] = new XML_RPC_Value($mod[0], 'string');
           $det[1] = new XML_RPC_Value($mod[1], 'string');
           $det[2] = new XML_RPC_Value($mod[2], 'string');
           $det[3] = new XML_RPC_Value($mod[3], 'string');
           $det[4] = new XML_RPC_Value($mod[4], 'string');
           $data[] = new XML_RPC_Value($det, 'array');
        }
        $val = new XML_RPC_Value($data,'array');
        return new XML_RPC_Response($val);
    }

    /**
     * Method to grab a specified skin from the RPC Server
     *
     * @param string $skinName
     */
    public function getSkin($skinName)
    {
        //grab the module array
        $skin = $skinName->getParam(0);
        $skin = $skin->scalarval();
        // log_debug($skin);
        // grok the skin path...
        $path = $this->objConfig->getskinRoot().$skin.'/';
        //log_debug($path." is being zipped up...");
        $filepath = $this->objConfig->getskinRoot().$skin.".zip";
        //log_debug("Zip is at $filepath");
        //zip up the skin
        //$objZip = $this->getObject('wzip', 'utilities');
        //$zipfile = $objZip->addArchive($path, $filepath, $this->objConfig->getSkinRoot());
        $zipfile = $this->makeZip($path, $filepath);
        $filetosend = file_get_contents($this->objConfig->getSiteRootPath()."/".$zipfile);
        $filetosend = base64_encode($filetosend);
        // log_debug($filetosend);
        $val = new XML_RPC_Value($filetosend, 'string');
        unlink($this->objConfig->getSiteRootPath()."/".$zipfile);
        log_debug("Sent Skin: ".$skin." to client at ".$_SERVER['REMOTE_ADDR']);
        return new XML_RPC_Response($val);
        // Ooops, couldn't open the file so return an error message.
        return new XML_RPC_Response(0, $XML_RPC_erruser+1, $this->objLanguage->languageText("mod_packages_fileerr", "packages"));
    }

    /**
     * Method to return a list of available skins for remote download
     *
     */
    public function getSkinList()
    {
        $path = $this->objConfig->getskinRoot();
        chdir($path);
        $sklist = NULL;
        foreach(glob('*') as $skins)
        {
            if($skins != 'icons2' || $skins != '_common2') {
                $sklist .= $skins."|";
            }
        }
        $val = new XML_RPC_Value($sklist, 'string');
        log_debug("Sent Skin List to client");
        return new XML_RPC_Response($val);
        // Ooops, couldn't open the file so return an error message.
        return new XML_RPC_Response(0, $XML_RPC_erruser+1, $this->objLanguage->languageText("mod_packages_fileerr", "packages"));
    }

    /**
     * Method to update the systemtypes.xml document
     *
     */
    public function updateSystemTypesFile()
    {
        $types = $this->objConfig->getsiteRootPath().'config/systemtypes.xml';
        $contents = file_get_contents($types);
        $filetosend = base64_encode($contents);
        $val = new XML_RPC_Value($filetosend, 'string');
        log_debug("Sent systemtypes.xml to client at ".$_SERVER['REMOTE_ADDR']);
        return new XML_RPC_Response($val);
    }

    /**
     * Method to get the remote engine version for core upgrade
     *
     */
    public function getEngineVersion()
    {
        $ver = $this->objCatalogueConfig->getEngineVer();
        $ver = $ver[0];
        log_debug("Remote/local engine is $ver");
        $val = new XML_RPC_Value($ver, 'string');
        return new XML_RPC_Response($val);
    }

    public function getEngUpgrade()
    {
        //$objZip = $this->getObject('wzip', 'utilities');
        $filepath = $this->objConfig->getsiteRootPath().'core.zip';
        $path = $this->objConfig->getsiteRootPath().'classes/';
        log_debug($path."  ".$filepath);
        //$zipfile = $objZip->addArchive($path, $filepath, $this->objConfig->getsiteRootPath());
        $zipfile = $this->makeZip($path, $filepath);
        $filetosend = file_get_contents($zipfile);
        $filetosend = base64_encode($filetosend);

        $val = new XML_RPC_Value($filetosend, 'string');
        unlink($filepath);
        log_debug("Sent core upgrade to client at ".$_SERVER['REMOTE_ADDR']);
        return new XML_RPC_Response($val);
        // Ooops, couldn't open the file so return an error message.
        return new XML_RPC_Response(0, $XML_RPC_erruser+1, $this->objLanguage->languageText("mod_packages_fileerr", "packages"));
    }

    private function makeZip($path, $filename) {
       /* if(!extension_loaded('zip')) {
            log_debug("Zip extension missing!");
            throw new customException("Zip extension required!");
        } */
        try {
            $zip = new ZipArchive();
            if ($zip->open($filename, ZIPARCHIVE::CREATE)!==TRUE) {
                log_debug("Unable to open zip file for creation");
                throw new customException("cannot open <$filename>");
            }
            // glob and get a list of files
            chdir($path);
            //log_debug("Changed path to $path");
            //log_debug($this->rglob('*', 0, ''));
            foreach($this->rglob('*', 0, '') as $files) {
                if(is_dir($files)) {
                    $zip->addEmptyDir($files);
                }
                else {
                    $zip->addFile($files);
                }
            }
            $zip->close();
        }
        catch (customException $e) {
            customException::cleanUp();
        }
        return $filename;
    }

    private function makeSysZip($paths, $filename) {
        try {
            $zip = new ZipArchive();
            if ($zip->open($filename, ZIPARCHIVE::CREATE)!==TRUE) {
                log_debug("Unable to open zip file for creation");
                throw new customException("cannot open <$filename>");
            }
            foreach($paths as $path) {
                // glob and get a list of files
                chdir($path);
                foreach($this->rglob('*', 0, '') as $files) {
                    if(is_dir($files)) {
                        $zip->addEmptyDir($files);
                    }
                    else {
                        $zip->addFile($files);
                    }
                }
            }
            $zip->close();
        }
        catch (customException $e) {
            customException::cleanUp();
        }
        return $filename;
    }


    public function rglob($pattern='*', $flags = 0, $path='') {
        $paths = glob($path.'*', GLOB_MARK|GLOB_ONLYDIR|GLOB_NOSORT);
        $files = glob($path.$pattern, $flags);
        foreach ($paths as $path) {
            $files = array_merge($files,$this->rglob($pattern, $flags, $path));
        }
        return $files;
    }
}
?>
