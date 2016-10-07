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
//if (class_exists('\Mia3\Mia3Location\Domain\Model\Location')) {
//    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['parameterProviders'][] = '\MIA3\Mia3Search\ParameterProviders\Mia3LocationParameterProvider';
//}

if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['indexingBlacklist'])) {
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['indexingBlacklist'] = array();
}
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['indexingBlacklist'][] = '.mia3-search-unindexed';

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['pageContentFilters'] = array(
    'cssFilter' => function($pageContent) {
        try {
            $content = new \Wa72\HtmlPageDom\HtmlPageCrawler($pageContent);
            $content->filter(implode(', ', $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['indexingBlacklist']))->remove();
            return $content->html();
        } catch(\Exception $e) {}
        return $pageContent;
    },
    'ensureWhitespaceBetweenTags' => function($pageContent) {
        return preg_replace("/><([A-Za-z\/])/is", "> <$1", $pageContent);
    },
    'scriptTags' => function($pageContent) {
        return preg_replace("/<script\\b[^>]*>(.*?)<\\/script>/is", "", $pageContent);
    },
    'styletTags' => function($pageContent) {
        return preg_replace("/<style\\b[^>]*>(.*?)<\\/style>/is", "", $pageContent);
    },
    'stripTags' => function($pageContent) {
        return strip_tags($pageContent);
    },
	'lineBreaks' => function($pageContent) {
		return str_replace("\n", '', $pageContent);
	}
);