<?php

namespace MIA3\Mia3Search\Configuration;

/*
 * This file is part of the mia3/mia3_search package.
 *
 * (c) Marc Neuhaus <marc@mia3.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\EventDispatcher\GenericEvent;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;
use TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser;
use TYPO3\CMS\Extbase\Annotation\Inject;

/**
 * Class SearchConfigurationManager
 * @package MIA3\Mia3Search\Configuration
 */
class SearchConfigurationManager extends \TYPO3\CMS\Extbase\Configuration\BackendConfigurationManager
{
    /**
     * @var \TYPO3\CMS\Extbase\Service\FlexFormService
     * @Inject
     */
    protected $flexformService;

    /**
     * @param $pageId
     *
     * @return array
     */
    public static function getRootline($pageId)
    {
        /** @var RootlineUtility $rootlineUtility */
        $rootlineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $pageId);
        // Get the rootline for the current page
        $rootline = $rootlineUtility->get();

        return $rootline;
    }

    /**
     * get flexform of tt_content
     *
     * @param        $contentUid
     * @param string $field
     *
     * @return array
     */
    public function getContentFlexform($contentUid, $field = 'pi_flexform')
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
        $row = $queryBuilder
	        ->select('*')
	        ->from('tt_content')
	        ->where(
	        	$queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($contentUid))
	        )
	        ->execute()
	        ->fetch();

        return $this->flexformService->convertFlexFormContentToArray($row[$field]);
    }

    /**
     * Returns TypoScript Setup array from current Environment.
     *
     * @return array the raw TypoScript setup
     */
    public function getPageTypoScript($pageId, $path = null)
    {
        if (!array_key_exists($pageId, $this->typoScriptSetupCache)) {
            /** @var $template \TYPO3\CMS\Core\TypoScript\TemplateService */
            $template = GeneralUtility::makeInstance(\TYPO3\CMS\Core\TypoScript\TemplateService::class);
            // do not log time-performance information
            $template->tt_track = 0;
            // Explicitly trigger processing of extension static files
            $template->setProcessExtensionStatics(true);
            // Get the root line
            $rootline = [];
            if ($pageId > 0) {
                $rootline = self::getRootline($pageId);
            }
            // This generates the constants/config + hierarchy info for the template.
            $template->runThroughTemplates($rootline, 0);
            $template->generateConfig();
            $this->typoScriptSetupCache[$pageId] = $template->setup;
        }

        $typoscript = $this->typoScriptSetupCache[$pageId];
        $typoscript = GeneralUtility::removeDotsFromTS($typoscript);
        if ($path !== null) {
            $typoscript = ObjectAccess::getPropertyPath($typoscript, $path);
        }

        return $typoscript;
    }

    /**
     * Returns the merged Page TSconfig for page id, $id.
     * Please read details about module programming elsewhere!
     *
     * @param int    $id Page uid
     * @param string $path
     *
     * @return array
     */
    public static function getModTSconfig($id, $path = null)
    {
        $tsconfig = GeneralUtility::removeDotsFromTS(static::getPagesTSconfig($id));

        if ($path !== null) {
            $tsconfig = ObjectAccess::getPropertyPath($tsconfig, $path);
        }

        return $tsconfig;
    }

    /**
     * Returns the Page TSconfig for page with id, $id
     *
     * @param int   $id              Page uid for which to create Page TSconfig
     * @param array $rootLine        If $rootLine is an array, that is used as rootline, otherwise rootline is just calculated
     * @param bool  $returnPartArray If $returnPartArray is set, then the array with accumulated Page TSconfig is returned non-parsed. Otherwise the output will be parsed by the TypoScript parser.
     *
     * @return array Page TSconfig
     * @see \TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser
     */
    public static function getPagesTSconfig($id, $rootLine = null, $returnPartArray = false)
    {
        static $pagesTSconfig_cacheReference = [];
        static $combinedTSconfig_cache = [];

        $id = (int) $id;
        if ($returnPartArray === false
            && $rootLine === null
            && isset($pagesTSconfig_cacheReference[$id])
        ) {
            return $combinedTSconfig_cache[$pagesTSconfig_cacheReference[$id]];
        } else {
            $TSconfig = [];
            if (!is_array($rootLine)) {
                $useCacheForCurrentPageId = true;
                $rootLine = self::getRootline($id);
            } else {
                $useCacheForCurrentPageId = false;
            }

            // Order correctly
            ksort($rootLine);
            $TSdataArray = [];
            // Setting default configuration
            $TSdataArray['defaultPageTSconfig'] = $GLOBALS['TYPO3_CONF_VARS']['BE']['defaultPageTSconfig'];
            foreach ($rootLine as $k => $v) {
                if (trim($v['tsconfig_includes'])) {
                    $includeTsConfigFileList = GeneralUtility::trimExplode(',', $v['tsconfig_includes'], true);
                    // Traversing list
                    foreach ($includeTsConfigFileList as $key => $includeTsConfigFile) {
                        if (StringUtility::beginsWith($includeTsConfigFile, 'EXT:')) {
                            list($includeTsConfigFileExtensionKey, $includeTsConfigFilename) = explode(
                                '/',
                                substr($includeTsConfigFile, 4),
                                2
                            );
                            if (
                                (string) $includeTsConfigFileExtensionKey !== ''
                                && ExtensionManagementUtility::isLoaded($includeTsConfigFileExtensionKey)
                                && (string) $includeTsConfigFilename !== ''
                            ) {
                                $includeTsConfigFileAndPath = ExtensionManagementUtility::extPath($includeTsConfigFileExtensionKey)
                                    .
                                    $includeTsConfigFilename;
                                if (file_exists($includeTsConfigFileAndPath)) {
                                    $TSdataArray['uid_' . $v['uid'] . '_static_'
                                    . $key] = GeneralUtility::getUrl($includeTsConfigFileAndPath);
                                }
                            }
                        }
                    }
                }
                $TSdataArray['uid_' . $v['uid']] = $v['TSconfig'];
            }
            $TSdataArray = TypoScriptParser::checkIncludeLines_array($TSdataArray);
            if ($returnPartArray) {
                return $TSdataArray;
            }
            // Parsing the page TS-Config
            $pageTS = implode(LF . '[GLOBAL]' . LF, $TSdataArray);
            /* @var $parseObj \TYPO3\CMS\Backend\Configuration\TsConfigParser */
            $parseObj = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Configuration\TsConfigParser::class);
            $res = $parseObj->parseTSconfig($pageTS, 'PAGES', $id, $rootLine);
            if ($res) {
                $TSconfig = $res['TSconfig'];
            }
        }

        return $TSconfig;
    }
}
