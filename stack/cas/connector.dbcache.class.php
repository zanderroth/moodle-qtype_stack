<?php
// This file is part of Stack - http://stack.bham.ac.uk/
//
// Stack is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stack is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.


/**
 * Class which undertakes process control to connect to Maxima.
 *
 * @copyright  2012 The University of Birmingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stack_cas_connection_db_cache implements stack_cas_connection {
    /** @var stack_cas_connection the un-cached connection to Maxima. */
    protected $rawconnection;

	/** @var stack_debug_log does the debugging. */
    protected $debug;

    /**
     * Constructor.
     * @param stack_cas_connection $rawconnection the un-cached connection.
     * @param stack_debug_log $debuglog the debug log to use.
     */
    public function __construct(stack_cas_connection $rawconnection, stack_debug_log $debuglog) {
        global $DB;

        $this->rawconnection = $rawconnection;
        $this->debug = $debuglog;
    }

    /* @see stack_cas_connection::compute() */
    public function compute($command) {
        $cached = $this->get_cached_result($command);
        if ($cached->result) {
            $this->debug->log('Maxima command', $command);
            $this->debug->log('Unpacked result found in the DB cache', print_r($cached->result, true));
            return $cached->result;
        }

        $this->debug->log('Maxima command not found in the cache. Using the raw connection.');
        $result = $this->rawconnection->compute($command);
        $this->add_to_cache($command, $result, $cached->key);

        return $result;
    }

    /* @see stack_cas_connection::get_debuginfo() */
    public function get_debuginfo() {
        return $this->debug->get_log();
    }

    /**
     * Get the cached result, if known.
     * @param string $command Maxima code to execute.
     * @return object with two fields:
     *      ->result, the cached result, if any, otherwise null, and
     *      ->key, the hashed key used to index this result.
     */
    protected function get_cached_result($command) {
        global $DB;

        $cached = new stdClass();
        $cached->key = $this->get_cache_key($command);

        $data = $DB->get_record('qtype_stack_cas_cache', array('hash' => $cached->key));
        if (!$data) {
            $cached->result = null;
            return $cached;
        }

        if ($data->command != $command) {
            throw new Exception('stack_cas_connection_db_cache: the command found at hash key ' .
                    $cached->key . ' did not match what was expected.');
        }

        $cached->result = json_decode($data->result, true);
        return $cached;
    }

    /**
     * Add a new result to the cache.
     * @param string $command Maxima code to execute.
     * @param array $result the result from Maxima for this command.
     * @param string $key the key used to store this command, if already known.
     */
    protected function add_to_cache($command, $result, $key = null) {
        global $DB;

        if (is_null($key)) {
            $key = $this->get_cache_key($command);
        }

        $data = new stdClass();
        $data->hash = $key;
        $data->command = $command;
        $data->result = json_encode($result);

        if ($DB->record_exists('qtype_stack_cas_cache', array('hash' => $key))) {
            // This will catch most . but not all, cases when two simulatneous
            // CAS connections try to cache the result of the same command.
            return;
        }

        // TODO but there is still a race-condition here. Find a good fix.

        $DB->insert_record('qtype_stack_cas_cache', $data);
    }

    /**
     * @param string $command Maxima code to execute.
     * @return string the key used to store this command.
     */
    protected function get_cache_key($command) {
        return sha1($command);
    }

    /**
     * Completely clear the cache.
     */
    public static function clear_cache() {
        global $DB;
        $DB->delete_records('qtype_stack_cas_cache');
    }

    /**
     * @return int the number of entries in the cache.
     */
    public static function entries_count() {
        global $DB;
        return $DB->count_records('qtype_stack_cas_cache');
    }
}
