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
/** Define all the backup steps that will be used by the backup_peer_activity_task
 *
 * @package     mod_peer
 * @category    backup
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Class backup_peer_activity_structure_step
 *
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_peer_activity_structure_step extends backup_activity_structure_step {

    /**
     * Has to exist
     *
     * @return mixed
     */
    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated
        $peer = new backup_nested_element('peer', array('id'), array(
            'course', 'name', 'intro', 'introformat'));

        $criterias = new backup_nested_element('criterias');

        $criteria = new backup_nested_element('criteria', array('id'), array(
            'peer', 'text'));

        $duedates = new backup_nested_element('duedates');

        $duedate = new backup_nested_element('duedate', array('id'), array(
            'peer', 'groupingid', 'duedate'));

        $submissions = new backup_nested_element('submissions');

        $submission = new backup_nested_element('submission', array('id'), array(
            'peer', 'groupid', 'blogid', 'timecreated', 'version', 'content', 'contentformat'));

        $reviews = new backup_nested_element('reviews');

        $review = new backup_nested_element('review', array('id'), array(
            'peer', 'submission', 'groupid', 'commentid', 'timecreated', 'content', 'contentformat', 'grade'));

        $reviewcriterias = new backup_nested_element('reviewcriterias');
        $reviewcriteria = new backup_nested_element('reviewcriteria', array('id'), array(
            'review', 'criteria', 'fulfill'));

        $reviewcriterias->add_child($reviewcriteria);
        $review->add_child($reviewcriterias);
        $reviews->add_child($review);
        $submission->add_child($reviews);

        $submissions->add_child($submission);
        $duedates->add_child($duedate);
        $criterias->add_child($criteria);

        // Build the tree
        $peer->add_child($submissions);
        $peer->add_child($duedates);
        $peer->add_child($criterias);

        // Define sources
        $peer->set_source_table('peer', array('id' => backup::VAR_ACTIVITYID));
        $criteria->set_source_table('peer_criteria', array('peer' => '../../id'));

        // All the rest of elements only happen if we are including user info
        if ($userinfo) {
            $duedate->set_source_table('peer_duedate', array('peer' => '../../id'));
            $submission->set_source_table('peer_submission', array('peer' => '../../id'));

            $review->set_source_table('peer_review', array('submission' => '../../id'));
            $reviewcriteria->set_source_table('peer_reviewcriteria', array('review' => '../../id'));
        }

        // Define id annotations
        $duedate->annotate_ids('grouping', 'groupingid');
        $submission->annotate_ids('group', 'groupid');
        $review->annotate_ids('group', 'groupid');

        // Define file annotations
        $peer->annotate_files('mod_peer', 'submission_content', null); // This file area does not have an itemid

        // Return the root element (peer), wrapped into standard activity structure
        return $this->prepare_activity_structure($peer);
    }
}