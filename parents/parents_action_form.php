<?php

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->libdir.'/datalib.php');

class parents_action_form extends moodleform {
    function definition() {
        global $CFG;

        $mform =& $this->_form;

        $syscontext = context_system::instance();
        $actions = array(0=>get_string('choose').'...');
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
        $objs[] =& $mform->createElement('submit', 'updateparents', get_string('parents_update','enrol_oss'));
        $mform->addElement('group','parentsgrp', get_string('parents_update_label', 'enrol_oss'), $objs, ' ', false);
    }
}

