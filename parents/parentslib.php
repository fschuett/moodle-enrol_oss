<?php

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

function get_selection_data($ufiltering) {
    global $SESSION, $DB, $CFG;

    // get the SQL filter
    list($sqlwhere, $params) = $ufiltering->get_sql_filter("id<>:exguest AND deleted <> 1", array('exguest'=>$CFG->siteguest));

    $total  = $DB->count_records_select('user', "id<>:exguest AND deleted <> 1", array('exguest'=>$CFG->siteguest));
    $acount = $DB->count_records_select('user', $sqlwhere, $params);
    $scount = count($SESSION->bulk_users);

    $userlist = array('acount'=>$acount, 'scount'=>$scount, 'ausers'=>false, 'susers'=>false, 'total'=>$total);
    $userlist['ausers'] = $DB->get_records_select_menu('user', $sqlwhere, $params, 'fullname', 
        'id,'.$DB->sql_concat($DB->sql_fullname(),'(',')').' AS fullname', 0, MAX_BULK_USERS);

    if ($scount) {
        if ($scount < MAX_BULK_USERS) {
            $bulkusers = $SESSION->bulk_users;
        } else {
            $bulkusers = array_slice($SESSION->bulk_users, 0, MAX_BULK_USERS, true);
        }
        list($in, $inparams) = $DB->get_in_or_equal($bulkusers);
        $userlist['susers'] = $DB->get_records_select_menu('user', "id $in", $inparams, 'fullname', 
            'id,'.$DB->sql_concat($DB->sql_fullname(),'(',')').' AS fullname');
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
