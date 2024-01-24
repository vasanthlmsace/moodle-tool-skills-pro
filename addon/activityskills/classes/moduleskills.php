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

defined('MOODLE_INTERNAL') || die();

use completion_info;
use cm_info;
use moodle_exception;
use stdClass;
use tool_skills\skills;;

require_once($CFG->dirroot.'/grade/lib.php');
require_once($CFG->dirroot.'/grade/querylib.php');

/**
 * Manage the skills for course module. Trigger skills to assign point for users.
 */
class moduleskills extends \tool_skills\allocation_method {

    /**
     * ID of the course module skill record id.
     *
     * @var int
     */
    protected $id;

    /**
     * ID of the course record id.
     *
     * @var int
     */
    protected $courseid;

    /**
     * ID of the course module record id.
     *
     * @var int
     */
    protected $cmid;

    /**
     * Constructor
     *
     * @param int $courseid ID of the skill course record.
     * @param int $cmid ID of the skill course module record.
     */
    public function __construct(int $courseid, int $cmid) {
        parent::__construct(); // Create a parent instance
        // Course id.
        $this->courseid = $courseid;
        // Course module id.
        $this->cmid = $cmid;
    }

    /**
     * Create the retun class instance for this skill coursemodule id.
     *
     * @param int $courseid Course id
     * @param int $cmid Course module id
     * @return self
     */
    public static function get(int $courseid, int $cmid) : self {
        return new self($courseid, $cmid);
    }

    /**
     * Get the course record for this courseid.
     *
     * @return stdClass Course record data.
     */
    public function get_course() : stdClass {
        return get_course($this->courseid);
    }

    /**
     * Fetch to the skills course module data.
     *
     * @param int $skillid
     * @return self
     */
    public static function get_for_skill(int $skillid) : array {
        global $DB;

        $modcourses = $DB->get_records('tool_skills_course_activity', ['skill' => $skillid]);

        return array_map(fn($module) => new self($module->courseid, $module->id), $modcourses);
    }

    /**
     * Fetch the skills assigned/enabled for this course module.
     *
     * @return array
     */
    public function get_instance_skills(): array {
        global $DB;

        $skills = $DB->get_records('tool_skills_course_activity', ['modid' => $this->cmid]);

        return array_map(fn($sk) => skills::get($sk->skill), $skills);
    }

    /**
     * Remove the course module skills records.
     *
     * @return void
     */
    public function remove_instance_skills() {
        global $DB;

        if ($data = $DB->get_record('tool_skills_course_activity', ['modid' => $this->cmid])) {
            $this->get_logs()->delete_method_log($data->id, 'activity');
            $DB->delete_records('tool_skills_course_activity', ['modid' => $this->cmid]);
        }
    }

    /**
     * Get the skill course module record.
     *
     * @return stdclass
     */
    public function build_data() {
        global $DB;

        if (!$this->instanceid) {
            throw new moodle_exception('skillcoursemodulenotset', 'skilladdon_activityskills');
        }
        // Fetch the skills course module record.
        $record = $DB->get_record('tool_skills_course_activity', ['id' => $this->instanceid]);

        $this->data = $record;

        return $this->data;
    }

    /**
     * Fetch the user points.
     *
     * @return int
     */
    public function get_points() {

        $this->build_data(); // Build the data of the skill for this course module.

        return $this->data->points ?? false;
    }

    /**
     * Get points earned from this activity completion.
     *
     * @param stdclass $moddata Course module data
     * @return string
     */
    public function get_points_earned_from_course_module($moddata) {

        if ($moddata->uponmodcompletion == skills::COMPLETIONPOINTS) {
            return $moddata->points;
        } else if ($moddata->uponmodcompletion == skills::COMPLETIONFORCELEVEL ||
            $moddata->uponmodcompletion == skills::COMPLETIONSETLEVEL) {

            $levelid = $moddata->level;
            $level = \tool_skills\level::get($levelid);
            return $level->get_points();
        }
        return '';
    }

    /**
     * Fetch the points user earned for this instance.
     *
     * @param int $userid
     * @param stdclass $data
     * @return int
     */
    public function get_user_earned_activity_points(int $userid, $data) {
        global $DB;
        $user = \tool_skills\user::get($userid);
        $points = $user->get_user_award_by_method('activity', $this->instanceid);
        if ($data->uponmodcompletion == skills::COMPLETIONPOINTSGRADE) {
            $points = $this->get_grade_point($data->modid, $userid);

        }
        return $points ?? null;
    }

    /**
     * Manage the course module completion to allocate the points to the module course skill.
     *
     * Given course module is completed for this user, fetch tht list of skills assigned for this course module.
     * Trigger the skills to update the user points based on the upon completion option for this skill added in course module.
     *
     * @param int $userid
     * @param int $cmid
     * @return void
     */
    public function manage_course_module_completions($userid, $cmid) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/lib/completionlib.php');

        $completion = new \completion_info($this->get_course());

        if (!$completion->is_enabled()) {
            return null;
        }

        // Get the number of modules that support completion.
        $cm = get_coursemodule_from_id(false, $cmid);
        $cminfo = \cm_info::create($cm, $userid);
        $modulecompletion = $completion->get_data($cminfo, true, $userid);

        if ($modulecompletion->completionstate == COMPLETION_COMPLETE) {
            $modskills = $this->get_instance_skills();
            foreach ($modskills as $modskillid => $skillobj) {
                // Create a skill course module record instance for this skill.
                $this->set_skill_instance($modskillid);
                $data = $this->build_data();

                $transaction = $DB->start_delegated_transaction();

                if ($record = $DB->get_record('tool_skills_awardlogs', ['userid' => $userid, 'skill' => $data->skill,
                    'methodid' => $data->id, 'method' => 'activity',
                    ])) {
                    $record->points = $record->points;
                    $DB->update_record('tool_skills_awardlogs', $record);
                } else {
                    switch ($data->uponmodcompletion) {

                        case skills::COMPLETIONPOINTS:
                            $skillobj->increase_points($this, $data->points, $userid);
                            break;

                        case skills::COMPLETIONSETLEVEL:
                            $skillobj->moveto_level($this, $data->level, $userid);
                            break;

                        case skills::COMPLETIONFORCELEVEL:
                            $skillobj->force_level($this, $data->level, $userid);
                            break;

                        case skills::COMPLETIONPOINTSGRADE:
                            $gradepoint = self::get_grade_point($data->modid, $userid);
                            $skillobj->increase_points($this, $gradepoint, $userid);
                            break;
                    }
                }

                $transaction->allow_commit();
            }
        }
    }

    /**
     * Manage the grade submission points.
     *
     * @param int $userid User ID
     * @param int $cmid Course module ID
     * @return void
     */
    public function manage_grade_by_points($userid, $cmid) {
        global $DB;

        $activtyskills = $this->get_instance_skills();
        foreach ($activtyskills as $id => $sobj) {
            $this->set_skill_instance($id);
            $data = $this->build_data();
            $transaction = $DB->start_delegated_transaction();

            if ($data->uponmodcompletion == skills::COMPLETIONPOINTSGRADE) {
                $gradepoint = self::get_grade_point($data->modid, $userid);
                $sobj->increase_points($this, $gradepoint, $userid);
            }

            $transaction->allow_commit();
        }
    }

    /**
     * Manage users module completion.
     *
     * @return void
     */
    public function manage_users_modcompletion() {
        global $CFG;

        require_once($CFG->dirroot . '/lib/enrollib.php');
        $context = \context_module::instance($this->cmid);

        // Enrolled users.
        $enrolledusers = get_enrolled_users($context);
        foreach ($enrolledusers as $user) {
            $this->manage_course_module_completions($user->id, $this->cmid);
        }
    }

    /**
     * Remove the skills for this course module award method.
     *
     * @param int $skillid
     * @return void
     */
    public static function remove_skills(int $skillid) {
        global $DB;

        $DB->delete_records('tool_skills_course_activity', ['skill' => $skillid]);
    }

    /**
     * Get the grade point form the completed skill activity.
     *
     * @param int $cmid Course module id
     * @param int $userid related Userid
     * @return int $gradepoint User grade point
     */
    public static function get_grade_point(int $cmid, int $userid) {
        $cm = get_coursemodule_from_id(false, $cmid);
        $grades = grade_get_grades($cm->course, 'mod', $cm->modname, $cm->instance, $userid);
        $grade = reset($grades->items[0]->grades);
        $gradepoint = floatval($grade->grade);
        return $gradepoint;
    }
}
