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
 * Submit an assignment or edit the already submitted work
 *
 * @package    mod_peer
 * @copyright  2009 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Peer instance review form is defined here.
 *
 * @package mod_peer
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class peer_review_form extends moodleform {

    /**
     * Definiton of Form
     */
    public function definition() {
        $mform = $this->_form;

        $peer = $this->_customdata['peer'];
        $submission = $this->_customdata['submission'];
        $contentopts = $this->_customdata['contentopts'];

        $mform->addElement('static', 'description', '',
            $submission->content);

        $mform->closeHeaderBefore('criteriaReview');
        $mform->addElement('header', 'criteriaReview', 'Kriterien');
        foreach ($peer->get_all_criterias()->criterias as $criteria) {
            $mform->addElement('advcheckbox', 'criteria_id_' . $criteria->id, 'YES/NO', $criteria->text, array('group' => 1), array(0, 1));
        }

        $mform->closeHeaderBefore('general');

        $mform->addElement('header', 'general', 'Peer Review');
        $mform->addElement('editor', 'content_editor', get_string('review_generalreview', 'peer'), null, $contentopts);
        $mform->setType('content', PARAM_RAW);

        $mform->addElement('hidden', 'subid', $submission->id);
        $mform->setType('subid', PARAM_INT);

        $mform->addElement('hidden', 'cmid', $peer->cm->id);
        $mform->setType('cmid', PARAM_INT);

        $this->add_action_buttons();
    }
}
