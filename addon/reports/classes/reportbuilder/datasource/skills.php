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
 * Skills datasource for the skill user points in course and activities.
 *
 * @package   skilladdon_reports
 * @copyright 2023, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace skilladdon_reports\reportbuilder\datasource;

use core_reportbuilder\datasource;
use core_reportbuilder\local\entities\course;
use core_reportbuilder\local\entities\user;
use core_cohort\reportbuilder\local\entities\cohort;
use core_cohort\reportbuilder\local\entities\cohort_member;
use core_course\reportbuilder\local\entities\course_category;

/**
 * Skill datasource definition.
 */
class skills extends datasource {

    /**
     * Return user friendly name of the datasource
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('skillsdatasource', 'tool_skills');
    }

    /**
     * Initialise all the entities used in the datasource,
     *
     * @return void
     */
    protected function initialise(): void {

        // Basic skill entity which is main table in this datasource.
        $skillsentity = new \skilladdon_reports\local\entities\skills();

        // List of table alias used here.
        $mainskillalias = $skillsentity->get_table_alias('tool_skills');
        $maxalias = $skillsentity->get_table_alias('tool_skills_levels_max');
        $skillcoursealias = $skillsentity->get_table_alias('tool_skills_courses');
        $userpointalias = $skillsentity->get_table_alias('tool_skills_userpoints');

        // Setup the main table for this datasource. tool_skills is the main table.
        $this->set_main_table('tool_skills', $mainskillalias);
        $this->add_entity($skillsentity);

        // Find the maximum points.
        $maxleveljoin = "JOIN (
            SELECT skill, MAX(points) as maxpoints FROM {tool_skills_levels} GROUP BY skill
        ) {$maxalias} ON {$maxalias}.skill = {$mainskillalias}.id";
        $this->add_join($maxleveljoin);

        $statsentity = new \skilladdon_reports\local\entities\skills_stats();
        $coursealias = $statsentity->get_table_alias('tool_skills_courses_count');
        $this->add_join($statsentity->skill_stats_join());
        $this->add_entity($statsentity);

        $maxleveljoin = "JOIN (
            SELECT skill, MAX(points) as maxpoints FROM {tool_skills_levels} GROUP BY skill
        ) {$maxalias} ON {$maxalias}.skill = {$mainskillalias}.id";
        $this->add_join($maxleveljoin);

        // Add core user join.
        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');

        // User stats entity.
        $userstatsentity = new \skilladdon_reports\local\entities\skills_user_stats();
        // User and user stats join.
        $joins['user'] = "LEFT JOIN {tool_skills_userpoints} {$userpointalias} ON {$userpointalias}.skill = {$mainskillalias}.id
        JOIN {user} {$useralias} ON {$useralias}.id = {$userpointalias}.userid";

        $cohortmementity = new cohort_member();
        // Update the cohort memeber table alias, It uses cm as alias same as course_modules.
        $cohortmementity = $cohortmementity->set_table_alias('cohort_members', 'chtm');
        $cohortmemalias = $cohortmementity->get_table_alias('cohort_members');
        $cohortentity = new cohort();
        $cohortentity = $cohortentity->set_table_alias('cohort', 'cht');
        $cohortalias = $cohortentity->get_table_alias('cohort');
        $cohortjoin = "LEFT JOIN {cohort_members} {$cohortmemalias} ON {$cohortmemalias}.userid = {$userpointalias}.userid
        LEFT JOIN {cohort} {$cohortalias} ON {$cohortalias}.id = {$cohortmemalias}.cohortid";

        $userentity->add_join($joins['user']);
        $userstatsentity->add_join($joins['user']);
        $userstatsentity->add_join($cohortjoin);

        $this->add_entity($userentity);
        $this->add_entity($userstatsentity);

        $cohortentity->add_join($joins['user']);
        $this->add_entity($cohortentity->add_join($cohortjoin));

        // Skill course entity.
        $coursentity = new course();
        $coursealias = $coursentity->get_table_alias('course');
        $joins['course'] = "LEFT JOIN {tool_skills_courses} {$skillcoursealias} ON {$skillcoursealias}.skill = {$mainskillalias}.id
            LEFT JOIN {course} {$coursealias} ON {$coursealias}.id = {$skillcoursealias}.courseid";
        $this->add_entity($coursentity->add_join($joins['course']));

        // Category entity.
        $categoryentity = new course_category();
        $categoryalias = $categoryentity->get_table_alias('course_categories');
        $joins['category'] = "LEFT JOIN {course_categories} {$categoryalias} ON {$categoryalias}.id = {$coursealias}.category";
        $this->add_entity($categoryentity->add_joins([$joins['course'], $joins['category']]));

        // Modules entity.
        $activityentity = new \skilladdon_reports\local\entities\skills_activities();
        $courseactivity = $activityentity->get_table_alias('tool_skills_course_activity');
        // Joins activity.
        $joins['activity'] = "LEFT JOIN {tool_skills_course_activity} {$courseactivity}
            ON {$courseactivity}.skill = {$mainskillalias}.id AND {$courseactivity}.uponmodcompletion != 0
            JOIN {course_modules} cm ON cm.id = {$courseactivity}.modid
            JOIN {modules} m ON m.id = cm.module ";
        $this->add_entity($activityentity->add_join($joins['activity']));

        // Skills activities entity.
        $modcompletionentity = new \skilladdon_reports\local\entities\skills_activities_completion();
        $this->add_entity($modcompletionentity->add_joins([$joins['activity'], $joins['user']]));

        // Support for 4.2.
        if (method_exists($this, 'add_all_from_entities')) {
            $this->add_all_from_entities();
        } else {
            // Add all the entities used in notification datasource. moodle 4.0 support.
            $this->include_all_from_entity($skillsentity->get_entity_name());
            $this->include_all_from_entity($userentity->get_entity_name());
            $this->include_all_from_entity($statsentity->get_entity_name());
            $this->include_all_from_entity($userentity->get_entity_name());
            $this->include_all_from_entity($userstatsentity->get_entity_name());
            $this->include_all_from_entity($cohortmementity->get_entity_name());
            $this->include_all_from_entity($cohortentity->get_entity_name());
            $this->include_all_from_entity($coursentity->get_entity_name());
            $this->include_all_from_entity($categoryentity->get_entity_name());
            $this->include_all_from_entity($activityentity->get_entity_name());
            $this->include_all_from_entity($modcompletionentity->get_entity_name());
        }

    }

    /**
     * Adds all columns/filters/conditions from the given entity to the report at once
     *
     * @param string $entityname
     */
    protected function include_all_from_entity(string $entityname): void {
        $this->add_columns_from_entity($entityname);
        $this->add_filters_from_entity($entityname);
        $this->add_conditions_from_entity($entityname);
    }

    /**
     * Return the columns that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_columns(): array {

        return [
            'skills:name',
            'skills:key',
            'skills:status',
            'skills:maximum',
        ];
    }

    /**
     * Return the filters that will be added to the report once is created
     *
     * @return array
     */
    public function get_default_filters(): array {
        return [];
    }

    /**
     * Return the conditions that will be added to the report once is created
     *
     * @return array
     */
    public function get_default_conditions(): array {
        return [];
    }
}
