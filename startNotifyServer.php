<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);


require_once(realpath(dirname(__FILE__)) . '/classes/WebSocketNotifyServer.php');



$addr = WikiGlobalConfig::getConf('notifier_ws_ip', 'wikiiocmodel');
$port = WikiGlobalConfig::getConf('notifier_ws_port', 'wikiiocmodel');


if ($argv[1] =='-kill' ) {
    WebSocketNotifyServer::killRunningServerByPort($port);
    echo 'Tancant el server al port ' . $port . '\n';

} else {

    echo 'Iniciant el server';
    $server = new WebSocketNotifyServer($addr, $port);

    try {
        $server->run();
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();

        $server->stdout($errorMessage);
        $server->logError($errorMessage);
    }

}

