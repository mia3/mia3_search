<?php
namespace MIA3\Mia3Search\Task;

use TYPO3\CMS\Scheduler\Task\AbstractTask;
use MIA3\Mia3Search\Service\ContentIndexer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

class IndexTask extends AbstractTask
{

    /**
     * Executes the command for indexing the pages
     */
    public function execute()
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $indexer = $objectManager->get(ContentIndexer::class);

        try {
            $indexer->update();
        } catch (TYPO3\CMS\Scheduler\FailedExecutionException $e) {
            return false;
        }
        return true;
    }
}
