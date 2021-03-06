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

require_once(dirname(__FILE__) . '/../../utils.class.php');

/**
 *Input that is a text area. Each line input becomes one element of a list.
 *
 * The value is stored as a string maxima list. For example [1,hello,x + y].
 *
 * @copyright  2012 University of Birmingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stack_textarea_input extends stack_input {

    public function render(stack_input_state $state, $fieldname, $readonly) {
        // Note that at the moment, $this->boxHeight and $this->boxWidth are only
        // used as minimums. If the current input is bigger, the box is expanded.

        $attributes = array(
            'name' => $fieldname,
        );

        if ('' === trim($state->contents)) {
            $current = $this->parameters['syntaxHint'];
        } else {
            $current = $state->contents;
        }

        $rows = $this->tokenize_list($current);
        $attributes['rows'] = max($this->parameters['boxHeight'], count($rows) + 1);

        $value = '';
        $boxwidth = $this->parameters['boxWidth'];
        foreach ($rows as $row) {
            $value .= $row . "\n";
            $boxwidth = max($boxwidth, strlen($row) + 5);
        }
        $attributes['cols'] = $boxwidth;

        if ($readonly) {
            $attributes['readonly'] = 'readonly';
        }

        return html_writer::tag('textarea', htmlspecialchars($value), $attributes);
    }

    /**
     * Converts the inputs passed in into a textareas into a Maxima list
     *
     * TODO worry about lines of input that contain ','.
     *
     * @param string $in
     * @return string
     * @access public
     */
    public function transform($in) {
        if (!trim($in)) {
             return '';
        }

        $rowsin = explode("\n", $in);
        $rowsout = array();
        foreach ($rowsin as $key => $row) {
            $cleanrow = trim($row);
            if ($cleanrow) {
                $rowsout[] = $cleanrow;
            }
        }

        return '[' . implode(',', $rowsout) . ']';
    }

    protected function tokenize_list($in) {
        return stack_utils::list_to_array($in, false);
    }

    /**
     * Return the default values for the options. Using this is optional, in this
     * base class implementation, no default options are set.
     * @return array option => default value.
     */
    public static function get_parameters_defaults() {
        return array(
            'mustVerify'     => true,
            'hideFeedback'   => false,
            'boxWidth'       => 20,
            'boxHeight'      => 5,
            'strictSyntax'  => true,
            'insertStars'    => false,
            'syntaxHint'     => '',
            'forbidWords'    => '',
            'forbidFloats'   => true,
            'lowestTerms'    => true,
            'sameType'       => true);
    }

    /**
     * Each actual extension of this base class must decide what parameter values are valid 
     * @return array of parameters names.
     */
    public function internal_validate_parameter($parameter, $value) {
        $valid = true;
        switch($parameter) {
            case 'boxWidth':
                $valid = is_int($value) && $value>0;
                break;

            case 'boxHeight':
                $valid = is_int($value) && $value>0;
                break;
        }
        return $valid;
    }
}
