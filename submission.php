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
 * Submission for Peer
 *
 * @package mod_peer
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once($CFG->dirroot . '/repository/lib.php');

$cmid = required_param('cmid', PARAM_INT);            // course module id
$subid = optional_param('subid', -1, PARAM_INT);           // submission id
$edit = optional_param('edit', false, PARAM_BOOL);    // open for editing?
$version = optional_param('version', -2, PARAM_INT);    // Version of Submission
$groupid = optional_param('groupid', -1, PARAM_INT);
$cm = get_coursemodule_from_id('peer', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$version = $version + 1;
require_login($course, false, $cm);

$peerrecord = $DB->get_record('peer', array('id' => $cm->instance), '*', MUST_EXIST);
$peer = new peer($peerrecord, $cm, $course);

$PAGE->set_url($peer->submission_url(), array('cmid' => $cmid, 'subid' => $subid, 'groupid' => $groupid, 'version' => $version));

require_once(dirname(__FILE__) . '/submission_form.php');

$cansubmit = has_capability('mod/peer:submit', $peer->context);

$PAGE->set_title($peer->name);
$PAGE->set_heading($course->fullname);

// Output starts here
$output = $PAGE->get_renderer('mod_peer');
echo $output->header();

if ($edit) {
    echo $output->heading(format_string($peer->name . ' - ' . $peer->get_current_groupname()), 2);

    // Abgabe hinzufügen oder bearbeiten

    $submission = $peer->get_submission_by_groupid($peer->get_current_groupid());

    if (!$submission) {
        $submission = new stdClass();
        $submission->content = '';
    } else {
        $submission->content = file_rewrite_pluginfile_urls($submission->content, 'pluginfile.php', $peer->context->id, 'mod_peer', 'submission_content', $submission->id);
    }
    $submission->id = null;

    $maxfiles = 99;
    $maxbytes = $course->maxbytes;

    $contentopts = array(
        'subdirs' => false,
        'maxfiles' => $maxfiles,
        'maxbytes' => $maxbytes,
        'changeformat' => 0,
        'noclean' => 0,
        'context' => $peer->context,
        'trusttext' => true,
        'enable_filemanagement' => true,
        'return_types' => FILE_INTERNAL | FILE_EXTERNAL);

    $submission = file_prepare_standard_editor($submission, 'content', $contentopts, $peer->context,
        'mod_peer', 'submission_content', $submission->id);

    $mform = new peer_submission_form(null, array('current' => $submission, 'peer' => $peer,
        'contentopts' => $contentopts));

    if ($mform->is_cancelled()) {
        redirect($peer->view_url());

    } else if ($cansubmit and $formdata = $mform->get_data()) {

        $formdata->peer = $peer->id;
        $formdata->groupid = $peer->get_current_groupid();
        $formdata->id = null;
        $formdata->version = $peer->get_max_version_for_group_submission($peer->get_current_groupid()) + 1;

        $formdata->content = '';
        $formdata->contentformat = FORMAT_HTML;
        $formdata->timecreated = time();

        $formdata->blogid = 0;

        if (is_null($submission->id)) {
            $submission->id = $formdata->id = $DB->insert_record('peer_submission', $formdata);
        }

        $formdata = file_postupdate_standard_editor($formdata, 'content', $contentopts, $peer->context,
            'mod_peer', 'submission_content', $submission->id);

        $blogid = $peer->submission_post_blog($formdata);
        $formdata->blogid = $blogid;

        $DB->update_record('peer_submission', $formdata);

        // $peer->updateComplition($formdata->groupid);

        $peer->criteria = $peer->get_all_criterias();
        $peer->course = $peer->course->id;
        peer_update_grades($peer);

        redirect($peer->submission_url($formdata->id));

    } else {
        // displays the form
        $mform->display();
    }
} else if ($subid > 0 || $groupid > 0) {

    $submission = $peer->get_submission_by_id($subid);
    if ($groupid > 0) {
        $submission = $peer->get_submission_by_groupid($groupid);
        if ($version > 0) {
            $submission = $peer->get_submission_by_groupid($groupid, $version);
        }

        $submission = new peer_submission($submission, $peer);
        $submission->reviews = $peer->get_reviews_for_submission($submission->id);

    }

    $PAGE->set_url($peer->submission_url(), array('cmid' => $cmid, 'subid' => $submission->id, 'groupid' => $submission->groupid));

    // Abgabe anschauen und Reviews lesen

    if ($submission) {
        $groupname = $peer->get_group_game($submission->groupid);

        echo $output->heading(format_string($peer->name . ' - ' . $groupname), 2);

        echo $output->render($submission);
    } else {
        echo get_string('submission_nosubwithid', 'peer');
    }
} else {
    echo get_string('submission_nosubwithid', 'peer');
}
echo $output->footer();
