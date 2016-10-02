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


$GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['mia3_search_server_identification'] = 'EXT:mia3_search/Classes/Eid/ServerIdentificationEid.php';

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = 'MIA3\Mia3Search\Command\IndexCommandController';

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['parameterProviders'] = array(
	'\MIA3\Mia3Search\ParameterProviders\LanguageParameterProvider'
);

if (class_exists('\GeorgRinger\News\Controller\NewsController')) {
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['parameterProviders'][] = '\MIA3\Mia3Search\ParameterProviders\NewsParameterProvider';
}
if (class_exists('\Mia3\Mia3Location\Domain\Model\Location')) {
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['parameterProviders'][] = '\MIA3\Mia3Search\ParameterProviders\Mia3LocationParameterProvider';
}

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['pageContentFilters'] = array(
	'scriptTags' => function($pageContent) {
		return preg_replace("/<script\\b[^>]*>(.*?)<\\/script>/is", "", $pageContent);
	},
	'lineBreaks' => function($pageContent) {
		return str_replace("\n", '', strip_tags($pageContent));
	}
);