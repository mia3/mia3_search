<?php
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
	'mia3_search',
	'Search',
	array(
		'MIA3\Mia3Search\Controller\SearchController' => 'index',

	),
	// non-cacheable actions
	array(
		'MIA3\Mia3Search\Controller\SearchController' => 'index',
	)
);


$GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['mia3_search_server_identification'] = \MIA3\Mia3Search\Eid\ServerIdentificationEid::class . '::printTokenFile';

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][\MIA3\Mia3Search\Task\IndexTask::class] = [
    'extension' => 'mia3_search',
    'title' => 'LLL:EXT:mia3_search/Resources/Private/Language/locallang_task.xlf:tx_mia3search_task.title',
    'description' => 'LLL:EXT:mia3_search/Resources/Private/Language/locallang_task.xlf:tx_mia3search_task.description',
];

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['parameterProviders'] = array(
	'\MIA3\Mia3Search\ParameterProviders\LanguageParameterProvider'
);

if (class_exists('\GeorgRinger\News\Controller\NewsController')) {
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['parameterProviders'][] = '\MIA3\Mia3Search\ParameterProviders\NewsParameterProvider';
}
//if (class_exists('\Mia3\Mia3Location\Domain\Model\Location')) {
//    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['parameterProviders'][] = '\MIA3\Mia3Search\ParameterProviders\Mia3LocationParameterProvider';
//}

$GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'] .= ',tx_mia3search_ignore';

if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['indexingBlacklist'])) {
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['indexingBlacklist'] = array();
}
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['indexingBlacklist'][] = '.mia3-search-unindexed';

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['pageContentFilters'] = array(
    'cssFilter' => function($pageContent) {
        try {
            $content = new \Wa72\HtmlPageDom\HtmlPageCrawler('<html><body>' . $pageContent . '</body></html>');
            $content->filter(implode(', ', $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['indexingBlacklist']))->remove();
            return html_entity_decode($content->html());
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
