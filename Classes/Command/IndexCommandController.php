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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Class IndexCommandController
 * @package MIA3\Mia3Search\Command
 */
class IndexCommandController extends Command
{

	/**
	 * Configure the command by defining the name, options and arguments
	 */
	protected function configure()
	{
		$this
			// configure arguments
			->addArgument('pageIds', InputArgument::OPTIONAL)
			->addArgument('logLevel', InputArgument::OPTIONAL)
		;
	}

	/**
	 * Executes the command for indexing the pages
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$objectManager = GeneralUtility::makeInstance(ObjectManager::class);
		$indexer = $objectManager->get(ContentIndexer::class);
		$indexer->update($input->getArgument('pageIds'), $input->getArgument('logLevel'));
	}
}
