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

use MIA3\Mia3Search\Configuration\SearchConfigurationManager;
use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * Class LanguageFacetHandler
 * @package MIA3\Mia3Search\FacetHandlers
 */
class LanguageFacetHandler extends DefaultFacetHandler implements FacetHandlerInterface
{
    /**
     * @param array $facet
     * @return array
     */
    public function addOptionLabels($facet)
    {
        $defaultLanguageLabel = SearchConfigurationManager::getModTSconfig($GLOBALS['TSFE']->id, 'mod.SHARED.defaultLanguageLabel');

        $facet = array_replace([
            'table' => 'sys_language',
            'idField' => 'uid',
            'labelField' => 'title',
            'labelMap' => [
                '0' => $defaultLanguageLabel
            ],
            'defaultValue' => $GLOBALS['TSFE']->sys_page->sys_language_uid,
        ], $facet);

        array_unshift(
            $facet['options'],
            [
                'label' => 'Alle',
                'value' => null,
            ]
        );

        return parent::addOptionLabels($facet);
    }
}