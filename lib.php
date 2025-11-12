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

/**
 * This file contains the moodle hooks for the externalassignment module.
 *
 * It delegates most functions to the controller classes.
 *
 * @package     mod_externalassignment
 * @copyright   2024 Marcel Suter <marcel.suter@bzz.ch>
 * @copyright   2024 Kevin Maurizi <kevin.maurizi@bzz.ch>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_externalassignment\local\assign;
use mod_externalassignment\local\assign_control;
use mod_externalassignment\local\grade;

/**
 * Adds an assignment instance
 *
 * This is done by calling the add_instance() method of the assignment type class
 * @param stdClass $instancedata
 * @param mod_externalassignment_mod_form|null $mform
 * @return int The instance id of the new assignment
 */
function externalassignment_add_instance(\stdClass $instancedata, ?mod_externalassignment_mod_form $mform = null) {
    $instance = context_module::instance($instancedata->coursemodule);
    $assigncontrol = new assign_control($instance, null);
    return $assigncontrol->add_instance($instancedata, $instancedata->coursemodule);
}

/**
 * Update an assignment instance
 *
 * This is done by calling the update_instance() method of the assignment type class
 * @param \stdClass $data
 * @param \stdClass $form - unused
 * @return bool
 */
function externalassignment_update_instance(\stdClass $data, $form) {
    $context = context_module::instance($data->coursemodule);
    $assigncontrol = new assign_control($context, null);
    return $assigncontrol->update_instance($data, $context->instanceid);
}

/**
 * delete an assignment instance
 * @param int $id the id of the assignment to be deleted
 * @return bool
 * @throws dml_exception
 * @throws coding_exception
 */
function externalassignment_delete_instance(int $id): bool {
    $cm = get_coursemodule_from_instance('externalassignment', $id, 0, false, MUST_EXIST);

    $context = context_module::instance($cm->id);
    $assignment = new assign_control($context, null, null);
    $assignment->delete_instance($id);

    return true;
}

/**
 * Returns additional information for showing the assignment in course listing
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info
 * @throws dml_exception
 */
function externalassignment_get_coursemodule_info(stdClass $coursemodule) {
    $assignment = new assign(null);
    $assignment->load_db($coursemodule->id);
    $result = new cached_cm_info();
    $result->name = $assignment->get_name();
    if ($assignment->get_duedate()) {
        $result->customdata['duedate'] = $assignment->get_duedate();
    }
    if ($assignment->get_cutoffdate()) {
        $result->customdata['cutoffdate'] = $assignment->get_cutoffdate();
    }
    if ($assignment->get_allowsubmissionsfromdate()) {
        $result->customdata['allowsubmissionsfromdate'] = $assignment->get_allowsubmissionsfromdate();
    }
    $result->customdata['alwaysshowlink'] = $assignment->is_alwaysshowlink();

    $result->customdata['externallink'] = $assignment->get_externallink();

    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['needspassinggrade'] = $assignment->get_needspassinggrade();
    }
    return $result;
}

/**
 * customize module display for the current user on course listing
 *
 * @param cm_info $coursemodule
 * @return void
 * @throws coding_exception
 */
function externalassignment_cm_info_view(cm_info $coursemodule): void {
    $externallink = '<a href="' . $coursemodule->customdata['externallink'] .
        '" target="_blank">' . get_string('externallink', 'externalassignment') . '</a>';
    $content = '';
    if (array_key_exists('allowsubmissionsfromdate', $coursemodule->customdata)) {
        if ($coursemodule->customdata['alwaysshowlink'] ||
            $coursemodule->customdata['allowsubmissionsfromdate'] < time()) {
            $content .= $externallink;
        }
        if ($coursemodule->customdata['allowsubmissionsfromdate'] >= time()) {
            $label = get_string('submissionsopen', 'externalassignment');
        } else {
            $label = get_string('submissionsopened', 'externalassignment');
        }
        $content .= '<br><strong>' . $label . '</strong> ' . userdate($coursemodule->customdata['allowsubmissionsfromdate']);

    } else {
        $content .= $externallink;
    }

    if (array_key_exists('duedate', $coursemodule->customdata)) {
        $content .= '<br><strong>' . get_string('submissionsdue', 'externalassignment') . '</strong> ' .
            userdate($coursemodule->customdata['duedate']);
    }

    $coursemodule->set_content($content);
}

/**
 * customize module display
 * @param cm_info $coursemodule
 * @return void
 * @throws coding_exception
 */
function externalassignment_cm_info_dynamic(cm_info $coursemodule) {
    $context = context_module::instance($coursemodule->id);
}


/**
 * Return the features this module supports
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know or string for the module purpose.
 */
function externalassignment_supports($feature) {
    switch ($feature) {
        case FEATURE_BACKUP_MOODLE2:
            return true;
        /*case FEATURE_GRADE_HAS_GRADE:
            return true;*/
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * Extend the settings navigation
 *
 * @param settings_navigation $settings
 * @param navigation_node $navref
 * @return void
 * @throws \core\exception\moodle_exception
 * @throws coding_exception
 */
function externalassignment_extend_settings_navigation(settings_navigation $settings, navigation_node $navref) {
    $cm = $settings->get_page()->cm;
    if (!$cm) {
        return;
    }

    $context = $cm->context;
    if (has_capability('mod/externalassignment:reviewgrades', $context)) {
        $url = new moodle_url('/mod/externalassignment/view.php', ['id' => $settings->get_page()->cm->id, 'action' => 'grading']);
        $navref->add(
            text: get_string('gradeitem:submissions', 'assign'),
            action: $url,
            type: navigation_node::TYPE_SETTING,
            key: 'mod_externalassignment_submissions'
        );
    }
}

/**
 * Callback to update the grade settings or the grade for one student
 * @param $modinstance
 * @param $grades
 * @return int
 */
function externalassignment_grade_item_update($modinstance, $grades=null): int {
    return grade_update(
        'mod/externalassignment',
        $modinstance->course,
        'mod',
        'externalassignment',
        $modinstance->id,
        0,
        $grades);
}

/**
 * Obtains the automatic completion state for this forum based on any conditions
 * in forum settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function externalassignment_get_completion_state(object $course, object $coursemodule, int $userid, bool $type): bool {
    return true; // TODO implement logic
}

/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm object with fields ->completion and ->customdata['customcompletionrules']
 * @return array $descriptions the array of descriptions for the custom rules.
 */
function mod_externalassignment_get_completion_active_rule_descriptions($cm) {
    // Values will be present in cm_info, and we assume these are up to date.
    if (empty($cm->customdata['customcompletionrules'])
        || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $key => $val) {
        switch ($key) {
            case 'completionsubmit':
                if (!empty($val)) {
                    $descriptions[] = get_string('needspassinggrade', 'externalassignment');
                }
                break;
            default:
                break;
        }
    }
    return $descriptions;
}

/**
 * Updates the grade for one student
 * @param $modinstance
 * @param $userid
 * @param $nullifnone
 * @return void
 * @throws coding_exception
 * @throws dml_exception
 * @throws moodle_exception
 */
function externalassignment_update_grades($modinstance, $userid=0, $nullifnone=true) {
    global $DB;
    $cm = get_coursemodule_from_instance('externalassignment', $modinstance->id, 0, false, MUST_EXIST);
    $grade = new grade(null);
    $grade->load_db($modinstance->id, $userid);
    $gradevalues = new \stdClass;
    $gradevalues->userid = $userid;
    $gradevalues->rawgrade = floatval($grade->get_externalgrade()) + floatval($grade->get_manualgrade());
    $link = new \moodle_url('/mod/externalassignment/view.php',
        ['id' => $modinstance->id]
    );
    $gradevalues->feedback = '<a href="' . $link->out(true) . '">' .
        get_string('seefeedback', 'externalassignment') . '</a>';
    $gradevalues->feedbackformat = 1;

    list ($course, $coursemodule) = get_course_and_cm_from_cmid($cm->id, 'externalassignment');
    $completion = new \completion_info($course);
    if ($completion->is_enabled($coursemodule)) {
        $completion->update_state($coursemodule, COMPLETION_COMPLETE, $userid);
    }

}

/**
 * Callback to remove all grades from gradebook
 *
 * @param int $courseid The ID of the course to reset
 * @param string $type Optional type of activity to limit the reset to a particular activity type
 */
function externalassignment_reset_gradebook(int $courseid, string $type='') {
    global $DB;

    $params = ['moduletype' => 'externalassignment', 'courseid' => $courseid];
    $sql = 'SELECT a.*, cm.idnumber as cmidnumber, a.course as courseid
            FROM {externalassignment} a, {course_modules} cm, {modules} m
            WHERE m.name=:moduletype AND m.id=cm.module AND cm.instance=a.id AND a.course=:courseid';

    if ($assignments = $DB->get_records_sql($sql, $params)) {
        foreach ($assignments as $assignment) {
            assign_grade_item_update($assignment, 'reset');
        }
    }
}

/**
 * Is the event visible?
 *
 * This is used to determine global visibility of an event in all places throughout Moodle.
 *
 * @param calendar_event $event
 * @param int $userid User id to use for all capability checks, etc. Set to 0 for current user (default).
 * @return bool Returns true if the event is visible to the current user, false otherwise.
 */
function mod_externalassignment_core_calendar_is_event_visible(calendar_event $event, int $userid = 0): bool {
    return true;
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @param int $userid User id to use for all capability checks, etc. Set to 0 for current user (default).
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_externalassignment_core_calendar_provide_event_action(
    calendar_event $event,
    \core_calendar\action_factory $factory,
    $userid = 0
) {
    $cm = get_fast_modinfo($event->courseid, $userid)->instances['externalassignment'][$event->instance];
    return $factory->create_instance(
        'view',
        new \moodle_url('/mod/externalassignment/view.php', ['id' => $cm->id]),
        1,
        true
    );
}

/**
 * Serve the files from the externalassignment file areas
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param context $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 */
function externalassignment_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, true, $cm);

    // Check if the file area is one of the feedback areas.
    if ($filearea !== 'externalfeedback' && $filearea !== 'manualfeedback') {
        return false;
    }

    $itemid = array_shift($args);
    $filename = array_pop($args);

    if (!$args) {
        $filepath = '/';
    } else {
        $filepath = '/' . implode('/', $args) . '/';
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_externalassignment', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }

    // Check capability to review grades (teachers) or if the user is viewing their own feedback.
    $grade = $DB->get_record('externalassignment_grades', ['id' => $itemid], '*', IGNORE_MISSING);
    if ($grade) {
        // Allow teachers with review capability.
        if (has_capability('mod/externalassignment:reviewgrades', $context)) {
            send_stored_file($file, 0, 0, $forcedownload, $options);
            return true;
        }
        // Allow students to view their own feedback.
        global $USER;
        if ($grade->userid == $USER->id && has_capability('mod/externalassignment:view', $context)) {
            send_stored_file($file, 0, 0, $forcedownload, $options);
            return true;
        }
    }

    return false;
}
