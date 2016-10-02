<?php

$tokenFile = PATH_site . 'typo3temp/mia3_search_server_identification';

if (file_exists($tokenFile)) {
    echo file_get_contents($tokenFile);
}