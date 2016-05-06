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

/**
 * Reviews for Peer
 *
 * @package mod_peer
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once($CFG->dirroot . '/repository/lib.php');

$cmid = required_param('cmid', PARAM_INT);            // course module id
$subid = optional_param('subid', null, PARAM_INT);           // submission id
$groupid = optional_param('groupid', null, PARAM_INT);           // group id

$cm = get_coursemodule_from_id('peer', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course, false, $cm);
require_capability('mod/peer:peerreview', $PAGE->context);

$peerrecord = $DB->get_record('peer', array('id' => $cm->instance), '*', MUST_EXIST);
$peer = new peer($peerrecord, $cm, $course);

$canreview = is_enrolled($peer->context, $USER, 'mod/peer:peerreview');

$PAGE->set_url($peer->review_url($subid), array('cmid' => $cmid, 'subid' => $subid, 'groupid' => $groupid));

$PAGE->set_title($peer->name);
$PAGE->set_heading($course->fullname);

// Output starts here
$output = $PAGE->get_renderer('mod_peer');
echo $output->header();

if (isset($subid)) {

    $submission = $peer->get_submission_by_id($subid);
    $ismemberofgroup = groups_is_member($submission->groupid, $USER->id);

    if ($ismemberofgroup) {
        redirect($peer->view_url());
    }

    $stringdata = new stdClass();
    $stringdata->peername = $peer->name;
    $stringdata->groupname = $peer->get_group_game($submission->groupid);

    echo $output->heading(format_string(get_string('review_reviewoverview', 'peer', $stringdata)), 2);

    require_once(dirname(__FILE__) . '/review_form.php');

    $review = new stdClass();
    $review->id = null;

    $contentopts = array(
        'subdirs' => 0,
        'maxfiles' => 0,
        'changeformat' => 0,
        'noclean' => 0,
        'context' => $peer->context,
        'trusttext' => 0,
        'enable_filemanagement' => false);

    $review = file_prepare_standard_editor($review, 'content', $contentopts, $peer->context,
        'mod_peer', 'review_content', $review->id);

    $mform = new peer_review_form(null, array('peer' => $peer, 'contentopts' => $contentopts, 'submission' => $submission));

    if ($mform->is_cancelled()) {
        redirect($peer->view_url());

    } else if ($canreview and $formdata = $mform->get_data()) {

        $formdata->id = null;
        $formdata->peer = $peer->id;
        $formdata->groupid = $peer->get_current_groupid();
        $formdata->timecreated = time();
        $formdata->content = '';
        $formdata->submission = $submission->id;
        $formdata->contentformat = FORMAT_HTML;

        $formdata->grade = 0;

        $formdata->commentid = 0;
        $formdata->id = $DB->insert_record('peer_review', $formdata);

        $formdata = file_postupdate_standard_editor($formdata, 'content', $contentopts, $peer->context,
            'mod_peer', 'review_content', $formdata->id);

        $DB->update_record('peer_review', $formdata);

        $grade = 0;

        foreach ($peer->get_all_criterias()->criterias as $criteria) {
            $reviewcriteria = new stdClass();

            $reviewcriteria->id = null;
            $reviewcriteria->review = $formdata->id;
            $reviewcriteria->criteria = $criteria->id;

            $criteriaid = 'criteria_id_' . $criteria->id;
            $reviewcriteria->fulfill = $formdata->$criteriaid;

            if ($reviewcriteria->fulfill) {
                $grade++;
            }
            $DB->insert_record('peer_reviewcriteria', $reviewcriteria);
        }

        $formdata->grade = $grade;

        $commentid = $peer->review_post_comment($submission, $formdata);
        $formdata->commentid = $commentid;
        echo 'Comment id' . var_dump($commentid);
        $DB->update_record('peer_review', $formdata);

        // $peer->updateComplition($formdata->groupid);
        $peer->review_send_message($submission, $formdata);
        $peer->criteria = $peer->get_all_criterias();
        $peer->course = $peer->course->id;
        peer_update_grades($peer);
        redirect($peer->view_url());
    } else {
        // displays the form
        $mform->display();
    }
} else {
    if (is_null($groupid)) {
        $groupid = $peer->get_current_groupid();
    }

    $stringdata = new stdClass();
    $stringdata->peername = $peer->name;
    $stringdata->groupname = $peer->get_group_game($groupid);

    echo $output->heading(format_string(get_string('review_reviewoverview', 'peer', $stringdata)), 2);

    $reviews = $peer->get_reviews_by_groupid($groupid);

    if (is_object($reviews)) {
        $reviews = array($reviews);
    }

    $reviews = new peer_reviews($reviews, $peer);

    if ($reviews) {
        echo $output->render($reviews);
    } else {

        $stringdata = new stdClass();
        $stringdata->groupname = $peer->get_group_game($groupid);
        echo get_string('review_noreviewbygroup', 'peer', $stringdata);
    }

}
echo $output->footer();
