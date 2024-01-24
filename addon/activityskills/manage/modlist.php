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
 * Tool Skill addon - Manage course activity skills list.
 *
 * @package   skilladdon_activityskills
 * @copyright 2023 bdecent GmbH <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Require config.
require(__DIR__.'/../../../../../../config.php');

// Require admin library.
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/tablelib.php');

// Get parameters.
$courseid = required_param('courseid', PARAM_INT);
$skillid = optional_param('skill', null, PARAM_INT);
$cmid = optional_param('modid', null, PARAM_INT);

// Get the course data from the course table.
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

// Get the course module data form the module id.
$cm = get_coursemodule_from_id(false, $cmid);

// Get course module context.
$context = \context_module::instance($cm->id);

// Login check required.
require_login();
// Access checks.
require_capability('tool/skills:managecourseskillslist', $context);

// Prepare the page (to make sure that all necessary information is already set even if we just handle the actions as a start).
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/skills/addon/activityskills/manage/modlist.php', [
    'courseid' => $courseid, 'modid' => $cm->id,
]));
$PAGE->set_cacheable(false);
$PAGE->set_course($course);
$PAGE->set_cm($cm);
$PAGE->set_heading($context->get_context_name(false));

// Add the body class for this activity skills list page.
$PAGE->add_body_class('module-skills');

// Further prepare the page.
$PAGE->set_title(get_string('moduleskills', 'skilladdon_activityskills'));
$PAGE->navbar->add(get_string('modskills', 'skilladdon_activityskills'),
    new moodle_url('/admin/tool/skills/addon/activityskills/manage/modlist.php', ['courseid' => $courseid, 'modid' => $cm->id]));

// Build activity skills table.
$table = new \skilladdon_activityskills\table\module_skills_table($courseid, $cm->id);
$table->define_baseurl($PAGE->url);

// Header.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('assignmodskills', 'skilladdon_activityskills'));

// Activity Skill description.
echo get_string('assignmodeskills_desc', 'skilladdon_activityskills');

$countmenus = $DB->count_records('tool_skills');
if ($countmenus < 1) {

    $table->out(0, true);
} else {
    $table->out(50, true);
    $PAGE->requires->js_call_amd('skilladdon_activityskills/modskills', 'init', ['courseid' => $courseid, 'modid' => $cm->id]);
}

// Footer.
echo $OUTPUT->footer();

