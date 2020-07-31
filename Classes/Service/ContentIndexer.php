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
use GuzzleHttp\Exception\RequestException;
use MIA3\Mia3Search\ParameterProviders\ParameterProviderInterface;
use MIA3\Saku\Index;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
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
     * @var Index
     */
    protected $index;

    /**
     * @var array
     */
    protected $settings;

    /**
     * @var int
     */
    protected $logLevel = 0;

    /**
     * @var Client
     */
    protected $client;

    public function __construct()
    {
    }

    /**
     * Update mia3_search indexes
     * @param string $pageIds
     */
    public function update($pageIds = null, $logLevel = 0)
    {
        $this->logLevel = $logLevel;
        $this->log('Starting to index');
        $sites = $this->getSites();

        if (is_string($pageIds) && !empty($pageIds)) {
            $this->log('Indexing specific PageIds: ' . $pageIds);
            $pageIds = GeneralUtility::trimExplode(',', $pageIds);
        }

        $timestamp = time();
        foreach ($sites as $site) {
            $this->log('Indexing site: ' . $site['title'] . ' [' . $site['uid'] . ']');
            $this->settings = $this->configurationManager->getPageTypoScript($site['uid'],
                'plugin.tx_mia3search_search.settings');

            $requestOptions = [
                'verify' => false
            ];
            if (isset($this->settings['auth']['username']) && !empty($this->settings['auth']['username'])) {
                $requestOptions['auth'] = [
                    $this->settings['auth']['username'],
                    $this->settings['auth']['password']
                ];
            }
            $this->client = new Client($requestOptions);

            if ($this->settings === null) {
                $this->log('Failed to gather settings for Site: ' . $site['title'] . ' [' . $site['uid'] . ']');
                continue;
            }

            $this->index = new Index(array_replace([
                    'indexName' => 'index-' . $site['uid'],
                ], $this->settings)
            );
            $this->indexSite($site, $pageIds);

            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_mia3search_objects');
            $rows = $queryBuilder->select('id')
                ->from('tx_mia3search_objects')
                ->where(
                    $queryBuilder->expr()->lt('updated',$timestamp)
                )
                ->execute()
                ->fetchAll();
            foreach ($rows as $row) {
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_mia3search_objects');
                $queryBuilder
                    ->delete('tx_mia3search_objects')
                    ->where(
                        $queryBuilder->expr()->eq('id', $row['id'])
                    )
                    ->execute();

                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_mia3search_contents');
                $queryBuilder
                    ->delete('tx_mia3search_contents')
                    ->where(
                        $queryBuilder->expr()->eq('object', $row['id'])
                    )
                    ->execute();
            }
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
            $this->log('failed to determined BaseUrl');
            return;
        }
        $pages = $this->getSitePages($site['uid']);
        $parameterGroups = array();
        foreach ($pages as $pageUid) {
            if (is_array($pageIds) && !in_array($pageUid, $pageIds)) {
                $this->log('page should not be indexed: ' . $pageUid);
                continue;
            }

            if ($this->pageShouldBeIgnored($pageUid)) {
                $this->log('page should be ignored: ' . $pageUid);
                continue;
            }

            $parameterGroups = array_merge(
                $parameterGroups,
                $this->getParameterGroups($pageUid, $baseUrl)
            );
        }

        $promise = new EachPromise($this->generatePromises($parameterGroups, $this->client), [
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
                    $this->log('failed to determine pageUrl: ' . $parameterGroup);
                    return;
                }

                try {
                    $this->index->addObject(
                        $parameterGroup,
                        $parameterGroup['pageUrl']
                    );
                } catch (\Exception $e) {
                    $this->log('failed to add page to index: ' . $e->getMessage());
                }
            },
            'rejected' => function (RequestException $e){
                $this->log("Request failed: " . $e->getResponse()->getStatusCode() . ' - ' . $e->getMessage());
                throw $e;
            }
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
     * @return array
     */
    public function getParameterGroups($pageUid, $baseUrl)
    {
        $pageRow = $this->getPage(($pageUid));
        $doktypes = GeneralUtility::trimExplode(',', $this->settings['doktypes']);
        if (!in_array($pageRow['doktype'], $doktypes)) {
            $this->log('page "' . $pageRow['title'] . '" doktype[' . $pageRow['doktype'] . '] should not be indexed [' . $this->settings['doktypes'] . ']');
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

        $this->log('determined parameterGroups for page: ' . $pageUid . ' ', $parameterGroups);

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

        return urldecode(trim($match[1]));
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
        $queryGenerator = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Database\\QueryGenerator');
        $query = 'hidden = 0';
        $pageUidList = $queryGenerator->getTreeList($pid, PHP_INT_MAX, 0, $query);
        $this->log('PageUids in Site to Index: ' . $pageUidList);

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
        $parameterGroup['columnPositions'] = implode(',', $this->getColumnPositions($parameterGroup['id']));
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
        $parameterGroup['columnPositions'] = implode(',', $this->getColumnPositions($parameterGroup['id']));
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
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
        $statement = $queryBuilder
            ->select('colPos')
            ->from('tt_content')
            ->add('where', '(`colPos` > -1) AND (`colPos` NOT IN (18181))' . BackendUtility::BEenableFields('tt_content'), 1)
            ->andWhere($queryBuilder->expr()->eq('pid', $pageUid))
            ->groupBy('colPos')
            ->execute();

        $colPos = array();
        while ($row = $statement->fetch()) {
            $colPos[] = $row['colPos'];
        }

        return $colPos;
    }

    /**
     * get all available sites
     *
     * @return array
     */
    public function getSites()
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $sites = $queryBuilder
            ->select('*')
            ->from('pages')
            ->add('where', '`is_siteroot` = 1' . BackendUtility::BEenableFields('pages'), true)
            ->execute()
            ->fetchAll();

        $this->log('Found ' . count($sites) . ' Site to index');

        return $sites;
    }

    /**
     * get all available sites
     *
     * @param integer $pid
     * @return array
     */
    public function getPage($pid)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $row = $queryBuilder
            ->select('*')
            ->from('pages')
            ->add('where', '`uid` = ' . $pid . BackendUtility::BEenableFields('pages'), 1)
            ->execute()
            ->fetch();

        return $row;
    }

    /**
     * get baseUrl of a specific site
     *
     * @param integer $siteUid
     * @return string
     */
    public function getBaseUrl($siteUid)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_domain');
        $domainRecords = $queryBuilder
            ->select('*')
            ->from('sys_domain')
            ->add('where', '`pid` = ' . $siteUid . BackendUtility::BEenableFields('sys_domain'), 1)
            ->execute()
            ->fetchAll();

        $token = uniqid('', true);
        file_put_contents(PATH_site . 'typo3temp/mia3_search_server_identification', $token);
        $this->log('create serverIdentificationToken: ' . $token);
        $protocols = ['https', 'http'];
        foreach ($domainRecords as $domainRecord) {
            foreach ($protocols as $protocol) {
                $baseUrl = sprintf($protocol . '://%s/', $domainRecord['domainName']);
                $this->log('baseUrl candidate: ' . $baseUrl);
                $serverIdentificationUrl = $baseUrl . 'index.php?eID=mia3_search_server_identification';
                $this->log('verificationUrl: ' . $serverIdentificationUrl);
                try {
                    $result = $this->client->get($serverIdentificationUrl);
                    $remoteToken = $result->getBody()->__toString();
                    $this->log('verificationToken: ' . $remoteToken);
                    if ($token == $remoteToken) {
                        $this->log('determined BaseUrl: ' . $baseUrl);
                        return $baseUrl;
                    }
                } catch (\Exception $exception) {
                    $this->log('failed to verify token: ' . $exception->getMessage());
                }
            }
        }
    }

    public function log($message, $data = null)
    {
        if ($data !== null) {
            $message .= ' ' . var_export($data, true);
        }
        if ($this->logLevel >= 1) {
            $GLOBALS['BE_USER']->writelog(4, '', 0, 0, '[mia3_search] ' . $message, []);
        }
        if ($this->logLevel >= 2) {
            echo $message . '<br />';
        }
    }

}
