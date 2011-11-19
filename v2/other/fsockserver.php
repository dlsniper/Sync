<?php
// PHP SOCKET SERVER
error_reporting(E_ALL);

// Configuration variables
//$host = "127.0.0.1";
$host = 0;
$port = 1234;
$max = 20;
$client = array();

// No timeouts, flush content immediatly
set_time_limit(0);
ob_implicit_flush();

// Server functions
function rLog($msg){
    return;
    $msg = "[" . date('Y-m-d H:i:s') . "] " . $msg;
    print($msg . "\n");

}

function printPage($content){
    $cdmp = "HTTP/1.1 200 OK\r\n";
    $cdmp .= "Date: Sun, 20 Mar 2011 16:01:02 GMT\r\n";
    $cdmp .= "Server: Helix App Server\r\n";
    $cdmp .= "Vary: Accept-Encoding\r\n";
    $cdmp .= "Cache-Control: private\r\n";
    $cdmp .= "Content-Length: 1055\r\n";
    $cdmp .= "Connection: close\r\n";
    $cdmp .= "Content-Type: text/html\r\n";
    $cdmp .= "\r\n";
    $cdmp .= "<!DOCTYPE html>\r\n";
    $cdmp .= "<html>\r\n";
    $cdmp .= "    <head>\r\n";
    $cdmp .= "        <meta name=\"language\" content=\"en\" />\r\n";
    $cdmp .= "        <meta charset=\"utf-8\"/>\r\n";
    $cdmp .= "        <title>Florin Patan Personal Website</title>\r\n";
    $cdmp .= "        <link href=\"http://resources.florinpatan.ro/favicon.ico\" rel=\"shortcut icon\" type=\"image/x-icon\" />\r\n";
    $cdmp .= "    </head>\r\n";
    $cdmp .= "    <body>\r\n";
    $cdmp .= "        <div id=\"content\">\r\n";
    $cdmp .= $content."\r\n";
    $cdmp .= "</div>\r\n";
    $cdmp .= "        <script type=\"text/javascript\" defer=\"defer\">\r\n";
    $cdmp .= "\r\n";
    $cdmp .= "            var _gaq = _gaq || [];\r\n";
    $cdmp .= "            _gaq.push(['_setAccount', 'UA-17697147-1']);\r\n";
    $cdmp .= "            _gaq.push(['_setDomainName', '.florinpatan.ro']);\r\n";
    $cdmp .= "            _gaq.push(['_trackPageview']);\r\n";
    $cdmp .= "\r\n";
    $cdmp .= "            (function() {\r\n";
    $cdmp .= "                var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;\r\n";
    $cdmp .= "                ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';\r\n";
    $cdmp .= "                var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);\r\n";
    $cdmp .= "            })();\r\n";
    $cdmp .= "\r\n";
    $cdmp .= "        </script>\r\n";
    $cdmp .= "    </body>\r\n";
    $cdmp .= "</html>\r\n";

    return $cdmp;
}

// Create socket
$sock = socket_create(AF_INET, SOCK_STREAM, 0) or die("[" . date('Y-m-d H:i:s') . "] Could not create socket\n");

// Bind to socket
socket_bind($sock, $host, $port) or die("[" . date('Y-m-d H:i:s') . "] Could not bind to socket\n");

// Start listening
socket_listen($sock) or die("[" . date('Y-m-d H:i:s') . "] Could not set up socket listener\n");

rLog("Server started at " . $host . ":" . $port);

// Server loop
while(true){
    socket_set_block($sock);

    // Setup clients listen socket for reading
    $read[0] = $sock;
    for($i = 0; $i < $max; $i ++){
        if($client[$i]['sock'] != null)
            $read[$i + 1] = $client[$i]['sock'];
    }

    // Set up a blocking call to socket_select()
    $ready = socket_select($read, $write = NULL, $except = NULL, $tv_sec = NULL);

    // If a new connection is being made add it to the clients array
    if(in_array($sock, $read)){
        for($i = 0; $i < $max; $i ++){
            if($client[$i]['sock'] == null){
                if(($client[$i]['sock'] = socket_accept($sock)) < 0){
                    rLog("socket_accept() failed: " . socket_strerror($client[$i]['sock']));
                } else {
                    rLog("Client #" . $i . " connected");
                }
                break;
            } elseif($i == $max - 1) {
                rLog("Too many clients");
            }
        }

        if(-- $ready <= 0)
            continue;
    }
    for($i = 0; $i < $max; $i ++){
        if(in_array($client[$i]['sock'], $read)){
            $input = socket_read($client[$i]['sock'], 1024);

            if($input == null){
                unset($client[$i]);
            }

            $n = trim($input);

            $headers = explode("\r\n", $input);

            //See if we have a valid GET request from the client
            if(stripos($headers[0], "get") !== false){
                // Let's see what the request wants
                preg_match('/GET (.*) HTTP\/\d+\.\d+/i', $headers[0], $path);

                $path = $path[1];

                if($path == "/term"){
                    socket_close($sock);
                    rLog("Terminated server (requested by client #" . $i . ")");
                    exit();
                } elseif($path == "/status") {
                    $status  = "<pre>";
                    $status .= "Server status \r\n";
                    $status .= "Used memory: " . memory_get_usage(true). "\r\n";
                    $status .= "Peak memory: " . memory_get_peak_usage(true) . "\r\n";
                    $status .= "</pre>";

                    socket_write($client[$i]['sock'], printPage($status) . chr(0));
                }

                // Disconnect the client
                socket_close($client[$i]['sock']);
                unset($client[$i]['sock']);
                rLog("Disconnected(2) client #" . $i);
                for($p = 0; $p < count($client); $p ++){
                    socket_write($client[$p]['sock'], "DISC " . $i . chr(0));
                }

                if($i == $adm){
                    $adm = - 1;
                }
            } else {
                // else we quit the client
                socket_close($client[$i]['sock']);
                unset($client[$i]['sock']);
                rLog("Disconnected(2) client #" . $i);
                for($p = 0; $p < count($client); $p ++){
                    socket_write($client[$p]['sock'], "DISC " . $i . chr(0));
                }

                if($i == $adm){
                    $adm = - 1;
                }
            }
        } else {
            if($client[$i]['sock']!=null){
                //Close the socket
                socket_close($client[$i]['sock']);
                unset($client[$i]);
                rLog("Disconnected(1) client #".$i);
            }
        }
    }
}

// Close the master sockets
socket_close($sock);
