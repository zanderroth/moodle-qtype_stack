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


require_once(dirname(__FILE__) . '/connector.interface.php');
require_once(dirname(__FILE__) . '/connector.dbcache.class.php');


/**
 * The base class for connections to Maxima.
 *
 * @copyright  2012 The University of Birmingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class stack_cas_connection_base implements stack_cas_connection {
    protected static $config = null;

    /** @var string path to write Maxiam error output to. */
    protected $logs;

    /** @var string the name of the maxima executable, to use of command-lines. */
    protected $command;

    /** @var string the opening command to send to maxima. */
    protected $initcommand;

    /** @var int the timeout to use on connections to Maxima. */
    protected $timeout;

	/** @var stack_debug_log does the debugging. */
    protected $debug;

    /**
     * Create a Maxima connection.
     * @return stack_cas_connection the connection.
     */
    public static function make() {
        if (is_null(self::$config)) {
            self::$config = get_config('qtype_stack');
        }

        $debuglog = stack_utils::make_debug_log(self::$config->casdebugging);

        switch (self::$config->platform) {
            case 'win':
                require_once(dirname(__FILE__) . '/connector.windows.class.php');
                $connection = new stack_cas_connection_windows(self::$config, $debuglog);
                break;

            case 'unix':
            case 'server':
                require_once(dirname(__FILE__) . '/connector.unix.class.php');
                $connection = new stack_cas_connection_unix(self::$config, $debuglog);
                break;

            default:
                throw new Exception('stack_cas_connection: Unknown platform ' . self::$config->platform);
        }

        switch (self::$config->casresultscache) {
            case 'db':
                $connection = new stack_cas_connection_db_cache($connection, $debuglog);
                break;

            default:
                // Just use the raw $connection.
        }

        return $connection;
    }

    /* @see stack_cas_connection::compute() */
    public function compute($command) {
        $this->debug->log('Maxima command', $command);

        $rawresult = $this->call_maxima($command);
        $this->debug->log('CAS result', $rawresult);

        $unpackedresult = $this->unpack_raw_result($rawresult);
        $this->debug->log('Unpacked result as', print_r($unpackedresult, true));

        return $unpackedresult;
    }

    /* @see stack_cas_connection::get_debuginfo() */
    public function get_debuginfo() {
        return $this->debug->get_log();
    }

    /**
     * Try to determine the name of the Maxima executable to use in command-lines,
     * if it is not specified in the configuration.
     * @param string $path the path to the stack workspace folder.
     * @return string Maxima executable name.
     */
    protected abstract function guess_maxima_command($path);

    /**
     * Connect directly to the CAS, and return the raw string result.
     *
     * @param string $command The string of CAS commands to be processed.
     * @return string|boolean The converted HTML string or FALSE if there was an error.
     */
    protected abstract function call_maxima($command);

    /**
     * Constructor.
     * @param stdClass $settings the Maxima configuration settings.
     * @param stack_debug_log $debuglog the debug log to use.
     */
    protected function __construct($settings, stack_debug_log $debuglog) {
        global $CFG;

        $path = $CFG->dataroot . '/stack';

        $initcommand = 'load("' . $path . '/maximalocal.mac");' . "\n";
        $initcommand = str_replace("\\", "/", $initcommand);
        $initcommand .= "\n";

        if ('' != trim($settings->maximacommand)) {
            $cmd = $settings->maximacommand;
        } else {
            $cmd = $this->guess_maxima_command($path);
        }

        $this->logs        = $path;
        $this->command     = $cmd;
        $this->initcommand = $initcommand;
        $this->timeout     = $settings->castimeout;
        $this->debug       = $debuglog;
    }

    /**
     * Top level Maxima-specific function used to parse CAS output into an array.
     *
     * @param array $rawresult Raw CAS output
     * @return array
     */
    protected function unpack_raw_result($rawresult) {
        $result = '';
        $errors = false;
        //check we have a timestamp & remove everything before it.
        $ts = substr_count($rawresult, '[TimeStamp');
        if ($ts != 1) {
            $this->debug->log('', 'receive_raw_maxima: no timestamp returned.');
            return array();
        } else {
            $result = strstr($rawresult, '[TimeStamp'); //remove everything before the timestamp
        }

        $result = trim(str_replace('#', '', $result));
        $result = trim(str_replace("\n", '', $result));

        $unp = $this->unpack_helper($result);

        if (array_key_exists('Locals', $unp)) {
            $uplocs = $unp['Locals']; // Grab the local variables
            unset($unp['Locals']);
        } else {
            $uplocs = '';
        }

        // Now we need to turn the (error,key,value,display) tuple into an array
        $locals = array();
        foreach ($this->unpack_helper($uplocs) as $var => $valdval) {
            if (is_array($valdval)) {
                $errors["CAS"] = "CAS failed to generate any useful output.";
            } else {
                if (preg_match('/.*\[.*\].*/', $valdval)) {
                    // There are some []'s in the string.
                    $loc = $this->unpack_helper($valdval);
                    if ('' == trim($loc['error'])) {
                        unset($loc['error']);
                    }
                    $locals[]=$loc;

                } else {
                    $errors["LocalVarGet$var"] = "Couldn't unpack the local variable $var from the string $valdval.";
                }
            }
        }

        // Next process and tidy up these values.
        for ($i=0; $i < count($locals); $i++) {

            if (isset($locals[$i]['error'])) {
                $locals[$i]['error'] = $this->tidy_error($locals[$i]['error']);
            } else {
                $locals[$i]['error'] = '';
            }
            // if theres a plot being returned
            $plot = isset($locals[$i]['display']) ? substr_count($locals[$i]['display'], '<img') : 0;
            if ($plot > 0) {
                //plots always contain errors, so remove
                $locals[$i]['error'] = '';
                //for mathml display, remove the mathml that is inserted wrongly round the plot.
                $locals[$i]['display'] = str_replace('<math xmlns=\'http://www.w3.org/1998/Math/MathML\'>',
                    '', $locals[$i]['display']);
                $locals[$i]['display'] = str_replace('</math>', '', $locals[$i]['display']);

                // for latex mode, remove the mbox
                // handles forms: \mbox{image} and (earlier?) \mbox{{} {image} {}}
                $locals[$i]['display'] = preg_replace("|\\\mbox{({})? (<html>.+</html>) ({})?}|", "$2", $locals[$i]['display']);
            }
        }
        return $locals;
    }


    protected function unpack_helper($rawresultfragment) {
        // Take the raw string from the CAS, and unpack this into an array.
        $offset = 0;
        $rawresultfragment_len = strlen($rawresultfragment);
        $unparsed = '';
        $errors = '';

        if ($eqpos = strpos($rawresultfragment, '=', $offset)) {
            // Check there are ='s
            do {
                $gb = stack_utils::substring_between($rawresultfragment, '[', ']', $eqpos);
                $val = substr($gb[0], 1, strlen($gb[0])-2);
                $val = str_replace('"', '', $val);
                $val = trim($val);

                if (preg_match('/[A-Za-z0-9].*/', substr($rawresultfragment, $offset, $eqpos-$offset), $regs)) {
                    $var = trim($regs[0]);
                } else {
                    $var = 'errors';
                    $errors['LOCVARNAME'] = "Couldn't get the name of the local variable.";
                }

                $unparsed[$var] = $val;
                $offset = $gb[2];
            } while (($eqpos = strpos($rawresultfragment, '=', $offset)) && ($offset < $rawresultfragment_len));

        } else {
            $errors['PREPARSE'] = "There are no ='s in the raw output from the CAS!";
        }

        if ('' != $errors) {
            $unparsed['errors'] = $errors;
        }

        return($unparsed);
    }

    /**
     * Deals with Maxima errors. Enables some translation.
     *
     * @param string $errstr a Maxima error string
     * @return string
     */
    protected function tidy_error($errstr) {
        if (strpos($errstr, '0 to a negative exponent') !== false) {
            $errstr = stack_string('Maxima_DivisionZero');
        }
        return $errstr;
    }
}
