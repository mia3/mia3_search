<?php
namespace MIA3\Mia3Search\Eid;

use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Stream;

class ServerIdentificationEid {

    public static function printTokenFile(){
        $tokenFile = \TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/typo3temp/mia3_search_server_identification';

        if (file_exists($tokenFile)) {
            $tokenContent = file_get_contents($tokenFile);
            $body = new Stream('php://temp', 'rw');
            $body->write($tokenContent);
            return (new Response())
                    ->withBody($body)
                    ->withStatus(200);
        }
    }
}
