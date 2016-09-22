<?php

//ALERTA[Xavi] Afegits
if (!defined("DOKU_INC")) {
    define('DOKU_INC', dirname(__FILE__) . '/../../../../../');
}
if (!defined('DOKU_PLUGIN')) {
    define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');
}

if (!defined('WIKI_IOC_MODEL')) {
    define('WIKI_IOC_MODEL', DOKU_INC . "lib/plugins/wikiiocmodel/");
}

require_once DOKU_INC . 'inc/init.php'; // ALERTA[avi] És necessari?
require_once DOKU_PLUGIN . 'wikiiocmodel/persistence/BasicPersistenceEngine.php';
require_once DOKU_PLUGIN . "wikiiocmodel/datamodel/WebsocketNotifyModel.php";
require_once DOKU_PLUGIN . "wikiiocmodel/WikiIocInfoManager.php";

// ALERTA[Xavi] Fi afegits

require_once('WebSocketServer.php');


class WebSocketNotifyServer extends WebSocketServer
{
    // PROTOCOL
    const AUTH = 'AUTH';
    const NOTIFY_TO_FROM = 'NOTIFY_TO_FROM';
    const NOTIFY_TO = 'NOTIFY_TO';


    const DEFAULT_TYPE = 'info';
    const DEFAULT_SENDER = 'system';

    const WARNING_TYPE = 'warning';

    //ALERTA[Xavi] Afegit
    private $notifyModel;


    //protected $maxBufferSize = 1048576; //1MB... overkill for an echo server, but potentially plausible for other applications.

    protected function process($user, $message)
    {
        $data = json_decode($message, true);

//        echo "Està autenticat? (" .$user->authenticated . ") [".$user->id."]\n";

        // L'usuari no està autenticat i el missatge es d'autenticacio
        if (!$user->authenticated && $data['command'] = self::AUTH) {

            // TODO[Xavi] Comprovar la autenticació, si es correcta assignar el id al usuari
            unset($this->users[$user->id]); // Eliminem la referència temporal de l'array
            $user->id = $data['user'];
            $user->authenticated = true;
            $this->users[$user->id] = $user; // Ho afegim amb la nova ID

            $this->send($user, 'Autenticació correcta. Benvingut ' . $user->id); // TODO: Canviar per missatge de confirmació de connexió amb éxit pel frontend

            $previousNotifications = $this->notifyModel->popNotifications($user->id);


            // TODO: recuperar tots els missatges del blackboard i enviar-los pel socket
            if ($previousNotifications) {
                echo "Trobades notificacions previes: \n";
                print_r($previousNotifications);
                $this->send($user, json_encode($previousNotifications));
            }


            $oldUser = $this->getUserById($user->id);
            if ($oldUser && $oldUser != $user) {
                // Es troba aquest usuari ja connectat? Desconnectar l'anterior
                $this->send($oldUser, 'Desconnectat. Iniciada sessió en altre dispositiu');
                $this->disconnect($oldUser->socket, true, 111);
            }


            // TODO: Si no és correcte desconnectar al client
//            $this->send($user, 'Error d\'autenticació');
//            $this->disconnect($user->socket, true, 111);


        }

        // O ja estava autenticat, o s'acaba d'autenticar
        if ($user->authenticated) {

            switch ($data['command']) {

                case self::AUTH:
                    // Ja ha d'estar ressolt en aquest punt
                    break;

                //    public function notifyMessageToFrom($text, $receiverId, $senderId = NULL)
                //    public function notifyTo($data, $receiverId, $type, $id=NULL)
                //    public function popNotifications($userId)
                //    public function close($userId) // Aquest cas no es donarà, la desconnexió es fa pel socket

                case self::NOTIFY_TO:
                    $receiver = $this->getUserById($data['receiverId']);

                    $message = [];
                    $message['type'] = $data['type'] ? $data['type'] : self::DEFAULT_TYPE;

                    // El receptor està connectat al server
                    if ($receiver) {
                        echo "Usuario conectado...";

                        $message['data'] = $data['data'];
                        $message['type'] = $data['type'] ? $data['type'] : self::DEFAULT_TYPE;
                        $message['sender'] = $user->id;
                        $this->send($receiver, json_encode($message));
                    } else {
                        //TODO: Enviar el missatge al sistema de notificacions timed, per recuperar-las quan es connecti
                        echo "No s'ha trobat al user " . $data['receiverId'] . ". Guardant les dades al blackboard\n";
                        $this->notifyModel->notifyTo($data['data'], $data['receiverId'], $message['type'], $user->id); // ALERTA: comprovar quina es la diferència entre quest i notifyTo

                        $message['data'] = 'L\'usuari no es troba connectat en aquests moments, s\'ha guardat el missatge';
                        $message['type'] = self::WARNING_TYPE;
                        $message['sender'] = self::DEFAULT_SENDER;

                        $this->send($user, json_encode($message));

                    }
                    break;

                case self::NOTIFY_TO_FROM:
                    // TODO: Adaptar el format al que s'envia com a resposta JSON
                    echo "Ejecutando NOTIFY_TO_FROM: " . $data['receiverId'] . " data: " . $data['data'] . "\n";


                    $receiver = $this->getUserById($data['receiverId']);

                    $message = [];
                    // El receptor està connectat al server
                    if ($receiver) {
                        echo "Usuario conectado...";

                        $message['data'] = $data['data'];
                        $message['type'] = $data['type'] ? $data['type'] : self::DEFAULT_TYPE;
                        $message['sender'] = $user->id;
                        $this->send($receiver, json_encode($message));
                    } else {
                        //TODO: Enviar el missatge al sistema de notificacions timed, per recuperar-las quan es connecti
                        echo "No s'ha trobat al user " . $data['receiverId'] . ". Guardant les dades al blackboard\n";
                        $this->notifyModel->notifyMessageToFrom($data['data'], $data['receiverId'], $user->id); // ALERTA: comprovar quina es la diferència entre quest i notifyTo

                        $message['data'] = 'L\'usuari no es troba connectat en aquests moments, s\'ha guardat el missatge';
                        $message['type'] = self::WARNING_TYPE;
                        $message['sender'] = self::DEFAULT_SENDER;

                        $this->send($user, json_encode($message));

                    }

                    break;

                default:
                    echo "no s'ha reconegut el command:" . $data['command'] . "\n";

            }


            // Envia el missatge al usuari (prova, s'hauria de fer un switch segons el command
//            $this->send($user, $message);
        }


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

    protected function startServer($addr, $port, $bufferLength)
    {
        parent::startServer($addr, $port, $bufferLength); // TODO: Change the autogenerated stub

        // Instanciem un DokuNotifyModel
        $this->notifyModel = new WebsocketNotifyModel(new BasicPersistenceEngine());

    }


    protected function send($user, $message)
    {


        if ($user->handshake) {
            $message = $this->frame($message, $user);
            @socket_write($user->socket, $message, strlen($message));
        } else {
            // User has not yet performed their handshake.  Store for sending later.
            $holdingMessage = array('user' => $user, 'message' => $message);
            $this->heldMessages[] = $holdingMessage;
        }

//        var_dump($message);
    }

}


