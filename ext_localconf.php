<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

// Define hooks:
$class = 'EXT:tstemplate_bin/class.tx_tstemplatebin.php:&tx_tstemplatebin';
$TYPO3_CONF_VARS['SC_OPTIONS']['ext/tstemplate_info/class.tx_tstemplateinfo.php']['postOutputProcessingHook'][] = $class.'->postOutputProcessingHook';
$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = $class;
$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tceforms.php']['getMainFieldsClass'][] = $class;

$TYPO3_CONF_VARS['EXTCONF']['tstemplate_bin']['fileComment'] = 
'/**
 * Template ${title} (${uid}) in ${rootLine} (${pid})
 * Included from ${templateFile}
 * 
 * @version $Id$
 */

';
?>