<?php
/**
* script for bulk user delete operations
*/

require_once('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');

if (!defined('MAX_BULK_USERS')) {
    define('MAX_BULK_USERS', 2000);
}

$confirm = optional_param('confirm', 0, PARAM_BOOL);
$newpassword = optional_param('newpassword', '', PARAM_TEXT);

require_login();
admin_externalpage_setup('enrol_oss_parents');
require_capability('moodle/user:update', context_system::instance());

$return = $CFG->wwwroot.'/enrol/oss/parents/parents.php';

if (empty($SESSION->bulk_users)) {
    redirect($return);
}

echo $OUTPUT->header();

//TODO: add support for large number of users

if ($confirm and confirm_sesskey()) {
    $notifications = '';
    $scount = count($SESSION->bulk_users);
    if ($newpassword == null) {
        debugging("parents_set_passwords($newpassword) ungültiges Passwort!\n");
		$notifications .= $OUTPUT->notification(get_string('parents_password_empty', 'enrol_oss'));
    } else {
		$hashedpassword = hash_internal_user_password($newpassword);
		if ($scount) {
			if ($scount < MAX_BULK_USERS) {
				$bulkusers = $SESSION->bulk_users;
			} else {
				$bulkusers = array_slice($SESSION->bulk_users, 0, MAX_BULK_USERS, true);
			}
			list($in, $inparams) = $DB->get_in_or_equal($bulkusers);
			$users = $DB->get_records_select('user', "id $in", $inparams);
			foreach ($users as $user) {
				$DB->set_field('user', 'password', $hashedpassword, array('id'=>$user->id));
			}
		} else {
			debugging("parents_set_passwords($newpassword) keine Benutzer ausgewählt!\n");
			$notifications .= $OUTPUT->notification(get_string('parents_no_selected_users', 'enrol_oss'));
		}
    }
    echo $OUTPUT->box_start('generalbox', 'notice');
    if (!empty($notifications)) {
        echo $notifications;
    } else {
        echo $OUTPUT->notification(get_string('changessaved'), 'notifysuccess');
    }
    $continue = new single_button(new moodle_url($return), get_string('continue'), 'post');
    echo $OUTPUT->render($continue);
    echo $OUTPUT->box_end();
} else {
    list($in, $params) = $DB->get_in_or_equal($SESSION->bulk_users);
    $userlist = $DB->get_records_select_menu('user', "id $in", $params, 'fullname', 'id,'.$DB->sql_fullname().' AS fullname');
    $usernames = implode(', ', $userlist);
    echo $OUTPUT->heading(get_string('confirmation', 'admin'));
    $formcontinue = new single_button(new moodle_url('parents_set_passwords.php', array('newpassword' => $newpassword, 'confirm' => 1)), get_string('yes'));
    $formcancel = new single_button(new moodle_url('parents.php'), get_string('no'), 'get');
    echo $OUTPUT->confirm(get_string('parents_confirm_set_passwords', 'enrol_oss', $usernames), $formcontinue, $formcancel);
}

echo $OUTPUT->footer();
