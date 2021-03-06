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
 * Unit tests for the stack_algebra_input class.
 *
 * @copyright  2012 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../factory.class.php');

/**
 * Unit tests for stack_algebra_input.
 *
 * @copyright  2012 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stack_algebra_input_test extends UnitTestCase {

    public function test_internal_validate_parameter() {
        $el = stack_input_factory::make('algebraic', 'input', 'x^2');
        $this->assertTrue($el->validate_parameter('boxWidth', 30));
        $this->assertFalse($el->validate_parameter('boxWidth', -10));
        $this->assertFalse($el->validate_parameter('boxWidth', "30"));
        $this->assertFalse($el->validate_parameter('boxWidth', ''));
        $this->assertFalse($el->validate_parameter('boxWidth', null));
    }

    public function test_render_blank() {
        $el = stack_input_factory::make('algebraic', 'ans1', 'x^2');
        $this->assertEqual('<input type="text" name="stack1__ans1" size="15" value="" />',
                $el->render(new stack_input_state(stack_input::VALID, '', '', ''),
                        'stack1__ans1', false));
    }

    public function test_render_zero() {
        $el = stack_input_factory::make('algebraic', 'ans1', '0');
        $this->assertEqual('<input type="text" name="stack1__ans1" size="15" value="0" />',
                $el->render(new stack_input_state(stack_input::VALID, '0', '', ''),
                        'stack1__ans1', false));
    }

    public function test_render_pre_filled() {
        $el = stack_input_factory::make('algebraic', 'test', 'x^2');
        $this->assertEqual('<input type="text" name="stack1__test" size="15" value="x+y" />',
                $el->render(new stack_input_state(stack_input::VALID, 'x+y', '', ''),
                        'stack1__test', false));
    }

    public function test_render_pre_filled_nasty_input() {
        $el = stack_input_factory::make('algebraic', 'test', 'x^2');
        $this->assertEqual('<input type="text" name="stack1__test" size="15" value="x&lt;y" />',
                $el->render(new stack_input_state(stack_input::VALID, 'x<y', '', ''),
                        'stack1__test', false));
    }

    public function test_render_max_length() {
        $el = stack_input_factory::make('algebraic', 'test', 'x^2');
        $this->assertEqual('<input type="text" name="stack1__test" size="15" value="x+y" />',
                $el->render(new stack_input_state(stack_input::VALID, 'x+y', '', ''),
                        'stack1__test', false));
    }

    public function test_render_disabled() {
        $el = stack_input_factory::make('algebraic', 'input', 'x^2');
        $this->assertEqual('<input type="text" name="stack1__input" size="15" value="x+1" readonly="readonly" />',
                $el->render(new stack_input_state(stack_input::VALID, 'x+1', '', ''),
                        'stack1__input', true));
    }

    public function test_render_different_size() {
        $el = stack_input_factory::make('algebraic', 'input', 'x^2');
        $el->set_parameter('boxWidth', 30);
        $this->assertEqual('<input type="text" name="stack1__input" size="30" value="x+1" />',
                $el->render(new stack_input_state(stack_input::VALID, 'x+1', '', ''),
                        'stack1__input', false));
    }

    public function test_render_syntaxhint() {
        $el = stack_input_factory::make('algebraic', 'sans1', '[a, b, c]');
        $el->set_parameter('syntaxHint', '[?, ?, ?]');
        $this->assertEqual('<input type="text" name="stack1__sans1" size="15" value="[?, ?, ?]" />',
                $el->render(new stack_input_state(stack_input::BLANK, '', '', ''),
                        'stack1__sans1', false));
    }

    public function test_validate_student_response_1() {
        $options = new stack_options();
        $el = stack_input_factory::make('algebraic', 'sans1', 'x^2/(1+x^2)');
        $state = $el->validate_student_response(array('sans1' => 'x^2'), $options, 'x^2/(1+x^2)', null);
        $this->assertEqual(stack_input::VALID, $state->status);
    }

    public function test_validate_student_response_2() {
        $options = new stack_options();
        $el = stack_input_factory::make('algebraic', 'sans1', 'x^2/(1+x^2)');
        $state = $el->validate_student_response(array('sans1' => '2x(1+x^2)'), $options, 'x^2/(1+x^2)', null);
        $this->assertEqual(stack_input::INVALID, $state->status);
    }

    public function test_validate_student_response_3() {
        $options = new stack_options();
        $el = stack_input_factory::make('algebraic', 'sans1', 'x^2/(1+x^2)');
        $el->set_parameter('insertStars', true);
        $el->set_parameter('strictSyntax', false);
        $state = $el->validate_student_response(array('sans1' => '2x', 'sans1_val' => '2x'), $options, 'x^2/(1+x^2)', array());
        $this->assertEqual(stack_input::SCORE, $state->status);
    }

    public function test_validate_student_response_4() {
        $options = new stack_options();
        $el = stack_input_factory::make('algebraic', 'sans1', 'x^2/(1+x^2)');
        $el->set_parameter('insertStars', true);
        $el->set_parameter('strictSyntax', false);
        $state = $el->validate_student_response(array('sans1' => '2x(1+x^2)'), $options, 'x^2/(1+x^2)', array('ta'));
        $this->assertEqual(stack_input::VALID, $state->status);
    }

    public function test_validate_student_response_5() {
        $options = new stack_options();
        $el = stack_input_factory::make('algebraic', 'sans1', 'x^2/(1+x^2)');
        $el->set_parameter('insertStars', true);
        $el->set_parameter('strictSyntax', false);
        $state = $el->validate_student_response(array('sans1' => '2x(1+x^2)+tans'), $options, 'x^2/(1+x^2)', array('tans'));
        $this->assertEqual(stack_input::INVALID, $state->status);
    }
}
