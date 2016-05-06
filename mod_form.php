<?php
// This file is part of Moodle - http:// moodle.org/
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

/** Peer instance settings form is defined here.
 *
 * @package mod_peer
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    // It must be included from a Moodle page
}

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/peer/lib.php');


/**
 * Class mod_peer_mod_form
 * @package mod_peer
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_peer_mod_form extends moodleform_mod {
    /**
     * @var null
     */
    protected $course = null;

    /**
     * mod_peer_mod_form constructor.
     * @param object $current
     * @param object $section
     * @param object $cm
     * @param object $course
     */
    public function __construct($current, $section, $cm, $course) {
        $this->course = $course;
        parent::__construct($current, $section, $cm, $course);
    }

    /**
     * Form definition
     */
    public function definition() {
        global $CFG, $DB, $OUTPUT;

        $mform =& $this->_form;

        // General --------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Name of Peer-Review
        $label = get_string('peername', 'peer');
        $mform->addElement('text', 'name', $label, array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements(get_string('introduction', 'peer'));

        $mform->addElement('header', 'review_criteria', get_string('criteria_plural', 'peer'));

        $repeatarray = array();
        $repeatarray[] = $mform->createElement('text', 'criteria', get_string('criteria_plural', 'peer'));
        $repeatarray[] = $mform->createElement('hidden', 'criteriaid', 0);

        $repeatno = 5;

        $repeatelcriterias = array();

        $mform->setType('criteria', PARAM_CLEANHTML);

        $mform->setType('criteriaid', PARAM_INT);

        $this->repeat_elements($repeatarray, $repeatno,
            $repeatelcriterias, 'criteria_repeats', 'criteria_add_fields', 2, null, true);

        // Termine bis zur Fertigstellung fuer jedes Grouping festlegen
        $mform->addElement('header', 'duedates_groupings', get_string('duedate_groupings', 'peer'));
        $groupings = groups_get_all_groupings($this->course->id);
        foreach ($groupings as $grouping) {
            $label = $grouping->name;
            $mform->addElement('date_selector', 'date_' . $label, $label, array(
                'startyear' => 2015,
                'stopyear' => 2020,
                'optional' => true
            ));
        }

        $mform->closeHeaderBefore('duedates_groupings');

        // Standard Coursemoudle Elements
        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }
}