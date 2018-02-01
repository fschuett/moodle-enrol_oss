<?php
# debug function
function kill($data){ var_dump($data); exit; }
@ini_set('display_errors','1');

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
