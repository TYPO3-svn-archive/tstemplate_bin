<?php
/**
 * Zfext - Zend Framework for TYPO3
 * 
 * LICENSE
 *
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 *
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This copyright notice MUST APPEAR in all copies of the script!
 * 
 * @copyright  Copyright (c) 2010 Christian Opitz - Netzelf GbR (http://netzelf.de)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @version    $Id$
 */

/**
 * @category   TYPO3
 * @package    TYPO3
 * @subpackage tx_tstemplatebin
 * @author     Christian Opitz <co@netzelf.de>
 */
class tx_tstemplatebin
{
    const TS_SAMPLE = '
# Default PAGE object:
page = PAGE
page.10 = TEXT
page.10.value = HELLO WORLD!
';
    
    /**
     * @var string The path where the template dirs will be created
     */
    protected $_path = 'fileadmin/template/ts/';
    
    /**
     * @var boolean If template dirs should get prefixed with the template-uid
     */
    protected $_prefixWithId = false;
    
    /**
     * @var boolean If comments should be added on first file creation
     */
    protected $_addComment = true;
    
    /**
     * @var integer The current template-uid
     */
    protected $_id;
    
    /**
     * @var array The current record
     */
    protected $_current;
    
    /**
     * @var string File extension for template files
     */
    protected $_fileExt = '.txt';
    
    /**
     * @var array Field names as keys, file names as values
     */
    protected $_fields = array(
        'constants' => 'constants',
        'config' => 'setup'
    );
    
    /**
     * Set the vars from extConf
     */
    public function __construct()
    {
        $conf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['tstemplate_bin']);
        if (!is_array($conf))
        {
            return;
        }
        if (!empty($conf['path']))
        {
            $this->_path = $conf['path'];
        }
        if (isset($conf['prefixWithId']))
        {
            $this->_prefixWithId = $conf['prefixWithId'] ? true : false;
        }
        if (isset($conf['addComment']))
        {
            $this->_addComment = $conf['addComment'] ? true : false;
        }
        if (!empty($conf['fileExt']))
        {
            $this->_fileExt = '.'.ltrim($conf['fileExt'],'.');
        }
    }
    
	/**
	 * Hook-function for tx_tstemplateinfo:
	 * Search for TS-includes in textareas for template module info/modify
	 * Adds file comment if enabled in extConf and no include was found
	 * @see _includeBinFile()
	 * @see ext_localconf.php
	 * 
	 * @param array $parameters
	 * @param tx_tstemplateinfo $pObj
	 */
	public function postOutputProcessingHook($parameters, $pObj)
	{		
	    $this->_id = $parameters['tplRow']['uid'];
	    $dirname = $this->_getDir($parameters['tplRow']['title']);
	    
	    $start = $parameters['theOutput'];
        foreach ($this->_fields as $field => $file)
	    {
	        if (array_key_exists($field, $parameters['e']))
	        {
	            $weCan = true;
	            $templateFile = $dirname.$file.$this->_fileExt;
	            $parameters['theOutput'] = $this->_includeBinFile(
	                $parameters['theOutput'],
	                $templateFile,
	                true
	            );
	        }
	    }
	    
	    if ($weCan && $this->_addComment && strcmp($parameters['theOutput'], $start) == 0)
	    {
	        // There is no file yet, add comment if enabled in extConf:
	        $parameters['theOutput'] = preg_replace(
	            '/\<textarea([^\>]*)\>/',
	            '<textarea$1>'.t3lib_div::formatForTextarea(
	                $this->_getFileComment(array('templateFile' => $templateFile))
	            ),
	            $parameters['theOutput']
	        );
	    }
	}
	
	/**
	 * Hook-function for t3lib_TCEforms:
	 * Override the TS-fields with content from bin files if any
	 * @see _includeBinFile()
	 * @see ext_localconf.php
	 * 
	 * @param string $table
	 * @param array $row
	 * @param t3lib_TCEforms $tce
	 */
	public function getMainFields_preProcess($table, &$row, t3lib_TCEforms $tce)
	{
	    if ($table == 'sys_template')
	    {
	        $this->_id = $row['uid'];
	        $dirname = $this->_getDir($row['title']);
	        
	        foreach ($this->_fields as $field => $file)
	        {
	            $row[$field] = $this->_includeBinFile($row[$field], $dirname.$file.$this->_fileExt);
	        }
	    }
	}
	
	/**
	 * Search for TS-includes of the $file - when found replaces this
	 * with the contents of the TS-File (just once).
	 * 
	 * @param string $code The code to search in
	 * @param string $file The file to match
	 * @param boolean $hsc Use htmlspecialchars for pattern
	 * @return string The code with replace include if any
	 */
	protected function _includeBinFile($code, $file, $hsc = false)
	{
	    $pattern = '/\<INCLUDE_TYPOSCRIPT\:\s+source\="\s*FILE\:\s*'.addcslashes($file,'/\\').'\s*"\s*\>/i';
	    if ($hsc)
	    {
	        $pattern = str_replace(array('\<','\>','"'), array('[\n\r]*&lt;','&gt;','&quot;'), $pattern);
	        //$pattern = '/&lt;INCLUDE_TYPOSCRIPT:\s+source=&quot;\s*FILE:\s*(.*?)\s*&quot;\s*&gt;/i';
	    }
	    if (preg_match($pattern, $code, $match, PREG_OFFSET_CAPTURE))
		{
		    $content = t3lib_div::formatForTextarea($this->_readFile($file));
		        
		    return substr_replace($code, $content, $match[0][1], strlen($match[0][0]));
		}
		return $code;
	}
	
	/**
	 * Reads a template file and adds comment if enabled in extConf and it's 
	 * empty, does not exist or is sample only.
	 * 
	 * @param string $file
	 * @return string
	 */
	protected function _readFile($file)
	{
	    $path = t3lib_div::getFileAbsFileName($file);
	    $addComment = $this->_addComment && $this->_isTemplateModule();
	    $c = '';
	    
	    if (file_exists($path))
	    {
	        $c = file_get_contents($path);
	        if ($addComment)
	        {
	            $str1 = preg_replace('/\r/','',$c);
	            $str2 = preg_replace('/\r/','',self::TS_SAMPLE);
	            
	            if (strcmp($str1, $str2) == 0)
	            {
	                $c = 
	                $this->_getFileComment(array('templateFile' => $file)).
	                ltrim(self::TS_SAMPLE);
	            }
	        }
	    }
	    if ($addComment && !strlen(trim($c)))
	    {
	        $c = $this->_getFileComment(array('templateFile' => $file));
	    }
	    return $c;
	}
	
	/**
	 * Hook-function for t3lib_TCEmain:
	 * Writes contents of fields to files and overrides the values for DB.
	 * Checks if title is valid, if not rewrites it and shows a flash message
	 * or looks for the next valid name when in template module
	 * @see ext_localconf.php
	 * 
	 * @param string $status
	 * @param string $table
	 * @param integer $uid
	 * @param array $fields
	 * @param t3lib_TCEmain $tce
	 */
	public function processDatamap_postProcessFieldArray($status, $table, $uid, &$fields, t3lib_TCEmain $tce)
	{
	    if ($table != 'sys_template')
	    {
	        return;
	    }
	    if ($status == 'update')
	    {	        
	        $this->_id = (integer) $uid;
	        
	        $current = $this->_getCurrent();
	        
		    if (isset($fields['title']) && strlen($current['title']) && strcasecmp($current['title'], $fields['title']) !== 0)
		    {
		        if ($this->_checkTitle($fields['title'], true))
		        {
    		        t3lib_div::rmdir(PATH_site.$this->_getDir($current['title']), true);
    		        $current['title'] = $fields['title'];
		        }
		        else
		        {
		            unset($fields['title']);
		        }
		    }
		    $fields = array_merge($fields, $this->_processFields($current['title'], $fields));
	    }
	    if ($status == 'new' && !$this->_checkTitle($fields['title']))
	    {
	        if ($this->_isTemplateModule())
	        {
    	        // Called from Web->Template->Info/Modify
    	        // When adding templates there, title is automatically set by the
    	        // module - so we look up for the next possible name
	            $i = 1;
    	        while(!$this->_checkTitle($fields['title'].'-'.$i))
    	        {
    	            $i++;
    	        }
    	        $fields['title'] = $fields['title'].'-'.$i;
	        }
	        else
	        {
	            // User has provided an unvalid title
	            $this->_checkTitle($fields['title'], true);
	            unset($fields['title']);
	        }
	    }
	}
	
	/**
	 * If we are in module web->template
	 * 
	 * @return boolean
	 */
	protected function _isTemplateModule()
	{
	    return strpos($_SERVER['REQUEST_URI'], t3lib_extMgm::extRelPath('tstemplate')) !== false;
	}
	
	/**
	 * Hook-function for t3lib_TCEmain:
	 * Writes contents of fields in new rows to files and overrides the values for DB.
	 * @see ext_localconf.php
	 * 
	 * @param string $status
	 * @param string $table
	 * @param integer $uid
	 * @param array $fields
	 * @param t3lib_TCEmain $tce
	 */
	public function processDatamap_afterDatabaseOperations($status, $table, $uid, &$fields, t3lib_TCEmain $tce)
	{
	    if ($table == 'sys_template' && $status == 'new' && isset($fields['title']))
	    {
	        $this->_id = (integer) $tce->substNEWwithIDs[$uid];
	        
    	    $newFields = $this->_processFields($fields['title'], $fields);
	        if (count($newFields))
	        {
	            $GLOBALS['TYPO3_DB']->exec_UPDATEquery($table, 'uid='.$this->_id, $newFields);
	            $fields = array_merge($fields, $newFields);
	        }
	    }
	}
	
	/**
	 * Get the current template row
	 * 
	 * @return array
	 */
	protected function _getCurrent()
	{
	    if (!$this->_id)
	    {
	        return array();
	    }
	    if (!is_array($this->_current))
	    {
    	    $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'sys_template', 'uid='.$this->_id);
    		$this->_current = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
	    }
		return $this->_current;
	}
	
	/**
	 * Checks if there is already a directory for $title
	 * 
	 * @param string $title The template title
	 * @param boolean $notify Show a message if it exists
	 * @return boolean
	 */
	protected function _checkTitle($title, $notify = false)
	{
	    if ($this->_prefixWithId)
	    {
	        return true;
	    }
	    $dir = $this->_getDir($title);
	    if (!is_dir(PATH_site.$dir))
	    {
	        return true;
	    }
        if ($notify)
        {
            $flashMessage = t3lib_div::makeInstance(
					't3lib_FlashMessage',
					'Template directory "'.$dir.' already in use - choose another template title.',
					'',
					t3lib_FlashMessage::ERROR,
					TRUE
			);
		    t3lib_FlashMessageQueue::addMessage($flashMessage);
        }
        return false;
	}
	
	/**
	 * Writes field contents to files if they are not empty or the file
	 * exists. Puts the include-code in field when succesfull.
	 * 
	 * @param string $title Template title
	 * @param array $fields Fields to save
	 * @return array Saved fields
	 */
	protected function _processFields($title, $fields)
	{
	    $dirname = $this->_getDir($title, true);
		    
	    $newFields = array();
	    
	    foreach ($this->_fields as $field => $file)
	    {
	        if (array_key_exists($field, $fields))
	        {
	            $filename = $dirname.$file.$this->_fileExt;
	            $strlen = strlen(trim($fields[$field]));
		        if ($strlen || file_exists(PATH_site.$filename))
		        {
		            $ok = t3lib_div::writeFile(PATH_site.$filename, $fields[$field]);
		        }
		        if ($strlen && $ok)
		        {
		            //Only include when not empty (for performance reasons)
		            $newFields[$field] = "<INCLUDE_TYPOSCRIPT: source=\"FILE:{$filename}\">";
		        }
	        }
	    }
	    
	    return $newFields;
	}
	
	/**
	 * Parses the file comment from and returns it if enabled in extConf
	 * Use placeholders in comment template if you like:
	 * ${db_field} - Field from current sys_template-row
	 * ${additionalParam} - Additional params, these are:
	 * ${rootLine} - Path to the parent page of the template (breadcrumb)
	 * ${templateFile} - The path to the file where template is saved
	 * @see ext_localconf.php
	 * 
	 * @param array $addParams Additional params (key-value-pairs)
	 * @return string
	 */
	protected function _getFileComment($addParams = array())
	{
	    if (!$this->_addComment)
	    {
	        return '';
	    }
	    $row = $this->_getCurrent();
	    $row['rootLine'] = '';
	    
	    $rootline = array_reverse(t3lib_BEfunc::BEgetRootLine($row['pid']));
	    array_shift($rootline);
	    foreach ($rootline as $page)
	    {
	        $row['rootLine'] .= '/'.$page['title'];
	    }
	    $row = array_merge($row, $addParams);
	    
	    $cTemplate = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tstemplate_bin']['fileComment'];
	    if (preg_match_all('/\$\{([A-Za-z_]*)\}/', $cTemplate, $matches, PREG_SET_ORDER))
	    {
    	    foreach ($matches as $match)
    	    {
    	    	$cTemplate = str_replace($match[0], $row[$match[1]], $cTemplate);
    	    }
	    }
	    return $cTemplate;
	}
	
	/**
	 * Returns path to directory relative to PATH_site based on
	 * $title and $this->_id (if set in extConf)
	 * 
	 * @param string $title Template title
	 * @param bool $create If true creates the dir
	 * @return string
	 */
	protected function _getDir($title, $create = false)
	{
	    /* @var $basicFF t3lib_basicFileFunctions */
	    $basicFF = t3lib_div::makeInstance('t3lib_basicFileFunctions');
	    
	    $dirname = preg_replace('/\s+/', '_', $title);
	    $dirname = t3lib_div::underscoredToLowerCamelCase($dirname);
	    $dirname = trim($basicFF->cleanFileName($dirname).'/', '_');
	    
	    if ($this->_prefixWithId)
	    {
	        $dirname = $this->_id.'-'.$dirname;
	    }
	    
	    $relPath = trim($this->_path, '\\/').'/'.$dirname;
	    
	    
	    $idPrefix = $this->_id.'-';

	    if ($create === true && !is_dir(PATH_site.$relPath))
	    {
	        t3lib_div::mkdir_deep(PATH_site, $relPath);
	    }
	    
	    return $relPath;
	}
}
?>