<?php

/**
 * This the server launcher.
 * It holds configuration for starting the instance of the taskserver.
 * @package TaskServer
 * @version 0.1
 * @author Florin Patan
 * @copyright Florin Patan
 * @todo Implement jobs auto upgrade feature.
 * @todo Implement autoRegisterJobType call when the task server will support it.
 * @todo Optimize.
 * @todo REMOVE debug code.
 */
/*
  INSERT INTO `florinp_sync`.`tasks` (`tasks_id`, `tasks_job_type`, `tasks_option`, `tasks_created`, `tasks_execute`, `tasks_status`, `tasks_retry_count`) VALUES (NULL, 'playlist', 'a:2:{s:8:"location";s:16:"ADD36670A6546824";s:8:"position";s:1:"0";}', '2010-11-01 00:00:00', '0000-00-00 00:00:00', 'new', '0'), (NULL, 'playlist', 'a:2:{s:8:"location";s:16:"ADD36670A6546824";s:8:"position";s:1:"0";}', '2010-11-03 00:00:00', '0000-00-00 00:00:00', 'new', '0');
  INSERT INTO `florinp_sync`.`tasks` (`tasks_id`, `tasks_job_type`, `tasks_option`, `tasks_created`, `tasks_execute`, `tasks_status`, `tasks_retry_count`) VALUES (NULL, 'playlist', 'a:2:{s:8:"location";s:16:"ADD36670A6546824";s:8:"position";s:1:"0";}', '2010-11-01 00:00:00', '0000-00-00 00:00:00', 'new', '0'), (NULL, 'playlist', 'a:2:{s:8:"location";s:16:"ADD36670A6546824";s:8:"position";s:1:"0";}', '2010-11-03 00:00:00', '0000-00-00 00:00:00', 'new', '0');
 */

$debugMode = true;

// get our server code
include_once ('./TaskServer.php');

// spawn our server
$server = new TaskServer($debugMode);

// register jobs handler
$server->registerJobType('Playlist');
$server->registerJobType('Demo');

/**
 * TODO
 * Implement the new model of job server for these jobs as well.
 * $server->registerJobType('Video');
 * $server->registerJobType('Sendmail');
 */
while (true) {
    /**
     * TODO
     * Make server reregister the jobs when they change
     * $server->checkJobsUpdates();
     * See php.net/runkit_import function
     */
    /**
     * TODO
     * Make the server self-aware of new job types
     * $server->autoRegisterJobType();
     */
    $server->processJobs();

    // sleep a bit
    $sleepTime = $server->getSleepPeriod();
    if ($debugMode) {
        echo "Going to sleep for: " . $sleepTime . "\n";
        flush();

        for ($i = 1; $i <= $sleepTime; $i++) {
            echo $i . " ";
            flush();
            sleep(1);
        }
        echo "\n";
    } else {
        sleep($sleepTime);
    }
}
