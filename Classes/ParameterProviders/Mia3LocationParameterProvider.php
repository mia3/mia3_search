<?php
namespace MIA3\Mia3Search\ParameterProviders;

/*
 * This file is part of the mia3/mia3_search package.
 *
 * (c) Marc Neuhaus <marc@mia3.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * Class Mia3LocationParameterProvider
 * @package MIA3\Mia3Search\ParameterProviders
 */
class Mia3LocationParameterProvider implements ParameterProviderInterface
{
    /**
     * @var DatabaseConnection
     */
    protected $database;

    protected $plugin = 'mia3location_locations';

    public function __construct()
    {
        $this->database = $GLOBALS['TYPO3_DB'];
    }

    /**
     * @return integer
     */
    public function getPriority()
    {
        return 10;
    }

    /**
     * @param array $parameterGroups
     * @return array
     */
    public function extendParameterGroups($parameterGroups)
    {
        foreach ($parameterGroups as $parameterGroup) {
            $parameterGroups = array_merge(
                $parameterGroups,
                $this->addPluginParameters($parameterGroup)
            );
        }

        return $parameterGroups;
    }

    public function addPluginParameters($parameterGroup)
    {
        $pageUid = $parameterGroup['id'];
        $language = isset($parameterGroup['L']) ? $parameterGroup['L'] : '0';
        $where = 'list_type = "' . $this->plugin . '" AND pid = ' . $pageUid;
        $where .= ' AND sys_language_uid IN (' . $language . ',-1)';
        $where .= BackendUtility::BEenableFields('tt_content');

        $rows = $this->database->exec_SELECTgetRows(
            '*',
            'tt_content',
            $where
        );

        if (empty($rows)) {
            return array();
        }

        $rows = $this->database->exec_SELECTgetRows(
            '*',
            'tx_mia3location_domain_model_location',
            '1=1' . BackendUtility::BEenableFields('tx_mia3location_domain_model_location')
        );

        $parameterGroups = array();
        foreach ($rows as $row) {
            $detailParameterGroup = $parameterGroup;
            $detailParameterGroup['tx_mia3location_locations'] = array(
                'action' => 'show',
                'location' => $row['uid'],
            );
            $parameterGroups[] = $detailParameterGroup;
        }

        return $parameterGroups;
    }


    /**
     * Get all languages available on a specific page
     *
     * @param integer $pageUid
     * @return array
     */
    public function getPageLanguages($pageUid)
    {
        return $this->database->exec_SELECTgetRows(
            'sys_language_uid',
            'pages_language_overlay',
            'pid = ' . $pageUid . BackendUtility::BEenableFields('pages_language_overlay'),
            '',
            '',
            '',
            'sys_language_uid'
        );
    }
}