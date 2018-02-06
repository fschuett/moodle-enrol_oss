<?php
# debug function
function kill($data){ var_dump($data); exit; }
@ini_set('display_errors','1');

require_once($CFG->dirroot.'/user/filters/lib.php');

if (!defined('MAX_BULK_USERS')) {
    define('MAX_BULK_USERS', 2000);
}

function add_selection_all($ufiltering) {
    global $SESSION, $DB, $CFG;

    list($sqlwhere, $params) = $ufiltering->get_sql_filter("id<>:exguest AND deleted <> 1", array('exguest'=>$CFG->siteguest));

    $rs = $DB->get_recordset_select('user', $sqlwhere, $params, 'fullname', 'id,'.$DB->sql_fullname().' AS fullname');
    foreach ($rs as $user) {
        if (!isset($SESSION->bulk_users[$user->id])) {
            $SESSION->bulk_users[$user->id] = $user->id;
        }
    }
    $rs->close();
}

function format_parents_select_menu ($parents) {
    global $DB;
    $keys = array_keys($parents);
    list($in, $params) = $DB->get_in_or_equal($keys);
    $sqlwhere = "p.id $in";
	$sql = "SELECT p.id AS id,".$DB->sql_concat(
	                    $DB->sql_fullname('p.firstname', 'p.lastname'),"'('", 
	                    $DB->sql_fullname('ch.firstname', 'ch.lastname'),"')'")." AS fullname
                        FROM {user} p
                        JOIN {role_assignments} ra ON p.id = ra.userid
                        JOIN {context} cx ON ra.contextid = cx.id
                        JOIN {user} ch ON ch.id = cx.instanceid
                        WHERE cx.contextlevel=". CONTEXT_USER ." AND $sqlwhere
                        ORDER BY fullname";
	if ($records = $DB->get_records_sql($sql, $params, 0, MAX_BULK_USERS)) {
		foreach ($records as $record) {
			$record = (array)$record;
			$key   = array_shift($record);
			$value = array_shift($record);
			$menu[$key] = $value;
		}
	}
	return $menu;
}

function get_selection_data($ufiltering) {
    global $SESSION, $DB, $CFG;

    // get the SQL filter
    list($sqlwhere, $params) = $ufiltering->get_sql_filter("id<>:exguest AND deleted <> 1", array('exguest'=>$CFG->siteguest));

    $total  = $DB->count_records_select('user', "id<>:exguest AND deleted <> 1", array('exguest'=>$CFG->siteguest));
    $acount = $DB->count_records_select('user', $sqlwhere, $params);
    $scount = count($SESSION->bulk_users);

    $userlist = array('acount'=>$acount, 'scount'=>$scount, 'ausers'=>false, 'susers'=>false, 'total'=>$total);
    $userlist['ausers'] = format_parents_select_menu($DB->get_records_select_menu('user', $sqlwhere, $params, 'fullname', 
        'id,'.$DB->sql_fullname().' AS fullname', 0, MAX_BULK_USERS));

    if ($scount) {
        if ($scount < MAX_BULK_USERS) {
            $bulkusers = $SESSION->bulk_users;
        } else {
            $bulkusers = array_slice($SESSION->bulk_users, 0, MAX_BULK_USERS, true);
        }
        list($in, $inparams) = $DB->get_in_or_equal($bulkusers);
        $userlist['susers'] = format_parents_select_menu($DB->get_records_select_menu('user', "id $in", $inparams, 'fullname', 
            'id,'.$DB->sql_fullname().' AS fullname'));
    }

    return $userlist;
}

function parents_update_parents() {
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
