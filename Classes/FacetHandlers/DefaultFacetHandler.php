<?php
namespace MIA3\Mia3Search\FacetHandlers;

/*
 * This file is part of the mia3/mia3_search package.
 *
 * (c) Marc Neuhaus <marc@mia3.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Class DefaultFacetHandler
 * @package MIA3\Mia3Search\FacetHandlers
 */
class DefaultFacetHandler implements FacetHandlerInterface
{
    public function __construct()
    {
    }

    /**
     * @param array $facet
     * @return array
     */
    public function addOptionLabels($facet)
    {

        if (isset($facet['table'])) {
            $ids = array();
            foreach ($facet['options'] as $option) {
                $ids[] = '"' . str_replace('"', '\"', $option['value']) . '"';
            }

            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($facet['table']);
			$statement = $queryBuilder->select($facet['labelField'], $facet['idField'])
				->from($facet['table'])
				->where(
					$queryBuilder->expr()->in($facet['idField'],$queryBuilder->createNamedParameter($ids))
				)
				->execute();
			$rows = array();
	        while ($row = $statement->fetch()) {
	        	$rows[$row[$facet['idField']]] = $row[$facet['labelField']];
	        }

            foreach ($facet['options'] as $key => $option) {
                if (isset($rows[$option['value']][$facet['labelField']])) {
                    $facet['options'][$key]['label'] = $rows[$option['value']][$facet['labelField']];
                }
            }
        }

        if (isset($facet['labelMap'])) {
            foreach ($facet['options'] as $key => $option) {
                if (isset($facet['options'][$key]['label'])) {
                    continue;
                }
                if (isset($facet['labelMap'][$option['value']])) {
                    $facet['options'][$key]['label'] = $facet['labelMap'][$option['value']];
                }
            }
        }

        return $facet;
    }
}
