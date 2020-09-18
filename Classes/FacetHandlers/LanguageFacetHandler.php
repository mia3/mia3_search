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
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

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
        /** @var LanguageAspect $languageAspect */
        $languageAspect = GeneralUtility::makeInstance(Context::class)->getAspect('language');
        $facet = array_replace([
            'table' => 'sys_language',
            'idField' => 'uid',
            'labelField' => 'title',
            'labelMap' => [
                '0' => $defaultLanguageLabel
            ],
            'defaultValue' => $languageAspect->getId(),
        ], $facet);

        //Add the correct labels to the languages in the facet options
        $languages = $this->getAllSiteLanguages();
        $languageLabelsByUid = [];
        if($languages){
            foreach($languages as $language){
                $languageLabelsByUid[$language->getLanguageId()] = $language->getTitle();
            }
            $facet['options'] = array_map(function ($option) use($languageLabelsByUid){
                $option['label'] = $languageLabelsByUid[$option['value']];
                return $option;
            }, $facet['options']);
        }

        array_unshift(
            $facet['options'],
            [
                'label' => 'Alle',
                'value' => null,
            ]
        );

        return parent::addOptionLabels($facet);
    }

    /**
     * Returns all the Languages, defined in the SiteConfiguration of the RootPage of this Frontend page
     * @return \TYPO3\CMS\Core\Site\Entity\SiteLanguage[]|null
     */
    public function getAllSiteLanguages(){
        $currentPageUid = $GLOBALS['TSFE']->id;
        /** @var RootlineUtility $rootLine */
        $rootLineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $currentPageUid);
        $rootLine = $rootLineUtility->get();
        /** @var SiteFinder $siteFinder */
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        try{
            $siteObject = $siteFinder->getSiteByRootPageId($rootLine[0]['uid']);
        }catch (SiteNotFoundException $e){
            return null;
        }
        return $siteObject->getAllLanguages();
    }
}
