<?php

/**
 * Demo for starting a job manualy
 * @package    TaskServer
 * @version 0.1
 * @author Florin Patan
 * @copyright Florin Patan
 */
set_include_path('./Jobs/');
spl_autoload_extensions('.php');
spl_autoload_register();

$job = new Playlist(1, true);
