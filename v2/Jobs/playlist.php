<?php

/**
 * Playlist class to implement the playlist converting
 * @package TaskServer
 * @version 0.1
 * @author Florin Patan
 * @copyright Florin Patan
 */
class Playlist extends JobServer {
    const JOB = 'playlist';
    private static $URLAPI = 'http://gdata.youtube.com/feeds/api/playlists/[location]?start-index=[start]&max-results=20';
    private static $URLPage = 'http://www.youtube.com/view_play_list?p=[location]';

    /**
     * Add a new job to the que
     * @param  array   Options of the job
     * @param  boolean Should we crash if we couldn't add the job or just send the error?
     * @return int     Id of the inserted job
     */
    public static function addJob($options, $fatalIfError) {
        // Make the options storable in the database
        $options = serialize($options);

        // The query looks like this
        $query = 'insert into tasks(tasks_job_type, tasks_option, tasks_status, tasks_created) values(?, ?, ?, NOW())';

        // These are the values that need to be added to the database
        $values = array('sss');

        // The job
        $values[] = array(self::JOB, $options, 'new');

        // Since we will connect to our database only here we don't need a global db object
        $db = new database();

        // Add our job to the que
        $id = $db->insert($query, $values);

        // Close our database
        unset($db);

        // Return the id of the new job
        return $id;
    }

    /**
     * Fetch the page
     * @param  string  Id of the playlist
     * @return array   Ids
     */
    private static function fetchPage($playlistID) {
        // Compose the URL
        $url = str_replace('[location]', $playlistID, self::$URLPage);

        // Fetch URL
        $contents = self::fetchURL($url);

        // Get the content that we need, video id and video name
        preg_match_all('@<a href="/watch\?v=(.{11})&amp;p=' . $playlistID . '" >(.*?)</a>@msi', $contents['content'], $matches);

        // Cleanup
        unset($contents);

        // Make the match between the video id and video name
        foreach ($matches[1] as $key => $value) {
            $titles[] = array('video', serialize(array('id' => $matches[1][$key], 'title' => $matches[2][$key])));
        }

        // Return the results
        return $titles;
    }

    /**
     * Fetch the page
     * @todo   FIX THIS! and document it better
     * @param  string  Id of the playlist
     * @return array   Ids
     */
    private static function fetchAPI($playlistID) {
        TaskServer::error('Playlist::fetchAPI is not completed', 'incomplete function call', true);

        // Compose the URL
        $url = str_replace('[location]', $playlistID, self::$URLAPI);

        $titles = array();
        $i = 1;
        while ($i < 200) {
            $url1 = str_replace('[start]', $i, $url);

            $contents = self::fetchURL($url1);

            $content = simplexml_load_string($contents['content']);

            foreach ($content->entry as $entry) {
                $titles[] = (string) $entry->title;
            }

            unset($content);
            unset($contents);

            $i += 20;
        }

        return $titles;
    }

    /**
     * Process our job
     * @param  int Our job id
     */
    public static function processJob($jobId) {
        // Let the server know we are about to do our job
        $this->updateStatus('processing');

        // Debug code
        $sleep = array(4, 180);
        sleep($sleep[rand(0, 1)]);
        $this->updateStatus('finished');

        return true;

        // Since we will connect to our database only here we don't need a global db object
        $db = new database();


        // Let's get this job rocking
        // Make the query to get the data from the database
        $query = "select * from tasks where tasks_id = " . $jobId;

        // Execute the query
        $job = $db->getOne($query);

        // Make the options readable again
        $job->tasks_option = unserialize($job->tasks_option);

        // Fetch the ids of the video by ripping the playlist page
        $titles = self::fetchPage($job->tasks_option['location']);

        // Make the query
        $query = 'INSERT INTO `tasks`(`tasks_job_type`, `tasks_option`, `tasks_created`) VALUES(?, ?, NOW())';

        // This is the type of the params
        $values = array('ss');

        // Now add the ids to be inserted into the database
        $values = array_merge($values, $titles);

        // And insert them into the database
        $db->insert($query, $values, false);

        // Close our database
        unset($db);

        // Let the server know we are done
        $this->updateStatus('finished');
    }

    /**
     * This will help us getting the details for each job server
     * @param  int     Number of jobs that can be run at the same time on the server
     * @param  int     How much time should a task be runing for before checking it's state
     * @param  int     How many times should we retry the current job before aborting it
     * @param  int     How much should we be waiting for until we retry the job
     * @return jobServerOption
     */
    public static function getJobServerDetails($parallelThreads = 5, $jobTimeout = 60, $retryCount = 3, $retryPause = 120) {
        self::$serverOption = new jobServerOption(self::JOB, $parallelThreads, $jobTimeout, $retryCount, $retryPause);
        return self::$serverOption;
    }

    /**
     * Create a new job
     * @param   int        ID of our job
     * @param   boolean    Debug mode?
     * @return  jobServer  Job
     */
    public function __construct($jobId, $debugMode) {
        return parent::__construct(self::JOB, $jobId, $debugMode);
    }

}
