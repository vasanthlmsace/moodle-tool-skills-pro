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
 * Tool skill addon - Course module form.
 *
 * @package   skilladdon_activityskills
 * @copyright 2023, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 namespace skilladdon_activityskills\form;

defined('MOODLE_INTERNAL') || die();

use tool_skills\skills;
use skilladdon_activityskills\moduleskills;
use moodle_url;
use stdClass;

require_once($CFG->libdir.'/formslib.php');

/**
 * Form to assign the skills to the courses.
 */
class course_mod_form extends \core_form\dynamic_form {

    /**
     * Defined the fields for the skill.
     *
     * @return void
     */
    public function definition() {
        global $DB;

        $mform = $this->_form;

        $skill = $this->optional_param('skill', 0, PARAM_INT);
        $courseid = $this->optional_param('courseid', 0, PARAM_INT);
        $modid = $this->optional_param('modid', 0, PARAM_INT);

        // Skill id.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Courseid hidden field.
        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        // Skill id.
        $mform->addElement('hidden', 'skill', $skill);
        $mform->setType('skill', PARAM_INT);

        // Moduleid hidden field.
        $mform->addElement('hidden', 'modid', $modid);
        $mform->setType('modid', PARAM_INT);

        // Upon activity completion.
        $options = [
            skills::COMPLETIONNOTHING => get_string('completionnothing', 'tool_skills'),
            skills::COMPLETIONPOINTS => get_string('completionpoints', 'tool_skills'),
            skills::COMPLETIONPOINTSGRADE => get_string('completionpointsgrade', 'tool_skills'),
            skills::COMPLETIONSETLEVEL => get_string('completionsetlevel', 'tool_skills'),
            skills::COMPLETIONFORCELEVEL => get_string('completionforcelevel', 'tool_skills'),
        ];
        $mform->addElement('select', 'uponmodcompletion', get_string('uponmodcompletion', 'tool_skills'), $options);
        $mform->addHelpButton('uponmodcompletion', 'uponmodcompletion', 'tool_skills');

        // Completion points element.
        $mform->addElement('text', 'points', get_string('completionpoints', 'tool_skills'));
        $mform->hideIf('points', 'uponmodcompletion', 'neq', skills::COMPLETIONPOINTS);
        $mform->addHelpButton('points', 'completionpoints', 'tool_skills');

        // Completion level.
        $skill = $this->optional_param('skill', 0, PARAM_INT);
        $levels = $DB->get_records_menu('tool_skills_levels', ['skill' => $skill], '', 'id, name');

        // List of levels to complete.
        $mform->addElement('select', 'level', get_string('completionlevel', 'tool_skills'), $levels);
        $mform->hideIf('level', 'uponmodcompletion', 'in', [
            skills::COMPLETIONNOTHING,
            skills::COMPLETIONPOINTS,
            skills::COMPLETIONPOINTSGRADE,
        ]);
        $mform->addHelpButton('level', 'completionlevel', 'tool_skills');
    }

    /**
     * Get the context of this form used.
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        // Course module record id.
        $cmid = $this->optional_param('modid', 0, PARAM_INT);

        return $cmid ? \context_module::instance($cmid) : \context_system::instance();
    }

    /**
     * Check the access for the submit data to this form.
     *
     * @return bool
     */
    protected function check_access_for_dynamic_submission(): void {
        // TODO: Validatation of user capability goes here.
    }

    /**
     * Process the submission from AJAX.
     *
     * @return void
     */
    public function process_dynamic_submission() {
        global $DB;

        // Get the submitted content data.
        $record = (object) $this->get_data();

        if (isset($record->id) && $record->id != '' && $DB->record_exists('tool_skills_course_activity', ['id' => $record->id])) {
            // Level id to update.
            $skillcourseid = $record->id;
            // Time modified the level.
            $record->timemodified = time();
            // Update the level record.
            $DB->update_record('tool_skills_course_activity', $record);
        } else {
            // New record add the created time.
            $record->timecreated = time();
            // Insert the record of the new skill.
            $skillcourseid = $DB->insert_record('tool_skills_course_activity', $record);
        }

        moduleskills::get($record->courseid, $record->modid)->manage_users_modcompletion();

        return true;
    }

    /**
     * Set the data to form. Need to call this method on direct data setup.
     *
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;

        $courseid = $this->optional_param('courseid', 0, PARAM_INT);
        $skillid = $this->optional_param('skill', 0, PARAM_INT);
        $modid = $this->optional_param('modid', 0, PARAM_INT);

        $defaults = [
            'courseid' => $courseid,
            'skill' => $skillid,
            'modid' => $modid,
        ];

        if ($skillid && $courseid) {
            $record = (array) $DB->get_record('tool_skills_course_activity', [
                'skill' => $skillid,
                'courseid' => $courseid,
                'modid' => $modid,
            ]);
            $defaults += $record;
        }

        // Setup the block config data to form.
        $this->set_data($defaults);
    }

    /**
     * Returns the URL fo the page to submit the data.
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/admin/tool/skills/addon/activityskills/manage/modlist.php',
            [
                'courseid' => $this->optional_param('courseid', 0, PARAM_INT),
                'modid' => $this->optional_param('modid', 0, PARAM_INT),
            ]);
    }

    /**
     * List of options available for completion.
     *
     * @return array
     */
    public static function get_uponcompletion_options() {
        static $options;

        $options = [
            skills::COMPLETIONNOTHING => get_string('completionnothing', 'tool_skills'),
            skills::COMPLETIONPOINTS => get_string('completionpoints', 'tool_skills'),
            skills::COMPLETIONPOINTSGRADE => get_string('completionpointsgrade', 'tool_skills'),
            skills::COMPLETIONSETLEVEL => get_string('completionsetlevel', 'tool_skills'),
            skills::COMPLETIONFORCELEVEL => get_string('completionforcelevel', 'tool_skills'),
        ];

        return $options;
    }

}
