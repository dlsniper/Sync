<?php

/**
 * This holds job informations
 * @package	TaskServer
 * @version 0.1
 * @author Florin Patan
 * @copyright Florin Patan
 */
class jobServerInfo {

    /**
     * This is the ID of our job
     */
    public $id;
    /**
     * This is the PID of our job
     * @var    int     Process ID
     */
    public $pid;
    /**
     * This is the timestamp of when we've started
     * @var    float   Timestamp
     */
    public $started;
    /**
     * Time when the job has been stopped
     * @var float	Timestamp
     */
    public $stopped;
    /**
     * This is the variable that holds our status.
     * The status can be:
     * running	-> the job is running
     * stopped	-> the job has been stopped by the server
     * finished -> the job has gracefully finished its job
     * @var string
     */
    public $status = 'running';
    /**
     * How many times did we've retried our job
     * @var    int     Retry count
     */
    public $retryCount;

    /**
     * This will be returned when a new job is created so we have a consistent interface across task types
     * @param  int     The id of our task
     * @param  int     The process id of our task
     * @param  int     The number of times we've tried to do our job
     */
    public function __construct($id) {
        // The ID of the job
        $this->id = $id;

        // When the job was stoppped
        $this->stopped = - 1;

        // How many runs did it have?
        // We start with -1 to account for the initial run since the option is
        // called retryCount not runCount
        $this->retryCount = -1;
    }

    /**
     * Clean up some mess that we've made
     */
    public function __destruct() {
        unset($this->id);
        unset($this->pid);
        unset($this->started);
        unset($this->stopped);
        unset($this->retryCount);
        unset($this->status);
    }

}
