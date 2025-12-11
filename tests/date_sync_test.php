<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_externalassignment;

/**
 * Unit tests for date sync functionality
 * @group mod_externalassignment
 * @package mod_externalassignment
 * @category test
 * @copyright 2024 Marcel Suter <marcel@ghwalin.ch>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class date_sync_test extends \advanced_testcase {

    /**
     * Test that due date and cutoff date can be set to the same value
     * @covers \mod_externalassignment_mod_form::validation
     */
    public function test_same_duedate_and_cutoffdate(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_externalassignment');
        
        // Create an instance with the same duedate and cutoffdate
        $sametime = time() + (7 * 24 * 60 * 60); // 7 days from now
        $instance = $generator->create_instance([
            'course' => $course->id,
            'duedate' => $sametime,
            'cutoffdate' => $sametime,
        ]);

        $this->assertNotEmpty($instance->id);
        $this->assertEquals($sametime, $instance->duedate);
        $this->assertEquals($sametime, $instance->cutoffdate);
    }

    /**
     * Test that validation allows cutoff date equal to due date
     * @covers \mod_externalassignment_mod_form::validation
     */
    public function test_validation_allows_equal_dates(): void {
        global $DB;
        
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_externalassignment');
        $instance = $generator->create_instance(['course' => $course->id]);

        $module = get_coursemodule_from_instance('externalassignment', $instance->id);
        $context = \context_module::instance($module->id);

        // Create form data with equal dates
        $sametime = time() + (7 * 24 * 60 * 60);
        $formdata = [
            'instance' => $instance->id,
            'coursemodule' => $module->id,
            'course' => $course->id,
            'name' => 'Test Assignment',
            'intro' => 'Test Description',
            'introformat' => 1,
            'externalname' => 'test',
            'externallink' => 'http://example.com',
            'allowsubmissionsfromdate' => time(),
            'duedate' => $sametime,
            'cutoffdate' => $sametime,
            'externalgrademax' => 100,
            'manualgrademax' => 10,
            'passingpercentage' => 60,
        ];

        $form = new \mod_externalassignment_mod_form(null, ['current' => $module]);
        $errors = $form->validation($formdata, []);

        // There should be no validation errors when dates are equal
        $this->assertArrayNotHasKey('cutoffdate', $errors);
        $this->assertArrayNotHasKey('duedate', $errors);
    }

    /**
     * Test that validation still fails if cutoff date is before due date
     * @covers \mod_externalassignment_mod_form::validation
     */
    public function test_validation_fails_cutoff_before_due(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_externalassignment');
        $instance = $generator->create_instance(['course' => $course->id]);

        $module = get_coursemodule_from_instance('externalassignment', $instance->id);
        
        // Create form data with cutoff before due date
        $duedate = time() + (7 * 24 * 60 * 60);
        $cutoffdate = time() + (5 * 24 * 60 * 60); // 2 days before due date
        $formdata = [
            'instance' => $instance->id,
            'coursemodule' => $module->id,
            'course' => $course->id,
            'name' => 'Test Assignment',
            'intro' => 'Test Description',
            'introformat' => 1,
            'externalname' => 'test',
            'externallink' => 'http://example.com',
            'allowsubmissionsfromdate' => time(),
            'duedate' => $duedate,
            'cutoffdate' => $cutoffdate,
            'externalgrademax' => 100,
            'manualgrademax' => 10,
            'passingpercentage' => 60,
        ];

        $form = new \mod_externalassignment_mod_form(null, ['current' => $module]);
        $errors = $form->validation($formdata, []);

        // There should be a validation error for cutoffdate
        $this->assertArrayHasKey('cutoffdate', $errors);
    }

    /**
     * Test that JavaScript module is loaded on the form
     * @covers \mod_externalassignment_mod_form::definition
     */
    public function test_javascript_module_loaded(): void {
        global $PAGE;
        
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        
        // Create a new form instance which should load the JavaScript
        $form = new \mod_externalassignment_mod_form(null, ['course' => $course]);
        
        // The form's definition() method should be called during construction
        // We can't directly test if JavaScript was added to the page, but we can
        // verify the form was created without errors
        $this->assertInstanceOf(\mod_externalassignment_mod_form::class, $form);
    }

    /**
     * Test updating an instance with same due date and cutoff date
     * @covers \mod_externalassignment\local\assign_control::update_instance
     */
    public function test_update_instance_with_same_dates(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_externalassignment');
        $instance = $generator->create_instance(['course' => $course->id]);

        $module = get_coursemodule_from_instance('externalassignment', $instance->id);
        $context = \context_module::instance($module->id);

        $assigncontrol = new \mod_externalassignment\local\assign_control($context, $module);

        // Update with same dates
        $sametime = time() + (7 * 24 * 60 * 60);
        $formdata = new \stdClass();
        $formdata->instance = $instance->id;
        $formdata->course = $course->id;
        $formdata->coursemodule = $module->id;
        $formdata->name = 'Updated Assignment';
        $formdata->intro = 'Updated Description';
        $formdata->introformat = 1;
        $formdata->alwaysshowdescription = true;
        $formdata->externalname = 'updated';
        $formdata->externallink = 'http://example.com';
        $formdata->alwaysshowlink = true;
        $formdata->allowsubmissionsfromdate = time();
        $formdata->duedate = $sametime;
        $formdata->cutoffdate = $sametime;
        $formdata->externalgrademax = 100;
        $formdata->manualgrademax = 10;
        $formdata->passingpercentage = 60;
        $formdata->needspassinggrade = 1;

        $result = $assigncontrol->update_instance($formdata, $module->id);

        $this->assertTrue($result);

        $record = $DB->get_record('externalassignment', ['id' => $instance->id]);
        $this->assertEquals($sametime, $record->duedate);
        $this->assertEquals($sametime, $record->cutoffdate);
    }
}
