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
 * @package mod_peer
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Class peer_submission_form
 * @package mod_peer
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class peer_submission_form extends moodleform {

    /**
     * Definition of Form
     */
    public function definition() {
        $mform = $this->_form;

        $current = $this->_customdata['current'];
        $peer = $this->_customdata['peer'];
        $contentopts = $this->_customdata['contentopts'];

        $mform->addElement('header', 'general', get_string('description', 'peer'));
        $mform->addElement('static', 'description', '',
            $peer->intro);

        $criterias = $peer->get_all_criterias();
        $data = array();
        foreach ($criterias->criterias as $criteria) {
            array_push($data, $criteria->text);
        }
        $mform->addElement('static', 'criterias', '', '<h3>' . get_string('criteria_plural', 'peer') . '</h3>' . html_writer::alist($data));

        $mform->addElement('header', 'general', get_string('submission', 'peer'));

        $mform->addElement('editor', 'content_editor', get_string('submissioncontent', 'peer'), null, $contentopts);
        $mform->setType('content', PARAM_RAW);

        $mform->addElement('hidden', 'subid', $current->id);
        $mform->setType('subid', PARAM_INT);

        $mform->addElement('hidden', 'cmid', $peer->cm->id);
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('hidden', 'edit', 'yes');
        $mform->setType('edit', PARAM_INT);

        $this->add_action_buttons();

        $this->set_data($current);
    }
}
