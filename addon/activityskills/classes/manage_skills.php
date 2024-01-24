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
 * Tool skill addon - Manage skills handler.
 *
 * @package   skilladdon_activityskills
 * @copyright 2023, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace skilladdon_activityskills;

use html_writer;

/**
 * Manage Skills class
 */
class manage_skills {

    /**
     * Get the allocation method of the module.
     *
     * @param \tool_skills\allocation_method $skillobj Object of the allocation method.
     * @return string
     */
    public function get_allocation_method($skillobj) {
        return ($skillobj instanceof \skilladdon_activityskills\moduleskills) ? 'activity' : false;
    }

    /**
     * * Remove the course activity data, when the skill is reomved in the site.
     *
     * @param int $skillid Skill ID
     */
    public function remove_skills(int $skillid) {
        \skilladdon_activityskills\moduleskills::remove_skills($skillid);
    }

    /**
     * Remove the course activity data, when the course is reomved in the site.
     *
     * @param int $courseid
     */
    public function remove_course_instance(int $courseid) {
        global $DB;
        $DB->delete_records('tool_skills_course_activity', ['courseid' => $courseid]);
    }


    /**
     * Add the userskill data in the user skills.
     *
     * @param stdclass $point
     * @return void
     */
    public function add_userskills_data(&$point) {
        global $DB;

        // Activity skills data.
        $point->activityskills = $DB->get_records('tool_skills_course_activity', [
            'skill' => $point->skill, 'courseid' => $point->courseid,
        ]);
    }

    /**
     * Add the user points content to add the user profile page navigation.
     *
     * @param string $skillstr Skills content string
     * @param stdclass $data Data
     * @return void
     */
    public function add_user_points_content(&$skillstr, $data) {
        global $DB, $USER;

        $modskill = $data->activityskills;
        foreach ($modskill as $modinstance => $moddata) {
            // Activity skills.
            $moduleskillsobj = new \skilladdon_activityskills\moduleskills($moddata->courseid, $moddata->modid);
            $moduleskillsobj->set_skill_instance($moddata->id);
            $modpointstoearn = $moduleskillsobj->get_points_earned_from_course_module($moddata);

            // Course module URL.
            $cm = get_coursemodule_from_id(false, $moddata->modid);
            if ($DB->record_exists('course_modules', ['id' => $cm->id])) {
                $moduleurl = new \moodle_url('/mod/'.$cm->modname.'/view.php', ['id' => $cm->id]);

                // Points earned from this course module.
                $pointsfromcoursemod = $moduleskillsobj->get_user_earned_activity_points($USER->id, $moddata);

                $li = html_writer::link($moduleurl, format_string($cm->name));

                $modulepointstr = get_string('pointsforcompletion', 'tool_skills') . " : " . $modpointstoearn;
                $modulepointstr .= html_writer::tag('b',
                    " (".get_string('earned', 'tool_skills') . ": " .( $pointsfromcoursemod ?? 0) . ")" );

                $li .= html_writer::tag('p', $modulepointstr, ['class' => 'skills-points-'.$cm->name]);

                $skillstr .= html_writer::tag('li', $li);
            }
        }
    }
}
