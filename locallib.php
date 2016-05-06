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
/** Local libary for Peer plugin. Defines peer add wrapper functions for databse access
 *
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Class peer
 * @package mod_peer
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class peer {
    /** @var cm_info course module record */
    public $cm;

    /** @var stdclass course record */
    public $course;

    /** @var stdclass context object */
    public $context;

    /** @var int peer instance identifier */
    public $id;

    /** @var string peer activity name */
    public $name;

    /**
     * @var
     */
    public $intro;
    /**
     * @var
     */
    public $introformat;


    /**
     * @var
     */
    public $timecreated;
    /**
     * @var
     */
    public $timemodified;


    /**
     * peer constructor.
     * @param stdclass $dbrecord Db record of peer
     * @param object $cm Coursemodule
     * @param object $course Course
     * @param stdclass|null $context Context of peer
     * @throws coding_exception
     */
    public function __construct(stdclass $dbrecord, $cm, $course, stdclass $context = null) {
        foreach ($dbrecord as $field => $value) {
            if (property_exists('peer', $field)) {
                $this->{$field} = $value;
            }
        }
        if (is_null($cm) || is_null($course)) {
            throw new coding_exception('Must specify $cm and $course');
        }
        $this->course = $course;
        if ($cm instanceof cm_info) {
            $this->cm = $cm;
        } else {
            $modinfo = get_fast_modinfo($course);
            $this->cm = $modinfo->get_cm($cm->id);
        }
        if (is_null($context)) {
            $this->context = context_module::instance($this->cm->id);
        } else {
            $this->context = $context;
        }
    }

    /**
     * Return groupings of course
     * @return mixed
     */
    public function get_groupings() {
        $array = groups_get_all_groupings($this->course->id);
        return $array;
    }


    /**
     * Return reviewcriterias
     * @param int $id
     * @return mixed
     */
    public function get_reviewcriteria_by_review($id) {
        global $DB;

        $reviewcriteras = $DB->get_records('peer_reviewcriteria', array('review' => $id));

        return $reviewcriteras;
    }


    /**
     * Send message via Message Api
     * @param stdClass $submission
     * @param stdClass $review
     */
    public function review_send_message($submission, $review) {

        global $USER;

        $message = new \core\message\message();
        $message->component = 'moodle';
        $message->name = 'instantmessage';
        $message->userfrom = $USER;

        $message->subject = get_string('review_recieved', 'peer');
        $message->fullmessage = 'message body';
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml = $submission->content . $review->content;
        $message->smallmessage = get_string('review_recieved', 'peer');
        $message->notification = '0';
        // $message->contexturl = 'http://GalaxyFarFarAway.com';
        // $message->contexturlname = 'Context name';

        $members = groups_get_members($submission->groupid, $fields = 'u.*', $sort = 'lastname ASC');

        foreach ($members as $member) {
            $message->userto = $member;
            $messageid = message_send($message);
        }
    }


    /**
     * Return all Groups of course
     * @return mixed
     */
    public function get_all_groups() {
        return groups_get_all_groups($this->course->id);
    }


    /**
     * Update Complition state
     * @param int $groupid
     */
    public function update_complition($groupid) {
        $completion = new completion_info($this->course);
        if ($completion->is_enabled($this->cm) && true) {

            $completion = 0;

            $submission = $this->get_submission_by_groupid($groupid);
            if ($submission) {
                $completion = $completion + 1;
            }

            $reviews = $this->get_reviews_by_groupid($groupid);
            if ($reviews) {
                if (is_array($reviews)) {
                    $completion = $completion + count($reviews);
                }
            }

            $completioninfo = new completion_info($this->course);

            if ($completion >= 3) {
                $completioninfo->update_state($this->cm, COMPLETION_COMPLETE);
            } else {
                $completioninfo->update_state($this->cm, COMPLETION_INCOMPLETE);
            }
        }
    }


    /**
     * Get Categorys for groupid. If groupoverview Plugin is installed.
     * @param int $groupid
     * @return string
     */
    public function get_category_for_groupid($groupid) {

        global $CFG, $DB;

        // Check whether the groupoverview plugin is installed.
        $groupoverviewinstalled = false;
        $plugins = core_plugin_manager::instance()->get_plugins_of_type('mod');
        if (isset($plugins['groupoverview'])) {
            $groupoverviewinstalled = true;
            require_once("$CFG->dirroot/mod/groupoverview/lib.php");
        }

        if (!$groupoverviewinstalled) {
            return 'N/A';
        }

        $allgroupoverviewinstances = $DB->get_records('groupoverview', array('course' => $this->course->id));

        if (count($allgroupoverviewinstances) > 0) {
            foreach ($allgroupoverviewinstances as $groupoverview) {

                $groupoverviewid = $groupoverview->id;

                $sql = "SELECT m.groupid, c.name FROM {groupoverview_mappings} m JOIN {groupoverview_categories} c
                        ON c.id = m.categoryid WHERE c.groupoverviewid = :groupoverviewid AND m.groupid = :groupid";

                $params = array('groupoverviewid' => $groupoverviewid, 'groupid' => $groupid);
                $record = $DB->get_record_sql($sql, $params);

                if ($record) {
                    return $record->name;
                } else {
                    return 'N/A';
                }

            }
        }
    }


    /**
     * Return Reviewable Overview
     * @return peer_reviewable_overview
     */
    public function get_reviewable_overview() {

        global $DB;

        $sql = "SELECT s.id,s.peer,s.groupid, s.timecreated, max(s.version),s.content,s.contentformat
                  FROM {peer_submission} s
                 WHERE s.peer = :peer GROUP BY s.groupid";
        $params['peer'] = $this->id;

        $groupswithsubmissionsdb = $DB->get_records_sql($sql, $params);

        $groups = array();

        foreach ($groupswithsubmissionsdb as $dbgroup) {

            $groupid = $dbgroup->groupid;
            $groupname = $this->get_group_game($groupid);

            $submission = $this->get_submission_by_id($dbgroup->id);

            $reviews = $this->get_reviews_by_groupid($groupid);

            if (is_object($reviews)) {
                $reviews = array($reviews);
            }

            $reviews = new peer_reviews($reviews, $this);

            $group = new peer_group($groupid, $groupname, $submission, $reviews);
            array_push($groups, $group);
        }

        $peergroups = new peer_groups($groups);
        $reviewableoverview = new peer_reviewable_overview($peergroups, $this);

        return $reviewableoverview;

    }

    /**
     * Get group name for groupid
     * @param int $groupid
     * @return mixed
     */
    public function get_group_game($groupid) {
        return groups_get_group_name($groupid);
    }

    /**
     * Return group information of groupid
     * @param int $groupid
     * @return mixed
     */
    public function get_group_by_id($groupid) {
        return groups_get_group($groupid);
    }

    /**
     * Return Overview
     * @return peer_overview
     */
    public function get_overview() {

        $groupsrecords = $this->get_all_groups();
        $groups = array();

        foreach ($groupsrecords as $grecord) {
            $groupid = $grecord->id;
            $groupname = $grecord->name;

            $sub = $this->get_submission_by_groupid($groupid);

            $submission = null;
            if ($sub) {
                $submission = new peer_submission($sub, $this);
            }

            $revs = $this->get_reviews_by_groupid($groupid);

            $reviews = null;
            if ($revs) {

                if (is_object($revs)) {
                    $revs = array($revs);
                }
                $reviews = new peer_reviews($revs, $this);
            }

            $g = new peer_group($groupid, $groupname, $submission, $reviews);
            array_push($groups, $g);
        }

        $pgroups = new peer_groups($groups);
        $overview = new peer_overview($pgroups, $this);
        return $overview;
    }

    /**
     * Return tag for peer instance
     * @return string
     */
    public function get_peer_tag() {
        return 'peer_' . trim(strtolower($this->name));
    }


    /**
     * Return tag for group
     * @return string
     */
    public function get_current_group_tag() {
        return 'group_' . trim(strtolower($this->get_group_game($this->get_current_groupid())));
    }

    /**
     * Post submission to website blog
     * @param stdClass $submission
     * @return mixed
     */
    public function submission_post_blog($submission) {
        global $USER, $CFG;

        require_once($CFG->dirroot . '/blog/locallib.php');
        require_once($CFG->dirroot . '/tag/lib.php');

        $data = new stdClass();
        $data->id = null;

        $data->subject = get_string('modulename', 'peer') . ' ' . $this->get_current_groupid();

        $summary = file_rewrite_pluginfile_urls($submission->content, 'pluginfile.php', $this->context->id, 'mod_peer', 'submission_content', $submission->id);
        $data->summary = $summary;
        $data->publishstate = 'site';
        $data->tags = array($this->get_peer_tag(), $this->get_current_group_tag());
        $data->courseid = $this->course->id;
        $data->groupid = $this->get_current_groupid();

        $blogentry = new blog_entry(null, $data);
        $blogentry->add();
        $blogentry->add_association($this->context->id);

        $coursecontext = context_course::instance($this->course->id);
        $blogentry->add_association($coursecontext->id);

        return $blogentry->id;
    }


    /**
     * Create Comment for for submission (blog)
     * @param stdClass $submission
     * @param stdClass $review
     * @return mixed
     */
    public function review_post_comment($submission, $review) {
        global $USER, $DB;
        $now = time();
        $newcmt = new stdClass;

        $bloguser = $DB->get_record('post', array('id' => $submission->blogid));
        $newcmt->contextid = context_user::instance($bloguser->userid)->id;
        $newcmt->courseid = $this->course->id;
        $newcmt->commentarea = 'format_blog';
        $newcmt->itemid = $submission->blogid;
        $newcmt->component = 'blog';

        $content = $review->content;

        $reviewcriterias = $submission->peer->get_reviewcriteria_by_review($review->id);

        $contentrev = '<h4>' . get_string('criteria_plural', 'peer') . '</h4>';

        foreach ($reviewcriterias as $criteria) {
            $text = $this->get_criteria_by_id($criteria->criteria)->text;
            if ($criteria->fulfill) {
                $contentrev .= '✔ ' . $text . '<br />';
            } else {
                $contentrev .= '✖ ' . $text . '<br />';
            }
        }
        $content .= $contentrev;

        $newcmt->content = $content;
        $newcmt->format = 1;
        $newcmt->userid = $USER->id;
        $newcmt->timecreated = $now;

        $cmtid = $DB->insert_record('comments', $newcmt);

        return $cmtid;
    }


    public function get_submission_grades_for_group($id) {
        global $DB;

        $sql = "SELECT s.id, SUM(r.grade) as sumgrade, COUNT(r.grade) as gradecount
                  FROM {peer_submission} s
                       JOIN {peer_review} r ON r.submission = s.id
                 WHERE s.groupid = :groupid AND s.peer = :peer GROUP BY s.id";
        $params['peer'] = $this->id;
        $params['groupid'] = $id;
        $subgrades = $DB->get_records_sql($sql, $params);

        return $subgrades;
    }


    /**
     * Return true if first submit by group
     * @param int|null $id
     * @return mixed
     */
    public function is_first_submission_by_group($id = null) {
        if (is_null(id)) {
            $id = $this->get_current_groupid();
        }
        return $this->get_submission_by_groupid($id);
    }


    /**
     * Return max verison for group submissions
     * @param int $id
     * @return mixed
     */
    public function get_max_version_for_group_submission($id) {
        global $DB;

        $sql = "SELECT COUNT(s.id)
                      FROM {peer_submission} s
                     WHERE s.peer = :peer AND s.groupid = :groupid";

        $params['peer'] = $this->id;
        $params['groupid'] = $id;
        $count = $DB->count_records_sql($sql, $params);

        return $count;
    }


    /**
     * Return all due dates
     * @return peer_duedates
     */
    public function get_due_dates() {
        global $DB;

        $duedatesrecords = $DB->get_records('peer_duedate', array('peer' => $this->id));

        $duedates = array();

        foreach ($duedatesrecords as $date) {
            $duedate = new peer_duedate($date);
            array_push($duedates, $duedate);
        }

        return new peer_duedates($duedates);

    }

    /**
     * Return current groupid
     * @return mixed
     */
    public function get_current_groupid() {
        return groups_get_activity_group($this->cm);
    }

    /**
     * Return current groupname
     * @return mixed
     */
    public function get_current_groupname() {
        return groups_get_group_name($this->get_current_groupid());
    }

    /**
     * Return view url
     * @return moodle_url
     */
    public function view_url() {
        global $CFG;
        return new moodle_url('/mod/peer/view.php', array('id' => $this->cm->id));
    }

    /**
     * Return submission url
     * @param int|null $id
     * @param int|null $groupid
     * @return moodle_url
     */
    public function submission_url($id = null, $groupid = null) {
        global $CFG;
        return new moodle_url('/mod/peer/submission.php', array('cmid' => $this->cm->id, 'subid' => $id, 'groupid' => $groupid));
    }

    /**
     * Return review url
     * @param int|null $subid
     * @return moodle_url
     */
    public function review_url($subid = null) {
        global $CFG;
        return new moodle_url('/mod/peer/review.php', array('cmid' => $this->cm->id, 'subid' => $subid));
    }


    /**
     * Return versions for submission
     * @param int $id
     * @return mixed
     */
    public function get_submission_versions_by_group($id) {

        global $DB;

        $sql = "SELECT s.version
                  FROM {peer_submission} s
                 WHERE s.peer = :peer AND groupid = :groupid";
        $params['peer'] = $this->id;
        $params['groupid'] = $id;

        $versions = $DB->get_records_sql($sql, $params);

        return $versions;

    }


    /**
     * Return all criterias
     * @return peer_criterias
     */
    public function get_all_criterias() {
        global $DB;

        $sql = "SELECT c.id, c.peer, c.text
                  FROM {peer_criteria} c
                 WHERE c.peer = :peer";
        $params['peer'] = $this->id;

        $criterias = new peer_criterias($DB->get_records_sql($sql, $params));

        return $criterias;
    }


    /**
     * Return a criteria
     * @return mixed
     */
    public function get_criteria_by_id($id) {
        global $DB;
        return $DB->get_record('peer_criteria', array('id' => $id));
    }


    /**
     * Return submission by id
     * @param int $id
     * @return null|peer_submission
     */
    public function get_submission_by_id($id) {
        global $DB;

        $submission = null;

        if ($DB->record_exists('peer_submission', array('id' => $id, 'peer' => $this->id))) {
            $submissionrecord = $DB->get_record('peer_submission', array('id' => $id, 'peer' => $this->id), '*', MUST_EXIST);
            $submission = new peer_submission($submissionrecord, $this);
            $submission->reviews = $this->get_reviews_for_submission($submission->id);
        }

        return $submission;
    }


    /**
     * Return reviews for submission
     * @param int $id
     * @return null|peer_reviews
     */
    public function get_reviews_for_submission($id) {
        global $DB;

        $sql = "SELECT *
                  FROM {peer_review} r
                 WHERE r.peer = :peer AND r.submission = :submission";
        $params['peer'] = $this->id;
        $params['submission'] = $id;

        $reviews = null;

        if ($DB->record_exists_sql($sql, $params)) {

            $dbreviews = $DB->get_records_sql($sql, $params);

            if (is_object($dbreviews)) {
                $dbreviews = array($dbreviews);
            }

            $revs = array();

            foreach ($dbreviews as $rev) {

                $r = new peer_review($rev);
                array_push($revs, $r);
            }

            $reviews = new peer_reviews($revs, $this);
        }

        return $reviews;

    }


    /**
     * Return all submissions
     * @return peer_submissions
     */
    public function get_all_submissions() {
        global $DB;

        $sql = "SELECT s.id, s.peer, s.groupid
                  FROM {peer_submission} s
                 WHERE s.peer = :peer";
        $params['peer'] = $this->id;

        $submissions = new peer_criterias($DB->get_records_sql($sql, $params));

        return new peer_submissions($submissions);
    }


    /**
     * Return submission by group
     * @param int $id
     * @param int $version
     * @return mixed
     */
    public function get_submission_by_groupid($id, $version = -1) {
        global $DB;

        $sql = '';
        $params = array();

        if ($version < 0) {

            $sql = "select max(s2.version) as 'version'
                     from {peer_submission} s2 WHERE s2.peer = :peer2 AND s2.groupid = :groupid2";
            $params['peer2'] = $this->id;
            $params['groupid2'] = $id;

            $version = $DB->get_record_sql($sql, $params);

            $sql = "SELECT s.id,s.peer,s.groupid, s.timecreated,s.version,s.content,s.contentformat
                  FROM {peer_submission} s
                 WHERE s.peer = :peer1 AND groupid = :groupid1 AND version = :version";
            $params['peer1'] = $this->id;
            $params['groupid1'] = $id;
            $params['version'] = $version->version;

        } else {

            $sql .= "SELECT s.id,s.peer,s.groupid, s.timecreated, s.version,s.content,s.contentformat
                  FROM {peer_submission} s
                 WHERE s.peer = :peer AND s.version = :version  AND groupid = :groupid";
            $params['peer'] = $this->id;
            $params['groupid'] = $id;
            $params['version'] = $version;
        }

        $submission = $DB->get_record_sql($sql, $params);

        return $submission;
    }


    /**
     * Return all reviews written by group
     * @param int $id
     * @return mixed
     */
    public function get_reviews_by_groupid($id) {
        global $DB;

        $sql = "SELECT *
                  FROM {peer_review} r
                 WHERE r.peer = :peer AND r.groupid = :groupid";
        $params['peer'] = $this->id;
        $params['groupid'] = $id;

        $reviews = $DB->get_records_sql($sql, $params);
        return $reviews;
    }
}

/**
 * Class peer_criterias
 * @package mod_peer
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class peer_criterias implements renderable {
    /**
     * @var array
     */
    public $criterias;

    /**
     * peer_criterias constructor.
     * @param array $crits
     */
    public function __construct(array $crits) {
        $this->criterias = $crits;
    }
}


/**
 * Class peer_submission
 * @package mod_peer
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class peer_submission implements renderable {
    /**
     * @var
     */
    public $id;
    /**
     * @var
     */
    public $peer;
    /**
     * @var
     */
    public $groupid;
    /**
     * @var
     */
    public $content;
    /**
     * @var
     */
    public $reviews;
    /**
     * @var
     */
    public $versions;
    /**
     * @var
     */
    public $blogid;
    /**
     * @var
     */
    public $version;
    /**
     * @var bool
     */
    public $maxversion;

    /**
     * peer_submission constructor.
     * @param stdClass $dbrecord
     * @param stdClass $ppeer
     */
    public function __construct(stdClass $dbrecord, $ppeer) {

        if ($dbrecord->peer === $ppeer->id) {
            $dbrecord->peer = $ppeer;
        }

        foreach ($dbrecord as $field => $value) {
            if (property_exists('peer_submission', $field)) {
                $this->{$field} = $value;
            }
        }

        if (!$this->versions) {
            $versions = $ppeer->get_submission_versions_by_group($this->groupid);
            $this->versions = $versions;
        }

        if ($this->version === max($this->versions)) {
            $this->maxversion = true;
        } else {
            $this->maxversion = false;
        }

        $this->content = file_rewrite_pluginfile_urls($dbrecord->content, 'pluginfile.php', $dbrecord->peer->context->id, 'mod_peer', 'submission_content', $dbrecord->id);
    }

}

/**
 * Class peer_review
 * @package mod_peer
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class peer_review implements renderable {

    /**
     * @var
     */
    public $id;
    /**
     * @var
     */
    public $submission;
    /**
     * @var
     */
    public $groupid;
    /**
     * @var
     */
    public $version;
    /**
     * @var
     */
    public $content;
    /**
     * @var
     */
    public $contentformat;

    /**
     * peer_review constructor.
     * @param stdClass $dbrecord
     */
    public function __construct(stdClass $dbrecord) {
        foreach ($dbrecord as $field => $value) {
            if (property_exists('peer_review', $field)) {
                $this->{$field} = $value;
            }
        }
    }
}

/**
 * Class peer_duedates
 * @package mod_peer
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class peer_duedates implements renderable {

    /**
     * @var array
     */
    public $duedates;

    /**
     * peer_duedates constructor.
     * @param array $duedates
     */
    public function __construct(array $duedates) {
        $this->duedates = $duedates;
    }
}


/**
 * Class peer_duedate
 * @package mod_peer
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class peer_duedate {

    /**
     * @var
     */
    public $id;
    /**
     * @var
     */
    public $peer;
    /**
     * @var
     */
    public $groupingid;
    /**
     * @var
     */
    public $duedate;
    /**
     * @var
     */
    public $groupingname;

    /**
     * peer_duedate constructor.
     * @param stdClass $dbrecord
     */
    public function __construct(stdClass $dbrecord) {
        foreach ($dbrecord as $field => $value) {
            if (property_exists('peer_duedate', $field)) {
                $this->{$field} = $value;
            }
        }

        $this->groupingname = groups_get_grouping_name($this->groupingid);
    }
}


/**
 * Class peer_reviews
 * @package mod_peer
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class peer_reviews implements renderable {
    /**
     * @var array
     */
    public $reviews;
    /**
     * @var peer
     */
    public $peer;


    /**
     * peer_reviews constructor.
     * @param array $revs
     * @param peer $peer
     */
    public function __construct(array $revs, peer $peer) {
        $this->reviews = $revs;
        $this->peer = $peer;
    }
}


/**
 * Class peer_submissions
 * @package mod_peer
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class peer_submissions implements renderable {
    /**
     * @var array
     */
    public $submissions;

    /**
     * peer_submissions constructor.
     * @param array $subs
     */
    public function __construct(array $subs) {
        $this->submissions = $subs;
    }
}


/**
 * Class peer_groups
 * @package mod_peer
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class peer_groups implements renderable {
    /**
     * @var array
     */
    public $peergroups;

    /**
     * peer_groups constructor.
     * @param array $groups Groups
     */
    public function __construct(array $groups) {
        $this->peergroups = $groups;
    }
}


/**
 * Class peer_group
 * @package mod_peer
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class peer_group {
    /**
     * @var
     */
    public $groupid;
    /**
     * @var
     */
    public $groupname;
    /**
     * @var peer_submission
     */
    public $submission;
    /**
     * @var peer_reviews
     */
    public $reviews;

    /**
     * peer_group constructor.
     * @param $groupid
     * @param $groupname
     * @param peer_submission|null $submission
     * @param peer_reviews|null $reviews
     */
    public function __construct($groupid, $groupname, peer_submission $submission = null, peer_reviews $reviews = null) {
        $this->groupid = $groupid;
        $this->groupname = $groupname;
        $this->submission = $submission;
        $this->reviews = $reviews;
    }
}

/**
 * Class peer_reviewable_overview
 * @package mod_peer
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class peer_reviewable_overview implements renderable {
    /**
     * @var peer
     */
    public $peer;
    /**
     * @var peer_groups
     */
    public $peergroups;

    /**
     * peer_reviewable_overview constructor.
     * @param peer_groups $groups
     * @param peer $peer
     */
    public function __construct(peer_groups $groups, peer $peer) {
        $this->peergroups = $groups;
        $this->peer = $peer;
    }

}

/**
 * Class peer_overview
 * @package mod_peer
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class peer_overview implements renderable {


    /**
     * @var peer
     */
    public $peer;
    /**
     * @var peer_groups
     */
    public $peergroups;
    /**
     * @var int
     */
    public $submissioncount;
    /**
     * @var int
     */
    public $reviewcount;
    /**
     * @var int
     */
    public $missingsubmissioncount;
    /**
     * @var int
     */
    public $missingreviewcount;


    /**
     * peer_overview constructor.
     * @param peer_groups $groups
     * @param peer $peer
     */
    public function __construct(peer_groups $groups, peer $peer) {
        $this->peergroups = $groups;
        $this->peer = $peer;
        $this->submissioncount = 0;
        $this->reviewcount = 0;
        $this->missingreviewcount = 0;
        $this->missingsubmissioncount = 0;

        foreach ($groups->peergroups as $group) {
            if (isset($group->submission)) {
                $this->submissioncount++;
            } else {
                $this->missingsubmissioncount++;
            }

            if (isset($group->reviews)) {

                if (count($group->reviews->reviews) >= 2) {
                    $this->reviewcount = $this->reviewcount + 2;
                } else {
                    $this->reviewcount++;
                    $this->missingreviewcount++;
                }

            } else {
                $this->missingreviewcount = $this->missingreviewcount + 2;
            }

        }
    }


}

/**
 *
 * Check if the activity is completed, not completed or if no conditions are set
 *
 * @package mod_peer
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function peer_get_completion_state($course, $cm, $userid, $type) {
    global $DB;

    // Get selfgroup details.
    if (!($peer = $DB->get_record('peer', array('id' => $cm->instance)))) {
        throw new Exception("Can't find peer activity {$cm->instance}");
    }

    $groupid = groups_get_user_groups($course->id, $userid);

    if (is_array($groupid)) {
        $groupid = array_values($groupid)[0];
    }

    $completion = 0;

    $submission = $peer->get_submission_by_groupid($groupid);
    if ($submission) {
        $completion = $completion + 1;
    }

    $reviews = $this->get_reviews_by_groupid($groupid);
    if ($reviews) {
        if (is_array($reviews)) {
            $completion = $completion + count($reviews);
        }
    }

    if ($completion >= 3) {
        return true;
    } else {
        return false;
    }

}

