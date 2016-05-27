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
    public function render($words, $wrap = '<strong>|</strong>', $crop = null, $suffix = '&hellip;', $prefix = '&hellip;', $wordsBeforeMatch = 5)
    {
        $content = $this->renderChildren();
        if (!is_array($words)) {
            $words = preg_split('/[ ,\.\?]/s', $words);
        }

        if ($wordsBeforeMatch > 0) {
//            $content = $this->cutBeforeMatch($content, $words, $crop, $prefix);
        }

        if ($crop !== NULL) {
            $content = $this->cropWords($content, $crop, $suffix);
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

    public function cutBeforeMatch($content, $words, $crop, $prefix) {
        $contentWords = preg_split('/[ ,\.\?]/s', $content);
        foreach ($contentWords as $key => $contentWord) {
            foreach ($words as $word) {
                similar_text(strtolower($contentWord), strtolower($word), $match);
                if ($match > 80) {
                    $startPosition = strpos($content, $contentWord);
                    for ($i = 1; $i <= $wordsBeforeMatch; $i++) {
                        if (isset($contentWords[$key - $i])) {
                            $startPosition -= strlen($contentWords[$key - $i]) + 1;
                        }
                    }
//                    $content = $prefix . ' ' . substr($content, $startPosition);
                    break;
                }
            }
        }
        return $content;
    }

    public function cropWords($text, $wordCount, $suffix = '&hellip;')
    {
        $text = strip_tags($text);

        $words = preg_split("/[\n\r\t ]+/", $text, $wordCount + 1, PREG_SPLIT_NO_EMPTY);
        $separator = ' ';

        if (count($words) > $wordCount) {
            array_pop($words);
            $text = implode($separator, $words);
            $text = $text . $suffix;
        } else {
            $text = implode($separator, $words);
        }

        return $text;
    }
}
