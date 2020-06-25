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
 * OSS parents lib.
 *
 * @package    enrol
 * @subpackage oss
 * @author     Frank Schütte
 * @copyright  2018 Frank Schütte <fschuett@gymhim.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/user/filters/lib.php');

if (!defined('MAX_BULK_USERS')) {
    define('MAX_BULK_USERS', 2000);
}

function enrol_oss_add_selection_all($ufiltering) {
    global $SESSION, $DB, $CFG;

    list($sqlwhere, $params) = $ufiltering->get_sql_filter("id<>:exguest AND deleted <> 1", array('exguest' => $CFG->siteguest));

    $rs = $DB->get_recordset_select('user', $sqlwhere, $params, 'fullname', 'id,'.$DB->sql_fullname().' AS fullname');
    foreach ($rs as $user) {
        if (!isset($SESSION->bulk_users[$user->id])) {
            $SESSION->bulk_users[$user->id] = $user->id;
        }
    }
    $rs->close();
}

function enrol_oss_format_parents_select_menu ($parents) {
    global $DB;
    $menu = array();
    if (!empty($parents)) {
        $keys = array_keys($parents);
        list($in, $params) = $DB->get_in_or_equal($keys);
        $sqlwhere = "p.id $in";
        $sql = "SELECT p.id AS id, ".
                $DB->sql_concat(
                    $DB->sql_fullname('p.firstname', 'p.lastname'), "'('", " COALESCE(ch.fullname, '') ", "')'")
                ." AS fullname FROM {user} p LEFT OUTER JOIN
                (
                    SELECT ".$DB->sql_fullname('ch.firstname', 'ch.lastname')." AS fullname,ra.userid AS elternid FROM {user} ch
                    JOIN {context} cx ON ch.id = cx.instanceid
                    JOIN {role_assignments} ra ON ra.contextid = cx.id
                    WHERE cx.contextlevel=".CONTEXT_USER."
                ) AS ch ON ch.elternid=p.id
                WHERE $sqlwhere ORDER BY fullname";
        if ($records = $DB->get_records_sql($sql, $params, 0, MAX_BULK_USERS)) {
            foreach ($records as $record) {
                $record = (array)$record;
                $key   = array_shift($record);
                $value = array_shift($record);
                $menu[$key] = $value;
            }
        }
    }
    return $menu;
}

function enrol_oss_get_selection_data($ufiltering) {
    global $SESSION, $DB, $CFG;

    // Get the SQL filter.
    list($sqlwhere, $params) = $ufiltering->get_sql_filter("id<>:exguest AND deleted <> 1", array('exguest' => $CFG->siteguest));

    $total  = $DB->count_records_select('user', "id<>:exguest AND deleted <> 1", array('exguest' => $CFG->siteguest));
    $acount = $DB->count_records_select('user', $sqlwhere, $params);
    $scount = count($SESSION->bulk_users);

    $userlist = array('acount' => $acount, 'scount' => $scount, 'ausers' => false, 'susers' => false, 'total' => $total);
    $userlist['ausers'] = enrol_oss_format_parents_select_menu($DB->get_records_select_menu('user', $sqlwhere, $params, 'fullname',
        'id,'.$DB->sql_fullname().' AS fullname', 0, MAX_BULK_USERS));

    if ($scount) {
        if ($scount < MAX_BULK_USERS) {
            $bulkusers = $SESSION->bulk_users;
        } else {
            $bulkusers = array_slice($SESSION->bulk_users, 0, MAX_BULK_USERS, true);
        }
        list($in, $inparams) = $DB->get_in_or_equal($bulkusers);
        $userlist['susers'] = enrol_oss_format_parents_select_menu($DB->get_records_select_menu('user', "id $in", $inparams, 'fullname',
            'id,'.$DB->sql_fullname().' AS fullname'));
    }

    return $userlist;
}

function enrol_oss_parents_update_parents() {
    global $CFG;
    require_once($CFG->dirroot . '/enrol/oss/lib.php');

    // The enrolment depends on user synchronization via auth_ldap.
    if (!is_enabled_auth('ldap')) {
        debugging('[AUTH LDAP] ' . get_string('pluginnotenabled', 'auth_ldap'));
        return;
    }

    if (!enrol_is_enabled('cohort')) {
        debugging('[ENROL COHORT]'.get_string('pluginnotenabled', 'enrol_cohort'));
        return;
    }

    if (!enrol_is_enabled('oss')) {
        debugging('[ENROL OSS] '.get_string('pluginnotenabled', 'enrol_oss'));
        return;
    }

    // Instance of enrol_flatfile_plugin.
    $plugin = enrol_get_plugin('oss');
    $result = $plugin->parents_sync_relationships();
    return $result;
}
