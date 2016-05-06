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
/** Structure step to restore one peer activity
 *
 * @package     mod_peer
 * @category    backup
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Class restore_peer_activity_structure_step
 *
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_peer_activity_structure_step extends restore_activity_structure_step {

    /**
     * Has to exist
     *
     * @return mixed
     */
    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('peer', '/activity/peer');
        $paths[] = new restore_path_element('peer_criteria', '/activity/peer/criterias/criteria');

        if ($userinfo) {
            $paths[] = new restore_path_element('peer_duedate', '/activity/peer/duedates/duedate');
            $paths[] = new restore_path_element('peer_submission', '/activity/peer/submissions/submission');
            $paths[] = new restore_path_element('peer_review', '/activity/peer/submissions/submission/reviews/review');
            $paths[] = new restore_path_element('peer_reviewcriteria', '/activity/peer/submissions/submission/reviews/review/reviewcriterias/reviewcriteria');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process each peer instance
     * @param object $data
     */
    protected function process_peer($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // insert the peer record
        $newitemid = $DB->insert_record('peer', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }


    /**
     * Processs Criterias of peer instance
     * @param object $data
     */
    protected function process_peer_criteria($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->peer = $this->get_new_parentid('peer');

        $newitemid = $DB->insert_record('peer_criteria', $data);
        $this->set_mapping('peer_criteria', $oldid, $newitemid);
    }


    /**
     * Process due dates of peer instance
     * @param object $data
     */
    protected function process_peer_duedate($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->peer = $this->get_new_parentid('peer');
        $data->groupingid = $this->get_mappingid('grouping', $data->groupingid);

        $newitemid = $DB->insert_record('peer_duedate', $data);
        $this->set_mapping('peer_duedate', $oldid, $newitemid);
    }

    /**
     * Process submissions
     * @param object $data
     */
    protected function process_peer_submission($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->peer = $this->get_new_parentid('peer');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->groupid = $this->get_mappingid('group', $data->groupid);

        $newitemid = $DB->insert_record('peer_submission', $data);
        $this->set_mapping('peer_submission', $oldid, $newitemid);
    }


    /**
     * Process reviews
     * @param object $data
     */
    protected function process_peer_review($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->peer = $this->get_new_parentid('peer');
        $data->submission = $this->get_mappingid('peer_submission', $data->submission);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->groupid = $this->get_mappingid('group', $data->groupid);

        $newitemid = $DB->insert_record('peer_review', $data);
        $this->set_mapping('peer_review', $oldid, $newitemid);
    }


    /**
     * process review criterias
     * @param object $data
     */
    protected function process_peer_reviewcriteria($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->review = $this->get_mappingid('peer_review', $data->review);
        $data->criteria = $this->get_mappingid('peer_criteria', $data->criteria);

        $newitemid = $DB->insert_record('peer_reviewcriteria', $data);
        $this->set_mapping('peer_reviewcriteria', $oldid, $newitemid);
    }


    /**
     * Add releated Files
     */
    protected function after_execute() {
        // Add peer related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_peer', 'submission_content', 'peer_submission');
    }
}