<?php
namespace MIA3\Mia3Search\Adapter;

/*
 * This file is part of the mia3/mia3_search package.
 *
 * (c) Marc Neuhaus <marc@mia3.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use MIA3\Saku\Adapter\MySQLAdapter;

/**
 * Class Typo3MySQLAdapter
 * @package MIA3\Mia3Search\Adapter
 */
class Typo3MySQLAdapter extends MySQLAdapter
{
    /**
     * Typo3MySQLAdapter constructor.
     *
     * @param $configuration
     */
    public function __construct($configuration)
    {
        if (isset($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default'])) {
            $configuration = array_replace(array(
                'database' => $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname'],
                'host' => $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['host'],
                'username' => $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user'],
                'password' => $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password'],
                'port' => isset($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['port']) ? $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['port'] : null,
                'socket' => $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['socket'],
                'table_prefix' => 'tx_mia3search_',
            ), $configuration);
        } else {
            $configuration = array_replace(array(
                'database' => $GLOBALS['TYPO3_CONF_VARS']['DB']['database'],
                'host' => $GLOBALS['TYPO3_CONF_VARS']['DB']['host'],
                'username' => $GLOBALS['TYPO3_CONF_VARS']['DB']['username'],
                'password' => $GLOBALS['TYPO3_CONF_VARS']['DB']['password'],
                'port' => isset($GLOBALS['TYPO3_CONF_VARS']['DB']['port']) ? $GLOBALS['TYPO3_CONF_VARS']['DB']['port'] : null,
                'socket' => $GLOBALS['TYPO3_CONF_VARS']['DB']['socket'],
                'table_prefix' => 'tx_mia3search_',
            ), $configuration);
        }

        parent::__construct($configuration);
    }
}
