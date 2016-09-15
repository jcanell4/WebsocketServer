<?php

require_once('./websockets.php');


class WebSocketNotifyServer extends WebSocketServer
{
    //protected $maxBufferSize = 1048576; //1MB... overkill for an echo server, but potentially plausible for other applications.

    protected function process($user, $message)
    {
        $this->send($user, $message);
    }

    protected function connected($user)
    {
        // Do nothing: This is just an echo server, there's no need to track the user.
        // However, if we did care about the users, we would probably have a cookie to
        // parse at this step, would be looking them up in permanent storage, etc.
    }

    protected function closed($user)
    {
        // Do nothing: This is where cleanup would go, in case the user had any sort of
        // open files or other objects associated with them.  This runs after the socket
        // has been closed, so there is no need to clean up the socket itself here.
    }
}


// TODO[Xavi] aquesta infromació s'ha d'obtenir de la configuració
$server = new WebSocketNotifyServer("0.0.0.0", "9090");

try {
    $server->run();
} catch (Exception $e) {
    $errorMessage = $e->getMessage();

    $server->stdout($errorMessage);
    $server->logError($errorMessage);
}
