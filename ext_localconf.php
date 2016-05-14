<?php
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
	'MIA3.' . $_EXTKEY,
	'Search',
	array(
		'SearchController' => 'index',
		
	),
	// non-cacheable actions
	array(
		'SearchController' => 'index',
		
	)
);
