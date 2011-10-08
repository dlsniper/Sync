<?php

// Time goes by... ticking... without this we won't be able to use the signal
// handler and we need this here to it will be
declare(ticks = 1);

/**
 * This is my task server.
 * Behold, dragons ahead :)
 * @package TaskServer
 * @version 0.1
 * @author Florin Patan
 * @copyright Florin Patan
 * @todo Implement logging for our server and jobs :)
 * @todo Implement extended server status for the webserver
 * @todo Make the error function send the mail not just exit
 * @todo Make use of the log() function to add loggin thru the whole application
 * @todo Implement the sys signals handling functions
 */
class TaskServer {

    /**
     * Define our task server pid file
     * @var    string  Path to our pid file
     */
    private static $pidFile = '/tmp/taskserver.sync.pid';
    /**
     * How many tasks have we've run until now
     * @var    int Number of tasks that have been processed until now
     */
    private $tasksProcessed = 0;
    /**
     * Store the maximum execution time of all the jobs
     * @var    int Maximum execution time from all jobs
     */
    private $maxExecutionTime = 0;
    /**
     * Store the maximum retry count of all the jobs
     * @var    int Number of maximum retries from all jobs
     */
    private $maxRetryCount = 0;
    /**
     * The maximum running threads on the server
     * @var	int	Maximum active threads
     */
    private $maxRunningThreads = 0;
    /**
     * List of available tasks
     * @var    array   List of available tasks
     */
    private $registeredServers = array();
    /**
     * Running threads stored by thread type
     * @var    array   List of running jobs
     */
    private $activeThreads = array();
    /**
     * Hold our database link
     * @var    MySQLi  Database object
     */
    private $db;
    /**
     * Is this a debug run or not?
     * @var boolean    Debug mode state
     */
    public static $debugMode = false;
    /**
     * If we've had a fatal error because we are already running the server shouldn't delete the pid file
     * @var boolean    Was this a fatal error or not?
     */
    private static $fatalError = false;
    /**
     * Path to the log file for fatal errors
     * @var string
     */
    private static $logFileFatal = '/var/log/fatal.log';
    /**
     * Path to the log file for errors
     * @var string
     */
    private static $logFileError = '/var/log/error.log';
    /**
     * Path to the log file for fatal errors
     * @var string
     */
    private static $logFileInfo = '/var/log/info.log';
    /**
     * Array containing the pointer for the log files
     * @var array
     */
    private static $logFilePointers = array();
    /**
     * Time when we've started the server
     * @var date
     */
    private $startTime;

    /**
     * Log messages to the files according to the message level
     * @param String	Message to be logged
     * @param int		Log level (see syslog() for levels
     */
    public function log($message, $level, $isFatal = false) {
        // Find out what level type are we using
        switch (true) {
            case $level == LOG_EMERG : $fp = fopen(self::$logFileFatal, 'a+');
                break;
            case $level == LOG_ALERT :
            case $level == LOG_CRIT :
            case $level == LOG_ERR : $fp = fopen(self::$logFileError, 'a+');
                break;
            case $level == LOG_WARNING :
            case $level == LOG_NOTICE :
            case $level == LOG_INFO :
            case $level == LOG_DEBUG : $fp = fopen(self::$logFileInfo, 'a+');
                break;
            default : $fp = fopen(self::$logFileInfo, 'a+');
        }

        // Add the message to the log file
        fwrite($fp, "[" . date('Y-m-d H:i:s') . "] " . trim($message) . "\n");

        // Check if it's a fatal error and we should let me know about it
        if ($isFatal) {
            // Close the file after this
            fclose($fp);

            // And actually send the message
            self::error($message, 'Unexpected error', true);
        }
    }

    /**
     * This checks to see if we've launched the server from command line (cron) or browser
     * @param  bool    Specify if the server should start anyway, NOT RECOMANDED
     * @return bool    If server is running it will return false else true
     */
    public static function checkRunMode($dieIfExisting = true) {
        if (php_sapi_name() != 'cli') {
            echo 'This should be run from command link not from the browser.';
            if ($dieIfExisting) {
                self::error('A launch has been tried using a web browser.', 'startup error', true);
            }
            return false;
        }

        return true;
    }

    /**
     * This checks to see if we are running multiple instances or not
     * @param  bool    Specify if the server should start anyway, NOT RECOMANDED
     * @return bool    If server is running it will return false else true
     */
    public static function checkInstances($dieIfExisting = true) {
        // clear file stat cache
        clearstatcache();

        // check to see if we have another instance of this already running, exit if so
        if (file_exists(self::$pidFile)) {
            if ($dieIfExisting) {
                self::$fatalError = true;
                self::error('PID file already exists.', 'startup error', true);
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Wrapper for checking if running the server is possible
     * @param  bool    Specify if the server should start anyway, NOT RECOMANDED
     * @return bool    If server is running it will return false else true
     */
    public static function checkStartUp($dieIfExisting = true) {
        return self::checkRunMode($dieIfExisting) && self::checkInstances($dieIfExisting);
    }

    /**
     * This is our global error function
     * @param  string  What should be sent as output
     * @param  string  optional What should be included as message
     * @param  bool    Is this a fatal error or not
     */
    public static function error($what, $title = '', $isFatal = false) {
        /**
         * @todo This needs to be changed to actually send the mail.
         */
        if ($isFatal)
            exit(1);
        return;

        $to = 'florinpatan@gmail.com';
        $subject = 'Sync - Task server error' . ($title == '' ? '' : ' - ' . $title);
        $message = 'Error on task server at: ' . date(DATE_RFC822) . "\r\n" . 'Error details:' . "\r\n" . $what . "\r\n" . "\r\n" . "\r\n" . 'Stack trace:' . "\r\n" . print_r(debug_backtrace(), true);
        $headers = 'From: Task server<webmaster@florinpatan.ro>';

        if (!self::$debugMode) {
            @mail($to, $subject, $message, $headers);
        } else {
            echo $message . "\n\n";
        }

        if ($isFatal) {
            exit(1);
        }
    }

    /**
     * Connect to our database
     */
    private function connectToDatabase() {
        $this->db = new database();
    }

    /**
     * This function will process the incoming signals
     * @param  int Signal number
     */
    public function signalHandler($signal) {
        // Disable signal processing for the moment, just dump the signal to email and quit.
        self::error("UNIX signal received", "Signal " . $signal . " as received.", true);

        // Process signals here
        switch ($signal) {
            // handle shutdown tasks
            case SIGTERM : {
                    /**
                     * TODO
                     * Implement what happens when we receive our shutdown call
                     */
                    exit();
                }
                exit();
            // handle restart tasks
            case SIGHUP : {
                    /**
                     * TODO
                     * Implement what happens when we receive our the restart call
                     */
                    exit();
                }
                break;
            default :
            /**
             * TODO
             * Find out more about UNIX signals
             * Implement the rest of the signals
             */
        }
    }

    /**
     * Register signal handlers
     */
    private function registerSignalHandlers() {
        // Register general used messages
        pcntl_signal(SIGTERM, array('TaskServer', 'signalHandler'));
        pcntl_signal(SIGHUP, array('TaskServer', "signalHandler"));
        pcntl_signal(SIGUSR1, array('TaskServer', "signalHandler"));
    }

    /**
     * Get what is the que status right now
     * @return array   queCount, nextJobTime How many jobs are in que, when is the next job due?
     */
    public function getQueInfo() {
        // get the information we need about the task que
        $query = 'SELECT COUNT(*) AS \'queCount\', (SELECT `tasks_execute` FROM tasks WHERE `tasks_status` = \'new\' ORDER BY `tasks_execute` LIMIT 1) AS \'nextJobTime\' FROM tasks';

        // run the query
        $results = $this->db->getOne($query, true);

        // return that information
        return $results;
    }

    /**
     * Determine how much the server can sleep in seconds
     * @param  int How much the server should sleep if noting else comes before that in que
     * @param  int If we have jobs from the past, what is the min period of sleep
     * @return int Sleep period in seconds
     */
    public function getSleepPeriod($normalSleep = 45, $recoverMinSleep = 5) {
        // normaly we should sleep our regular sleep
        $sleep = $normalSleep;

        // get our que info
        $queInfo = $this->getQueInfo();

        // we might not have new jobs
        if ($queInfo->nextJobTime != null) {
            // and convert the time to a unix timestamp
            $nextJobTime = date_parse($queInfo->nextJobTime);

            // convert the time from mysql timestamp to unix timestamp
            $nextJobTime = mktime($nextJobTime['hour'], $nextJobTime['minute'], $nextJobTime['second'], $nextJobTime['month'], $nextJobTime['day'], $nextJobTime['year']);

            // what's the time now
            $now = time();

            // if next scheduled job should be in less that the sleep time then sleep less, work more :P
            if ($now + $normalSleep > $nextJobTime) {
                $sleep = $nextJobTime - $now;
            }
        }

        // Get the next close event for a job and compare it to our sleep period.
        $nextJobEvent = $sleep;

        // Get the current time
        $time = microtime(true);

        // Since we are going to go thru all the jobs we will process all the
        // active jobs here and see if they need a forced stop or to be restarted
        foreach ($this->activeThreads as $jobType => $jobs) {
            // If we don't have any jobs of this type then just skip to next job type
            if (count($jobs) < 1)
                continue;

            // Our arrays are care sensitive
            $jobType = ucfirst($jobType);

            // Check all the jobs of this type
            foreach ($jobs as $jobId => $job) {
                // Is the current job running or not
                if ($job->isRunning()) {
                    $nextJobEvent = min($sleep, abs($this->registeredServers[$jobType]->jobTimeout - ($time - $job->serverInfo->started)));
                } else {
                    $nextJobEvent = min($sleep, abs($this->registeredServers[$jobType]->retryPause - ($time - $job->serverInfo->stopped)));
                }
            }
        }

        // Wait one more sec
        $nextJobEvent = (int) $nextJobEvent + 1;

        // If job should have occured already (have we been down?) then let the server take its breath
        if ($nextJobEvent < $recoverMinSleep) {
            // We need to wait a bit in order to let the server do its magic
            $sleep = $recoverMinSleep;
        }

        // Feeling sleepy, are we?
        return $sleep;
    }

    /**
     * Use the shared memory in order to let our web server companion know about our status :)
     * @return void
     */
    private function updateServerStatus() {
        $result = array();

        foreach ($this->activeThreads as $jobType => $jobs) {
            // If we don't have any jobs of this type then just skip to next job type
            if (count($jobs) < 1)
                continue;

            // Our arrays are care sensitive
            $jobType = ucfirst($jobType);

            // Check all the jobs of this type
            foreach ($jobs as $jobId => $job) {
                // Get the job status
                switch ($job->serverInfo->status) {
                    case JobServer::UNDEFINED : $result[$jobType][] = 'U';
                        break;
                    case JobServer::RUNNING : $result[$jobType][] = 'W';
                        break;
                    case JobServer::FINISHED : $result[$jobType][] = 'F';
                        break;
                    default : $result[$jobType][] = 'E';
                }
            }
        }

        // Go thru all the registered job types and pr
        foreach ($this->registeredServers as $serverType) {
            // Get our thread count for this job type
            $activeThreads = isset($result[$serverType->jobType]) ? count($result[$serverType->jobType]) : 0;

            // Add some blanks, for the show
            while ($activeThreads < $serverType->parallelThreads) {
                $result[$serverType->jobType][] = '.';
                $activeThreads++;
            }
        }

        $result['__info']['startTime'] = $this->startTime;

        // Try and get a lock using the semaphore system
        $semId = sem_get(8088);
        if ($semId) {
            if (sem_acquire($semId)) {
                // Put the var into the shared memory
                $shmId = shm_attach(8088);
                shm_put_var($shmId, 8088, $result);
                shm_detach($shmId);
            }

            sem_release($semId);
        }
    }

    /**
     * Add a new job type to our server
     *
     * @param  string  Job name
     * @param  int     Number of jobs that can be run at the same time on the server
     * @param  int     How much time should a task be runing for before checking it's state
     * @param  int     How many times should we retry the current job before aborting it
     * @param  int     How much should we be waiting for until we retry the job
     */
    public function registerJobType($jobName, $parallelThreads = 5, $jobTimeout = 60, $retryCount = 3, $retryPause = 120) {
        // check to see if we have a job server already registered for this job type
        if (array_key_exists($jobName, $this->registeredServers)) {
            self::error('Unable to register a new job server type as the server already exists: ' . $jobName, 'job already registered');
        }

        // fetch a new job type
        $jobServerDetails = $jobName::getJobServerDetails($parallelThreads, $jobTimeout, $retryCount, $retryPause);

        if (!is_object($jobServerDetails)) {
            self::error('Unable to register a new server type: ' . $jobName, 'unable to load job type', true);
        }

        // add the job to the list
        $this->registeredServers[$jobName] = $jobServerDetails;

        // check to see which job has the bigger di.. erm execution time
        if ($this->maxExecutionTime < $jobServerDetails->jobTimeout) {
            $this->maxExecutionTime = $jobServerDetails->jobTimeout;
        }

        // again but with number of retries this time
        if ($this->maxRetryCount < $jobServerDetails->retryCount) {
            $this->maxRetryCount = $jobServerDetails->retryCount;
        }

        // Add the number of threads that this job can have to the total max threads of the server
        $this->maxRunningThreads += $jobServerDetails->parallelThreads;

        // create entry in the active job types
        $this->activeThreads[$jobServerDetails->jobType] = array();

        // make room
        unset($jobServerDetails);
    }

    /**
     * This will retrive all the jobs ids from the database and group them by their type
     * @return int Number of jobs grouped by type
     */
    private function getPendingJobsByType() {
        // Fetch the info from the database
        $query = 'SELECT `tasks_id`, `tasks_job_type`
                FROM `tasks`
                WHERE
                (`tasks_execute` <= NOW() AND `tasks_status` = \'new\')
                OR
                (`tasks_execute` <= NOW() AND `tasks_status` = \'processing\' AND `tasks_execute` + ' . $this->maxExecutionTime . ' < NOW() AND `tasks_retry_count` < ' . $this->maxRetryCount . ')
                ORDER BY `tasks_execute`, `tasks_id`, `tasks_status` DESC
                LIMIT 0, ' . $this->maxRunningThreads;

        $jobs = array();

        // Run the query
        $results = $this->db->getAll($query, true);

        // Add our new jobs
        while ($result = $results->fetch_object()) {
            $jobs[$result->tasks_job_type][] = $result->tasks_id;
        }

        // Free the results
        $results->close();

        // Return the jobs
        return $jobs;
    }

    /**
     * This is used to add new functions to the job que
     */
    private function addJobsToQue() {
        // Get the list of pending jobs
        $pendingJobs = $this->getPendingJobsByType();

        // Go thru all the registered job types and pr
        foreach ($this->registeredServers as $serverType) {
            // Get our thread count for this job type
            $jobThreads = count($this->activeThreads[$serverType->jobType]);

            // Check to see if we've reached the limit for this job type
            if ($jobThreads >= $serverType->parallelThreads ||
                !array_key_exists($serverType->jobType, $pendingJobs)) {
                // And skip to the next job type if so
                continue;
            }

            // Try and add the job to the que
            foreach ($pendingJobs[$serverType->jobType] as $jobId) {
                // Check to see if this job is not in the que already and we have enough slots for this job type
                if (!array_key_exists($jobId, $this->activeThreads[$serverType->jobType]) &&
                    $jobThreads < $serverType->parallelThreads) {
                    // Since we won't have a task running
                    $jobType = $serverType->jobType;
                    $this->activeThreads[$serverType->jobType][$jobId] = new $jobType($jobId, self::$debugMode);

                    //Increment current running threads for our job type
                    $jobThreads++;

                    // And add our new job to the number of processed jobs
                    $this->tasksProcessed++;
                }

                // Check to see if we've reached the limit for this job type
                if ($jobThreads >= $serverType->parallelThreads) {
                    // And skip to the next job type if so
                    continue;
                }
            }

            // Cleanup
            unset($jobThreads);
        }

        // Cleanup
        unset($pendingJobs);
    }

    /**
     * This is used to clear the que from dead or finished jobs.
     * @param	string	Job type
     * @param	int		Job id
     */
    private function stopDeadJob($jobType, $jobId) {
        $jobType = strtolower($jobType);
        $this->activeThreads[$jobType][$jobId]->closeJob(true);

        // Make the change in the database
        self::changeJobStatus($jobId, 'forcedstoped');

        // Remove this job from the list of active jobs
        unset($this->activeThreads[$jobType][$jobId]);
    }

    /**
     * This is used to stop a broken job.
     * @param	string	Job type
     * @param	int		Job id
     */
    private function stopBrokenJob($jobType, $jobId) {
        $jobType = strtolower($jobType);
        echo "stopBrokenJob \n";
        $this->activeThreads[$jobType][$jobId]->closeJob();
    }

    /**
     * This is used to restart a broken job.
     * @param	string	Job type
     * @param	int		Job id
     */
    private function restartBrokenJob($jobType, $jobId) {
        $jobType = strtolower($jobType);
        echo "restartBrokenJob \n";
        $this->activeThreads[$jobType][$jobId]->startJob($jobId);
    }

    /**
     * This is used to mark a job as finished in the server.
     * @param	string	Job type
     * @param	int		Job id
     */
    private function stopFinishedJob($jobType, $jobId) {
        $jobType = strtolower($jobType);
        echo "stopFinishedJob \n";
        unset($this->activeThreads[$jobType][$jobId]);
    }

    /**
     * This will check if our active jobs should be restarted or stopped
     */
    private function processActiveJobs() {
        // Get the current time
        $time = microtime(true);

        // Since we are going to go thru all the jobs we will process all the
        // active jobs here and see if they need a forced stop or to be restarted
        foreach ($this->activeThreads as $jobType => $jobs) {
            // If we don't have any jobs of this type then just skip to next job type
            if (count($jobs) < 1)
                continue;

            // Our arrays are care sensitive
            $jobType = ucfirst($jobType);

            // Check all the jobs of this type
            foreach ($jobs as $jobId => $job) {
                // Is the current job running or not
                $jobIsRunning = $job->isRunning();

                // If it's not registered as runnning the check to see what we can do for it
                switch (true) {
                    // Check to see if a job has passed it's time and retry count
                    case ($jobIsRunning &&
                    $job->serverInfo->retryCount >= $this->registeredServers[$jobType]->retryCount &&
                    $time - $job->serverInfo->started > $this->registeredServers[$jobType]->jobTimeout) :
                        $this->stopDeadJob($jobType, $jobId);
                        break;

                    // Check to see if the job should be stopped
                    case ($jobIsRunning &&
                    $time - $job->serverInfo->started > $this->registeredServers[$jobType]->jobTimeout) :
                        $this->stopBrokenJob($jobType, $jobId);
                        break;

                    // Check to see if the job should be started again
                    case (!$jobIsRunning &&
                    $time - $job->serverInfo->stopped > $this->registeredServers[$jobType]->retryPause) :
                        $this->restartBrokenJob($jobType, $jobId);
                        break;

                    // Check to see if the job finished and should be removed from the server que
                    case (!$jobIsRunning && $job->serverInfo->status == 'finished') :
                        $this->stopFinishedJob($jobType, $jobId);
                        break;
                }
            }
        }
    }

    /**
     * This is the job processing function of our server
     * It should check on every tick if the active jobs are finished or with errors
     * and launch new jobs.
     */
    public function processJobs() {
        $this->processActiveJobs();

        $this->addJobsToQue();

        $this->updateServerStatus();
    }

    /**
     * Change the status of our job.
     * The status of the job can be:
     * processing   -> the job is currently running
     * finished     -> the job is finished
     * forcedstoped -> the job was running for too much time
     * @param int		The id of our job
     * @param string 	The status of our job
     */
    public static function changeJobStatus($jobId, $status) {
        switch ($status) {
            case 'processing' :
                self::jobIsProcessing($jobId);
                break;
            case 'finished' :
                self::jobIsFinished($jobId);
                break;
            case 'forcedstoped' :
                self::jobIsForcedStoped($jobId);
                break;
        }
    }

    /**
     * Mark a job as processing
     * @param int	Job id
     */
    public static function jobIsProcessing($jobId) {
        // Make a connection to the database
        $db = new database();

        // Query to mark the job as finished
        $query = "update `tasks` set `tasks_status` = 'processing', `tasks_execute` = NOW(), `tasks_retry_count` = `tasks_retry_count` + 1 where `tasks_id` = " . $jobId;

        // Execute it again
        $db->query($query);

        // Close the database link
        $db->close();

        // Clear the memory
        unset($db);
    }

    /**
     * Mark a job as finished
     * @param int	Job id
     */
    private static function jobIsFinished($jobId) {
        // Make a connection to the database
        $db = new database();

        // This is our query to move the task from a table to another
        $query = "insert into tasks_done(tasks_id, tasks_job_type, tasks_options, tasks_created, tasks_execute)
        		  select tasks_id, tasks_job_type, tasks_option, tasks_created, tasks_execute
        		  from tasks
        		  where tasks_id = " . $jobId;

        // Execute the query
        $db->query($query);

        // And now delete the task from the table
        $query = "delete from tasks where tasks_id = " . $jobId;

        // Execute it again
        $db->query($query);

        // Close the database link
        $db->close();

        // Clear the memory
        unset($db);
    }

    /**
     * Mark a job as broken
     * @param int	Job id
     */
    private static function jobIsForcedStoped($jobId) {
        // Make a connection to the database
        $db = new database();

        // This is our query to move the task from a table to another
        $query = "insert into tasks_error(tasks_id, tasks_job_type, tasks_options, tasks_created, tasks_execute, tasks_finished)
        		  select tasks_id, tasks_job_type, tasks_option, tasks_created, tasks_execute, NOW()
        		  from tasks
        		  where tasks_id = " . $jobId;

        // Execute the query
        $db->query($query);

        // And now delete the task from the table
        $query = "delete from tasks where tasks_id = " . $jobId;

        // Execute it again
        $db->query($query);

        // Close the database link
        $db->close();

        // Clear the memory
        unset($db);
    }

    /**
     * Create our Task Server
     * @param	boolean	If we are running in debug mode or not
     */
    public function __construct($debug = false) {
        // Register our classes for independent servers separately
        // We only want to autoload classes from our path
        set_include_path('.:./Jobs/');

        // and only with php extension (faster)
        spl_autoload_extensions('.php');

        // and use the default autoloader
        spl_autoload_register();

        // Are we in debug mode
        self::$debugMode = $debug;

        // Check if we can run
        self::checkStartUp(!$debug);

        // Connect to the database
        $this->connectToDatabase();

        // register signal handlers
        $this->registerSignalHandlers();

        // clear file cache again
        clearstatcache();

        // Set the log files to our directory instead of the default location
        self::$logFileFatal = __DIR__ . '/logs/fatal.log';
        self::$logFileError = __DIR__ . '/logs/error.log';
        self::$logFileInfo = __DIR__ . '/logs/info.log';

        // If we could do all the above then let's make a .pid file and start processing those tasks
        @file_put_contents(self::$pidFile, 'taskserver started at: ' . date(DATE_RFC822));

        // deleting failed warn the admin
        if (!file_exists(self::$pidFile)) {
            self::error('Failed to delete the pid file of the task server', 'delete pid file', true);
        }

        self::$logFilePointers['fatal'] = fopen(self::$logFileFatal, 'a+');
        self::$logFilePointers['error'] = fopen(self::$logFileError, 'a+');
        self::$logFilePointers['info'] = fopen(self::$logFileInfo, 'a+');

        $this->startTime = time();
    }

    /**
     * Here we say bye bye to our server
     */
    public function __destruct() {
        // We shouldn't reach here but if we do and we are not comming from a error
        if (!self::$fatalError) {
            self::error('Unexpected shutdown call', 'unxepected shutdown call');
        }

        // Since we are shutting down it would be nice to clear the path for the next task server to start processing
        if (!self::$fatalError && file_exists(self::$pidFile)) {
            // Try and delete the file
            @unlink(self::$pidFile);

            // Deleting failed warn the admin
            if (file_exists(self::$pidFile)) {
                self::error('Failed to delete the pid file of the task server', 'delete pid file');
            }
        }

        // Well this is a emergency since we are actually here, shutting down
        self::log("Shutting down the server...", LOG_EMERG, false);

        // Close the error logs
        fclose(self::$logFilePointers['fatal']);
        fclose(self::$logFilePointers['error']);
        fclose(self::$logFilePointers['info']);

        // Unset stuff
        unset($this->db);
        unset($this->activeThreads);
        unset($this->registeredServers);
        unset($this->tasksProcessed);
        unset($this->startTime);
        unset(self::$logFilePointers['fatal']);
        unset(self::$logFilePointers['error']);
        unset(self::$logFilePointers['info']);
        unset(self::$logFilePointers);
        self::$pidFile = '';
    }

}

