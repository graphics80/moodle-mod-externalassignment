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
 * Integration with report_editdates plugin.
 *
 * This class provides the necessary methods for the report_editdates plugin
 * to display and edit date fields for externalassignment activities.
 *
 * @package   mod_externalassignment
 * @copyright 2024 Marcel Suter <marcel.suter@bzz.ch>
 * @copyright 2024 Kevin Maurizi <kevin.maurizi@bzz.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Date extractor class for external assignment module integration with report_editdates.
 *
 * This class extends the report_editdates_mod_date_extractor to provide
 * date field information for the external assignment activity module.
 *
 * @package   mod_externalassignment
 * @copyright 2024 Marcel Suter <marcel.suter@bzz.ch>
 * @copyright 2024 Kevin Maurizi <kevin.maurizi@bzz.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_externalassignment_report_editdates_integration extends report_editdates_mod_date_extractor {

    /**
     * Constructor.
     *
     * @param stdClass $course The course database row.
     */
    public function __construct($course) {
        parent::__construct($course, 'externalassignment');
        parent::load_data();
    }

    /**
     * Get a list of the date settings for this activity instance.
     *
     * Returns the three date fields available for external assignments:
     * - allowsubmissionsfromdate: When students can start submitting
     * - duedate: When the assignment is due
     * - cutoffdate: After this date, no submissions are accepted
     *
     * @param cm_info $cm The course module to return the settings for.
     * @return array Array of report_editdates_date_setting objects.
     */
    public function get_settings(cm_info $cm) {
        $externalassignment = $this->mods[$cm->instance];

        return [
            'allowsubmissionsfromdate' => new report_editdates_date_setting(
                get_string('allowsubmissionsfromdate', 'externalassignment'),
                $externalassignment->allowsubmissionsfromdate,
                self::DATETIME,
                true
            ),
            'duedate' => new report_editdates_date_setting(
                get_string('duedate', 'externalassignment'),
                $externalassignment->duedate,
                self::DATETIME,
                true
            ),
            'cutoffdate' => new report_editdates_date_setting(
                get_string('cutoffdate', 'externalassignment'),
                $externalassignment->cutoffdate,
                self::DATETIME,
                true
            ),
        ];
    }

    /**
     * Validate the submitted dates for this activity instance.
     *
     * Performs the same validation as mod_form.php:
     * - Due date must be after allow submissions from date
     * - Cut-off date must not be earlier than due date
     * - Cut-off date must not be earlier than allow submissions from date
     *
     * @param cm_info $cm The activity to validate the dates for.
     * @param array $dates Array with keys matching those returned by get_settings().
     * @return array Any validation errors. Empty array if no errors.
     */
    public function validate_dates(cm_info $cm, array $dates) {
        $errors = [];

        // Due date must be after allow submissions from date.
        if (!empty($dates['allowsubmissionsfromdate']) && !empty($dates['duedate'])) {
            if ($dates['duedate'] <= $dates['allowsubmissionsfromdate']) {
                $errors['duedate'] = get_string('duedateaftersubmissionvalidation', 'externalassignment');
            }
        }

        // Cut-off date must not be earlier than due date.
        if (!empty($dates['cutoffdate']) && !empty($dates['duedate'])) {
            if ($dates['cutoffdate'] < $dates['duedate']) {
                $errors['cutoffdate'] = get_string('cutoffdatevalidation', 'externalassignment');
            }
        }

        // Cut-off date must not be earlier than allow submissions from date.
        if (!empty($dates['allowsubmissionsfromdate']) && !empty($dates['cutoffdate'])) {
            if ($dates['cutoffdate'] < $dates['allowsubmissionsfromdate']) {
                $errors['cutoffdate'] = get_string('cutoffdatefromdatevalidation', 'externalassignment');
            }
        }

        return $errors;
    }

    /**
     * Save the new dates for this activity instance.
     *
     * Updates the date fields in the externalassignment table.
     *
     * @param cm_info $cm The activity to save the dates for.
     * @param array $dates Array of dates to save.
     */
    public function save_dates(cm_info $cm, array $dates) {
        global $DB;

        $update = new stdClass();
        $update->id = $cm->instance;
        $update->allowsubmissionsfromdate = $dates['allowsubmissionsfromdate'];
        $update->duedate = $dates['duedate'];
        $update->cutoffdate = $dates['cutoffdate'];
        $update->timemodified = time();

        $DB->update_record('externalassignment', $update);
    }
}
