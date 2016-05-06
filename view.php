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
 * View the peer instance
 *
 * @package mod_peer
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once($CFG->libdir . '/completionlib.php');

// Parameter
$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$p = optional_param('w', 0, PARAM_INT);  // peer instance ID

$id = required_param('id', PARAM_INT);
list ($course, $cm) = get_course_and_cm_from_cmid($id, 'peer');
$peerrecord = $DB->get_record('peer', array('id' => $cm->instance), '*', MUST_EXIST);
// Security
require_login($course, true, $cm);
require_capability('mod/peer:view', $PAGE->context);

$peer = new peer($peerrecord, $cm, $course);

// Mark viewed
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Print the page header.
$PAGE->set_url('/mod/peer/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($peer->name));
$PAGE->set_heading(format_string($course->fullname));

$output = $PAGE->get_renderer('mod_peer');

echo $output->header();
echo $output->heading($peer->name);

print_collapsible_region_start('', 'peer-viewlet-intro', get_string('description', 'peer'));
echo $output->box(format_module_intro('peer', $peer, $peer->cm->id), 'generalbox');

echo $output->box(get_string('duedates', 'peer'), 'generalbox');
$duedates = $peer->get_due_dates();
echo $output->render($duedates);

echo $output->box(get_string('criteria_plural', 'peer'), 'generalbox');
$criterias = $peer->get_all_criterias();
echo $output->render($criterias);
print_collapsible_region_end();

if (has_capability('mod/peer:viewoverview', $PAGE->context)) {
    // Teacher
    print_collapsible_region_start('', 'peer-viewlet-overview', 'Overview');
    $overview = $peer->get_overview();
    echo $output->render($overview);
    print_collapsible_region_end();

} else {
    // Students

    print_collapsible_region_start('', 'peer-viewlet-abgabe', get_string('submission', 'peer'));

    $groupid = $peer->get_current_groupid();
    $submission = $peer->get_submission_by_groupid($groupid);

    if ($submission) {

        $reviews = $peer->get_reviews_for_submission($submission->id);
        if ($reviews) {
            echo $output->box(get_string('view_reviewavailable', 'peer'), 'generalbox');
            $btnurl = new moodle_url($peer->submission_url(), array('groupid' => $peer->get_current_groupid()));
            echo $output->single_button($btnurl, get_string('renderer_viewproject', 'peer'), 'get');
        }

        $btnurl = new moodle_url($peer->submission_url(), array('edit' => 'yes'));
        echo $output->single_button($btnurl, get_string('edit', 'peer'), 'get');
    } else {

        $stringdata = new stdClass();
        $stringdata->groupname = $peer->get_current_groupname();

        echo $output->box(get_string('view_hastosubmit', 'peer', $stringdata), 'generalbox');

        $btnurl = new moodle_url($peer->submission_url(), array('edit' => 'yes'));
        echo $output->single_button($btnurl, get_string('view_submit', 'peer'), 'get');
    }
    print_collapsible_region_end();

    $reviews = $peer->get_reviews_by_groupid($groupid);

    print_collapsible_region_start('', 'peer-viewlet-review', 'Review');

    $reviews = $peer->get_reviews_by_groupid($peer->get_current_groupid());

    if (count($reviews) < 2) {
        $stringdata = new stdClass();
        $stringdata->groupname = $peer->get_current_groupname();
        $stringdata->reviewcount = 2 - count($reviews);

        echo $output->box(get_string('view_hastoreview', 'peer', $stringdata), 'generalbox');
    }

    $possiblereviews = $peer->get_reviewable_overview();
    echo $output->render($possiblereviews);
    print_collapsible_region_end();
}

echo $OUTPUT->footer();
