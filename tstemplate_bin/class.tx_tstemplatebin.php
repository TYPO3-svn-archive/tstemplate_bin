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
    }
    
	/**
	 * Hook-function:
	 * called in typo3/sysext/tstemplate_info/class.tx_tstemplateinfo.php
	 *
	 * @param array $parameters
	 * @param tx_tstemplateinfo $pObj
	 */
	public function postOutputProcessingHook($parameters, $pObj)
	{		
	    $this->_id = $parameters['tplRow']['uid'];
	    $dirname = $this->_getDirname($parameters['tplRow']['title']);
	    
	    $startLen = strlen($parameters['theOutput']);
        foreach ($this->_fields as $field => $file)
	    {
	        if ($weCan = array_key_exists($field, $parameters['e']))
	        {
	            $templateFile = $dirname.$file.'.txt';
	            $parameters['theOutput'] = $this->_includeBinFile(
	                $parameters['theOutput'],
	                $templateFile,
	                true
	            );
	        }
	    }
	    if (strlen($parameters['theOutput']) == $startLen && $weCan && $this->_addComment)
	    {
	        // There is no file yet, add content if enabled in extConf:
	        $parameters['theOutput'] = preg_replace(
	            '/\<textarea([^\>]*)\>/',
	            '<textarea$1>'.t3lib_div::formatForTextarea(
	                $this->_getFileComment(array('templateFile'=>$templateFile))
	            ),
	            $parameters['theOutput']
	        );
	    }
	}
	
	public function getMainFields_preProcess($table, &$row, t3lib_TCEforms $tce)
	{
	    if ($table == 'sys_template')
	    {
	        $this->_id = $row['uid'];
	        $dirname = $this->_getDirname($row['title']);
	        
	        foreach ($this->_fields as $field => $file)
	        {
	            $row[$field] = $this->_includeBinFile($row[$field], $dirname.$file.'.txt');
	        }
	    }
	}
	
	protected function _includeBinFile($code, $file, $hsc = false)
	{
	    $pattern = '/\<INCLUDE_TYPOSCRIPT\:\s+source\="\s*FILE\:\s*'.addcslashes($file,'/\\').'\s*"\s*\>/i';
	    if ($hsc)
	    {
	        $pattern = str_replace(array('\<','\>','"'), array('\n&lt;','&gt;','&quot;'), $pattern);
	        //$pattern = '/&lt;INCLUDE_TYPOSCRIPT:\s+source=&quot;\s*FILE:\s*(.*?)\s*&quot;\s*&gt;/i';
	    }
	    if (preg_match($pattern, $code, $match, PREG_OFFSET_CAPTURE))
		{
		    $file = t3lib_div::getFileAbsFileName($file);
		        
	        $content = file_exists($file) ? t3lib_div::formatForTextarea(file_get_contents($file)) : '';
		        
		    return substr_replace($code, $content, $match[0][1], strlen($match[0][0]));
		}
		return $code;
	}
	
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
    		        t3lib_div::rmdir(PATH_site.$this->_getDirname($current['title']), true);
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
	        if (strpos($_SERVER['HTTP_REFERER'], t3lib_extMgm::extRelPath('tstemplate')) !== false)
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
	
	protected function _checkTitle($title, $notify = false)
	{
	    if ($this->_prefixWithId)
	    {
	        return true;
	    }
	    $dir = $this->_getDirname($title);
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
	
	protected function _processFields($title, $fields)
	{
	    $dirname = $this->_getDirname($title, true);
		    
	    $newFields = array();
	    
	    foreach ($this->_fields as $field => $file)
	    {
	        if (array_key_exists($field, $fields))
	        {
	            $filename = $dirname.$file.'.txt';
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
	 * @param unknown_type $row
	 */
	protected function _getDirname($title, $create = false)
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