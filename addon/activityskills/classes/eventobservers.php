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
 * Tool skill addon - Course module skills handler.
 *
 * @package   skilladdon_activityskills
 * @copyright 2023, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace skilladdon_activityskills;

use skilladdon_activityskills\moduleskills;

/**
 * Event observer class define.
 */
class eventobservers {

    /**
     * Observe the course module completion event and update the assigned module skills of this course for this user.
     *
     * @param \core\event\course_module_completion_updated $event
     * @return void
     */
    public static function course_module_completed(\core\event\course_module_completion_updated $event) {

        // Fetch the event data.
        $data = $event->get_data();

        // ID of the course module completed user.
        $courseid = $data['courseid'];
        $cmid = $data['contextinstanceid'];
        $relateduserid = $data['relateduserid'];

        // Manage the upon course module completion options for various skills assigned in this course module.
        moduleskills::get($courseid, $cmid)->manage_course_module_completions($relateduserid, $cmid, $data);
    }

    /**
     * Course module deleted, then deletes the course module skills records.
     *
     * @param \core\event\course_module_deleted $event
     * @return void
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event) {
        // The deleted course ID of the course module.
        $courseid = $event->courseid;
        // ID of the deleted course module.
        $cmid = $event->contextinstanceid;

        // Remove the course module skills of the deleted course module.
        moduleskills::get($courseid, $cmid)->remove_instance_skills();
    }

    /**
     * Get the assignment submission graded.
     *
     * @param \mod_assign\event\submission_graded $event
     * @return void
     */
    public static function get_submission_grade(\mod_assign\event\submission_graded $event) {
        $data = $event->get_data();

        $courseid = $event->courseid;
        $cmid = $event->contextinstanceid;
        $relateduserid = $event->relateduserid;

        // Manage the upon course module completion options for various skills assigned in this course module.
        moduleskills::get($courseid, $cmid)->manage_grade_by_points($relateduserid, $cmid, $data);
    }
}
