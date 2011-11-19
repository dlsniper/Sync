#!/usr/local/bin/php
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
/*
  INSERT INTO `florinp_sync`.`tasks` (`tasks_id`, `tasks_job_type`, `tasks_option`, `tasks_created`, `tasks_execute`, `tasks_status`, `tasks_retry_count`) VALUES (NULL, 'playlist', 'a:2:{s:8:"location";s:16:"ADD36670A6546824";s:8:"position";s:1:"0";}', '2010-11-01 00:00:00', '0000-00-00 00:00:00', 'new', '0'), (NULL, 'playlist', 'a:2:{s:8:"location";s:16:"ADD36670A6546824";s:8:"position";s:1:"0";}', '2010-11-03 00:00:00', '0000-00-00 00:00:00', 'new', '0');
  INSERT INTO `florinp_sync`.`tasks` (`tasks_id`, `tasks_job_type`, `tasks_option`, `tasks_created`, `tasks_execute`, `tasks_status`, `tasks_retry_count`) VALUES (NULL, 'playlist', 'a:2:{s:8:"location";s:16:"ADD36670A6546824";s:8:"position";s:1:"0";}', '2010-11-01 00:00:00', '0000-00-00 00:00:00', 'new', '0'), (NULL, 'playlist', 'a:2:{s:8:"location";s:16:"ADD36670A6546824";s:8:"position";s:1:"0";}', '2010-11-03 00:00:00', '0000-00-00 00:00:00', 'new', '0');
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
