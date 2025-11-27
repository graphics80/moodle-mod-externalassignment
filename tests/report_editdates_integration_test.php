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
 * Unit tests for the report_editdates integration class.
 *
 * @group mod_externalassignment
 * @package mod_externalassignment
 * @category test
 * @copyright 2024 Marcel Suter <marcel.suter@bzz.ch>
 * @copyright 2024 Kevin Maurizi <kevin.maurizi@bzz.ch>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report_editdates_integration_test extends \advanced_testcase {

    /**
     * Test the constructor and basic functionality.
     *
     * @covers \mod_externalassignment_report_editdates_integration::__construct
     */
    public function test_constructor(): void {
        global $CFG;

        // Skip if report_editdates is not installed.
        if (!file_exists($CFG->dirroot . '/report/editdates/lib.php')) {
            $this->markTestSkipped('report_editdates plugin is not installed.');
        }

        require_once($CFG->dirroot . '/report/editdates/lib.php');
        require_once($CFG->dirroot . '/mod/externalassignment/classes/report_editdates_integration.php');

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_externalassignment');
        $generator->create_instance(['course' => $course->id]);

        $integration = new \mod_externalassignment_report_editdates_integration($course);
        $this->assertInstanceOf(\mod_externalassignment_report_editdates_integration::class, $integration);
    }

    /**
     * Test get_settings returns correct date fields.
     *
     * @covers \mod_externalassignment_report_editdates_integration::get_settings
     */
    public function test_get_settings(): void {
        global $CFG;

        // Skip if report_editdates is not installed.
        if (!file_exists($CFG->dirroot . '/report/editdates/lib.php')) {
            $this->markTestSkipped('report_editdates plugin is not installed.');
        }

        require_once($CFG->dirroot . '/report/editdates/lib.php');
        require_once($CFG->dirroot . '/mod/externalassignment/classes/report_editdates_integration.php');

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_externalassignment');

        $now = time();
        $instance = $generator->create_instance([
            'course' => $course->id,
            'allowsubmissionsfromdate' => $now,
            'duedate' => $now + DAYSECS,
            'cutoffdate' => $now + (2 * DAYSECS),
        ]);

        $cm = get_coursemodule_from_instance('externalassignment', $instance->id);
        $modinfo = get_fast_modinfo($course);
        $cminfo = $modinfo->get_cm($cm->id);

        $integration = new \mod_externalassignment_report_editdates_integration($course);
        $settings = $integration->get_settings($cminfo);

        // Check that all three date fields are returned.
        $this->assertArrayHasKey('allowsubmissionsfromdate', $settings);
        $this->assertArrayHasKey('duedate', $settings);
        $this->assertArrayHasKey('cutoffdate', $settings);

        // Check that the settings are report_editdates_date_setting objects.
        $this->assertInstanceOf(\report_editdates_date_setting::class, $settings['allowsubmissionsfromdate']);
        $this->assertInstanceOf(\report_editdates_date_setting::class, $settings['duedate']);
        $this->assertInstanceOf(\report_editdates_date_setting::class, $settings['cutoffdate']);

        // Check the current values match what was set.
        $this->assertEquals($now, $settings['allowsubmissionsfromdate']->currentvalue);
        $this->assertEquals($now + DAYSECS, $settings['duedate']->currentvalue);
        $this->assertEquals($now + (2 * DAYSECS), $settings['cutoffdate']->currentvalue);

        // Check that dates are optional (can be disabled).
        $this->assertTrue($settings['allowsubmissionsfromdate']->isoptional);
        $this->assertTrue($settings['duedate']->isoptional);
        $this->assertTrue($settings['cutoffdate']->isoptional);
    }

    /**
     * Test validate_dates with valid dates.
     *
     * @covers \mod_externalassignment_report_editdates_integration::validate_dates
     */
    public function test_validate_dates_valid(): void {
        global $CFG;

        // Skip if report_editdates is not installed.
        if (!file_exists($CFG->dirroot . '/report/editdates/lib.php')) {
            $this->markTestSkipped('report_editdates plugin is not installed.');
        }

        require_once($CFG->dirroot . '/report/editdates/lib.php');
        require_once($CFG->dirroot . '/mod/externalassignment/classes/report_editdates_integration.php');

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_externalassignment');
        $instance = $generator->create_instance(['course' => $course->id]);

        $cm = get_coursemodule_from_instance('externalassignment', $instance->id);
        $modinfo = get_fast_modinfo($course);
        $cminfo = $modinfo->get_cm($cm->id);

        $integration = new \mod_externalassignment_report_editdates_integration($course);

        $now = time();
        $dates = [
            'allowsubmissionsfromdate' => $now,
            'duedate' => $now + DAYSECS,
            'cutoffdate' => $now + (2 * DAYSECS),
        ];

        $errors = $integration->validate_dates($cminfo, $dates);
        $this->assertEmpty($errors);
    }

    /**
     * Test validate_dates with due date before allow submissions from date.
     *
     * @covers \mod_externalassignment_report_editdates_integration::validate_dates
     */
    public function test_validate_dates_duedate_before_allowsubmissionsfromdate(): void {
        global $CFG;

        // Skip if report_editdates is not installed.
        if (!file_exists($CFG->dirroot . '/report/editdates/lib.php')) {
            $this->markTestSkipped('report_editdates plugin is not installed.');
        }

        require_once($CFG->dirroot . '/report/editdates/lib.php');
        require_once($CFG->dirroot . '/mod/externalassignment/classes/report_editdates_integration.php');

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_externalassignment');
        $instance = $generator->create_instance(['course' => $course->id]);

        $cm = get_coursemodule_from_instance('externalassignment', $instance->id);
        $modinfo = get_fast_modinfo($course);
        $cminfo = $modinfo->get_cm($cm->id);

        $integration = new \mod_externalassignment_report_editdates_integration($course);

        $now = time();
        $dates = [
            'allowsubmissionsfromdate' => $now + DAYSECS,
            'duedate' => $now,
            'cutoffdate' => $now + (2 * DAYSECS),
        ];

        $errors = $integration->validate_dates($cminfo, $dates);
        $this->assertArrayHasKey('duedate', $errors);
        $this->assertEquals(get_string('duedateaftersubmissionvalidation', 'externalassignment'), $errors['duedate']);
    }

    /**
     * Test validate_dates with cutoff date before due date.
     *
     * @covers \mod_externalassignment_report_editdates_integration::validate_dates
     */
    public function test_validate_dates_cutoffdate_before_duedate(): void {
        global $CFG;

        // Skip if report_editdates is not installed.
        if (!file_exists($CFG->dirroot . '/report/editdates/lib.php')) {
            $this->markTestSkipped('report_editdates plugin is not installed.');
        }

        require_once($CFG->dirroot . '/report/editdates/lib.php');
        require_once($CFG->dirroot . '/mod/externalassignment/classes/report_editdates_integration.php');

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_externalassignment');
        $instance = $generator->create_instance(['course' => $course->id]);

        $cm = get_coursemodule_from_instance('externalassignment', $instance->id);
        $modinfo = get_fast_modinfo($course);
        $cminfo = $modinfo->get_cm($cm->id);

        $integration = new \mod_externalassignment_report_editdates_integration($course);

        $now = time();
        $dates = [
            'allowsubmissionsfromdate' => $now,
            'duedate' => $now + (2 * DAYSECS),
            'cutoffdate' => $now + DAYSECS,
        ];

        $errors = $integration->validate_dates($cminfo, $dates);
        $this->assertArrayHasKey('cutoffdate', $errors);
        $this->assertEquals(get_string('cutoffdatevalidation', 'externalassignment'), $errors['cutoffdate']);
    }

    /**
     * Test validate_dates with cutoff date before allow submissions from date.
     *
     * @covers \mod_externalassignment_report_editdates_integration::validate_dates
     */
    public function test_validate_dates_cutoffdate_before_allowsubmissionsfromdate(): void {
        global $CFG;

        // Skip if report_editdates is not installed.
        if (!file_exists($CFG->dirroot . '/report/editdates/lib.php')) {
            $this->markTestSkipped('report_editdates plugin is not installed.');
        }

        require_once($CFG->dirroot . '/report/editdates/lib.php');
        require_once($CFG->dirroot . '/mod/externalassignment/classes/report_editdates_integration.php');

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_externalassignment');
        $instance = $generator->create_instance(['course' => $course->id]);

        $cm = get_coursemodule_from_instance('externalassignment', $instance->id);
        $modinfo = get_fast_modinfo($course);
        $cminfo = $modinfo->get_cm($cm->id);

        $integration = new \mod_externalassignment_report_editdates_integration($course);

        $now = time();
        $dates = [
            'allowsubmissionsfromdate' => $now + (2 * DAYSECS),
            'duedate' => 0,
            'cutoffdate' => $now + DAYSECS,
        ];

        $errors = $integration->validate_dates($cminfo, $dates);
        $this->assertArrayHasKey('cutoffdate', $errors);
        $this->assertEquals(get_string('cutoffdatefromdatevalidation', 'externalassignment'), $errors['cutoffdate']);
    }

    /**
     * Test validate_dates with empty dates (all disabled).
     *
     * @covers \mod_externalassignment_report_editdates_integration::validate_dates
     */
    public function test_validate_dates_empty(): void {
        global $CFG;

        // Skip if report_editdates is not installed.
        if (!file_exists($CFG->dirroot . '/report/editdates/lib.php')) {
            $this->markTestSkipped('report_editdates plugin is not installed.');
        }

        require_once($CFG->dirroot . '/report/editdates/lib.php');
        require_once($CFG->dirroot . '/mod/externalassignment/classes/report_editdates_integration.php');

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_externalassignment');
        $instance = $generator->create_instance(['course' => $course->id]);

        $cm = get_coursemodule_from_instance('externalassignment', $instance->id);
        $modinfo = get_fast_modinfo($course);
        $cminfo = $modinfo->get_cm($cm->id);

        $integration = new \mod_externalassignment_report_editdates_integration($course);

        $dates = [
            'allowsubmissionsfromdate' => 0,
            'duedate' => 0,
            'cutoffdate' => 0,
        ];

        $errors = $integration->validate_dates($cminfo, $dates);
        $this->assertEmpty($errors);
    }

    /**
     * Test save_dates updates the database correctly.
     *
     * @covers \mod_externalassignment_report_editdates_integration::save_dates
     */
    public function test_save_dates(): void {
        global $CFG, $DB;

        // Skip if report_editdates is not installed.
        if (!file_exists($CFG->dirroot . '/report/editdates/lib.php')) {
            $this->markTestSkipped('report_editdates plugin is not installed.');
        }

        require_once($CFG->dirroot . '/report/editdates/lib.php');
        require_once($CFG->dirroot . '/mod/externalassignment/classes/report_editdates_integration.php');

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_externalassignment');
        $instance = $generator->create_instance([
            'course' => $course->id,
            'allowsubmissionsfromdate' => 0,
            'duedate' => 0,
            'cutoffdate' => 0,
        ]);

        $cm = get_coursemodule_from_instance('externalassignment', $instance->id);
        $modinfo = get_fast_modinfo($course);
        $cminfo = $modinfo->get_cm($cm->id);

        $integration = new \mod_externalassignment_report_editdates_integration($course);

        $now = time();
        $dates = [
            'allowsubmissionsfromdate' => $now,
            'duedate' => $now + DAYSECS,
            'cutoffdate' => $now + (2 * DAYSECS),
        ];

        $integration->save_dates($cminfo, $dates);

        // Verify the dates were saved.
        $record = $DB->get_record('externalassignment', ['id' => $instance->id]);
        $this->assertEquals($now, $record->allowsubmissionsfromdate);
        $this->assertEquals($now + DAYSECS, $record->duedate);
        $this->assertEquals($now + (2 * DAYSECS), $record->cutoffdate);
        $this->assertGreaterThanOrEqual($now, $record->timemodified);
    }

    /**
     * Test save_dates with zero/disabled dates.
     *
     * @covers \mod_externalassignment_report_editdates_integration::save_dates
     */
    public function test_save_dates_disabled(): void {
        global $CFG, $DB;

        // Skip if report_editdates is not installed.
        if (!file_exists($CFG->dirroot . '/report/editdates/lib.php')) {
            $this->markTestSkipped('report_editdates plugin is not installed.');
        }

        require_once($CFG->dirroot . '/report/editdates/lib.php');
        require_once($CFG->dirroot . '/mod/externalassignment/classes/report_editdates_integration.php');

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_externalassignment');

        $now = time();
        $instance = $generator->create_instance([
            'course' => $course->id,
            'allowsubmissionsfromdate' => $now,
            'duedate' => $now + DAYSECS,
            'cutoffdate' => $now + (2 * DAYSECS),
        ]);

        $cm = get_coursemodule_from_instance('externalassignment', $instance->id);
        $modinfo = get_fast_modinfo($course);
        $cminfo = $modinfo->get_cm($cm->id);

        $integration = new \mod_externalassignment_report_editdates_integration($course);

        $dates = [
            'allowsubmissionsfromdate' => 0,
            'duedate' => 0,
            'cutoffdate' => 0,
        ];

        $integration->save_dates($cminfo, $dates);

        // Verify the dates were saved as zero.
        $record = $DB->get_record('externalassignment', ['id' => $instance->id]);
        $this->assertEquals(0, $record->allowsubmissionsfromdate);
        $this->assertEquals(0, $record->duedate);
        $this->assertEquals(0, $record->cutoffdate);
    }
}
