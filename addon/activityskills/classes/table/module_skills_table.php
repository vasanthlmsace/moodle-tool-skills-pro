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
 * Tool skill addon - Module skills list table currently in use.
 *
 * @package    skilladdon_activityskills
 * @copyright  2023 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace skilladdon_activityskills\table;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/tablelib.php');

use stdClass;
use moodle_url;
use core_course_category;
use core\output\notification;
use core_table\dynamic;
use html_writer;
use tool_skills\skills;

/**
 * Course module skills list table.
 */
class module_skills_table extends \table_sql {

    /**
     * ID of the course to fetch skills.
     *
     * @var int
     */
    protected $courseid;

    /**
     * ID of the course module to fetch skills.
     *
     * @var int
     */
    protected $modid;

    /**
     * Table contructor to define columns and headers.
     *
     * @param int $courseid course ID
     * @param int $modid Unique ID
     */
    public function __construct($courseid, $modid) {

        $this->courseid = $courseid;

        $this->modid = $modid;

        // Call parent constructor.
        parent::__construct($modid);

        // Define table headers and columns.
        $columns = ['identitykey', 'name', 'description', 'uponmodcompletion', 'actions'];
        $headers = [
            get_string('key', 'tool_skills'),
            get_string('name', 'core'),
            get_string('description', 'core'),
            get_string('uponmodcompletion', 'tool_skills'),
            get_string('actions'),
        ];

        $this->define_columns($columns);
        $this->define_headers($headers);

        // Remove sorting for some fields.
        $this->sortable(false);

        // Do not make the table collapsible.
        $this->collapsible(false);

        $this->set_attribute('id', 'mod_skills_list');
    }

    /**
     * Get the skills list.
     *
     * @param int $pagesize
     * @param bool $useinitialsbar
     * @throws \dml_exception
     */
    public function query_db($pagesize, $useinitialsbar = true) {

        // Set the query values to fetch skills.
        $select = 's.*,
            sc.status as coursestatus, sa.uponmodcompletion, sc.courseid, sa.id as skillcourseid, sa.points, sa.level';

        $from = '{tool_skills} s
        LEFT JOIN {tool_skills_course_activity} sa ON sa.skill = s.id AND sa.courseid = :courseid AND sa.modid = :modid
        LEFT JOIN {tool_skills_courses} sc ON sc.skill = s.id AND sc.courseid = '.$this->courseid;

        $this->set_sql($select, $from, 's.archived != 1 AND s.status != 0 AND sc.status != 0', [
            'courseid' => $this->courseid,
            'modid' => $this->modid,
        ]);

        parent::query_db($pagesize, $useinitialsbar);
    }

    /**
     * Name of the skill column. Format the string to support multilingual.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_name(stdClass $row) : string {
        return format_string($row->name);
    }

    /**
     * Description of the skill.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_description(stdClass $row) : string {
        return format_text($row->description, FORMAT_HTML, ['overflow' => false]);
    }

    /**
     * Categories list where this skill is available.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_uponmodcompletion(stdClass $row) : string {

        $completion = $row->uponmodcompletion ?? 0;

        switch ($completion) {

            case skills::COMPLETIONFORCELEVEL:
                return get_string('completionforcelevel', 'tool_skills') . ' - ' . \tool_skills\level::get($row->level)->get_name();
                break;

            case skills::COMPLETIONPOINTS:
                return get_string('completionpoints', 'tool_skills') .' - '. $row->points;
                break;

            case skills::COMPLETIONPOINTSGRADE:
                return get_string('completionpointsgrade', 'tool_skills');
                break;

            case skills::COMPLETIONSETLEVEL:
                return get_string('completionsetlevel', 'tool_skills') . ' - ' . \tool_skills\level::get($row->level)->get_name();
                break;

            case skills::COMPLETIONNOTHING:
            default:
                return get_string('completionnothing', 'tool_skills');

        }
    }

    /**
     * Actions to manage the skill row. Like edit, change status, archive and delete.
     *
     * @param stdClass $row
     * @return string
     */
    public function col_actions(stdClass $row) : string {
        global $OUTPUT;

        // Base url to edit the activity skills.
        $baseurl = new \moodle_url('/admin/tool/skills/manage/editcourse.php', [
            'skill' => $row->id,
            'courseid' => $row->courseid ?: $this->courseid,
            'modid' => $this->modid,
            'sesskey' => \sesskey(),
        ]);

        $actions = [];

        // Edit.
        $actions[] = [
            'url' => $baseurl,
            'icon' => new \pix_icon('t/edit', \get_string('edit')),
            'attributes' => ['class' => 'action-edit', 'data-target' => "toolskill-edit", "data-skillid" => $row->id],
        ];

        $actionshtml = [];
        foreach ($actions as $action) {
            if (!is_array($action)) {
                $actionshtml[] = $action;
                continue;
            }
            $action['attributes']['role'] = 'button';
            $actionshtml[] = $OUTPUT->action_icon(
                $action['url'],
                $action['icon'],
                ($action['action'] ?? null),
                $action['attributes'],
            );
        }
        return html_writer::div(join('', $actionshtml), 'skill-course-actions skill-actions mr-0');
    }


    /**
     * Override the default "Nothing to display" message when no skills are available.
     *
     * @return void
     */
    public function print_nothing_to_display() {
        global $OUTPUT;

        // Show notification as html element.
        $notification = new notification(get_string('modskillsnothingtodisplay', 'skilladdon_activityskills'),
            notification::NOTIFY_INFO);
        $notification->set_show_closebutton(false); // No close button for this notification.

        echo $OUTPUT->render($notification); // Print the notification on page.
    }
}
