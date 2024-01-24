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
 * Tool Skill addon - library functions.
 *
 * @package   skilladdon_activityskills
 * @copyright 2023 bdecent GmbH <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Add the link in course activity secondary navigation menu to open the activity skills list page.
 *
 * @param  navigation_node $navigation
 * @param  stdClass $course
 * @param  context_course $context
 * @return void
 */
function skilladdon_activityskills_extend_navigation_course(navigation_node $navigation, stdClass $course, $context) {
    global $PAGE;
    // Add the manage skills page to the secondary navigation on the mod page.
    if ($PAGE->context->contextlevel == CONTEXT_MODULE && has_capability('tool/skills:managecourseskillslist', $context)) {

        $content = '';
        $id = $context->instanceid;
        $name = get_string('manageskills', 'tool_skills');
        $manageurl = new moodle_url('/admin/tool/skills/addon/activityskills/manage/modlist.php', [
            'courseid' => $id, 'modid' => $PAGE->cm->id,
        ]);

        // Manage skills menu added in the course module secondray navigation.
        $content .= html_writer::start_tag("li", ["data-key" => $name, "class" => "nav-item",
                            "role" => "none", "data-forceintomoremenu" => "true", ]);
        $content .= html_writer::link($manageurl, $name, ['role' => 'menuitem', 'class' => 'dropdown-item']);
        $content .= html_writer::end_tag("li");

        $PAGE->requires->js_amd_inline("
            require(['jquery', 'core/moremenu'], function($, MenuMore) {
                $(document).ready(function() {
                    var moremenu = document.querySelector('.secondary-navigation ul.nav-tabs .dropdownmoremenu ul');
                    // Added the manage skills on the module page.
                    if (moremenu) {
                        $(moremenu).append('$content');
                    }
                });
            });
        ");
    }
}
