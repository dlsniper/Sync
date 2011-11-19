<?php

/**
 * This holds job information
 * @package TaskServer
 * @version 0.1
 * @author Florin Patan
 * @copyright Florin Patan
 */
class jobServerOption
{

    /**
     * Type of the job
     * @var string
     */
    public $jobType;
    /**
     * Number of jobs that can be run at the same time on the server
     * @var int
     */
    public $parallelThreads;
    /**
     * How much time should a task be runing for before checking it's state
     * @var int
     */
    public $jobTimeout;
    /**
     * How many times should we retry the current job before aborting it
     * @var int
     */
    public $retryCount;
    /**
     * How much should we be waiting for until we retry the job
     * @var int
     */
    public $retryPause;

    /**
     * This is how we construct our options
     * @param  int     Number of jobs that can be run at the same time on the server
     * @param  int     How much time should a task be runing for before checking it's state
     * @param  int     How many times should we retry the current job before aborting it
     * @param  int     How much should we be waiting for until we retry the job
     */
    public function __construct($jobType, $parallelThreads = 5, $jobTimeout = 60, $retryCount = 3, $retryPause = 120)
    {
        $this->jobType = $jobType;
        $this->parallelThreads = $parallelThreads;
        $this->jobTimeout = $jobTimeout;
        $this->retryCount = $retryCount;
        $this->retryPause = $retryPause;
    }

    /**
     * Let's have a little destruction of our own
     */
    public function __destruct()
    {
        unset($this->jobType);
        unset($this->parallelThreads);
        unset($this->jobTimeout);
        unset($this->retryCount);
        unset($this->retryPause);
    }

}
