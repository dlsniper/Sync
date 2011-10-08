<?php

/**
 * This is how a job is launched
 * @package	TaskServer
 * @version 0.1
 * @author Florin Patan
 * @copyright Florin Patan
 */
// we accept the ticks as they happen
declare(ticks = 1);

// We can run forever
set_time_limit(0);

// Allow the autoinclude to work as we want
set_include_path('.:./Jobs/');
spl_autoload_extensions('.php');
spl_autoload_register();

// Read the params
$jobId = getenv('jobId');
$jobType = getenv('jobType');
$debugMode = getenv('jobDebug') == 'true';

// Are we in debug mode?
TaskServer::$debugMode = $debugMode;

// Make our job be instantiable
$jobType = ucfirst($jobType);

// Do the job
$jobType::processJob($jobId);
