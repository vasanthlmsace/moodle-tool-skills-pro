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
 * Theme Skills - Custom Behat rules for skills
 *
 * @package    skilladdon_activityskills
 * @copyright  2023 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../../../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\{TableNode, PyStringNode};

/**
 * Class behat_skilladdon_activityskills
 *
 * @package    skilladdon_activityskills
 * @copyright  2023 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_skilladdon_activityskills extends behat_base {

    /**
     * Set the student view to complete the activity condition.
     * @Given I set the student view to complete the activity condition
     */
    public function i_set_the_student_view_to_complete_the_activity_conditiony() {
        global $CFG;

        if ($CFG->branch == "403") {
            $this->execute('behat_forms::i_set_the_field_to', ['id_completion_2', '2']);
            $this->execute('behat_forms::i_set_the_field_to', ['completionview', '1']);
        } else {
            $this->execute('behat_forms::i_set_the_field_to',
            ['Completion tracking', 'Show activity as complete when conditions are met']);
            $this->execute('behat_forms::i_set_the_field_to', ['completionview', '1']);
        }
    }

}
