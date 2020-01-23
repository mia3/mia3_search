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
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class Mia3LocationParameterProvider
 * @package MIA3\Mia3Search\ParameterProviders
 */
class Mia3LocationParameterProvider implements ParameterProviderInterface
{

    protected $plugin = 'mia3location_locations';

    public function __construct()
    {
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

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
        $rows = $queryBuilder
	        ->select('*')
	        ->from('tt_content')
	        ->add(
	        	'where',
		        '`list_type` = "' . $this->plugin . '" AND `pid` = ' . $pageUid
		        . ' AND `sys_language_uid` IN (' . $language . ',-1)'
		        . BackendUtility::BEenableFields('tt_content'),
		        true
	        )
	        ->execute()
	        ->fetchAll();

        if (empty($rows)) {
            return array();
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_mia3location_domain_model_location');
        $rows = $queryBuilder
	        ->select('*')
	        ->from('tx_mia3location_domain_model_location')
	        ->add(
	        	'where',
		        '1=1' . BackendUtility::BEenableFields('tx_mia3location_domain_model_location'),
		        true
	        )
	        ->execute()
	        ->fetchAll();

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
	    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
	    $statement = $queryBuilder
		    ->select('sys_language_uid')
		    ->from('pages')
		    ->add('where', '`l10n_parent` = ' . $pageUid . BackendUtility::BEenableFields('pages'), true)
		    ->execute();

	    $languageUids = array();
	    while ($row = $statement->fetch()) {
		    $languageUids[$row['sys_language_uid']] = $row;
	    }

	    return $languageUids;
    }
}
