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
 * Javascript to sync due date with cut-off date.
 *
 * @module     mod_externalassignment/date_sync
 * @copyright  2024 Marcel Suter <marcel@ghwalin.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Cache DOM elements
let cutoffdateElements = null;
let duedateElements = null;

export const init = () => {
    // Get the cutoffdate fields (enabled checkbox and date/time selectors)
    cutoffdateElements = {
        enabled: document.getElementById('id_cutoffdate_enabled'),
        day: document.getElementById('id_cutoffdate_day'),
        month: document.getElementById('id_cutoffdate_month'),
        year: document.getElementById('id_cutoffdate_year'),
        hour: document.getElementById('id_cutoffdate_hour'),
        minute: document.getElementById('id_cutoffdate_minute')
    };

    if (!cutoffdateElements.enabled || !cutoffdateElements.day || !cutoffdateElements.month || 
        !cutoffdateElements.year || !cutoffdateElements.hour || !cutoffdateElements.minute) {
        // eslint-disable-next-line no-console
        console.warn('mod_externalassignment/date_sync: Cut-off date form elements not found');
        return; // Elements not found, exit gracefully
    }

    // Get the duedate fields
    duedateElements = {
        enabled: document.getElementById('id_duedate_enabled'),
        day: document.getElementById('id_duedate_day'),
        month: document.getElementById('id_duedate_month'),
        year: document.getElementById('id_duedate_year'),
        hour: document.getElementById('id_duedate_hour'),
        minute: document.getElementById('id_duedate_minute')
    };

    if (!duedateElements.enabled || !duedateElements.day || !duedateElements.month || 
        !duedateElements.year || !duedateElements.hour || !duedateElements.minute) {
        // eslint-disable-next-line no-console
        console.warn('mod_externalassignment/date_sync: Due date form elements not found');
        return; // Elements not found, exit gracefully
    }

    // Add event listeners to cutoffdate fields
    cutoffdateElements.enabled.addEventListener('change', syncDates);
    cutoffdateElements.day.addEventListener('change', syncDates);
    cutoffdateElements.month.addEventListener('change', syncDates);
    cutoffdateElements.year.addEventListener('change', syncDates);
    cutoffdateElements.hour.addEventListener('change', syncDates);
    cutoffdateElements.minute.addEventListener('change', syncDates);
};

/**
 * Sync the due date with the cut-off date
 */
function syncDates() {
    // Only sync if cutoffdate is enabled
    if (!cutoffdateElements.enabled.checked) {
        return;
    }

    // Get cutoffdate values from cached elements
    const cutoffdateValues = {
        day: cutoffdateElements.day.value,
        month: cutoffdateElements.month.value,
        year: cutoffdateElements.year.value,
        hour: cutoffdateElements.hour.value,
        minute: cutoffdateElements.minute.value
    };

    // Enable duedate if not already enabled
    if (!duedateElements.enabled.checked) {
        duedateElements.enabled.checked = true;
        // Trigger change event to enable the date fields
        duedateElements.enabled.dispatchEvent(new Event('change'));
    }

    // Set duedate values to match cutoffdate
    duedateElements.day.value = cutoffdateValues.day;
    duedateElements.month.value = cutoffdateValues.month;
    duedateElements.year.value = cutoffdateValues.year;
    duedateElements.hour.value = cutoffdateValues.hour;
    duedateElements.minute.value = cutoffdateValues.minute;
}
