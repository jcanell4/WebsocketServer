<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);


require_once(realpath(dirname(__FILE__)) . '/classes/WebSocketNotifyServer.php');



$addr = WikiGlobalConfig::getConf('notifier_ws_ip', 'wikiiocmodel');
$port = WikiGlobalConfig::getConf('notifier_ws_port', 'wikiiocmodel');


$server = new WebSocketNotifyServer($addr, $port);

try {
    $server->run();
} catch (Exception $e) {
    $errorMessage = $e->getMessage();

    $server->stdout($errorMessage);
    $server->logError($errorMessage);
}
