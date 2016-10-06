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
    public function render(
        $words,
        $wrap = '<strong>|</strong>',
        $crop = null,
        $suffix = '&hellip;',
        $prefix = '&hellip;',
        $wordsBeforeMatch = 10
    )
    {
        $highlighter = new SearchWordHighlighter($this->renderChildren());
        $highlighter->setWrap($wrap);
        $highlighter->setCrop($crop);
        $highlighter->setPrefix($prefix);
        $highlighter->setSuffix($suffix);
        $highlighter->setWordsBeforeMatch($wordsBeforeMatch);

        return $highlighter->highlight($words);
    }
}
