<?php

/**
 * Class to implement sending mails with task server
 * @package TaskServer
 * @version 0.1
 * @author Florin Patan
 * @copyright Florin Patan
 */
class Sendmail extends JobServer
{

    /**
     * This holds our options for the job
     * @var Options for our job server
     */
    private $options;
    /**
     * This holds the extra options for the job
     * @var Extra options of our job
     */
    private $extra;
    /**
     * This holds our job server options
     * @var jobServerOption
     */
    public static $serverOption;

    /**
     * This function will start the job
     */
    public function execute()
    {
        return true;
    }

    /**
     * This will help us getting the details for each job server
     * @param  int     Number of jobs that can be run at the same time on the server
     * @param  int     How much time should a task be runing for before checking it's state
     * @param  int     How many times should we retry the current job before aborting it
     * @param  int     How much should we be waiting for until we retry the job
     * @return jobServerOption
     */
    public static function getJobServerDetails($parallelThreads = 5, $jobTimeout = 60, $retryCount = 3, $retryPause = 120)
    {
        self::$serverOption = new jobServerOption('mail', $parallelThreads, $jobTimeout, $retryCount, $retryPause);
        return self::$serverOption;
    }

    /**
     * Create our job
     * @param   $jobOptions Options of the job that is going to be processed
     * @param   $jobExtra   Extra options for the job
     * @return  JobServer   Return the job object that is going to be launched
     */
    public function __construct($jobOptions, $jobExtra = null)
    {
        $this->options = $jobOptions;
        $this->extra = $jobExtra;
    }

}
