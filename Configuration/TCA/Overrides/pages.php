<?php
defined('TYPO3_MODE') or die();

$temporaryColumn = array(
    'tx_mia3search_ignore' => array(
        'exclude' => 0,
        'label' => 'LLL:EXT:mia3_search/Resources/Private/Language/locallang_db.xlf:pages.tx_mia3search_ignore',
        'config' => array(
            'type' => 'check',
            'items' => [
                [
                    'LLL:EXT:mia3_search/Resources/Private/Language/locallang_db.xlf:pages.tx_mia3search_ignore.disable',
                    '',
                ],
                [
                    'LLL:EXT:mia3_search/Resources/Private/Language/locallang_db.xlf:pages.tx_mia3search_ignore.disable_children',
                    '',
                ],
            ],
        ),
    ),
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns(
    'pages',
    $temporaryColumn,
    true
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'pages',
    'tx_mia3search_ignore',
    '',
    'after:hidden'
);