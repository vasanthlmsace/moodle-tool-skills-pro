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
 * Skills activities completion data entities for report builder.
 *
 * @package   skilladdon_reports
 * @copyright 2023, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace skilladdon_reports\local\entities;

// Prevent direct access.
defined('MOODLE_INTERNAL') || die();

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\report\{column, filter};
use core_reportbuilder\local\filters\{date, number, select, text};
use lang_string;

// Grade lib.
require_once($CFG->dirroot.'/lib/gradelib.php');

/**
 * Skills activities completion data entity base for report source.
 */
class skills_activities_completion extends base {

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {

        return [
            'tool_skills' => 'sk',
            'tool_skills_levels' => 'sksl',
            'tool_skills_courses' => 'sksc',
            'tool_skills_userpoints' => 'skup',
            'tool_skills_course_activity' => 'sksa',
            'tool_skills_levels_max' => 'sklm',
            'tool_skills_courses_count' => 'skcc',
            'tool_skills_userpoints_count' => 'skupc',
        ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('activitiestatsentity', 'tool_skills');
    }

    /**
     * Initialise the entity columns and filter, conditions.
     *
     * @return base
     */
    public function initialise(): base {

        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        return $this;
    }

    /**
     * List of columns available for this datasource.
     *
     * @return array
     */
    protected function get_all_columns(): array {

        $columns = [];

        // Table alias used in fields.
        $userpoints = $this->get_table_alias('tool_skills_userpoints');
        $skillmodsalias = $this->get_table_alias('tool_skills_course_activity');

        // Mod completion status.
        $columns[] = (new column(
            'modcompletionstatus',
            new lang_string('modcompletionstatus', 'tool_skills'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_joins($this->get_joins())
        ->add_field("{$skillmodsalias}.modid") // Cmid.
        ->add_field("{$skillmodsalias}.courseid") // Course id.
        ->add_field("{$userpoints}.userid")
        ->add_join("LEFT JOIN {course_modules_completion} cmc
            ON cmc.userid = {$userpoints}.userid AND cmc.coursemoduleid = {$skillmodsalias}.modid ")
        ->add_field("cmc.completionstate")
        ->add_callback(static function ($value, $row): string {

            if (!$row->modid) {
                return false;
            }

            switch($row->completionstate) {
                case COMPLETION_INCOMPLETE:
                    return get_string('incomplete', 'tool_skills');
                case COMPLETION_COMPLETE:
                    return get_string('complete', 'tool_skills');
                case COMPLETION_COMPLETE_PASS:
                    return get_string('complete_pass', 'tool_skills');
                case COMPLETION_COMPLETE_FAIL:
                    return get_string('complete_fail', 'tool_skills');
                default:
                    return '';
            }

            return '';
        });

        // Grade.
        $columns[] = (new column(
            'modgrade',
            new lang_string('grade', 'tool_skills'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_joins($this->get_joins())
        ->add_field("{$skillmodsalias}.modid") // Course module id (cmid).
        ->add_field("{$skillmodsalias}.courseid")
        ->add_field("{$userpoints}.userid")
        ->add_field("m.name") // Module name "assign".
        ->add_callback(static function ($value, $row): string {
            // Fetch the user grades for this module.
            $gradinginfo = grade_get_grades($row->courseid, 'mod', $row->name, $row->modid, array_keys([$row->userid]));
            $userfinalgrade = $gradinginfo->items[0]->grades[$row->userid];
            return $userfinalgrade ?: '-';
        });

        // Time modified.
        $columns[] = (new column(
            'modcompletiontimemodified',
            new lang_string('timemodified', 'tool_skills'),
            $this->get_entity_name()
        ))
        ->set_is_sortable(true)
        ->add_joins($this->get_joins())
        ->add_join("LEFT JOIN {course_modules_completion} cmc
            ON cmc.userid = {$userpoints}.userid AND cmc.coursemoduleid = {$skillmodsalias}.modid")
        ->add_field("cmc.timemodified")
        ->add_callback(static function ($value, $row): string {
            return $value ? userdate($value, get_string('strftimedatetime', 'langconfig')) : '-';
        });

        return $columns;

    }
}
