<?php
namespace MIA3\Mia3Search\Command;

/*
 * This file is part of the mia3/mia3_search package.
 *
 * (c) Marc Neuhaus <marc@mia3.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use MIA3\Mia3Search\Service\ContentIndexer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class IndexCommandController
 * @package MIA3\Mia3Search\Command
 */
class IndexCommandController extends \TYPO3\CMS\Extbase\Mvc\Controller\CommandController
{

    /**
     * Update mia3_search indexes
     * @param string $pageIds
     */
    public function updateCommand($pageIds = NULL)
    {
        $indexer = $this->objectManager->get(ContentIndexer::class);
        $indexer->update($pageIds);
    }

}
