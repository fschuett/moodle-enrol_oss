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
 * @copyright  2018 Frank Schütte <fschuett@gymhim.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_oss\parents;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->libdir.'/datalib.php');

class action_form extends \moodleform {
    function definition() {
        global $CFG;

        $mform =& $this->_form;

        $syscontext = \context_system::instance();
        $actions = array(0 => get_string('choose').'...');
        if (has_capability('moodle/user:update', $syscontext)) {
            $actions[1] = get_string('confirm');
        }
        if (has_capability('moodle/site:readallmessages', $syscontext) && !empty($CFG->messaging)) {
            $actions[2] = get_string('messageselectadd');
        }
        if (has_capability('moodle/user:delete', $syscontext)) {
            $actions[3] = get_string('delete');
        }
        $actions[4] = get_string('displayonpage');
        if (has_capability('moodle/user:update', $syscontext)) {
            $actions[5] = get_string('download', 'admin');
        }
        if (has_capability('moodle/user:update', $syscontext)) {
            $actions[7] = get_string('forcepasswordchange');
        }
        $objs = array();
        $objs[] =& $mform->createElement('select', 'action', null, $actions);
        $objs[] =& $mform->createElement('submit', 'doaction', get_string('go'));
        $mform->addElement('group', 'actionsgrp', get_string('withselectedusers'), $objs, ' ', false);
        $objs = array();
        $objs[] =& $mform->createElement('text', 'newpassword', null);
        $mform->setType('newpassword', PARAM_TEXT);
        $objs[] =& $mform->createElement('submit', 'dosetpassword', get_string('parents_set_password', 'enrol_oss'));
        $mform->addGroup($objs, 'passwordgrp', get_string('withselectedusers'), ' ', false);

        $objs = array();
        $objs[] =& $mform->createElement('static', 'parents_update_info', get_string('parents_all_label', 'enrol_oss'), get_string('parents_update_desc', 'enrol_oss'));
        $objs[] =& $mform->createElement('submit', 'updateparents', get_string('go'));
        $mform->addElement('group','parentsgrp', get_string('parents_all_label', 'enrol_oss'), $objs, ' ', false);

    }
}

