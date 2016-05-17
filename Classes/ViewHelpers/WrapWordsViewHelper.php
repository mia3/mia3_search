<?php
namespace MIA3\Mia3Search\ViewHelpers;

    /*                                                                        *
     * This script is part of the TYPO3 project - inspiring people to share!  *
     *                                                                        *
     * TYPO3 is free software; you can redistribute it and/or modify it under *
     * the terms of the GNU General Public License version 2 as published by  *
     * the Free Software Foundation.                                          *
     *                                                                        *
     * This script is distributed in the hope that it will be useful, but     *
     * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
     * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General      *
     * Public License for more details.                                       *
     *                                                                        */

/**
 */
class WrapWordsViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper {
    /**
     *
     * @param mixed $words
     * @param string $wrap
     * @return string
     */
    public function render($words, $wrap = '<strong>|</strong>') {
        $content = $this->renderChildren();
        if (!is_array($words)) {
            $words = preg_split('/[ ,\.\?]/s', $words);
        }
        foreach ($words as $word) {
            // do a case-insensitive search to find all case-sensitive matches
            preg_match_all('/(' . preg_quote($word) . ')/i', $content, $matches);

            if (count($matches[0]) > 0) {
                // replace each found case-sensitive match with it's wrapped
                // counterpart
                foreach ($matches[0] as $key => $match) {
                    $wrappedMatch = str_replace('|', $match, $wrap);
                    $content = str_replace($match, $wrappedMatch, $content);
                }
            }
        }
        return $content;
    }

}
