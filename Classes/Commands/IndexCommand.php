<?php
namespace MIA3\Mia3Search\Commands;

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
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class IndexCommandController
 * @package MIA3\Mia3Search\Command
 */
class IndexCommand extends Command
{
    /**
     * @var ContentIndexer
     */
    private $contentIndexer;

    public function __construct(ContentIndexer $contentIndexer) {
        parent::__construct();
        $this->contentIndexer = $contentIndexer;
    }

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
		$this->contentIndexer->update($input->getArgument('pageIds'), $input->getArgument('logLevel'));
		return 0;
	}
}
