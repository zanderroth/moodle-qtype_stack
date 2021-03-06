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

require_once(dirname(__FILE__) . '/../../../locallib.php');
require_once(dirname(__FILE__) . '/../cassession.class.php');
require_once(dirname(__FILE__) . '/../keyval.class.php');

class stack_cas_keyval_test extends UnitTestCase {

    public function get_valid($s, $val, $session) {
        $at1 = new stack_cas_keyval($s, null, 123, 's', true, false);
        $at1->instantiate();
        $this->assertEqual($val, $at1->get_valid());

        $atsession = $at1->get_session();
        $this->assertEqual($session, $atsession);
    }

    public function test_get_valid() {

        $cs0 = new stack_cas_session(null, null, 123);
        $cs0->instantiate();

        $a1=array('a:x^2', 'b:(x+1)^2');
        $s1=array();
        foreach ($a1 as $s) {
            $s1[] = new stack_cas_casstring($s);
        }
        $cs1 = new stack_cas_session($s1, null, 123);
        $cs1->instantiate();

        $a2=array('a:x^2)', 'b:(x+1)^2');
        $s2=array();
        foreach ($a2 as $s) {
            $s2[] = new stack_cas_casstring($s);
        }
        $cs2 = new stack_cas_session($s2, null, 123);
        $cs2->instantiate();

        $a3=array('a:1/0');
        $s3=array();
        foreach ($a3 as $s) {
            $s3[] = new stack_cas_casstring($s);
        }
        $cs3 = new stack_cas_session($s3, null, 123);
        $cs3->instantiate();

        $cases = array(
                array('', true, $cs0),
                array("a=x^2 \n b=(x+1)^2", true, $cs1),
                array("a:x^2 \n b:(x+1)^2", true, $cs1),
                array("a=x^2; b=(x+1)^2", true, $cs1),
                array('a=x^2); b=(x+1)^2', false, $cs2),
                array('a=1/0', true, $cs3)
            );

        foreach ($cases as $case) {
            $this->get_valid($case[0], $case[1], $case[2]);
        }
    }

    public function test_empty_case_1() {
        $at1 = new stack_cas_keyval('', null, 123, 's', true, false);
        $this->assertTrue($at1->get_valid());
    }

    public function test_equations_1() {
        $at1 = new stack_cas_keyval('ta1 = x=1; ta2 = x^2-2*x=1', null, 123, 's', true, false);
        $at1->instantiate();
        $s = $at1->get_session();

        $this->assertEqual($s->get_value_key('ta1'), 'x = 1');
        $this->assertEqual($s->get_value_key('ta2'), 'x^2-2*x = 1');
    }

    public function test_remove_comment() {
        $at1 = new stack_cas_keyval("a=1\n /* This is a comment \n b:2\n */\n c=3", null, 123, 's', true, false);
        $this->assertTrue($at1->get_valid());

        $a3=array('a:1', 'c:3');
        $s3=array();
        foreach ($a3 as $s) {
            $s3[] = new stack_cas_casstring($s);
        }
        $cs3 = new stack_cas_session($s3, null, 123);
        $cs3->instantiate();
        $at1->instantiate();
        $this->assertEqual($cs3, $at1->get_session());
    }

    public function test_keyval_session_keyval_0() {
        $kvin = "";
        $at1 = new stack_cas_keyval($kvin, null, 123, 's', true, false);
        $session = $at1->get_session();
        $kvout = $session->get_keyval_representation();
        $this->assertEqual($kvin, $kvout);
    }

    public function test_keyval_session_keyval_1() {
        $kvin = "a=1; c=3;";
        $at1 = new stack_cas_keyval($kvin, null, 123, 's', true, false);
        $session = $at1->get_session();
        $kvout = $session->get_keyval_representation();
        $this->assertEqual($kvin, $kvout);
    }

    public function test_keyval_session_keyval_2() {
        // Equation and function
        $kvin = "ans1=x^2-2*x=1; f(x):=x^2; sin(x^3);";
        $at1 = new stack_cas_keyval($kvin, null, 123, 's', true, false);
        $session = $at1->get_session();
        $kvout = $session->get_keyval_representation();
        $this->assertEqual($kvin, $kvout);
    }

    public function test_keyval_session_keyval_3() {
        // Inserting stars
        $kvin  = "a=2x; b=(x+1)(x-1); b=f(x);";
        $kvins = "a=2*x; b=(x+1)*(x-1); b=f(x);";
        $at1 = new stack_cas_keyval($kvin, null, 123, 's', false, true);
        $session = $at1->get_session();
        $kvout = $session->get_keyval_representation();

        $this->assertEqual($kvins, $kvout);
    }
}

class stack_cas_keyval_exception_test extends UnitTestCase
{
    public function test_exception_1() {
        $this->expectException();
        $at1 = new stack_cas_keyval(array(), false, false, false);
    }

    public function test_exception_2() {
        $this->expectException();
        $at1 = new stack_cas_keyval(1, false, false, false);
    }

    public function test_exception_3() {
        $this->expectException();
        $at1 = new stack_cas_keyval('x=1', false, false, false);
    }

    public function test_exception_4() {
        $this->expectException();
        $at1 = new stack_cas_keyval('x=1', null, false, false);
    }

    public function test_exception_5() {
        $this->expectException();
        $at1 = new stack_cas_keyval('x=1', 'z', false, false);
    }

    public function test_exception_6() {
        $this->expectException();
        $at1 = new stack_cas_keyval('x=1', 't', 1, false);
    }

    public function test_exception_7() {
        $this->expectException();
        $at1 = new stack_cas_keyval('x=1', 't', false, 1);
    }
}
