<?php
namespace MIA3\Mia3Search\ParameterProviders;

use GeorgRinger\News\Controller\NewsController;
use MIA3\Mia3Search\Configuration\BackendConfigurationManager;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Page\PageRepository;

class NewsParameterProvider extends NewsController implements ParameterProviderInterface
{
    /**
     * @var DatabaseConnection
     */
    protected $database;

    /**
     * @var \MIA3\Mia3Search\Configuration\PluginConfigurationManager
     * @inject
     */
    protected $configurationManager;

    /**
     * NewsParameterProvider constructor.
     */
    public function __construct()
    {
        $this->database = $GLOBALS['TYPO3_DB'];
    }

    /**
     * @return integer
     */
    public function getPriority() {
        return 10;
    }

    /**
     * @param array $parameterGroups
     * @return array
     */
    public function extendParameterGroups($parameterGroups) {
        foreach ($parameterGroups as $parameterGroup) {
            $parameterGroups = array_merge(
                $parameterGroups,
                $this->addNewsPluginParameterGroups($parameterGroup)
            );
        }
        return $parameterGroups;
    }

    public function addNewsPluginParameterGroups($parameterGroup) {
        $newsPlugins = $this->getNewsPlugins($parameterGroup);
        if (!is_array($newsPlugins)) {
            return array();
        }
        $parameterGroups = array();
        foreach ($newsPlugins as $newsPlugin) {
            $flexform = $this->configurationManager->getContentFlexform($newsPlugin['uid']);

            // only process lists for now
            if ($flexform['switchableControllerActions'] !== 'News->list') {
                continue;
            }

            // merge settings from flexform & typoscript
            $settings = array_replace(
                $this->configurationManager->getPageTypoScript($parameterGroup['id'], 'plugin.tx_news.settings'),
                $flexform['settings']
            );

            $newsRecords = $this->findNewsRecords($settings);
            foreach($newsRecords as $newsRecord) {
                $detailParameterGroup = $this->getDetailParameterGroup($parameterGroup, $newsRecord, $settings);
                $parameterGroups[] = $detailParameterGroup;
            }
        }
        return $parameterGroups;
    }

    public function getDetailParameterGroup($parameterGroup, $newsRecord, $settings) {
        $newsRecordUid = $this->getLocalizedNewsRecordUid($newsRecord, $parameterGroup);
        return array_replace(
            $parameterGroup,
            array(
                'id' => $this->getDetailPage($settings, $newsRecord, $parameterGroup['id']),
                'tx_news_pi1' => array(
                    'action' => 'detail',
                    'news' => $newsRecordUid
                )
            )
        );
    }

    public function findNewsRecords($settings) {
        $demand = $this->createDemandObjectFromSettings($flexform);
        $demand->setLimit(PHP_INT_MAX);
        return $this->newsRepository->findDemanded($demand);
    }

    public function getLocalizedNewsRecordUid($newRecord, $parameterGroup) {
        $languageUid = isset($parameterGroup['L']) ? $parameterGroup['L'] : 0;
        $row = $this->database->exec_SELECTgetSingleRow(
            'uid',
            'tx_news_domain_model_news',
            sprintf(
                '(l10n_parent = %s AND sys_language_uid = %s) OR (uid = %s AND sys_language_uid = -1) AND deleted = 0 AND hidden = 0' ,
                $newRecord->getUid(),
                $languageUid,
                $newRecord->getUid()
            ) . BackendUtility::BEenableFields('tx_news_domain_model_news')
        );
        return $row['uid'];
    }

    /**
     * get all columns present on a specific page
     *
     * @param array $parameterGroup
     * @return array
     */
    public function getNewsPlugins($parameterGroup) {
        $pageUid = $parameterGroup['id'];
        $language = isset($parameterGroup['L']) ? $parameterGroup['L'] : '0';
        $where = 'list_type = "news_pi1" AND pid = ' . $pageUid;
        $where .= ' AND sys_language_uid IN (' . $language . ',-1)';
        $where .= BackendUtility::BEenableFields('tt_content');

        return $this->database->exec_SELECTgetRows(
            '*',
            'tt_content',
            $where
        );
    }

    public function getDetailPage($settings, $newsItem, $fallbackPid = 0) {
        $detailPidDeterminationCallbacks = [
            'flexform' => 'getDetailPidFromFlexform',
            'categories' => 'getDetailPidFromCategories',
            'default' => 'getDetailPidFromDefaultDetailPid',
        ];
        foreach (GeneralUtility::trimExplode(',', $settings['detailPidDetermination']) as $determinationMethod) {
            if ($callback = $detailPidDeterminationCallbacks[$determinationMethod]) {
                if ($detailPid = call_user_func([$this, $callback], $settings, $newsItem)) {
                    break;
                }
            }
        }
        return $detailPid > 0 ? $detailPid : $fallbackPid;
    }

    /**
     * Gets detailPid from categories of the given news item. First will be return.
     *
     * @param  array $settings
     * @param  \GeorgRinger\News\Domain\Model\News $newsItem
     * @return int
     */
    protected function getDetailPidFromCategories($settings, $newsItem)
    {
        $detailPid = 0;
        if ($newsItem->getCategories()) {
            foreach ($newsItem->getCategories() as $category) {
                if ($detailPid = (int)$category->getSinglePid()) {
                    break;
                }
            }
        }
        return $detailPid;
    }

    /**
     * Gets detailPid from defaultDetailPid setting
     *
     * @param  array $settings
     * @param  \GeorgRinger\News\Domain\Model\News $newsItem
     * @return int
     */
    protected function getDetailPidFromDefaultDetailPid($settings, $newsItem)
    {
        return (int)$settings['defaultDetailPid'];
    }

    /**
     * Gets detailPid from flexform of current plugin.
     *
     * @param  array $settings
     * @param  \GeorgRinger\News\Domain\Model\News $newsItem
     * @return int
     */
    protected function getDetailPidFromFlexform($settings, $newsItem)
    {
        return (int)$settings['detailPid'];
    }
}