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
 * OSS enrolment plugin settings and presets.
 *
 * @package    enrol
 * @subpackage oss
 * @author     Frank Schütte
 * @copyright  2020 Frank Schütte <fschuett@gymhim.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/enrol/oss/parents/parentslib.php');

admin_externalpage_setup('enrol_oss_parents');

$parents_enabled = get_config('enrol_oss', 'parents_enabled');

if( $parents_enabled )  {
    if (!isset($SESSION->bulk_users)) {
        $SESSION->bulk_users = array();
    }
    // create the user filter form
    $ufiltering = new \enrol_oss\parents\parent_filtering();

    // array of bulk operations
    // create the bulk operations form
    $action_form = new \enrol_oss\parents\action_form();
    if ($data = $action_form->get_data()) {
        if (!empty($data->updateparents)) {
            // update parents relationships
            enrol_oss_parents_update_parents();
        } else if (!empty($data->dosetpassword)) {
            // update selected users passwords
            redirect(new moodle_url($CFG->wwwroot.'/enrol/oss/parents/parents_set_passwords.php', array('newpassword' => $data->newpassword)));
        } else {
            // check if an action should be performed and do so
            switch ($data->action) {
                case 1: redirect($CFG->wwwroot.'/'.$CFG->admin.'/user/user_bulk_confirm.php');
                case 2: redirect($CFG->wwwroot.'/'.$CFG->admin.'/user/user_bulk_message.php');
                case 3: redirect($CFG->wwwroot.'/'.$CFG->admin.'/user/user_bulk_delete.php');
                case 4: redirect($CFG->wwwroot.'/'.$CFG->admin.'/user/user_bulk_display.php');
                case 5: redirect($CFG->wwwroot.'/'.$CFG->admin.'/user/user_bulk_download.php');
                case 7: redirect($CFG->wwwroot.'/'.$CFG->admin.'/user/user_bulk_forcepasswordchange.php');
            }
        }
    }

    $user_bulk_form = new \enrol_oss\parents\parents_form(null, enrol_oss_get_selection_data($ufiltering));

    if ($data = $user_bulk_form->get_data()) {
        if (!empty($data->addall)) {
            enrol_oss_add_selection_all($ufiltering);

        } else if (!empty($data->addsel)) {
            if (!empty($data->ausers)) {
                if (in_array(0, $data->ausers)) {
                    enrol_oss_add_selection_all($ufiltering);
                } else {
                    foreach($data->ausers as $userid) {
                        if ($userid == -1) {
                            continue;
                        }
                        if (!isset($SESSION->bulk_users[$userid])) {
                            $SESSION->bulk_users[$userid] = $userid;
                        }
                    }
                }
            }

        } else if (!empty($data->removeall)) {
            $SESSION->bulk_users = array();

        } else if (!empty($data->removesel)) {
            if (!empty($data->susers)) {
                if (in_array(0, $data->susers)) {
                    $SESSION->bulk_users = array();
                } else {
                    foreach($data->susers as $userid) {
                        if ($userid == -1) {
                            continue;
                        }
                        unset($SESSION->bulk_users[$userid]);
                    }
                }
            }
        }

        // reset the form selections
        unset($_POST);
        $user_bulk_form = new \enrol_oss\parents\parents_form(null, enrol_oss_get_selection_data($ufiltering));
    }
    // do output
    echo $OUTPUT->header();

    $ufiltering->display_add();
    $ufiltering->display_active();

    $user_bulk_form->display();

    $action_form->display();

    echo $OUTPUT->footer();
} else {
    // do output
    echo $OUTPUT->header();

    echo $OUTPUT->heading(get_string('parents_list', 'enrol_oss'));

    echo $OUTPUT->box_start('generalbox', 'notice');
    echo $OUTPUT->notification(get_string('parents_not_enabled','enrol_oss'), 'info');
    echo $OUTPUT->box_end();


    echo $OUTPUT->footer();
}
