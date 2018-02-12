<?php
namespace MIA3\Mia3Search\Controller;

/*
 * This file is part of the mia3/mia3_search package.
 *
 * (c) Marc Neuhaus <marc@mia3.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use MIA3\Mia3Search\FacetHandlers\DefaultFacetHandler;
use MIA3\Saku\Index;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class SearchController
 * @package MIA3\Mia3Search\Controller
 */
class SearchController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{

    /**
     * @var DatabaseConnection
     */
    protected $databaseConnection;

    /**
     * @param string $query
     * @return void
     * @throws \Exception
     */
    public function indexAction($query = null)
    {
        if (!isset($this->settings['adapter'])) {
            throw new \Exception('could not determine search adapter, did you forget to include the mia3_search typoscript template?');
        }

        $index = new Index($this->settings);
        $this->view->assign('query', $query);
        $this->databaseConnection = $GLOBALS['TYPO3_DB'];

        $options = array();
        if ($this->request->hasArgument('facets')) {
            $options['facets'] = $this->request->getArgument('facets');
        }

        $facets = $this->addFacetLabels($index->getFacets());
        foreach ($facets as $facetName => $facet) {
            if (isset($options['facets'][$facetName])) {
                $facets[$facetName]['value'] = $options['facets'][$facetName];
            } else {
                if (isset($facet['defaultValue'])) {
                    $facets[$facetName]['value'] = $facet['defaultValue'];
                    $options['facets'][$facetName] = $facet['defaultValue'];
                }
            }
        }
        $this->view->assign('facets', $facets);

        foreach ($options['facets'] as $key => $value) {
            if ($value === '') {
                unset($options['facets'][$key]);
            }
        }

        if (empty($query)) {
            $this->view->assign('hasNoResults', false);
            return;
        }
        $results = $index->search($query, $options);
        $this->view->assign('results', $results);
        $this->view->assign('hasNoResults', $results->getTotal() < 1);
    }

    /**
     * @param $facets
     * @return mixed
     */
    protected function addFacetLabels($facets)
    {
        foreach ($facets as $facetName => $facet) {
            if (isset($facet['handler'])) {
                $handler = GeneralUtility::makeInstance(ltrim($facet['handler'], '\\'));
            } else {
                $handler = GeneralUtility::makeInstance(ltrim(DefaultFacetHandler::class, '\\'));
            }
            $facets[$facetName] = $handler->addOptionLabels($facet);
        }

        return $facets;
    }
}