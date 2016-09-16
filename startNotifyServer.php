<?php
require_once('./classes/WebSocketNotifyServer.php');

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
