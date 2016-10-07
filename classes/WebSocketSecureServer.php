<?php

require_once('WebSocketServer.php');

abstract class WebSocketSecureServer extends WebSocketServer{

    protected function startServer($addr, $port, $bufferLength)
    {
        $this->startSecureServer($addr, $port);
        return;

        $this->maxBufferSize = $bufferLength;
        $this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP) or die("Failed: socket_create()");
        socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1) or die("Failed: socket_option()");

        if (@socket_bind($this->master, $addr, $port) /*or die("Failed: socket_bind()")*/) {
            socket_listen($this->master, 20) or die("Failed: socket_listen()");
            $this->sockets['m'] = $this->master;
            $this->stdout("Server started\nListening on: $addr:$port\nMaster socket: " . $this->master);
            file_put_contents(self::PID_FILE, getmypid());

        } else {
            $errorMessage = posix_strerror(socket_last_error($this->master));
            $this->logError($errorMessage);

            // OPCIO 1: No es fa res
//              exit("Error: " . $errorMessage . "\n" . ' address: ' . $addr. ':' .$port);

            // OPCIO 2: Es mata el process (si es PHP i està escoltant pel port) i es reinicia el servidor
            self::killRunningServerByPort($port);
            $this->startServer($addr, $port, $bufferLength);
        }
    }



    /**
     * Main processing loop
     */
    public function run()
    {
        $this->runSecure();
        return;


        while (true) {
            if (empty($this->sockets)) {
                $this->sockets['m'] = $this->master;
            }
            $read = $this->sockets;
            $write = $except = null;
            $this->_tick();
            $this->tick();
            @socket_select($read, $write, $except, 1);
            foreach ($read as $socket) {
                if ($socket == $this->master) {
                    $client = socket_accept($socket);
                    if ($client < 0) {
                        $this->stderr("Failed: socket_accept()");
                        continue;
                    } else {
                        $this->connect($client);
                        $this->stdout("Client connected. " . $client);
                    }
                } else {
                    $numBytes = @socket_recv($socket, $buffer, $this->maxBufferSize, 0);
                    if ($numBytes === false) {
                        $sockErrNo = socket_last_error($socket);
                        switch ($sockErrNo) {
                            case 102: // ENETRESET    -- Network dropped connection because of reset
                            case 103: // ECONNABORTED -- Software caused connection abort
                            case 104: // ECONNRESET   -- Connection reset by peer
                            case 108: // ESHUTDOWN    -- Cannot send after transport endpoint shutdown -- probably more of an error on our part, if we're trying to write after the socket is closed.  Probably not a critical error, though.
                            case 110: // ETIMEDOUT    -- Connection timed out
                            case 111: // ECONNREFUSED -- Connection refused -- We shouldn't see this one, since we're listening... Still not a critical error.
                            case 112: // EHOSTDOWN    -- Host is down -- Again, we shouldn't see this, and again, not critical because it's just one connection and we still want to listen to/for others.
                            case 113: // EHOSTUNREACH -- No route to host
                            case 121: // EREMOTEIO    -- Rempte I/O error -- Their hard drive just blew up.
                            case 125: // ECANCELED    -- Operation canceled

                                $this->stderr("Unusual disconnect on socket " . $socket);
                                $this->disconnect($socket, true, $sockErrNo); // disconnect before clearing error, in case someone with their own implementation wants to check for error conditions on the socket.
                                break;
                            default:

                                $this->stderr('Socket error: ' . socket_strerror($sockErrNo));
                        }

                    } elseif ($numBytes == 0) {
                        $this->disconnect($socket);
                        $this->stderr("Client disconnected. TCP connection lost: " . $socket);
                    } else {
                        $user = $this->getUserBySocket($socket);
                        if (!$user->handshake) {
                            $tmp = str_replace("\r", '', $buffer);
                            if (strpos($tmp, "\n\n") === false) {
                                continue; // If the client has not finished sending the header, then wait before sending our upgrade response.
                            }
                            $this->doHandshake($user, $buffer);
                        } else {
                            //split packet into frame and send it to deframe
                            $this->split_packet($numBytes, $buffer, $user);
                        }
                    }
                }
            }

        }
    }




    // ALERTA[Xavi] Nou codi pel servidor segur

    protected function startSecureServer($ip, $port) {
//        $ip="127.0.0.1";               //Set the TCP IP Address to listen on
//        $port="8099";                  //Set the TCP Port to listen on
        $pem_passphrase = "canviarperungenerataleatoriament";   //Set a password here TODO[Xavi] Canviar per un password generat aleatoriament
        $pem_file = "filename.pem";    //Set a path/filename for the PEM SSL Certificate which will be created.

//The following array of data is needed to generate the SSL Cert
        $pem_dn = array(
            "countryName" => "ES",                 //Set your country name
            "stateOrProvinceName" => "Barcelona",      //Set your state or province name
            "localityName" => "Barcelona",        //Ser your city name
            "organizationName" => "IOC",  //Set your company name
            "organizationalUnitName" => "Development", //Set your department name
            "commonName" => "ioc.xtec.cat",  //Set your full hostname.
            "emailAddress" => "email@example.com"  //Set your email address
        );

//create ssl cert for this scripts life.
        echo "Creating SSL Cert\n";
        $this->createSSLCert($pem_file, $pem_passphrase, $pem_dn);

//setup and listen to a tcp IP/port, returning the socket stream
        echo "Listening to {$ip}:{$port} for connections\n";
        $this->master= $this->setupTcpStreamServer($pem_file, $pem_passphrase, $ip, $port);

        file_put_contents(self::PID_FILE, getmypid());
    }

    protected function runSecure() {
        //enter a loop until an exit command is received.

        while(true) {

            //Accept any new connections

            $forkedSocket = stream_socket_accept($this->master, "-1", $remoteIp);

            if ($forkedSocket) {
                echo "New connection from $remoteIp\n";

                //start SSL on the connection
                stream_set_blocking ($forkedSocket, true); // block the connection until SSL is done.
//            stream_socket_enable_crypto($forkedSocket, true, STREAM_CRYPTO_METHOD_SSLv3_SERVER);
                stream_socket_enable_crypto($forkedSocket, true, 9);

                //Read the command from the client. This will read 8192 bytes of data, If you need to read more you may need to increase this. However some systems will fragment the command over 8192 anyway, so you would instead need to write a loop waiting for the command input to end before proceeding.
                $command = fread($forkedSocket, 8192);

                //unblock connection
                stream_set_blocking ($forkedSocket, false);

                fwrite($forkedSocket, 'Connectat...');
                //run a switch on the command to determine what we need to do
                /*
                switch($command) {
                    //exit command will cause this script to quit out
                    CASE "exit";
                        $exit=true;
                        echo "exit command received \n";
                        break;

                    //hi command
                    CASE "hi";
                        //write back to the client a response.
                        fwrite($forkedSocket, "Hello {$remoteIp}. This is our $i command run!");
                        $i++;

                        echo "hi command received \n";
                        break;
                }
                */

                echo $command . "\n";

                //close the connection to the client
//                fclose($forkedSocket);

            }



        }

    }

private function createSSLCert($pem_file, $pem_passphrase, $pem_dn) {
    //create ssl cert for this scripts life.

    //Create private key
    $privkey = openssl_pkey_new();

    //Create and sign CSR
    $cert    = openssl_csr_new($pem_dn, $privkey);
    $cert    = openssl_csr_sign($cert, null, $privkey, 365);

    //Generate PEM file
    $pem = array();
    openssl_x509_export($cert, $pem[0]);
    openssl_pkey_export($privkey, $pem[1], $pem_passphrase);
    $pem = implode($pem);

    //Save PEM file
    file_put_contents($pem_file, $pem);
    chmod($pem_file, 0600);
}

private function setupTcpStreamServer($pem_file, $pem_passphrase, $ip, $port) {
    //setup and listen to a tcp IP/port, returning the socket stream

    //create a stream context for our SSL settings
    $context = stream_context_create();

    //Setup the SSL Options
    stream_context_set_option($context, 'ssl', 'local_cert', $pem_file);  // Our SSL Cert in PEM format
    stream_context_set_option($context, 'ssl', 'passphrase', $pem_passphrase); // Private key Password
    stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
    stream_context_set_option($context, 'ssl', 'verify_peer', false);

    //create a stream socket on IP:Port
    $socket = stream_socket_server("tcp://{$ip}:{$port}", $errno, $errstr, STREAM_SERVER_BIND|STREAM_SERVER_LISTEN, $context);
    stream_socket_enable_crypto($socket, false);



    return $socket;
}


}
