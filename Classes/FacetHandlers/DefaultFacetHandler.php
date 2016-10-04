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

/**
 * Class DefaultFacetHandler
 * @package MIA3\Mia3Search\FacetHandlers
 */
class DefaultFacetHandler implements FacetHandlerInterface
{
    /**
     * @var DatabaseConnection
     */
    protected $databaseConnection;

    public function __construct()
    {
        $this->databaseConnection = $GLOBALS['TYPO3_DB'];
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
            $rows = $this->databaseConnection->exec_SELECTgetRows(
                $facet['labelField'] . ',' . $facet['idField'],
                $facet['table'],
                sprintf('%s IN (%s)', $facet['idField'], implode(', ', $ids)),
                '',
                '',
                '',
                $facet['idField']
            );

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