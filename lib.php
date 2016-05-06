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

/** Libary for Peer plugin
 *
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/calendar/lib.php');

/**
 * Returns the information if the module supports a feature
 * @param object $feature
 * @package mod_peer
 * @see plugin_supports() in lib/moodlelib.php
 * @copyright   2016 Johannes Grün <gruen.jojo.development@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @return mixed true if the feature is supported, null if unknown
 */
function peer_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the peer into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will save a new instance and return the id number
 * of the new instance.
 * @package mod_peer
 * @param stdClass $peer An object from the form in mod_form.php
 * @return int The id of the newly inserted peer record
 */
function peer_add_instance(stdclass $peer) {

    global $CFG, $DB;
    require_once(dirname(__FILE__) . '/locallib.php');

    $peer->timecreated = time();
    $peer->timemodified = time();

    // insert the new record so we get the id
    $peer->id = $DB->insert_record('peer', $peer);

    // we need to use context now, so we need to make sure all needed info is already in db
    $cmid = $peer->coursemodule;
    $DB->set_field('course_modules', 'instance', $peer->id, array('id' => $cmid));
    $context = context_module::instance($cmid);

    // insert Criterias into database
    foreach ($peer->criteria as $key => $value) {
        $value = trim($value);
        if (isset($value) && $value <> '') {
            $crt = new stdClass();
            $crt->text = $value;
            $crt->peer = $peer->id;
            $DB->insert_record("peer_criteria", $crt);
        }
    }
    // insert duedates into Database
    $groupings = groups_get_all_groupings($peer->course);
    foreach ($groupings as $grouping) {
        $date = $peer->{'date_' . $grouping->name};

        if ($date > 0) {

            $duedate = new stdClass();
            $duedate->peer = $peer->id;
            $duedate->groupingid = $grouping->id;
            $duedate->duedate = $date;

            $DB->insert_record("peer_duedate", $duedate);

            // Add Calender Events
            $groups = groups_get_all_groups($peer->course, 0, $grouping->id, $fields = 'g.*');

            $event = new stdClass;
            $event->name = get_string('modulename', 'peer') . ' ' . $peer->name;

            $event->description = format_module_intro('peer', $peer, $peer->coursemodule);
            $event->courseid = $peer->course;
            // $event->userid = 0;
            $event->modulename = 'peer';
            $event->instance = $peer->id;
            // For activity module's events, this can be used to set the alternative text of the event icon. Set it to 'pluginname' unless you have a better string.
            $event->eventtype = 'peer';
            $event->timestart = $date;
            $event->visible = true;
            $event->timeduration = 0;

            foreach ($groups as $group) {
                $event->groupid = $group->id;
                calendar_event::create($event);
            }
        }
    }

    peer_grade_item_update($peer);

    return $peer->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 * @package mod_peer
 * @param stdClass $peer An object from the form in mod_form.php
 * @return bool success
 */
function peer_update_instance(stdclass $peer) {
    global $CFG, $DB;
    require_once(dirname(__FILE__) . '/locallib.php');

    $peer->id = $peer->instance;
    $peer->timemodified = time();
    $DB->update_record('peer', $peer);
    $context = context_module::instance($peer->coursemodule);

    // insert Criterias into database
    foreach ($peer->criteria as $key => $value) {
        $value = trim($value);
        if (isset($value) && $value <> '') {
            $crt = new stdClass();
            $crt->text = $value;
            $crt->peer = $peer->id;
            $DB->insert_record("peer_criteria", $crt);
        }
    }
    // insert duedates into Database
    $groupings = groups_get_all_groupings($peer->course);
    foreach ($groupings as $grouping) {
        $date = $peer->{'date_' . $grouping->name};

        if ($date > 0) {
            $duedate = new stdClass();
            $duedate->peer = $peer->id;
            $duedate->groupingid = $grouping->id;
            $duedate->duedate = $date;

            $dbrecord = $DB->get_record('peer_duedate', array('peer' => $peer->id, 'groupingid' => $grouping->id));

            if (!$dbrecord) {
                $duedate = new stdClass();
                $duedate->peer = $peer->id;
                $duedate->groupingid = $grouping->id;
                $duedate->duedate = $date;
                $DB->insert_record("peer_duedate", $duedate);
            } else {
                $dbrecord->duedate = $date;
                $DB->update_record("peer_duedate", $dbrecord);
            }

            // Add Calender Events

            $currentevents = $DB->get_records('event', array('modulename' => 'peer', 'instance' => $peer->id));

            $groups = groups_get_all_groups($peer->course, 0, $grouping->id, $fields = 'g.*');

            $event = new stdClass;
            $event->name = get_string('modulename', 'peer') . ' ' . $peer->name;
            $event->description = format_module_intro('peer', $peer, $peer->coursemodule);
            $event->courseid = $peer->course;
            // $event->userid = 0;
            $event->modulename = 'peer';
            $event->instance = $peer->id;
            // For activity module's events, this can be used to set the alternative text of the event icon. Set it to 'pluginname' unless you have a better string.
            $event->eventtype = 'peer';
            $event->timestart = $date;
            $event->visible = true;
            $event->timeduration = 0;

            if ($reusedevent = array_shift($currentevents)) {
                $event->id = $reusedevent->id;
            } else {
                // should not be set but just in case
                unset($event->id);
            }
            foreach ($groups as $group) {
                $event->groupid = $group->id;

                $eventobj = new calendar_event($event);
                $eventobj->update($event, false);
            }
        }
    }

    peer_grade_item_update($peer);

    return true;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 * @package mod_peer
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function peer_delete_instance($id) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    if (!$peer = $DB->get_record('peer', array('id' => $id))) {
        return false;
    }

    $DB->delete_records('peer_duedate', array('peer' => $peer->id));

    // get the list of ids of all submissions
    $submissions = $DB->get_records('peer_submission', array('peer' => $peer->id), '', 'id');

    $reviews = $DB->get_records_list('peer_review', 'submission', array_keys($submissions), '', 'id');
    // Reviews
    $DB->delete_records_list('peer_reviewcriteria', 'review', array_keys($reviews));
    $DB->delete_records_list('peer_review', 'id', array_keys($reviews));
    $DB->delete_records_list('peer_submission', 'id', array_keys($submissions));

    $DB->delete_records('peer_criteria', array('peer' => $peer->id));

    // delete the calendar events
    $events = $DB->get_records('event', array('modulename' => 'peer', 'instance' => $peer->id));
    foreach ($events as $event) {
        $event = calendar_event::load($event);
        $event->delete();
    }

    // finally remove the peer record itself
    $DB->delete_records('peer', array('id' => $peer->id));

    grade_update('mod/peer', $peer->course, 'mod', 'peer', $peer->id, 0, null, array('deleted' => true));
    grade_update('mod/peer', $peer->course, 'mod', 'peer', $peer->id, 1, null, array('deleted' => true));

    return true;
}

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area peer_intro for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @package  mod_peer
 * @category files
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function peer_get_file_areas($course, $cm, $context) {
    $areas = array();
    $areas['submission_content'] = get_string('areasubmissioncontent', 'peer');
    return $areas;
}

/**
 * Serves the files from the peer file areas
 *
 * Apart from module intro (handled by pluginfile.php automatically), peer files may be
 * media inserted into submission content (like images) and submission attachments. For these two,
 * the fileareas submission_content and submission_attachment are used.
 * Besides that, areas instructauthors, instructreviewers and conclusion contain the media
 * embedded using the mod_form.php.
 *
 * @package  mod_peer
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the peer's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 */
function peer_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options = array()) {
    global $DB, $CFG, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, true, $cm);

    if ($filearea === 'submission_content') {
        $itemid = (int)array_shift($args);
        if (!$peer = $DB->get_record('peer', array('id' => $cm->instance))) {
            return false;
        }
        if (!$submission = $DB->get_record('peer_submission', array('id' => $itemid, 'peer' => $peer->id))) {
            return false;
        }

        $fs = get_file_storage();
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_peer/$filearea/$itemid/$relativepath";
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            return false;
        }
        // finally send the file
        // these files are uploaded by students - forcing download for security reasons
        send_stored_file($file, 0, 0, true, $options);

    }
    return false;
}

/**
 * File browsing support for peer file areas
 *
 * @package  mod_peer
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function peer_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG, $DB, $USER;

    /** @var array internal cache for author names */
    static $submissionauthors = array();

    $fs = get_file_storage();

    if ($filearea === 'submission_content') {

        // we are inside some particular submission container

        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;

        if (!$storedfile = $fs->get_file($context->id, 'mod_peer', $filearea, $itemid, $filepath, $filename)) {
            if ($filepath === '/' and $filename === '.') {
                $storedfile = new virtual_root_file($context->id, 'mod_peer', $filearea, $itemid);
            } else {
                // not found
                return null;
            }
        }

        // Checks to see if the user can manage files or is the owner.
        if (!has_capability('moodle/course:managefiles', $context)) {
            return null;
        }

        $urlbase = $CFG->wwwroot . '/pluginfile.php';
        // do not allow manual modification of any files!
        return new file_info_stored($browser, $context, $storedfile, $urlbase, $itemid, true, true, false, false);
    }

}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 *
 * $return->time = the time they did it
 * $return->info = a short text description
 * @package mod_peer
 * @param stdClass $course The course record
 * @param stdClass $user The user record
 * @param cm_info|stdClass $mod The course module info object or record
 * @param stdClass $peer The peer instance record
 * @return stdClass|null
 */
function peer_user_outline($course, $user, $mod, $peer) {
    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * It is supposed to echo directly without returning a value.
 * @package mod_peer
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $peer the module instance record
 */
function peer_user_complete($course, $user, $mod, $peer) {
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in peer activities and print it out.
 * @package mod_peer
 * @param stdClass $course The course record
 * @param bool $viewfullnames Should we display full names
 * @param int $timestart Print activity since this timestamp
 * @return boolean True if anything was printed, otherwise false
 */
function peer_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link peer_print_recent_mod_activity()}.
 *
 * Returns void, it adds items into $activities and increases $index.
 * @package mod_peer
 * @param array $activities sequentially indexed array of objects with added 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 */
function peer_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid = 0, $groupid = 0) {
}

/**
 * Prints single activity item prepared by {@link peer_get_recent_mod_activity()}
 * @package mod_peer
 * @param stdClass $activity activity record with added 'cmid' property
 * @param int $courseid the id of the course we produce the report for
 * @param bool $detail print detailed report
 * @param array $modnames as returned by {@link get_module_types_names()}
 * @param bool $viewfullnames display users' full names
 */
function peer_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
}

/**
 * Function to be run periodically according to the moodle cron
 *
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * Note that this has been deprecated in favour of scheduled task API.
 * @package mod_peer
 * @return boolean
 */
function peer_cron() {
    return true;
}

/**
 * Returns all other caps used in the module
 *
 * For example, this could be array('moodle/site:accessallgroups') if the
 * module uses that capability.
 * @package mod_peer
 * @return array
 */
function peer_get_extra_capabilities() {
    return array();
}

/* Gradebook API */
/**
 * Is a given scale used by the instance of peer?
 *
 * This function returns if a scale is being used by one peer
 * if it has support for grading and scales.
 * @package mod_peer
 * @param int $peer ID of an instance of this module
 * @param int $scaleid ID of the scale
 * @return bool true if the scale is used by the given peer instance
 */
function peer_scale_used($peer, $scaleid) {
    return false;
}

/**
 * Checks if scale is being used by any instance of peer.
 *
 * This is used to find out if scale used anywhere.
 * @package mod_peer
 * @param int $scaleid ID of the scale
 * @return boolean true if the scale is used by any peer instance
 */
function peer_scale_used_anywhere($scaleid) {
    return false;
}

/**
 * Creates or updates grade items for the give peer instance
 *
 * Needed by grade_update_mod_grades() in lib/gradelib.php. Also used by
 * {@link peer_update_grades()}.
 * @package mod_peer
 * @param stdClass $peer instance object with extra cmidnumber property
 * @param stdClass $submissiongrade data for the first grade item
 * @param stdClass $reviewgrade data for the second grade item
 * @return void
 */
function peer_grade_item_update($peer, $submissiongrades = null, $reviewgrades = null) {
    global $CFG, $DB;

    require_once($CFG->libdir . '/gradelib.php');

    $a = new stdclass();
    $a->peername = clean_param($peer->name, PARAM_NOTAGS);

    $item = array();
    $item['itemname'] = get_string('gradeitemsubmission', 'peer', $a);
    $item['gradetype'] = GRADE_TYPE_VALUE;

    $sql = "SELECT COUNT(c.id)
                      FROM {peer_criteria} c
                     WHERE c.peer = :peer";

    $params['peer'] = $peer->id;
    $count = $DB->count_records_sql($sql, $params);

    $item['grademax'] = $count;
    $item['grademin'] = 0;

    grade_update('mod/peer', $peer->course, 'mod', 'peer', $peer->id, 0, $submissiongrades, $item);

    $item = array();
    $item['itemname'] = get_string('gradeitemreview', 'peer', $a);
    $item['gradetype'] = GRADE_TYPE_VALUE;
    $item['grademax'] = 2;
    $item['grademin'] = 0;
    grade_update('mod/peer', $peer->course, 'mod', 'peer', $peer->id, 1, $reviewgrades, $item);
}

/**
 * Update peer grades in the gradebook
 *
 * Needed by grade_update_mod_grades() in lib/gradelib.php
 * @package mod_peer
 * @category grade
 * @param stdClass $peer instance object with extra cmidnumber and modname property
 * @param int $userid update grade of specific user only, 0 means all participants
 * @return void
 */
function peer_update_grades($peer, $userid = 0) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    $params = array();
    $sql = '';

    if ($userid) {
        $groupid = groups_get_user_groups($peer->course->id, $userid);

        if (is_array($groupid)) {
            $groupid = array_values($groupid)[0];
        }

        $params = array('peer' => $peer->id, 'groupid' => $groupid);
        $sql = 'SELECT id, groupid FROM {peer_submission} s WHERE peer = :peer AND groupid = :groupid';

    } else {
        $params = array('peer' => $peer->id);
        $sql = 'SELECT id, groupid FROM {peer_submission} s WHERE peer = :peer';
    }
    $records = $DB->get_records_sql($sql, $params);
    $submissiongrades = array();
    foreach ($records as $record) {

        // Calculate Submission Grade by Reviews

        $sql = "SELECT * FROM {peer_review} r WHERE r.peer = :peer AND r.submission = :submission";
        $params['peer'] = $peer->id;
        $params['submission'] = $record->id;

        $reviews = null;

        if ($DB->record_exists_sql($sql, $params)) {
            $dbreviews = $DB->get_records_sql($sql, $params);

            if (is_object($dbreviews)) {
                $dbreviews = array($dbreviews);
            }

            $revs = array();

            foreach ($dbreviews as $rev) {
                array_push($revs, $rev);
            }
            $reviews = $revs;
        } else {
            $reviews = array();
        }

        $count = 0;
        $grade = new stdClass();
        $grade->rawgrade = 0;
        foreach ($reviews as $review) {
            $grade->rawgrade = $grade->rawgrade + $review->grade;
            $count++;
        }

        if ($count > 0) {
            $grade->rawgrade = grade_floatval($grade->rawgrade / $count);
        } else {
            $grade->rawgrade = grade_floatval(0);
        }

        $members = groups_get_members($record->groupid, $fields = 'u.*', $sort = 'lastname ASC');

        // Save Grade for all Members of Group
        foreach ($members as $member) {
            $grade->userid = $member->id;
            $submissiongrades[$member->id] = clone $grade;
        }
    }

    $reviewgrades = array();

    if ($userid) {
        $groupid = groups_get_user_groups($peer->course->id, $userid);

        if (is_array($groupid)) {
            $groupid = array_values($groupid)[0];
        }

        $count = $DB->count_records('peer_review', array('peer' => $peer->id, 'groupid' => $groupid));
        $grade = new stdClass();

        if ($count >= 2) {
            $grade->rawgrade = 2;
        } else if ($grade == 1) {
            $grade->rawgrade = 1;
        } else {
            $grade->rawgrade = 0;
        }

        $grade->rawgrade = grade_floatval($grade->grade / $count);

        $reviewgrades[$userid] = $grade;

    } else {

        $groups = groups_get_all_groups($peer->course, 0, 0, $fields = 'g.*');

        foreach ($groups as $group) {
            $count = $DB->count_records('peer_review', array('peer' => $peer->id, 'groupid' => $group->id));

            $grade = new stdClass();

            if ($count >= 2) {
                $grade->rawgrade = 2;
            } else if ($count == 1) {
                $grade->rawgrade = 1;
            } else {
                $grade->rawgrade = 0;
            }

            $members = groups_get_members($group->id, $fields = 'u.*', $sort = 'lastname ASC');

            // Save Grade for all Members of Group
            foreach ($members as $member) {
                $grade->userid = $member->id;
                $reviewgrades[$member->id] = clone $grade;
            }
        }
    }

    foreach ($submissiongrades as $key => $item) {
        $gradesub = array('userid' => $item->userid, 'rawgrade' => $item->rawgrade);
        $graderev = array('userid' => $item->userid, 'rawgrade' => $reviewgrades[$item->userid]->rawgrade);

        peer_grade_item_update($peer, $gradesub, $graderev);
    }
}

/**
 * Delete grade item for given peer instance
 * @package mod_peer
 * @param stdClass $peer instance object
 * @return grade_item
 */
function peer_grade_item_delete($peer) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    grade_update('mod/peer', $peer->course, 'mod', 'peer',
        $peer->id, 0, null, array('deleted' => 1));

    return grade_update('mod/peer', $peer->course, 'mod', 'peer',
        $peer->id, 1, null, array('deleted' => 1));
}

