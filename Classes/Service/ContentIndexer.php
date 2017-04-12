<?php
namespace MIA3\Mia3Search\Service;

/*
 * This file is part of the mia3/mia3_search package.
 *
 * (c) Marc Neuhaus <marc@mia3.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use GuzzleHttp\Client;
use GuzzleHttp\Promise\EachPromise;
use MIA3\Mia3Search\ParameterProviders\ParameterProviderInterface;
use MIA3\Saku\Index;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * Class ContentIndexer
 * @package MIA3\Mia3Search\Service
 */
class ContentIndexer
{

    /**
     * @var \MIA3\Mia3Search\Configuration\SearchConfigurationManager
     * @inject
     */
    protected $configurationManager;

    /**
     * @var \TYPO3\CMS\Frontend\Page\PageRepository
     * @inject
     */
    protected $pageRepository;

    /**
     * @var DatabaseConnection
     */
    protected $database;

    /**
     * @var Index
     */
    protected $index;

    /**
     * @var array
     */
    protected $settings;

    public function __construct()
    {
        $this->database = $GLOBALS['TYPO3_DB'];
    }

    /**
     * Update mia3_search indexes
     * @param string $pageIds
     */
    public function update($pageIds = null)
    {
        $sites = $this->getSites();

        if (is_string($pageIds)) {
            $pageIds = GeneralUtility::trimExplode(',', $pageIds);
        }

        $this->database->exec_TRUNCATEquery('tx_mia3search_contents');
        $this->database->exec_TRUNCATEquery('tx_mia3search_objects');

        foreach ($sites as $site) {
            $this->settings = $this->configurationManager->getPageTypoScript($site['uid'],
                'plugin.tx_mia3search_search.settings');

            if ($this->settings === null) {
                continue;
            }

            $this->index = new Index($this->settings);
            $this->indexSite($site, $pageIds);
        }
    }

    public function generatePromises($parameterGroups, $client)
    {
        foreach ($parameterGroups as $parameterGroup) {
            yield $client->requestAsync('GET', $this->getPageUrl($parameterGroup));
        }
    }

    /**
     * index a certain page
     *
     * @param integer $site
     */
    public function indexSite($site, $pageIds)
    {
        $baseUrl = $this->getBaseUrl($site['uid']);
        if ($baseUrl === null) {
            return;
        }
        $pages = $this->getSitePages($site['uid']);
        $parameterGroups = array();
        foreach ($pages as $pageUid) {
            if ($pageIds !== null && !in_array($pageUid, $pageIds)) {
                continue;
            }

            if ($this->pageShouldBeIgnored($pageUid)) {
                continue;
            }

            $parameterGroups = array_merge(
                $parameterGroups,
                $this->getParameterGroups($pageUid, $baseUrl)
            );
        }

        $client = new Client(['verify' => false]);
        $promise = new EachPromise($this->generatePromises($parameterGroups, $client), [
            'concurrency' => 3,
            'fulfilled' => function (ResponseInterface $response, $index) use ($parameterGroups) {
                $parameterGroup = $parameterGroups[$index];

                $start = microtime(true);
                $parameterGroup['content'] = strval($response->getBody());
                $parameterGroup['pageUrl'] = $this->getPageSpeakingUrl($parameterGroup['content']);
                $parameterGroup['language'] = $this->getLanguage($parameterGroup['content']);
                $parameterGroup['content'] = $this->applyPageContentFilters($parameterGroup['content']);
                $parameterGroup['indexedAt'] = (new \DateTime())->format(\DateTime::ISO8601);

                $categories = CategoryApi::getRelatedCategories($parameterGroup['id'], 'categories', 'pages');
                $parameterGroup['categories'] = array();
                foreach ($categories as $category) {
                    $parameterGroup['categories'][] = $category['uid'];
                }

                if (empty($parameterGroup['pageUrl'])) {
                    return;
                }

                try {
                    $this->index->addObject(
                        $parameterGroup,
                        $parameterGroup['pageUrl']
                    );
                } catch (\Exception $e) {
                }
            },
        ]);
        $promise->promise()->wait();
    }

    public function pageShouldBeIgnored($pageUid)
    {
        $pageRow = $this->getPage(($pageUid));
        if ($pageRow['tx_mia3search_ignore'] > 0) {
            return true;
        }
        foreach ($this->pageRepository->getRootLine($pageUid) as $parentPage) {
            if ($parentPage['tx_mia3search_ignore'] > 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * index a certain page
     *
     * @param integer $pageUid
     * @param string $baseUrl
     */
    public function getParameterGroups($pageUid, $baseUrl)
    {
        $pageRow = $this->getPage(($pageUid));
        $doktypes = GeneralUtility::trimExplode(',', $this->settings['doktypes']);
        if (!in_array($pageRow['doktype'], $doktypes)) {
            return array();
        }

        $parameterGroups = array(
            array(
                'id' => $pageUid,
                'baseUrl' => $baseUrl,
                'pageTitle' => $pageRow['title'],
            ),
        );
        $parameterProviders = $this->getParameterProviders();

        if (is_array($parameterProviders)) {
            foreach ($parameterProviders as $parameterProvider) {
                /** @var ParameterProviderInterface $parameterProvider */
                $parameterGroups = $parameterProvider->extendParameterGroups($parameterGroups);
            }
        }

        return $parameterGroups;
    }

    public function getParameterProviders()
    {
        $parameterProviders = (array)$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['mia3_search']['parameterProviders'];

        $objectManager = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class);

        // create instances
        array_walk($parameterProviders, function (&$parameterProvider) use ($objectManager) {
            $parameterProvider = $objectManager->get(ltrim($parameterProvider, '\\'));
        });

        // sort instances by priority
        usort($parameterProviders, function ($left, $right) {
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
    public function getPageSpeakingUrl($pageContent)
    {
        preg_match('/<!--PageUrl:(.*)-->/Us', $pageContent, $match);

        return trim($match[1]);
    }

    /**
     * Parse out pageUrl from pageContent
     *
     * @param string $pageContent
     * @return string
     */
    public function getLanguage($pageContent)
    {
        preg_match('/<!--Language:(.*)-->/Us', $pageContent, $match);

        return trim($match[1]);
    }

    /**
     * @param string $pageContent
     * @return string
     */
    public function applyPageContentFilters($pageContent)
    {
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
    public function getSitePages($pid)
    {
        $queryGenerator = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Database\\QueryGenerator');
        $query = 'hidden = 0';
        $pageUidList = $queryGenerator->getTreeList($pid, PHP_INT_MAX, 0, $query);

        return explode(',', $pageUidList);
    }

    /**
     * Fetch content of a specific parameter combination from the frontend
     *
     * @param array $parameterGroup
     * @return string
     */
    public function getPageContent($parameterGroup)
    {
        $baseUrl = $parameterGroup['baseUrl'];
        unset($parameterGroup['baseUrl']);

        $parameterGroup['type'] = 3728;
        $parameterGroup['no_cache'] = 1;
        $parameterGroup['columnPositions'] = implode(',', $this->getColumnPositions($pageUid));
        $url = $baseUrl . 'index.php?' . http_build_query($parameterGroup);

        return GeneralUtility::getUrl($url);
    }

    /**
     * Fetch content of a specific parameter combination from the frontend
     *
     * @param array $parameterGroup
     * @return string
     */
    public function getPageUrl($parameterGroup)
    {
        $baseUrl = $parameterGroup['baseUrl'];
        unset($parameterGroup['baseUrl']);

        $parameterGroup['type'] = 3728;
        $parameterGroup['no_cache'] = 1;
        $parameterGroup['columnPositions'] = implode(',', $this->getColumnPositions($pageUid));
        $url = $baseUrl . 'index.php?' . http_build_query($parameterGroup);

        return $url;
    }

    /**
     * get all columns present on a specific page
     *
     * @param integer $pageUid
     * @return array
     */
    public function getColumnPositions($pageUid)
    {
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
    public function getSites()
    {
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
    public function getPage($pid)
    {
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
    public function getBaseUrl($siteUid)
    {

        $domainRecords = $this->database->exec_SELECTgetRows(
            '*',
            'sys_domain',
            'pid = ' . $siteUid . BackendUtility::BEenableFields('sys_domain')
        );

        $token = uniqid('', true);
        file_put_contents(PATH_site . 'typo3temp/mia3_search_server_identification', $token);
        $client = new Client(['verify' => false]);
        $protocols = ['https', 'http'];
        foreach ($domainRecords as $domainRecord) {
            foreach ($protocols as $protocol) {
                $baseUrl = sprintf($protocol . '://%s/', $domainRecord['domainName']);
                $serverIdentificationUrl = $baseUrl . 'index.php?eID=mia3_search_server_identification';
                $result = $client->get($serverIdentificationUrl);
                $remoteToken = $result->getBody()->__toString();
                if ($token == $remoteToken) {
                    return $baseUrl;
                }
            }
        }
    }
}
