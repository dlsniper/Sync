<?php

/**
 * Add automatic detection of our host and connect without messing with configs
 * in the source files in the database
 * @package    TaskServer
 * @version 0.1
 * @author Florin Patan
 * @copyright Florin Patan
 */
class database extends mysqli
{

    /**
     * Run the query and get a single result
     * @param  string  Query
     * @param  boolean Fatal error in case query can't be executed
     * @return object  Query result
     */
    public function getOne($query, $isFatal = false)
    {
        // Try and run the query
        if ($results = $this->query($query)) {
            // Fetch the result
            $result = $results->fetch_object();

            // Free the results
            $results->close();
        } else {
            // Send the error to the admin
            TaskServer::error('Failed to run query:' . "\r\n" . $query, 'query error', $isFatal);
        }

        // Return the results
        return $result;
    }

    /**
     * Run the query and all results
     * @param  string  Query
     * @param  boolean Fatal error in case query can't be executed
     * @return object  Query results
     */
    public function getAll($query, $isFatal = false)
    {
        // Try and run the query
        if ($results = $this->query($query)) {

        } else {
            // Send the error to the admin
            TaskServer::error('Failed to run query:' . "\r\n" . $query, 'query error', $isFatal);
        }

        // Return the results
        return $results;
    }

    /**
     * Insert values into database using query $query and $types for data type
     * @param  string  Query
     * @param  array   The values
     * @param  boolean Fatal error?
     */
    public function insert($query, $values, $fatalError = false)
    {
        // Create a prepared statement
        if ($stmt = $this->prepare($query)) {
            // Get the type of paramteres
            $types = array_shift($values);

            // This will store the inserted ids
            $ids = array();

            // For each element of the values do some
            foreach ($values as $no => $value) {
                // This is our param placeholder
                $params = array();

                // Add the types
                $params[] = $types;

                // Bind params call_user_func_array style
                foreach ($value as $key => $val) {
                    $params[] = &$values[$no][$key];
                }

                // Bind our params
                call_user_func_array(array($stmt, 'bind_param'), $params);

                // Execute the query
                $stmt->execute();

                // Get the inserted id
                $ids[] = $this->insert_id;

                // Clean up
                unset($params);
            }

            // Close our query
            $stmt->close();

            // Clean up
            unset($stmt);

            // Return the inserted id
            return $ids;
        } else {
            TaskServer::error('Could not create a prepared statement for query: ' . "\r\n" . $query, 'failed to do insert query', $fatalError);
        }
    }

    /**
     * Connect to the MySQL database automatically depending on the hostname of the current computer
     * @return database
     */
    public function __construct()
    {
        // Detect where are we
        $hostname = php_uname('n');

        // Find out where we are
        switch (true) {
            // We are on the main server
            case strpos($hostname, 'rodb.ro') !== false :
                $host = 'localhost';
                break;

            // We are on a virtual machine from home
            case strpos($hostname, 'mirror') !== false :
                $host = '192.168.0.199';
                break;

            // We are on a linux machine from home
            case strpos($hostname, 'linux-monzi') !== false :
                $host = 'localhost';
                break;

            // We are home!
            case strpos($hostname, 'monzi') !== false :
                $host = 'localhost';
                break;

            // This is a new place, let's leave it for the time being
            default :
                TaskServer::error('Failed to detect the host in order to connect to the database', 'failed to connect to database', true);
        }

        // Create our object
        @parent::__construct($host, "root", "x", "florinp_sync");

        // Check of the connection was possible or not
        if ($this->connect_error) {
            // This is a error
            TaskServer::error('Connect Error: ' . $this->connect_error, 'failed to connect to database', true);
        }

        // Let's return ourselfs
        return $this;
    }

    /**
     * Close our database before we go bye bye
     */
    public function __destruct()
    {
        // Attempt to close our existing connection
        @$this->close();
    }

}