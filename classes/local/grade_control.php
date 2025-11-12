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

use core\context;
use mod_externalassignment\form\grader_form;
use mod_externalassignment\form\override_form;

/**
 * Represents the model of an external assignment
 *
 * @package   mod_externalassignment
 * @copyright 2024 Marcel Suter <marcel.suter@bzz.ch>
 * @copyright 2024 Kevin Maurizi <kevin.maurizi@bzz.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_control {
    /** @var int  the coursemodule-id */
    private $coursemoduleid;

    /** @var int the course-id */
    private $courseid;

    /** @var context the context of the course module for this grade instance
     *               (or just the course if we are creating a new one)
     */
    private context $context;

    /** @var assign $assign the externalassignment instance this grade belongs to */
    private assign $assign;

    /** @var array A key used to identify userlists created by this object. *
     * private array $userlist;
     *
     * /** @var int The key to identify the user
     */
    private int $userid;

    /**
     * default constructor
     * @param $coursemoduleid
     * @param $context
     * @param int|null $userid
     * @throws \dml_exception
     */
    public function __construct($coursemoduleid, $context, ?int $userid = 0) {
        $this->set_coursemoduleid($coursemoduleid);
        $this->set_courseid($context->get_course_context()->instanceid);
        $this->set_context($context);
        $this->set_assign(new assign(null, $this->get_context()));
        $this->get_assign()->load_db($coursemoduleid, 'lastname', 'asc', $userid);
        $this->set_userid($userid);
    }

    /**
     * process the feedback form for a student
     * @return void
     * @throws \dml_exception
     * @throws \coding_exception
     * @throws \moodle_exception
     * @codeCoverageIgnore
     */
    public function process_feedback(): void {
        global $CFG;
        $student = $this->get_assign()->take_student($this->get_userid());
        $data = new \stdClass();

        $data->id = $this->coursemoduleid;
        $data->userid = $this->userid;
        $data->assignmentid = $this->get_assign()->get_id();
        $data->courseid = $this->courseid;
        $data->firstname = $student->get_firstname();
        $data->lastname = $student->get_lastname();
        $data->externalgrademax = $this->get_assign()->get_externalgrademax();
        $data->manualgrademax = $this->get_assign()->get_manualgrademax();

        $grade = $student->get_grade();
        if (!empty($student->get_grade())) {
            $data->gradeid = $grade->get_id();
        } else {
            $data->gradeid = -1;
        }
        $data->externalassignment = $this->get_assign()->get_id();
        $data->status = $student->get_status();
        $data->allowsubmissionsfromdate = $this->get_assign()->get_allowsubmissionsfromdate();
        $data->duedate = $this->get_assign()->get_duedate();
        if (!empty($student->get_override()) && $student->get_override()->get_duedate() != 0) {
            $data->duedate = $student->get_override()->get_duedate();
        }
        $data->cutoffdate = $this->get_assign()->get_cutoffdate();

        // Time remaining.
        $timeremaining = $data->duedate - time();
        $due = '';
        if ($timeremaining <= 0) {
            $due = get_string('assignmentisdue', 'externalassignment');
        } else {
            $due = get_string('timeremainingcolon', 'externalassignment', format_time($timeremaining));
        }
        $data->timeremainingstr = $due;

        require_once($CFG->dirroot . '/mod/externalassignment/classes/form/grader_form.php');
        $data->context = $this->get_context();
        $mform = new grader_form(null, $data);

        // Form processing and displaying is done here.
        if ($mform->is_cancelled()) {
            debugging('Cancelled');  // TODO MDL-1 reset the form.
        } else {
            if ($formdata = $mform->get_data()) {
                global $DB;
                $grade = new grade($formdata);
                if ($grade->get_id() == - 1) {
                    $grade->set_id($DB->insert_record('externalassignment_grades', $grade->to_stdclass()));
                } else {
                    $result = $DB->update_record('externalassignment_grades', $grade->to_stdclass());
                }

                // Save files for externalfeedback.
                $formdata = file_postupdate_standard_editor(
                    $formdata,
                    'externalfeedback',
                    [
                        'subdirs' => 0,
                        'maxbytes' => $CFG->maxbytes ?? 0,
                        'maxfiles' => EDITOR_UNLIMITED_FILES,
                        'context' => $this->get_context(),
                    ],
                    $this->get_context(),
                    'mod_externalassignment',
                    'externalfeedback',
                    $grade->get_id()
                );

                // Save files for manualfeedback.
                $formdata = file_postupdate_standard_editor(
                    $formdata,
                    'manualfeedback',
                    [
                        'subdirs' => 0,
                        'maxbytes' => $CFG->maxbytes ?? 0,
                        'maxfiles' => EDITOR_UNLIMITED_FILES,
                        'context' => $this->get_context(),
                    ],
                    $this->get_context(),
                    'mod_externalassignment',
                    'manualfeedback',
                    $grade->get_id()
                );

                // Update grade record with processed feedback text.
                $grade->set_externalfeedback($formdata->externalfeedback);
                $grade->set_manualfeedback($formdata->manualfeedback);
                $DB->update_record('externalassignment_grades', $grade->to_stdclass());

                $gradevalues = new \stdClass();
                $gradevalues->userid = $this->get_userid();
                $gradevalues->rawgrade = floatval($grade->get_externalgrade()) + floatval($grade->get_manualgrade());
                externalassignment_grade_item_update($this->get_assign()->to_stdclass(), $gradevalues);

                list ($course, $coursemodule) = get_course_and_cm_from_cmid($this->coursemoduleid, 'externalassignment');
                $completion = new \completion_info($course);
                if ($completion->is_enabled($coursemodule)) {
                    $completion->update_state($coursemodule, COMPLETION_UNKNOWN, $this->get_userid());
                }
                redirect(
                    new \moodle_url('view.php',
                        [
                            'id' => $this->coursemoduleid,
                            'action' => 'grader',
                            'userid' => $this->userid,
                        ]
                    )
                );
            } else {  // Display the form.
                $data->externalgrade = '';
                $data->manualgrade = '';
                $data->externallink = '';
                $data->externalfeedback['text'] = '';
                $data->externalfeedback['format'] = 1;
                $data->manualfeedback['text'] = 'TODO';
                $data->manualfeedback['format'] = 1;
                $data->gradefinal = 0;
                if (array_key_exists($this->get_userid(), $this->get_assign()->get_students())) {

                    if (!empty($student->get_grade())) {
                        $grade = $student->get_grade();
                        $data->gradeid = $grade->get_id();
                        $data->externalassignment = $grade->get_externalassignment();
                        $data->status = $student->get_status();
                        $data->externalgrade = $grade->get_externalgrade();

                        // Prepare draft file area for externalfeedback.
                        $draftitemid = file_get_submitted_draft_itemid('externalfeedback');
                        $data->externalfeedback['text'] = file_prepare_draft_area(
                            $draftitemid,
                            $this->get_context()->id,
                            'mod_externalassignment',
                            'externalfeedback',
                            $grade->get_id(),
                            [
                                'subdirs' => 0,
                                'maxbytes' => $CFG->maxbytes ?? 0,
                                'maxfiles' => EDITOR_UNLIMITED_FILES,
                            ],
                            $grade->get_externalfeedback()
                        );
                        $data->externalfeedback['itemid'] = $draftitemid;
                        $data->externalfeedback['format'] = FORMAT_HTML;

                        $data->manualgrade = $grade->get_manualgrade();

                        // Prepare draft file area for manualfeedback.
                        $draftitemid = file_get_submitted_draft_itemid('manualfeedback');
                        $data->manualfeedback['text'] = file_prepare_draft_area(
                            $draftitemid,
                            $this->get_context()->id,
                            'mod_externalassignment',
                            'manualfeedback',
                            $grade->get_id(),
                            [
                                'subdirs' => 0,
                                'maxbytes' => $CFG->maxbytes ?? 0,
                                'maxfiles' => EDITOR_UNLIMITED_FILES,
                            ],
                            $grade->get_manualfeedback()
                        );
                        $data->manualfeedback['itemid'] = $draftitemid;
                        $data->manualfeedback['format'] = FORMAT_HTML;

                        $data->gradefinal = $grade->get_externalgrade() + $grade->get_manualgrade();
                    }
                }
                $mform->set_data($data);
                $mform->display();
            }
        }
    }

    /**
     * process the override form
     * @param array $userids
     * @return void
     * @throws \dml_exception
     * @throws \coding_exception
     * @throws \moodle_exception
     * @codeCoverageIgnore
     */
    public function process_override(array $userids): void {
        global $CFG;

        $data = new \stdClass();
        $data->id = $this->coursemoduleid;
        $data->externalassignment = $this->get_assign()->get_id();
        $data->courseid = $this->courseid;
        $data->allowsubmissionsfromdate = $this->get_assign()->get_allowsubmissionsfromdate();
        $data->duedate = $this->get_assign()->get_duedate();
        $data->cutoffdate = $this->get_assign()->get_duedate();
        $data->users = [];
        foreach ($userids as $userid) {
            if (array_key_exists($userid, $this->get_assign()->get_students())) {
                $student = $this->get_assign()->get_students()[$userid];
                $data->users[] = $student;
            }
        }

        // Form processing and displaying is done here.
        $url = new \moodle_url('/mod/externalassignment/view.php', ['action' => 'override']);
        require_once($CFG->dirroot . '/mod/externalassignment/classes/form/override_form.php');
        $mform = new override_form($url->out(false), $this->get_assign(), $data);
        if ($mform->is_cancelled()) {
            debugging('Cancelled');  // FIXME reset the form.
        } else {
            if ($formdata = $mform->get_data()) {
                foreach ($formdata->uid as $userid) {
                    require_once($CFG->dirroot . '/mod/externalassignment/classes/local/override.php');
                    // FIXME: Find out why autoloading does not work here.
                    $override = new override();
                    $override->set_externalassignment($formdata->externalassignment);
                    $override->set_userid($userid);
                    $override->set_allowsubmissionsfromdate($formdata->allowsubmissionsfromdate);
                    $override->set_duedate($formdata->duedate);
                    $override->set_cutoffdate($formdata->cutoffdate);
                    $this->override_update($override);
                }
                $url = new \moodle_url(
                    '/mod/externalassignment/view.php',
                    [
                        'id' => $formdata->id,
                        'action' => 'grading',
                    ]
                );
                redirect($url);

            } else {
                $mform->set_data($data);
                $mform->display();
            }
        }
    }

    /**
     * inserts or updates a user override
     * @param override $override
     * @return void
     * @throws \dml_exception
     * @codeCoverageIgnore
     */
    private function override_update(override $override): void {
        global $DB;
        if ($record = $DB->get_record(
            'externalassignment_overrides',
            [
                'externalassignment' => $override->get_externalassignment(),
                'userid' => $override->get_userid(),
            ]
        )) {
            $override->set_id($record->id);
            $DB->update_record('externalassignment_overrides', $override->to_stdclass());
        } else {
            $DB->insert_record('externalassignment_overrides', $override->to_stdclass());
        }
    }


    /**
     * Gets the coursemoduleid
     * @return int
     */
    public function get_coursemoduleid(): int {
        return $this->coursemoduleid;
    }

    /**
     * Sets the coursemoduleid
     * @param int $coursemoduleid
     */
    public function set_coursemoduleid(int $coursemoduleid): void {
        $this->coursemoduleid = $coursemoduleid;
    }

    /**
     * Gets the courseid
     * @return int
     */
    public function get_courseid(): int {
        return $this->courseid;
    }

    /**
     * Sets the courseid
     * @param int $courseid
     */
    public function set_courseid(int $courseid): void {
        $this->courseid = $courseid;
    }

    /**
     * Gets the context
     * @return context
     */
    public function get_context(): context {
        return $this->context;
    }

    /**
     * Sets the context
     * @param context $context
     */
    public function set_context(context $context): void {
        $this->context = $context;
    }

    /**
     * Gets the assign
     * @return assign
     */
    public function get_assign(): assign {
        return $this->assign;
    }

    /**
     * Sets the assign
     * @param assign $assign
     */
    public function set_assign(assign $assign): void {
        $this->assign = $assign;
    }

    /**
     * Gets the userlist
     * @return array
     */
    public function get_userlist(): array {
        return $this->userlist;
    }

    /**
     * Sets the userlist
     * @param array $userlist
     */
    public function set_userlist(array $userlist): void {
        $this->userlist = $userlist;
    }

    /**
     * Gets the userid
     * @return string
     */
    public function get_userid(): ?int {
        return $this->userid;
    }

    /**
     * Sets the userid
     * @param int|null $userid
     */
    public function set_userid(?int $userid): void {
        $this->userid = $userid;
    }

}
