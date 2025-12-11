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

namespace mod_externalassignment\local;

/**
 * Unit tests for cutoffdate and needspassinggrade interaction
 *
 * Tests that verify the needspassinggrade completion rule is preserved
 * when cutoffdate is set.
 *
 * @group mod_externalassignment
 * @package mod_externalassignment
 * @category test
 * @copyright 2024 Marcel Suter <marcel@ghwalin.ch>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class cutoffdate_needspassinggrade_test extends \advanced_testcase {

    /**
     * Test that needspassinggrade is preserved when cutoffdate is set during creation
     *
     * @covers \mod_externalassignment\local\assign::__construct
     * @covers \mod_externalassignment\local\assign::load_data
     * @covers \mod_externalassignment\local\assign::get_needspassinggrade
     */
    public function test_needspassinggrade_with_cutoffdate_on_creation(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_externalassignment');

        // Create an instance with both cutoffdate and needspassinggrade set.
        $cutofftime = time() + 86400; // Tomorrow.
        $instance = $generator->create_instance([
            'course' => $course->id,
            'cutoffdate' => $cutofftime,
            'needspassinggrade' => 1,
        ]);

        // Load the instance and verify needspassinggrade is still set.
        $assign = new assign(null);
        $assign->load_db($instance->cmid);

        $this->assertEquals(1, $assign->get_needspassinggrade(),
            'needspassinggrade should be 1 when cutoffdate is set');
        $this->assertEquals($cutofftime, $assign->get_cutoffdate(),
            'cutoffdate should be preserved');
    }

    /**
     * Test that needspassinggrade is preserved when cutoffdate is updated
     *
     * @covers \mod_externalassignment\local\assign::__construct
     * @covers \mod_externalassignment\local\assign::load_data
     * @covers \mod_externalassignment\local\assign::get_needspassinggrade
     */
    public function test_needspassinggrade_preserved_when_cutoffdate_updated(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_externalassignment');

        // Create an instance with needspassinggrade but no cutoffdate.
        $instance = $generator->create_instance([
            'course' => $course->id,
            'cutoffdate' => 0,
            'needspassinggrade' => 1,
        ]);

        // Load and verify initial state.
        $assign = new assign(null);
        $assign->load_db($instance->cmid);
        $this->assertEquals(1, $assign->get_needspassinggrade(),
            'needspassinggrade should be 1 initially');
        $this->assertEquals(0, $assign->get_cutoffdate(),
            'cutoffdate should be 0 initially');

        // Now update the instance to add a cutoffdate.
        global $DB;
        $cutofftime = time() + 86400;
        $updatedata = $DB->get_record('externalassignment', ['id' => $instance->id]);
        $updatedata->cutoffdate = $cutofftime;
        $DB->update_record('externalassignment', $updatedata);

        // Reload and verify needspassinggrade is still set.
        $assign2 = new assign(null);
        $assign2->load_db($instance->cmid);
        $this->assertEquals(1, $assign2->get_needspassinggrade(),
            'needspassinggrade should remain 1 after cutoffdate is added');
        $this->assertEquals($cutofftime, $assign2->get_cutoffdate(),
            'cutoffdate should be updated');
    }

    /**
     * Test that needspassinggrade can be set independently of cutoffdate
     *
     * @covers \mod_externalassignment\local\assign::__construct
     * @covers \mod_externalassignment\local\assign::set_needspassinggrade
     * @covers \mod_externalassignment\local\assign::get_needspassinggrade
     * @covers \mod_externalassignment\local\assign::to_stdclass
     */
    public function test_needspassinggrade_independent_of_cutoffdate(): void {
        $this->resetAfterTest(true);

        // Test various combinations.
        $testcases = [
            ['cutoffdate' => 0, 'needspassinggrade' => 0],
            ['cutoffdate' => 0, 'needspassinggrade' => 1],
            ['cutoffdate' => time() + 86400, 'needspassinggrade' => 0],
            ['cutoffdate' => time() + 86400, 'needspassinggrade' => 1],
        ];

        foreach ($testcases as $testcase) {
            $formdata = new \stdClass();
            $formdata->instance = 1;
            $formdata->course = 1;
            $formdata->coursemodule = 1;
            $formdata->name = 'Test Assignment';
            $formdata->intro = 'Test';
            $formdata->introformat = 1;
            $formdata->alwaysshowdescription = 0;
            $formdata->externalname = 'test';
            $formdata->externallink = 'http://example.com';
            $formdata->alwaysshowlink = 0;
            $formdata->allowsubmissionsfromdate = 0;
            $formdata->duedate = 0;
            $formdata->cutoffdate = $testcase['cutoffdate'];
            $formdata->externalgrademax = 100;
            $formdata->manualgrademax = 0;
            $formdata->passingpercentage = 60;
            $formdata->needspassinggrade = $testcase['needspassinggrade'];

            $assign = new assign($formdata);

            $this->assertEquals($testcase['needspassinggrade'], $assign->get_needspassinggrade(),
                "needspassinggrade should be {$testcase['needspassinggrade']} " .
                "when cutoffdate is {$testcase['cutoffdate']}");
            $this->assertEquals($testcase['cutoffdate'], $assign->get_cutoffdate(),
                "cutoffdate should be {$testcase['cutoffdate']}");

            // Also verify to_stdclass preserves the values.
            $stdclass = $assign->to_stdclass();
            $this->assertEquals($testcase['needspassinggrade'], $stdclass->needspassinggrade,
                "to_stdclass should preserve needspassinggrade as {$testcase['needspassinggrade']}");
            $this->assertEquals($testcase['cutoffdate'], $stdclass->cutoffdate,
                "to_stdclass should preserve cutoffdate as {$testcase['cutoffdate']}");
        }
    }

    /**
     * Test full lifecycle: create, update cutoffdate, verify needspassinggrade remains
     *
     * @covers \mod_externalassignment\local\assign::__construct
     * @covers \mod_externalassignment\local\assign::load_db
     * @covers \mod_externalassignment\local\assign::get_needspassinggrade
     * @covers \mod_externalassignment\local\assign::get_cutoffdate
     */
    public function test_full_lifecycle_with_generator(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_externalassignment');

        // Step 1: Create instance with needspassinggrade = 1, no cutoffdate.
        $instance1 = $generator->create_instance([
            'course' => $course->id,
            'needspassinggrade' => 1,
            'cutoffdate' => 0,
        ]);

        $assign1 = new assign(null);
        $assign1->load_db($instance1->cmid);
        $this->assertEquals(1, $assign1->get_needspassinggrade(),
            'Step 1: needspassinggrade should be 1');
        $this->assertEquals(0, $assign1->get_cutoffdate(),
            'Step 1: cutoffdate should be 0');

        // Step 2: Update to add cutoffdate via database (simulating form submission).
        global $DB;
        $newcutofftime = time() + 172800; // 2 days from now.
        $record = $DB->get_record('externalassignment', ['id' => $instance1->id]);
        $record->cutoffdate = $newcutofftime;
        // Ensure needspassinggrade is explicitly set in the update.
        $record->needspassinggrade = 1;
        $DB->update_record('externalassignment', $record);

        // Step 3: Reload and verify both values are correct.
        $assign2 = new assign(null);
        $assign2->load_db($instance1->cmid);
        $this->assertEquals(1, $assign2->get_needspassinggrade(),
            'Step 3: needspassinggrade should still be 1 after adding cutoffdate');
        $this->assertEquals($newcutofftime, $assign2->get_cutoffdate(),
            'Step 3: cutoffdate should be updated to new value');

        // Step 4: Create another instance with both set from the start.
        $cutofftime2 = time() + 259200; // 3 days from now.
        $instance2 = $generator->create_instance([
            'course' => $course->id,
            'needspassinggrade' => 1,
            'cutoffdate' => $cutofftime2,
        ]);

        $assign3 = new assign(null);
        $assign3->load_db($instance2->cmid);
        $this->assertEquals(1, $assign3->get_needspassinggrade(),
            'Step 4: needspassinggrade should be 1 when both set at creation');
        $this->assertEquals($cutofftime2, $assign3->get_cutoffdate(),
            'Step 4: cutoffdate should be set to specified value');
    }
}
