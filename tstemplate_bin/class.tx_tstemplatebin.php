<?php
/**
 * TS
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
class tx_tstemplatebin {

    /**
     * @var array Field names as keys, file names as values
     */
    protected $_fields = array(
        'constants',
        'config'
    );

	/**
	 * Hook-function for tx_tstemplateinfo:
	 * Search for TS-includes in textareas for template module info/modify
	 * @see ext_localconf.php
	 *
	 * @param array $parameters
	 * @param tx_tstemplateinfo $pObj
	 */
	public function postOutputProcessingHook($parameters, $pObj) {
    	//$pattern = '/[\n\r\s]*(\<INCLUDE_TYPOSCRIPT\:\s+source\="\s*FILE\:\s*([^"]*?)\s*"\s*\>/i';
    	$pattern = '/^[ \t]*&lt;INCLUDE_TYPOSCRIPT\:\s+source\=&quot;\s*FILE\:\s*(.*)\s*&quot;\s*&gt;/mie';
    	$parameters['theOutput'] = preg_replace(
    		$pattern,
    		'"### &lt;INCLUDE_TYPOSCRIPT: source=&quot;FILE:\\1&quot;&gt;".
    		t3lib_div::formatForTextarea($this->_readFile("\\1")).
    		"\n### &lt;/INCLUDE_TYPOSCRIPT&gt;"'
    		,
    		$parameters['theOutput']
    	);
	}

	/**
	 * Hook-function for t3lib_TCEmain:
	 * Writes contents of fields to files and overrides the values for DB.
	 * @see ext_localconf.php
	 *
	 * @param string $status
	 * @param string $table
	 * @param integer $uid
	 * @param array $fields
	 * @param t3lib_TCEmain $tce
	 */
	public function processDatamap_postProcessFieldArray($status, $table, $uid, &$fields, t3lib_TCEmain $tce) {
	    if ($table != 'sys_template') {
	        return;
	    }
		$pattern = '/### \<INCLUDE_TYPOSCRIPT\:\s+source\="\s*FILE\:([^"]*)"\s*\>\s*\n(.*?)\R### \<\/INCLUDE_TYPOSCRIPT\>/s';
		foreach ($this->_fields as $field) {
			if (preg_match_all($pattern, $fields[$field], $matches, PREG_SET_ORDER)) {
				foreach ($matches as $match) {
					$path = t3lib_div::getFileAbsFileName($match[1]);
					if (!$path) {
					    continue;
					}
					if (!file_exists($path) && !is_dir(dirname($path))) {
				        t3lib_div::mkdir_deep(PATH_site, substr(dirname($path), strlen(PATH_site)));
				    }
				    if (t3lib_div::writeFile($path, $match[2])) {
				    	$fields[$field] = str_replace($match[0], '<INCLUDE_TYPOSCRIPT: source="FILE:'.$match[1].'">', $fields[$field]);
				    }
				}
			}
		}
	}

	/**
	 * Reads a template file
	 *
	 * @param string $file
	 * @return string
	 */
	protected function _readFile($file) {
	    $path = t3lib_div::getFileAbsFileName($file);
	    return is_readable($path) ? file_get_contents($path) : '';
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/tstemplate_bin/class.tx_tstemplatebin.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/tstemplate_bin/class.tx_tstemplatebin.php']);
}
?>