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
        $db = $GLOBALS['TYPO3_CONF_VARS']['DB'];
        $configuration = array_replace(array(
            'database' => $db['database'],
            'host' => $db['host'],
            'username' => $db['username'],
            'password' => $db['password'],
            'port' => isset($db['port']) ? $db['port'] : null,
            'socket' => $db['socket'],
            'table_prefix' => 'tx_mia3search_',
        ), $configuration);

        parent::__construct($configuration);
    }
}
