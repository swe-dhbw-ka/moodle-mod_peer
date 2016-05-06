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
/** Renderer for peer plugin
 *
 * @package mod_peer
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Class mod_peer_renderer is defined here.
 *
 * @category    output
 * @package mod_peer
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_peer_renderer extends plugin_renderer_base {


    /**
     * Render reviewable overview
     * @param peer_reviewable_overview $overview
     * @return mixed
     */
    protected function render_peer_reviewable_overview(peer_reviewable_overview $overview) {

        $table = new html_table();
        $table->head = array(get_string('renderer_projectname', 'peer'), get_string('renderer_projectcategory', 'peer'), get_string('renderer_viewproject', 'peer'));

        $rows = array();

        foreach ($overview->peergroups->peergroups as $group) {

            $groupurl = new moodle_url('/user/index.php', array('id' => $overview->peer->course->id, 'group' => $group->groupid));
            $groupnamelink = $this->render(new action_link($groupurl, $group->groupname));

            $suburl = new moodle_url('submission.php', array('cmid' => $overview->peer->cm->id, 'groupid' => $group->groupid));
            $groupsubmissionlink = $this->render(new action_link($suburl, get_string('renderer_viewproject', 'peer')));

            $row = array($groupnamelink, $overview->peer->get_category_for_groupid($group->groupid), $groupsubmissionlink);
            array_push($rows, $row);
        }

        $table->data = $rows;
        return html_writer::table($table);
    }

    /**
     * Render criterias
     * @param peer_criterias $criterias
     * @return mixed
     */
    protected function render_peer_criterias(peer_criterias $criterias) {
        $data = array();
        foreach ($criterias->criterias as $criteria) {
            array_push($data, $criteria->text);
        }
        return html_writer::alist($data);
    }


    /**
     * Render overview (teacher)
     * @param peer_overview $overview
     * @return string
     */
    protected function render_peer_overview(peer_overview $overview) {
        $o = '';

        $infodata = new stdClass();
        $infodata->subcount = $overview->submissioncount;
        $infodata->revcount = $overview->reviewcount;
        $infodata->missingsubcount = $overview->missingsubmissioncount;
        $infodata->missingrevcount = $overview->missingreviewcount;

        $o .= $this->box(get_string('renderer_overviewinfo', 'peer', $infodata), 'generalbox');

        $table = new html_table();

        $one = get_string('renderer_projectname', 'peer');
        $two = get_string('renderer_submitted', 'peer');
        $three = get_string('renderer_wrtittenreviews', 'peer');

        $table->head = array($one, $two, $three, get_string('renderer_grade', 'peer'));

        $rows = array();

        foreach ($overview->peergroups->peergroups as $group) {

            $groupurl = new moodle_url('/user/index.php', array('id' => $overview->peer->course->id, 'group' => $group->groupid));

            $groupnamelink = $this->render(new action_link($groupurl, $group->groupname));

            $csubmissioninfo = '✖';
            $grade = 0;
            if (!is_null($group->submission)) {
                $suburl = new moodle_url('submission.php', array('cmid' => $overview->peer->cm->id, 'groupid' => $group->groupid));
                $groupsubmissionlink = $this->render(new action_link($suburl, get_string('renderer_viewproject', 'peer')));
                $csubmissioninfo = '✔' . ' ' . $groupsubmissionlink;

                $subgrades = $overview->peer->get_submission_grades_for_group($group->groupid);
                if ($subgrades) {

                    $tmpgrade = 0;

                    foreach ($subgrades as $subgrade) {
                        $tmpgrade = $tmpgrade + $subgrade->sumgrade / $subgrade->gradecount;
                    }

                    $grade = $tmpgrade / count($subgrades);

                }
            }

            $creviewinfo = '✖';
            if (!is_null($group->reviews)) {

                $groupurl = new moodle_url('review.php', array('cmid' => $overview->peer->cm->id, 'groupid' => $group->groupid));

                $groupreviewlink = $this->render(new action_link($groupurl, get_string('renderer_viewproject', 'peer')));
                $creviewinfo = count($group->reviews->reviews) . ' ' . $groupreviewlink;
            }
            $row = array($groupnamelink, $csubmissioninfo, $creviewinfo, round($grade, 2));
            array_push($rows, $row);
        }

        $table->data = $rows;

        $o .= html_writer::table($table);

        return $o;

    }


    /**
     * Render duedates
     * @param peer_duedates $duedates
     * @return mixed
     */
    protected function render_peer_duedates(peer_duedates $duedates) {

        $data = array();
        foreach ($duedates->duedates as $duedate) {
            array_push($data, $duedate->groupingname . ' : ' . userdate($duedate->duedate));
        }
        return html_writer::alist($data);
    }


    /**
     * Redner submissions
     * @param peer_submissions $submissions
     * @return mixed
     */
    protected function render_peer_submissions(peer_submissions $submissions) {

        $table = new html_table();
        $table->head = array('Projekt Name', 'Anzahl Reviews', 'Reviewen');

        $data = array();
        foreach ($submissions->submissions as $submission) {
            $row = array($submission['group'], '0', '<input class="singlebutton" type="submit" value="Go"/>');

            array_push($data, $row);
        }
        $table->data = $data;

        return html_writer::table($table);
    }


    /**
     * Render reviews
     * @param peer_reviews $reviews
     * @return string
     */
    protected function render_peer_reviews(peer_reviews $reviews) {

        $o = '';

        $peer = $reviews->peer;
        $index = 1;

        foreach ($reviews->reviews as $review) {
            $o .= $this->heading($index . ' ' . $peer->get_group_game($review->groupid), 1, 'title');
            $groupnamelink = $this->render(new action_link($peer->submission_url($review->submission), get_string('renderer_viewproject', 'peer')));

            $o .= $groupnamelink;
            $index++;
        }

        return $o;
    }


    /**
     * Render submission
     * @param peer_submission $submission
     * @return string
     */
    protected function render_peer_submission(peer_submission $submission) {

        global $DB;
        global $USER;

        $o = '';

        $options = array();

        if ($submission->versions) {
            foreach ($submission->versions as $version) {
                array_push($options, 'Version ' . $version->version);
            }
        } else {
            array_push($options, 'Version 1');
        }

        $o .= $this->single_select($this->page->url, 'version', $options, $submission->version - 1);
        $o .= $this->output->container($submission->content, 'content');

        $outreviews = '';

        $outreviews .= print_collapsible_region_start('', 'peer-viewlet-reviews', 'Reviews', true, true, true);

        if ($submission->reviews) {

            $index = 1;

            foreach ($submission->reviews->reviews as $review) {
                $groupname = $submission->peer->get_group_game($review->groupid);
                $group = $submission->peer->get_group_by_id($review->groupid);

                $outreviews .= $this->output->container_start('feedback feedbackforauthor');
                $outreviews .= $this->output->container_start('header');

                $stringdata = new stdClass();
                $stringdata->index = $index;
                $stringdata->groupname = $groupname;

                $outreviews .= $this->output->heading(get_string('renderer_reviewby', 'peer', $stringdata), 3, 'title');
                $index++;

                $coursecontext = context_course::instance($submission->peer->course->id);

                $grouppictureurl = moodle_url::make_pluginfile_url($coursecontext->id, 'group', 'icon', $group->id, '/', 'f1');
                $grouppictureurl->param('rev', $group->picture);

                $src = $grouppictureurl;
                $alt = $groupname;
                $attributes = array('class' => 'mod_peer_grouppicture', 'title' => $groupname);
                $groupimage = html_writer::img($src, $alt, $attributes);

                $outreviews .= $groupimage;
                $outreviews .= $this->output->container_end(); // end of header

                $content = format_text($review->content, $review->contentformat, array('overflowdiv' => true));

                $reviewcriterias = $submission->peer->get_reviewcriteria_by_review($review->id);

                $contentrev = '<h4>' . get_string('criteria_plural', 'peer') . '</h4>';

                foreach ($reviewcriterias as $criteria) {
                    $text = $submission->peer->get_criteria_by_id($criteria->criteria)->text;
                    if ($criteria->fulfill) {
                        $contentrev .= '✔ ' . $text . '<br />';
                    } else {
                        $contentrev .= '✖ ' . $text . '<br />';
                    }
                }
                $content .= $contentrev;

                $outreviews .= $this->output->container($content, 'mod_peer_review_content');
                $outreviews .= $this->output->container_end();
            }
        } else {
            $outreviews .= get_string('renderer_noreviewsrecieved', 'peer');
        }

        $outreviews .= print_collapsible_region_end(true);

        $o .= $outreviews;

        $canreview = is_enrolled($submission->peer->context, $USER, 'mod/peer:peerreview');
        $ismemberofgroup = groups_is_member($submission->groupid, $USER->id);

        if ($canreview and !$ismemberofgroup) {
            $btnurl = new moodle_url('/mod/peer/review.php', array('cmid' => $submission->peer->cm->id, 'subid' => $submission->id));
            $o .= $this->single_button($btnurl, get_string('renderer_writereview', 'peer'));
        }

        return $o;
    }


}