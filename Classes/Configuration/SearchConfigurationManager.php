<?php
namespace MIA3\Mia3Search\Configuration;

/*
 * This file is part of the mia3/mia3_search package.
 *
 * (c) Marc Neuhaus <marc@mia3.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

/**
 * Class SearchConfigurationManager
 * @package MIA3\Mia3Search\Configuration
 */
class SearchConfigurationManager extends \TYPO3\CMS\Extbase\Configuration\BackendConfigurationManager
{
    /**
     * @var \TYPO3\CMS\Extbase\Service\FlexFormService
     * @inject
     */
    protected $flexformService;

    /**
     * get flexform of tt_content
     *
     * @param $contentUid
     * @param string $field
     * @return array
     */
    public function getContentFlexform($contentUid, $field = 'pi_flexform')
    {
        $row = $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow(
            '*',
            'tt_content',
            'uid = ' . $contentUid
        );

        return $this->flexformService->convertFlexFormContentToArray($row[$field]);
    }

    /**
     * Returns TypoScript Setup array from current Environment.
     *
     * @return array the raw TypoScript setup
     */
    public function getPageTypoScript($pageId, $path = null)
    {
        if (!array_key_exists($pageId, $this->typoScriptSetupCache)) {
            /** @var $template \TYPO3\CMS\Core\TypoScript\TemplateService */
            $template = GeneralUtility::makeInstance(\TYPO3\CMS\Core\TypoScript\TemplateService::class);
            // do not log time-performance information
            $template->tt_track = 0;
            // Explicitly trigger processing of extension static files
            $template->setProcessExtensionStatics(true);
            $template->init();
            // Get the root line
            $rootline = array();
            if ($pageId > 0) {
                /** @var $sysPage \TYPO3\CMS\Frontend\Page\PageRepository */
                $sysPage = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\Page\PageRepository::class);
                // Get the rootline for the current page
                $rootline = $sysPage->getRootLine($pageId, '', true);
            }
            // This generates the constants/config + hierarchy info for the template.
            $template->runThroughTemplates($rootline, 0);
            $template->generateConfig();
            $this->typoScriptSetupCache[$pageId] = $template->setup;
        }

        $typoscript = $this->typoScriptSetupCache[$pageId];
        $typoscript = GeneralUtility::removeDotsFromTS($typoscript);

        if ($path !== null) {
            $typoscript = ObjectAccess::getPropertyPath($typoscript, $path);
        }

        return $typoscript;
    }
}
