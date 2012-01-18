#!/usr/bin/env php
<?php
/**
 * This the server launcher.
 * It holds configuration for starting the instance of the taskserver.
 * @package WebTaskServer
 * @version 0.1
 * @author Florin Patan
 * @copyright Florin Patan
 * @todo REMOVE debug code.
 */

$debugMode = true;

// Get our servers code
include_once ('./WebTaskServer.php');

function rLog($msg)
{
    $msg = "[" . date('Y-m-d H:i:s') . "] " . $msg;
    print($msg . "\n");
}

// spawn our server
$server = new WebTaskServer($debugMode, LOG_INFO);

// Run the server
$server->run();
