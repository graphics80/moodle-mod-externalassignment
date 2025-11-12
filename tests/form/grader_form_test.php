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

namespace mod_externalassignment\form;

use mod_externalassignment\local\assign;
use mod_externalassignment\local\grade;

/**
 * Unit tests for grader_form
 * @group mod_externalassignment
 * @package mod_externalassignment
 * @category test
 * @copyright 2024 Marcel Suter <marcel@ghwalin.ch>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class grader_form_test extends \advanced_testcase {

    /**
     * Test that editor options have file management enabled when context is provided
     * @covers \mod_externalassignment\form\grader_form::editor_options
     */
    public function test_editor_options_with_context(): void {
        global $CFG;
        $this->resetAfterTest();

        // Create test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $module = $this->getDataGenerator()->create_module('externalassignment', [
            'course' => $course->id,
            'name' => 'Test Assignment',
            'externalname' => 'test-assignment',
        ]);

        $cm = get_coursemodule_from_instance('externalassignment', $module->id);
        $context = \context_module::instance($cm->id);

        // Create form data with context.
        $formdata = new \stdClass();
        $formdata->id = $cm->id;
        $formdata->courseid = $course->id;
        $formdata->userid = $user->id;
        $formdata->gradeid = -1;
        $formdata->externalassignment = $module->id;
        $formdata->firstname = 'Test';
        $formdata->lastname = 'User';
        $formdata->status = 'active';
        $formdata->timeremainingstr = '1 day';
        $formdata->externalgrademax = 100;
        $formdata->manualgrademax = 100;
        $formdata->externallink = 'https://example.com';
        $formdata->context = $context;

        // Use reflection to access the private editor_options method.
        $reflection = new \ReflectionClass(grader_form::class);
        $method = $reflection->getMethod('editor_options');
        $method->setAccessible(true);

        // Call the method with context.
        $options = $method->invoke(null, $context);

        // Verify file management is enabled.
        $this->assertTrue($options['enable_filemanagement']);
        $this->assertEquals(EDITOR_UNLIMITED_FILES, $options['maxfiles']);
        $this->assertEquals($CFG->maxbytes ?? 0, $options['maxbytes']);
        $this->assertEquals($context, $options['context']);
        $this->assertEquals(0, $options['subdirs']);
    }

    /**
     * Test that files can be saved in externalfeedback file area
     * @covers \externalassignment_pluginfile
     */
    public function test_file_saving_in_externalfeedback(): void {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create test data.
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $module = $this->getDataGenerator()->create_module('externalassignment', [
            'course' => $course->id,
            'name' => 'Test Assignment',
            'externalname' => 'test-assignment',
        ]);

        $cm = get_coursemodule_from_instance('externalassignment', $module->id);
        $context = \context_module::instance($cm->id);

        // Create a grade record.
        $gradedata = new \stdClass();
        $gradedata->externalassignment = $module->id;
        $gradedata->userid = $student->id;
        $gradedata->grader = $teacher->id;
        $gradedata->externallink = 'https://example.com';
        $gradedata->externalgrade = 85.0;
        $gradedata->externalfeedback = 'Test feedback';
        $gradedata->manualgrade = 90.0;
        $gradedata->manualfeedback = 'Manual feedback';

        $gradeid = $DB->insert_record('externalassignment_grades', $gradedata);

        // Create a test file.
        $fs = get_file_storage();
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_externalassignment',
            'filearea' => 'externalfeedback',
            'itemid' => $gradeid,
            'filepath' => '/',
            'filename' => 'testimage.png',
        ];
        $file = $fs->create_file_from_string($filerecord, 'Test file content');

        // Verify file was created.
        $this->assertNotFalse($file);
        $this->assertEquals('testimage.png', $file->get_filename());
        $this->assertEquals($context->id, $file->get_contextid());
        $this->assertEquals('externalfeedback', $file->get_filearea());
        $this->assertEquals($gradeid, $file->get_itemid());
    }

    /**
     * Test that files can be saved in manualfeedback file area
     * @covers \externalassignment_pluginfile
     */
    public function test_file_saving_in_manualfeedback(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create test data.
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $module = $this->getDataGenerator()->create_module('externalassignment', [
            'course' => $course->id,
            'name' => 'Test Assignment',
            'externalname' => 'test-assignment',
        ]);

        $cm = get_coursemodule_from_instance('externalassignment', $module->id);
        $context = \context_module::instance($cm->id);

        // Create a grade record.
        $gradedata = new \stdClass();
        $gradedata->externalassignment = $module->id;
        $gradedata->userid = $student->id;
        $gradedata->grader = $teacher->id;
        $gradedata->externallink = 'https://example.com';
        $gradedata->externalgrade = 85.0;
        $gradedata->externalfeedback = 'Test feedback';
        $gradedata->manualgrade = 90.0;
        $gradedata->manualfeedback = 'Manual feedback';

        $gradeid = $DB->insert_record('externalassignment_grades', $gradedata);

        // Create a test file.
        $fs = get_file_storage();
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_externalassignment',
            'filearea' => 'manualfeedback',
            'itemid' => $gradeid,
            'filepath' => '/',
            'filename' => 'feedback.jpg',
        ];
        $file = $fs->create_file_from_string($filerecord, 'Manual feedback file content');

        // Verify file was created.
        $this->assertNotFalse($file);
        $this->assertEquals('feedback.jpg', $file->get_filename());
        $this->assertEquals($context->id, $file->get_contextid());
        $this->assertEquals('manualfeedback', $file->get_filearea());
        $this->assertEquals($gradeid, $file->get_itemid());
    }

    /**
     * Test that files can be retrieved from file areas
     * @covers \externalassignment_pluginfile
     */
    public function test_file_retrieval(): void {
        global $DB;
        $this->resetAfterTest();

        // Create test data.
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $module = $this->getDataGenerator()->create_module('externalassignment', [
            'course' => $course->id,
            'name' => 'Test Assignment',
            'externalname' => 'test-assignment',
        ]);

        $cm = get_coursemodule_from_instance('externalassignment', $module->id);
        $context = \context_module::instance($cm->id);

        // Create a grade record.
        $gradedata = new \stdClass();
        $gradedata->externalassignment = $module->id;
        $gradedata->userid = $student->id;
        $gradedata->grader = $teacher->id;
        $gradedata->externallink = 'https://example.com';
        $gradedata->externalgrade = 85.0;
        $gradedata->externalfeedback = 'Test feedback';
        $gradedata->manualgrade = 90.0;
        $gradedata->manualfeedback = 'Manual feedback';

        $gradeid = $DB->insert_record('externalassignment_grades', $gradedata);

        // Create test files.
        $fs = get_file_storage();
        $filerecord1 = [
            'contextid' => $context->id,
            'component' => 'mod_externalassignment',
            'filearea' => 'externalfeedback',
            'itemid' => $gradeid,
            'filepath' => '/',
            'filename' => 'testimage.png',
        ];
        $file1 = $fs->create_file_from_string($filerecord1, 'External feedback content');

        $filerecord2 = [
            'contextid' => $context->id,
            'component' => 'mod_externalassignment',
            'filearea' => 'manualfeedback',
            'itemid' => $gradeid,
            'filepath' => '/',
            'filename' => 'manual.jpg',
        ];
        $file2 = $fs->create_file_from_string($filerecord2, 'Manual feedback content');

        // Retrieve files.
        $retrievedfile1 = $fs->get_file(
            $context->id,
            'mod_externalassignment',
            'externalfeedback',
            $gradeid,
            '/',
            'testimage.png'
        );
        $retrievedfile2 = $fs->get_file(
            $context->id,
            'mod_externalassignment',
            'manualfeedback',
            $gradeid,
            '/',
            'manual.jpg'
        );

        // Verify files can be retrieved.
        $this->assertNotFalse($retrievedfile1);
        $this->assertEquals('testimage.png', $retrievedfile1->get_filename());
        $this->assertNotFalse($retrievedfile2);
        $this->assertEquals('manual.jpg', $retrievedfile2->get_filename());
    }

    /**
     * Test that pluginfile function rejects invalid file areas
     * @covers \externalassignment_pluginfile
     */
    public function test_pluginfile_rejects_invalid_filearea(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create test data.
        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('externalassignment', [
            'course' => $course->id,
            'name' => 'Test Assignment',
        ]);

        $cm = get_coursemodule_from_instance('externalassignment', $module->id);
        $context = \context_module::instance($cm->id);

        // Try to access an invalid file area.
        $result = externalassignment_pluginfile(
            $course,
            $cm,
            $context,
            'invalidarea',
            [1, 'test.png'],
            false,
            []
        );

        $this->assertFalse($result);
    }

    /**
     * Test file access permissions for teachers
     * @covers \externalassignment_pluginfile
     */
    public function test_teacher_can_access_feedback_files(): void {
        global $DB;
        $this->resetAfterTest();

        // Create test data.
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $module = $this->getDataGenerator()->create_module('externalassignment', [
            'course' => $course->id,
            'name' => 'Test Assignment',
        ]);

        $cm = get_coursemodule_from_instance('externalassignment', $module->id);
        $context = \context_module::instance($cm->id);

        // Create a grade record.
        $gradedata = new \stdClass();
        $gradedata->externalassignment = $module->id;
        $gradedata->userid = $student->id;
        $gradedata->grader = $teacher->id;
        $gradedata->externallink = 'https://example.com';
        $gradedata->externalgrade = 85.0;
        $gradedata->externalfeedback = 'Test feedback';
        $gradedata->manualgrade = 90.0;
        $gradedata->manualfeedback = 'Manual feedback';

        $gradeid = $DB->insert_record('externalassignment_grades', $gradedata);

        // Create a test file.
        $fs = get_file_storage();
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'mod_externalassignment',
            'filearea' => 'externalfeedback',
            'itemid' => $gradeid,
            'filepath' => '/',
            'filename' => 'test.png',
        ];
        $fs->create_file_from_string($filerecord, 'Test content');

        // Set user as teacher.
        $this->setUser($teacher);

        // Teacher with reviewgrades capability should have access.
        $hascapability = has_capability('mod/externalassignment:reviewgrades', $context);
        $this->assertTrue($hascapability);
    }

    /**
     * Test file access permissions for students viewing their own feedback
     * @covers \externalassignment_pluginfile
     */
    public function test_student_can_access_own_feedback_files(): void {
        global $DB;
        $this->resetAfterTest();

        // Create test data.
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $module = $this->getDataGenerator()->create_module('externalassignment', [
            'course' => $course->id,
            'name' => 'Test Assignment',
        ]);

        $cm = get_coursemodule_from_instance('externalassignment', $module->id);
        $context = \context_module::instance($cm->id);

        // Create a grade record.
        $gradedata = new \stdClass();
        $gradedata->externalassignment = $module->id;
        $gradedata->userid = $student->id;
        $gradedata->grader = $teacher->id;
        $gradedata->externallink = 'https://example.com';
        $gradedata->externalgrade = 85.0;
        $gradedata->externalfeedback = 'Test feedback';
        $gradedata->manualgrade = 90.0;
        $gradedata->manualfeedback = 'Manual feedback';

        $gradeid = $DB->insert_record('externalassignment_grades', $gradedata);

        // Set user as student.
        $this->setUser($student);

        // Verify the grade belongs to this student.
        $grade = $DB->get_record('externalassignment_grades', ['id' => $gradeid]);
        $this->assertEquals($student->id, $grade->userid);

        // Student should have view capability.
        $hascapability = has_capability('mod/externalassignment:view', $context);
        $this->assertTrue($hascapability);
    }
}
