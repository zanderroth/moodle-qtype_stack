<?php
// This file is part of Stack - http://stack.bham.ac.uk//
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
 * General answer test which connects to the CAS - prevents duplicate code.
 *
 * @copyright  2012 University of Birmingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stack_answertest_general_cas extends stack_anstest {

    /**
     * @var string The name of the cas function this answer test uses.
     */
    private $casfunction;

    /**
     * $var bool Are options required.
     */
    private $requirecasoptions;

    /**
     * $var bool If this variable is set to true or false we override the
     *      simplification options in the CAS variables.
     */
    private $simp;

    /**
     * @param  string $sans
     * @param  string $tans
     * @param  string $casoption
     */
    public function __construct($sans, $tans, $casfunction, $requirecasoptions = false,
            $casoption = null, $options = null, $simp = false) {
        parent::__construct($sans, $tans, $options, $casoption);

        if (!is_bool($requirecasoptions)) {
            throw new Exception('stack_answertest_general_cas: requirecasoptions, must be Boolean.');
        }

        if (!(null===$options || is_a($options, 'stack_options'))) {
            throw new Exception('stack_answertest_general_cas: options must be stack_options or null.');
        }

        $this->casfunction       = $casfunction;
        $this->requirecasoptions = $requirecasoptions;
        $this->simp              = (bool) $simp;
    }

    /**
     *
     *
     * @return bool
     * @access public
     */
    public function do_test() {
        if ($this->requirecasoptions) {
            if (null == $this->atoption or '' == $this->atoption) {
                $this->aterror      = 'TEST_FAILED';
                $this->atfeedback   =  stack_string("TEST_FAILED").stack_string("AT_MissingOptions");
                $this->atansnote    = 'STACKERROR_OPTION';
                $this->atmark       = 0;
                $this->atvalid      = false;
                return null;
            } else {
                //validate with teacher privileges, strict syntax & no automatically adding stars.
                $ct  = new stack_cas_casstring($this->atoption);

                if (!$ct->get_valid('t', true, true)) {
                    $this->aterror      = 'TEST_FAILED';
                    $this->atfeedback   = stack_string("TEST_FAILED");
                    $errors = $ct->get_errors();
                    $this->atfeedback  .= stack_string('AT_InvalidOptions', array("errors" => $errors));
                    $this->atansnote    = 'STACKERROR_OPTION';
                    $this->atmark       = 0;
                    $this->atvalid      = false;
                    return null;
                }
            }
            $atopt = $this->atoption;
            $ta   = "[$this->tanskey,$atopt]";
        } else {
            $ta = $this->tanskey;
        }

        // Sort out options
        if (null === $this->options) {
            $this->options = new stack_options();
        }
        if (!(null===$this->simp)) {
            $this->options->set_option('simplify', $this->simp);
        }

        $cascommands = array();
        $cascommands[] = "STACKSA:$this->sanskey";
        $cascommands[] = "STACKTA:$ta";
        $cascommands[] = "result:StackReturn({$this->casfunction}(STACKSA,STACKTA))";

        $cts = array();
        foreach ($cascommands as $com) {
            $cs    = new stack_cas_casstring($com);
            $cs->validate('t', true, false);
            $cts[] = $cs;
        }
        $session = new stack_cas_session($cts, $this->options, 0);
        $session->instantiate();

        if (''!=$session->get_errors_key('STACKSA')) {
            $this->aterror      = 'TEST_FAILED';
            $this->atfeedback   = ' stack_trans("TEST_FAILED"); ';
            $this->atansnote    = $this->casfunction.'_STACKERROR_SAns';
            $this->atmark       = 0;
            $this->atvalid      = false;
            return null;
        }

        if (''!=$session->get_errors_key('STACKTA')) {
            $this->aterror      = 'TEST_FAILED';
            $this->atfeedback   = stack_string("TEST_FAILED");
            $this->atansnote    = $this->casfunction.'_STACKERROR_TAns';
            $this->atmark       = 0;
            $this->atvalid      = false;
            return null;
        }

        if (''!=$session->get_errors_key('result')) {
            $this->aterror      = 'TEST_FAILED';
            $this->atfeedback   = stack_string("TEST_FAILED");
            $this->atansnote    = $this->casfunction.'_STACKERROR_result';
            $this->atmark       = 0;
            $this->atvalid      = false;
            return null;
        }

        $session = $session->get_session();
        $result = $session[2];

        $this->atansnote  = $result->get_answernote();

        // convert maxima string 'true' to PHP true
        if ('true'==$result->get_value()) {
            $this->atmark = 1;
        } else {
            $this->atmark = 0;
        }
        $this->atfeedback = $result->get_feedback();
        $this->atvalid    = $result->get_valid();

        if ($this->atmark) {
            return true;
        } else {
            return false;
        }
    }

    public function process_atoptions() {
        return $this->requirecasoptions;
    }
}