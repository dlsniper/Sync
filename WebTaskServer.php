<?php

/**
 * This is my web based task server.
 * Behold, dragons ahead :)
 * @package WebTaskServer
 * @version 0.1
 * @author Florin Patan
 * @copyright Florin Patan
 * @todo Implement extended server status
 */
class WebTaskServer
{

    /**
     * On what interface should we listen to?
     * @var String
     */
    private $host = 0;
    /**
     * On what port should we listen to?
     * @var    int
     */
    private $port = 8088;
    /**
     * Number of maximum clients
     * @var int
     */
    private $maxClients = 20;
    /**
     * Clients that are connected to this server
     * @var Array
     */
    private $client = array();
    /**
     * Socket on which the server is bound to
     * @var resource
     */
    private $sock;
    /**
     * The server signature when replying to the socket requests
     * @var String
     */
    private $serverSignature = "Helix App Server";

    /**
     * Print the response page to the client of the webserver
     * @param    String    Type of the response, html or json
     * @param    String    Title of the page if the type is text/html
     * @param    String    Response content
     */
    private function printPage($pageType, $pageTitle, $pageContent)
    {
        if ($pageType == "json") {
            // Set the page type of the response
            $pageType = "application/json";
            $page = "";
        } else {
            // Set the page type of the response
            $pageType = "text/html";

            // Start the page rendering
            $page .= "<!DOCTYPE html>\r\n";
            $page .= "<html>\r\n";
            $page .= "    <head>\r\n";
            $page .= "        <meta name=\"language\" content=\"en\" />\r\n";
            $page .= "        <meta charset=\"utf-8\"/>\r\n";
            $page .= "        <title>" . $pageTitle . "</title>\r\n";

            // Since I don't know how to send files yet...
            $page .= "        <link href=\"http://resources.florinpatan.ro/favicon.ico\" rel=\"shortcut icon\" type=\"image/x-icon\" />\r\n";

            $page .= "    </head>\r\n";
            $page .= "    <body>\r\n";
            $page .= "        <div id=\"content\">\r\n";

            // Dump the content
            $page .= $pageContent . "\r\n";

            // Then finish the page rendering
            $page .= "        </div>\r\n";
            $page .= "    </body>\r\n";
            $page .= "</html>\r\n";
        }


        // These are the headers
        // Response as a modern webserver that we are :)
        $cdmp = "HTTP/1.1 200 OK\r\n";

        // Date when the page was generated
        $cdmp .= "Date: " . date(DATE_RFC822) . "\r\n";

        // Mark ourselves in the history and confuse noobs
        $cdmp .= "Server: " . $this->serverSignature . "\r\n";

        // We accept all the encodings as we don't really care about them
        $cdmp .= "Vary: Accept-Encoding\r\n";

        // Send the length of the response
        $cdmp .= "Content-Length: " . strlen($page) . "\r\n";

        // Close the connection to the server automatically
        //(I don't know how to make persistent connections in PHP yet :D)
        $cdmp .= "Connection: close\r\n";

        // Send the type of the response
        $cdmp .= "Content-Type: " . $pageType . "\r\n";

        // Check the type of the response in order to send the appropiate expires headers
        if ($pageType == "application/json") {
            // If the page is json encoded the response won't be cached
            $cdmp .= "Cache-Control: no-cache, must-revalidate\r\n";
            $cdmp .= "Expires: Mon, 26 Jul 1997 05:00:00 GMT\r\n";
        } else {
            // Tell the client to cache stuff otherwise
            $cdmp .= "Cache-Control: private\r\n";

            // Cache the page for 15 minutes
            $cdmp .= "Expires: " . date(DATE_RFC822, time() + (60 * 15)) . "\r\n";
        }

        // Let's be compliant on how to do stuff
        $cdmp .= "\r\n";

        // Send the actual response
        $cdmp .= $page;

        // Return the page to the client
        return $cdmp;
    }

    /**
     * Get the status of the TaskServer
     * @return string
     */
    private function getTaskServerStatus()
    {
        $result = '';

        $result .= '<pre>';

        $result .= 'Task Server Running Processes<br/>';

        $res = false;

        $semId = sem_get(8088);
        if ($semId) {
            if (sem_acquire($semId)) {
                $shmId = shm_attach(8088);
                if (shm_has_var($shmId, 8088)) {
                    $res = shm_get_var($shmId, 8088);
                }

                shm_detach($shmId);
            }

            sem_release($semId);
        }

        if ($res !== false) {
            $result .= '<br/><br/>';

            $result .= "Server start time:   " . date(DATE_RFC2822, $res['__info']['startTime']) . "<br/>";
            $result .= "Server current time: " . date(DATE_RFC2822, time()) . "<br/>";

            $res['__info'] = null;
            unset($res['__info']);

            $result .= '<br/><br/>';

            foreach ($res as $jobType => $jobs) {
                $result .= ucfirst($jobType) . ': ' . implode(' ', $jobs) . '<br/>';
            }

            $result .= '<br/><br/>';

            $result .= 'Scoreboard Key:<br/>';
            $result .= '"." Open slot with no process<br/>';
            $result .= '"U" Undefined status<br/>';
            $result .= '"W" Working process<br/>';
            $result .= '"F" Finishing process<br/>';
            $result .= '"E" Error in reading process stastus<br/>';

            $result .= '<br/><br/>';

            $result .= 'Extended info:<br/>';
            $result .= 'Not implemented at the moment<br/>';

            $result .= '<br/><br/>';
        } else {
            $result .= 'Couldn\'t fetch the job status, please try again.<br/>';
        }

        $result .= '</pre>';

        return $result;
    }

    /**
     * Run the webserver and the taskserver
     */
    public function run()
    {
        // The time of the current sleep (task sever related)
        $sleepTime = 0;

        // Time since we started sleeping (task server related)
        $sleepStart = 0;

        // Server loop
        while (true) {
            // Block the socket while we are waiting for clients
            socket_set_block($this->sock);

            // Setup clients listen socket for reading
            $read[0] = $this->sock;
            for ($i = 0; $i < $this->maxClients; $i++) {
                if (isset($this->client[$i]) && isset($this->client[$i]['sock']) && $this->client[$i]['sock'] != null) {
                    $read[$i + 1] = $this->client[$i]['sock'];
                }

            }

            // Set up a blocking call to socket_select()
            $ready = socket_select($read, $write = NULL, $except = NULL, $tv_sec = NULL);

            // If a new connection is being made add it to the clients array
            if (in_array($this->sock, $read)) {
                for ($i = 0; $i < $this->maxClients; $i++) {
                    if (!isset($this->client[$i]['sock'])) {
                        if (($this->client[$i]['sock'] = socket_accept($this->sock)) < 0) {
                            rLog("socket_accept() failed: " . socket_strerror($this->client[$i]['sock']));
                        } else {
                            rLog("Client #" . $i . " connected");
                        }
                        break;
                    }
                    //elseif (isset($this->client[$i]) && $i == $this->maxClients - 1) {
                    //    rLog("Too many clients");
                    //}
                }

                if (--$ready <= 0)
                    continue;
            }

            for ($i = 0; $i < $this->maxClients; $i++) {
                if (isset($this->client[$i]) && in_array($this->client[$i]['sock'], $read)) {
                    $input = socket_read($this->client[$i]['sock'], 1024);

                    if ($input == null) {
                        unset($this->client[$i]);
                    }

                    $n = trim($input);

                    $headers = explode("\r\n", $input);

                    //See if we have a valid GET request from the client
                    if (stripos($headers[0], "get") !== false) {
                        // Let's see what the request wants
                        preg_match('/GET (.*) HTTP\/\d+\.\d+/i', $headers[0], $path);

                        $path = $path[1];

                        // @todo Improve the request detection using switch(true)
                        if ($path == "/term") {
                            socket_close($this->sock);
                            rLog("Terminated server (requested by client #" . $i . ")");
                            exit();
                        } elseif ($path == "/status") {
                            $status = $this->getTaskServerStatus();

                            socket_write($this->client[$i]['sock'], $this->printPage("html", "Status page", $status) . chr(0));
                        }

                        // Disconnect the client as we don't have persisten connection implemented yet
                        for ($p = 0; $p < count($this->client); $p++) {
                            socket_write($this->client[$p]['sock'], "DISC " . $i . chr(0));
                        }
                        socket_close($this->client[$i]['sock']);
                        unset($this->client[$i]['sock']);
                        rLog("Disconnected(2) client #" . $i);

                    } else {
                        // else we quit the client
                        socket_close($this->client[$i]['sock']);
                        unset($this->client[$i]['sock']);
                        rLog("Disconnected(2) client #" . $i);
                        for ($p = 0; $p < count($this->client); $p++) {
                            socket_write($this->client[$p]['sock'], "DISC " . $i . chr(0));
                        }
                    }

                } elseif (isset($this->client[$i])) {
                    // Disconnect clients
                    if ($this->client[$i]['sock'] != null) {
                        //Close the socket
                        socket_close($this->client[$i]['sock']);
                        unset($this->client[$i]);
                        rLog("Disconnected(1) client #" . $i);
                    }
                }
            }
        }
    }

    /**
     * Create our Task Server
     * @param    boolean    If we are running in debug mode or not
     */
    public function __construct($debug = false)
    {
        // Start the web server
        // No timeouts, flush content immediatly
        set_time_limit(0);
        ob_implicit_flush();

        // Init the clients
        //        for($i=0; $i<$this->maxClients; $i++)
        //            $this->client[$i]['sock'] = null;
        // Create socket
        $this->sock = socket_create(AF_INET, SOCK_STREAM, 0) or die("Could not create socket");

        // Bind to socket
        socket_bind($this->sock, $this->host, $this->port) or die("Could not bind to socket");

        // Start listening
        socket_listen($this->sock) or die("Could not set up socket listener");

        // Start the task server
        //        parent::__construct($debug);
    }

    /**
     * Destruct the web server when we are shutting down
     * @see TaskServer::__destruct()
     */
    public function __destruct()
    {
        for ($i = 0; $i < $this->maxClients; $i++) {
            // Disconnect clients
            if ($this->client[$i]['sock'] != null) {
                //Close the socket
                socket_close($this->client[$i]['sock']);
                unset($this->client[$i]);
                rLog("Disconnected(1) client #" . $i);
            }
        }

        // Close the master socket
        socket_close($this->sock);
    }

}