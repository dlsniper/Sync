<?php

/**
 * Abstract class of the job servers
 * @package TaskServer
 * @version 0.3
 * @author Florin Patan
 * @copyright Florin Patan
 */
abstract class JobServer
{

    /**
     * Path to the php exec
     * @var string
     */
    protected $phpPath = '/usr/local/bin/php';

    /**
     * Various jobs statuses
     * @const string
     */
    const UNDEFINED = -1;
    const RUNNING = 10;
    const FINISHED = 20;

    /**
     * This holds our job pipes
     * @var    array   Read, Write, Error pipes
     */
    public $pipes;
    private $pid;
    /**
     * This holds our job server options
     * @var jobServerOption    Options of our job
     */
    public static $serverOption;
    /**
     * This holds information about our job server
     * @var    joServerInfo    Information about our job
     */
    public $serverInfo;
    /**
     * cURL related options
     * @var    array   cURL related options
     */
    protected static $curlOptions = array(CURLOPT_RETURNTRANSFER => true, // return web page
                                          CURLOPT_HEADER => false, // don't return headers
                                          CURLOPT_FOLLOWLOCATION => true, // follow redirects
                                          CURLOPT_ENCODING => "", // handle all encodings
                                          CURLOPT_USERAGENT => "Opera/9.80 (Windows NT 6.1; U; en) Presto/2.8.131 Version/11.10", // who am i but a ghost
                                          CURLOPT_AUTOREFERER => true, // set referer on redirect
                                          CURLOPT_CONNECTTIMEOUT => 10, // timeout on connect
                                          CURLOPT_TIMEOUT => 10, // timeout on response
                                          CURLOPT_MAXREDIRS => 10); // stop after 10 redirects
    //CURLOPT_ENCODING       => "deflate, gzip, x-gzip, identity, *;q=0", //
    /**
     * Debug mode
     * @var boolean
     */
    private $debugMode = false;

    /**
     * Add a new job to the que
     * @param  array   Options of the job
     * @param  boolean Should we crash if we couldn't add the job or just send the error?
     * @return int     Id of the inserted job
     */
    abstract static function addJob($options, $fatalIfError);

    /**
     * Process our job
     * @param  int Our job id
     */
    abstract public static function processJob($jobId);

    /**
     * Fetch a URL
     * @param  string  URL
     * @return string  Page contents
     */
    protected static function fetchURL($url)
    {
        // cURL magic
        $ch = curl_init($url);
        curl_setopt_array($ch, self::$curlOptions);
        $content = curl_exec($ch);
        $err = curl_errno($ch);
        $errmsg = curl_error($ch);
        $header = curl_getinfo($ch);
        curl_close($ch);

        // Assign the values we are going to return
        $result['errno'] = $err;
        $result['errmsg'] = $errmsg;
        $result['header'] = $header;
        $result['content'] = $content;

        // Return the results
        return $result;
    }

    /**
     * Start our job
     * @param     int                The id of our job
     * @return    jobServerInfo    Information about our job
     */
    protected function startJob()
    {
        // We are going to use this alot
        $jobId = $this->serverInfo->id;

        // Set the pipes for our job
        $pipesDescriptor = array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => array("pipe", "w"));

        // Unset the previous pipes if any
        unset($this->pipes[0], $this->pipes[1], $this->pipes[2], $this->pipes);

        // Set the current working directory
        $cwd = __DIR__;

        // Send out ENV to the child script
        $env = $_ENV;

        // pass information to our job
        $env['jobId'] = $jobId;
        $env['jobType'] = self::$serverOption->jobType;
        $env['jobDebug'] = (string)$this->debugMode;

        // Create our process
        $this->serverInfo->pid = proc_open($this->phpPath . ' ' . __DIR__ . '/job.php', $pipesDescriptor, $this->pipes, $cwd, $env);

        // If we couldn't spawn a new process...
        if (!is_resource($this->serverInfo->pid)) {
            // Crash and burn
            TaskServer::error('Unable to start job ' . $jobId, 'Unable to start a new job', true);
        }

        // make the job able to be run in background
        stream_set_blocking($this->pipes[0], 0);
        stream_set_blocking($this->pipes[1], 0);
        stream_set_blocking($this->pipes[2], 0);

        // We started now
        $this->serverInfo->started = microtime(true);

        // Set the run count for this job
        $this->serverInfo->retryCount++;
    }

    /**
     * Check if our job is still running or not
     * @return boolean Status of our job
     */
    protected function isRunning()
    {
        $status = @proc_get_status($this->serverInfo->pid);

        return $status['running'];
    }

    /**
     * Get the output of the job
     * @return string  Output
     */
    protected function getOutput()
    {
        $result = array();

        foreach ($this->pipes as $pipe) {
            $result[] = stream_get_contents($pipe);
        }

        return $result;
    }

    /**
     * Close the job since the server says so
     * @param  boolean Kill the job
     * @return array   Exit code, Output pipe, Error pipe
     */
    protected function closeJob($kill = false)
    {
        // Get contents of our pipes
        $writePipe = $this->pipes[1];
        $errorPipe = $this->pipes[2];

        // Close our pipes
        foreach ($this->pipes as $pipe)
            fclose($pipe);

        // Get the status of our process
        $status = proc_get_status($this->serverInfo->pid);

        // Kill our process
        exec('kill ' . $status['pid'] . ' 2>/dev/null >&- >/dev/null');

        // Get the exit code of our application
        $returnValue = proc_close($this->serverInfo->pid);

        // Should we kill our task in case it is still open?
        if ($kill) {
            // Sleep a bit before we get to terminating part
            usleep(2000);

            // First try it nicely
            @posix_kill($this->serverInfo->pid, SIGTERM);

            // Sleep a bit before killing stuff (2000 microseconds)
            usleep(2000);

            // If at first we don't succeed then let's bbq the sucker
            @posix_kill($this->serverInfo->pid, SIGKILL);
        }

        // Mark the job as stopped
        $this->serverInfo->stopped = microtime(true);
        $this->serverInfo->status = 'stopped';

        // Send our info back to the task server
        return array('writePipe' => $writePipe, 'errorPipe' => $errorPipe, 'returnValue' => $returnValue);
    }

    /**
     * Update the status of the job across the server and internally
     * @param string $status
     */
    protected function updateStatus($status = JobServer::UNDEFINED)
    {
        $this->serverInfo->status = $status;
        TaskServer::changeJobStatus($this->serverInfo->id, $status);
    }

    /**
     * Create our job
     * @param  string          Job type
     * @param  int             Job ID
     * @param  boolean         Debug mode?
     * @return JobServerInfo   Return the job object that is going to be launched
     */
    public function __construct($jobType, $jobId, $debugMode = false)
    {

        // Are we in debug mode?
        $this->debugMode = $debugMode;

        // We are what we are
        self::$job = $jobType;

        // Init our job details
        $this->serverOption = $jobType::getJobServerDetails();

        // Create a new server info object
        $this->serverInfo = new jobServerInfo($jobId);

        // Start the job and get it's process id
        $this->startJob();

        return $this;
    }

    /**
     * Clean our mess
     */
    public function __destruct()
    {
        // If our job is still running the close it
        if (is_resource($this->serverInfo->pid)) {
            $this->closeJob(true);
        }

        unset($this->debugMode);
        unset($this->pipes);
        unset($this->serverOption);
        unset($this->serverInfo);
    }

}

