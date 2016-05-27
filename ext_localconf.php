<?php
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
	'MIA3.' . $_EXTKEY,
	'Search',
	array(
		'Search' => 'index',
		
	),
	// non-cacheable actions
	array(
		'Search' => 'index',
		
	)
);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = 'MIA3\Mia3Search\Command\IndexCommandController';

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['parameterProviders'] = array(
	'\MIA3\Mia3Search\ParameterProviders\NewsParameterProvider',
	'\MIA3\Mia3Search\ParameterProviders\LanguageParameterProvider'
);

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['pageContentFilters'] = array(
	function($pageContent) {
		return preg_replace("/<script\\b[^>]*>(.*?)<\\/script>/is", "", $pageContent);
	},
	function($pageContent) {
		return str_replace("\n", '', strip_tags($pageContent));
	}
);