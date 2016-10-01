<?php
namespace MIA3\Mia3Search\Service;

use DmitryDulepov\Realurl\Encoder\UrlEncoder;
use MIA3\Mia3Search\ParameterProviders\ParameterProviderInterface;
use MIA3\Saku\Index;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/*                                                                        *
 * This script is part of the TYPO3 project - inspiring people to share!  *
 *                                                                        *
 * TYPO3 is free software; you can redistribute it and/or modify it under *
 * the terms of the GNU General Public License version 2 as published by  *
 * the Free Software Foundation.                                          *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General      *
 * Public License for more details.                                       *
 *                                                                        */

class ContentIndexer {

    /**
     * @var DatabaseConnection
     */
    protected $database;

    /**
     * @var Index
     */
    protected $index;

    public function __construct() {
        $this->database = $GLOBALS['TYPO3_DB'];
        $this->index = new Index($GLOBALS['TYPO3_CONF_VARS']['SEARCH']);
    }

    /**
     * Update mia3_search indexes
     */
    public function update() {
        $sites = $this->getSites();
        foreach ($sites as $site) {
            $this->indexSite($site);
        }
    }

    /**
     * index a certain page
     *
     * @param integer $site
     */
    public function indexSite($site) {
        $baseUrl = $this->getBaseUrl($site['uid']);
        $pages = $this->getSitePages($site['uid']);
        foreach ($pages as $pageUid) {
            $this->indexPage($pageUid, $baseUrl);
        }
    }

    /**
     * index a certain page
     *
     * @param integer $pageUid
     * @param string $baseUrl
     */
    public function indexPage($pageUid, $baseUrl) {
        $pageRow = $this->getPage(($pageUid));
        $parameterGroups = array(
            array(
                'id' => $pageUid,
                'baseUrl' => $baseUrl,
                'pageTitle' => $pageRow['title']
            )
        );
        $parameterProviders = $this->getParameterProviders();

        if (is_array($parameterProviders)) {
            foreach ($parameterProviders as $parameterProvider) {
                /** @var ParameterProviderInterface $parameterProvider */
                $parameterGroups = $parameterProvider->extendParameterGroups($parameterGroups);
            }
        }

        foreach($parameterGroups as $parameterGroup) {
            $parameterGroup['content'] = $this->getPageContent($parameterGroup);
            $parameterGroup['pageUrl'] = $this->getPageUrl($parameterGroup['content']);
            $parameterGroup['content'] = $this->applyPageContentFilters($parameterGroup['content']);
            $parameterGroup['indexedAt'] = (new \DateTime())->format(\DateTime::ISO8601);

            if (empty($parameterGroup['pageUrl'])) {
                continue;
            }

            try {
                $this->index->addObject(
                    $parameterGroup,
                    $parameterGroup['pageUrl']
                );
            } catch(\Exception $e) {
                var_dump($e->getMessage());
            }
        }
    }

    public function getParameterProviders() {
        $parameterProviders = (array) $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['parameterProviders'];

        $objectManager = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class);

        // create instances
        array_walk($parameterProviders, function(&$parameterProvider) use($objectManager) {
            $parameterProvider = $objectManager->get(ltrim($parameterProvider, '\\'));
        });

        // sort instances by priority
        usort($parameterProviders, function($left, $right){
            return $left->getPriority() > $right->getPriority();
        });

        return $parameterProviders;
    }

    /**
     * Parse out pageUrl from pageContent
     *
     * @param string $pageContent
     * @return string
     */
    public function getPageUrl($pageContent) {
        preg_match('/<!--PageUrl:(.*)-->/s', $pageContent, $match);
        return $match[1];
    }

    /**
     * @param string $pageContent
     * @return string
     */
    public function applyPageContentFilters($pageContent) {
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['pageContentFilters'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['pageContentFilters'] as $filter) {
                $pageContent = call_user_func($filter, $pageContent);
            }
        }
        return $pageContent;
    }

    /**
     * get all pages of a specific site
     *
     * @param integer $pid
     * @return array
     */
    public function getSitePages($pid) {
        $queryGenerator = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Database\\QueryGenerator' );
        $pageUidList = $queryGenerator->getTreeList($pid, PHP_INT_MAX, 0, 1);
        return explode(',', $pageUidList);
    }

    /**
     * Fetch content of a specific parameter combination from the frontend
     *
     * @param array $parameterGroup
     * @return string
     */
    public function getPageContent($parameterGroup) {
        $baseUrl = $parameterGroup['baseUrl'];
        unset($parameterGroup['baseUrl']);

        $parameterGroup['type'] = 3728;
        $parameterGroup['no_cache'] = 1;
        $parameterGroup['columnPositions'] = implode(',', $this->getColumnPositions($pageUid));
        $url = $baseUrl . 'index.php?' . http_build_query($parameterGroup);
//        echo $url . chr(10);
        return GeneralUtility::getUrl($url);
    }

    /**
     * get all columns present on a specific page
     *
     * @param integer $pageUid
     * @return array
     */
    public function getColumnPositions($pageUid) {
        $rows = $this->database->exec_SELECTgetRows(
            'colPos',
            'tt_content',
            'colPos > -1 AND colPos NOT IN(18181) ' . BackendUtility::BEenableFields('tt_content'),
            'colPos',
            '',
            '',
            'colPos'
        );
        return array_keys($rows);
    }

    /**
     * get all available sites
     *
     * @return array
     */
    public function getSites() {
        return $this->database->exec_SELECTgetRows(
            '*',
            'pages',
            'is_siteroot = 1' . BackendUtility::BEenableFields('pages')
        );
    }

    /**
     * get all available sites
     *
     * @return array
     */
    public function getPage($pid) {
        return $this->database->exec_SELECTgetSingleRow(
            '*',
            'pages',
            'uid = ' . $pid . BackendUtility::BEenableFields('pages')
        );
    }

    /**
     * get baseUrl of a specific site
     *
     * @param integer $siteUid
     * @return string
     */
    public function getBaseUrl($siteUid) {
        $domainRecord = $this->database->exec_SELECTgetSingleRow(
            '*',
            'sys_domain',
            'pid = ' . $siteUid . BackendUtility::BEenableFields('sys_domain')
        );
        return sprintf('http://%s/', $domainRecord['domainName']);
    }
}
