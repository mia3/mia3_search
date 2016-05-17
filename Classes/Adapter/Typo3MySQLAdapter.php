<?php

namespace MIA3\Mia3Search\Adapter;

use MIA3\Saku\Adapter\MySQLAdapter;

class Typo3MySQLAdapter extends MySQLAdapter
{
    public function __construct($configuration)
    {
        $db = $GLOBALS['TYPO3_CONF_VARS']['DB'];
        $configuration = array_replace(array(
            'database' => $db['database'],
            'host' => 'localhost',
            'username' => $db['username'],
            'password' => $db['password'],
            'port' => isset($db['port']) ? $db['port'] : null,
            'socket' => $db['socket'],
            'table_prefix' => 'tx_mia3search_'
        ), $configuration);

        parent::__construct($configuration);
    }
}