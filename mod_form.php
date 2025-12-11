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
 * This file contains the forms to create and edit an instance of this module
 *
 * @package   mod_externalassignment
 * @copyright 2024 Marcel Suter <marcel.suter@bzz.ch>
 * @copyright 2024 Kevin Maurizi <kevin.maurizi@bzz.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Assignment program settings form.
 *
 * @package   mod_assign
 */
class mod_externalassignment_mod_form extends moodleform_mod {
    /**
     * Definition of the settings form
     * @return void
     */
    public function definition() {
        $mform =& $this->_form;
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('assignmentname', 'externalassignment'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'externalname', get_string('externalname', 'externalassignment'), ['size' => '64']);
        $mform->setType('externalname', PARAM_TEXT);
        $mform->addHelpButton('externalname', 'externalname', 'externalassignment');

        $mform->addElement('text', 'externallink', get_string('externallink', 'externalassignment'), ['size' => '64']);
        $mform->setType('externallink', PARAM_TEXT);
        $mform->addRule('externallink', null, 'required', null, 'client');
        $mform->addHelpButton('externallink', 'externallink', 'externalassignment');

        $mform->addElement('checkbox', 'alwaysshowlink', get_string('alwaysshowlink', 'externalassignment'));
        $mform->addHelpButton('alwaysshowlink', 'alwaysshowlink', 'externalassignment');

        $this->standard_intro_elements(get_string('description', 'externalassignment'));

        $mform->addElement('checkbox', 'alwaysshowdescription', get_string('alwaysshowdescription', 'externalassignment'));
        $mform->addHelpButton('alwaysshowdescription', 'alwaysshowdescription', 'externalassignment');
        $mform->disabledIf('alwaysshowdescription', 'allowsubmissionsfromdate[enabled]', 'notchecked');

        $mform->addElement('header', 'availability', get_string('availability', 'externalassignment'));
        $mform->setExpanded('availability', true);

        $options = ['optional' => true];
        $mform->addElement('date_time_selector', 'allowsubmissionsfromdate',
            get_string('allowsubmissionsfromdate', 'externalassignment'), $options);
        $mform->addHelpButton('allowsubmissionsfromdate', 'allowsubmissionsfromdate', 'externalassignment');

        $mform->addElement('date_time_selector', 'duedate', get_string('duedate', 'externalassignment'), $options);
        $mform->addHelpButton('duedate', 'duedate', 'externalassignment');

        $mform->addElement('date_time_selector', 'cutoffdate', get_string('cutoffdate', 'externalassignment'), $options);
        $mform->addHelpButton('cutoffdate', 'cutoffdate', 'externalassignment');

        $mform->addElement('header', 'grading', get_string('grading', 'externalassignment'));
        $mform->setExpanded('grading', true);

        $mform->addElement('float', 'externalgrademax', get_string('externalgrademax', 'externalassignment'));
        $mform->addRule('externalgrademax', null, 'required', null, 'client');
        $mform->setDefault('externalgrademax', 100);
        $mform->addHelpButton('externalgrademax', 'externalgrademax', 'externalassignment');

        $mform->addElement('float', 'manualgrademax', get_string('manualgrademax', 'externalassignment'));
        $mform->setDefault('manualgrademax', 0);
        $mform->addHelpButton('manualgrademax', 'manualgrademax', 'externalassignment');

        $mform->addElement('float', 'passingpercentage', get_string('passingpercentage', 'externalassignment'));
        $mform->addRule('passingpercentage', null, 'required', null, 'client');
        $mform->setDefault('passingpercentage', 60);
        $mform->addHelpButton('passingpercentage', 'passingpercentage', 'externalassignment');

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }

    /**
     * Prepares the form data before loading
     *
     * Maps the database field names to form field names, especially for completion rules
     * which use suffixed names.
     *
     * @param array $defaultvalues
     * @return void
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        // Map needspassinggrade from database to the suffixed form field name.
        if (isset($defaultvalues['needspassinggrade'])) {
            $defaultvalues[$this->get_suffixed_name('needspassinggrade')] = $defaultvalues['needspassinggrade'];
        }
    }

    /**
     * Validates the data in the form
     * @param array $data  The data entered in the form that needs to be validate
     * @param array $files The files uploaded in the form
     * @return array
     * @throws coding_exception
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (!empty($data['allowsubmissionsfromdate']) && !empty($data['duedate'])) {
            if ($data['duedate'] <= $data['allowsubmissionsfromdate']) {
                $errors['duedate'] = get_string('duedateaftersubmissionvalidation', 'externalassignment');
            }
        }
        if (!empty($data['cutoffdate']) && !empty($data['duedate'])) {
            if ($data['cutoffdate'] < $data['duedate']) {
                $errors['cutoffdate'] = get_string('cutoffdatevalidation', 'externalassignment');
            }
        }
        if (!empty($data['allowsubmissionsfromdate']) && !empty($data['cutoffdate'])) {
            if ($data['cutoffdate'] < $data['allowsubmissionsfromdate']) {
                $errors['cutoffdate'] = get_string('cutoffdatefromdatevalidation', 'externalassignment');
            }
        }

        return $errors;
    }

    /**
     * Add elements for setting the custom completion rules.
     *
     * @return array List of added element names, or names of wrapping group elements.
     * @throws coding_exception
     * @category completion
     */
    public function add_completion_rules(): array {
        $mform = $this->_form;
        $group = [
            $mform->createElement(
                'checkbox',
                $this->get_suffixed_name('needspassinggrade'),
                ' ',
                get_string('needspassinggradedesc', 'externalassignment'),
                0
            ),

        ];
        $mform->addGroup(
            $group,
            $this->get_suffixed_name('completiongradesgroup'),
            '',
            [' '],
            false
        );
        $mform->setDefault('passinggradeenabled', 1);
        $mform->addHelpButton(
            $this->get_suffixed_name('completiongradesgroup'),
            'completiongradesgroup',
            'externalassignment'
        );
        return [$this->get_suffixed_name('completiongradesgroup')];
    }

    /**
     * gets the name of the element with the suffix
     * @param string $fieldname
     * @return string
     */
    protected function get_suffixed_name(string $fieldname): string {
        return $fieldname . $this->get_suffix();
    }

    /**
     * checks if custom completion conditions are enabled
     * @param array $data  The data entered in the form
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return (!empty($data[$this->get_suffixed_name('needspassinggrade')]));
    }
}
