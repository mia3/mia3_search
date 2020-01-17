<?php
namespace MIA3\Mia3Search\ViewHelpers;

/*
 * This file is part of the mia3/mia3_search package.
 *
 * (c) Marc Neuhaus <marc@mia3.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use MIA3\Saku\SearchWordHighlighter;

/**
 * Class WrapWordsViewHelper
 * @package MIA3\Mia3Search\ViewHelpers
 */
class WrapWordsViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments()
    {
	    parent::initializeArguments();
	    $this->registerArgument('words', 'mixed',
		    'Searched words', true);
	    $this->registerArgument('wrap', 'string', 'wrap',
		    false, '<strong>|</strong>');
	    $this->registerArgument('crop', 'integer', 'value for crop',
		    false, null);
	    $this->registerArgument('suffix', 'string', 'suffix',
		    false, '&hellip;');
	    $this->registerArgument('prefix', 'string', 'prefix',
		    false, '&hellip;');
	    $this->registerArgument('wordsBeforeMatch', 'string', 'words to show before a match',
		    false, '10');

    }

    /**
     *
     * @param mixed $words
     * @param string $wrap
     * @param integer $crop
     * @param string suffix
     * @param string $prefix
     * @param string $wordsBeforeMatch
     * @return string
     */
    public function render()
    {
        $highlighter = new SearchWordHighlighter($this->renderChildren());
        $highlighter->setWrap($this->arguments['wrap']);
        $highlighter->setCrop($this->arguments['crop']);
        $highlighter->setPrefix($this->arguments['prefix']);
        $highlighter->setSuffix($this->arguments['suffix']);
        $highlighter->setWordsBeforeMatch($this->arguments['wordsBeforeMatch']);

        return $highlighter->highlight($this->arguments['words']);
    }
}
