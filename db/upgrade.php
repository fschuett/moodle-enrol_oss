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
 * OSS enrolment plugin implementation.
 *
 * This file keeps track of upgrades to the oss enrol plugin
 *
 * @package    enrol
 * @subpackage oss
 * @author     Frank Schütte
 * @copyright  2012 Frank Schütte <fschuett@gymhim.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function xmldb_enrol_oss_upgrade($oldversion) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/accesslib.php');

    if ($oldversion < '2019110201') {
        // Create the category teacher role (ccteacher).
        if (!$DB->record_exists('role', array('shortname' => 'ccteacher'))) {
            $ccteacherid = create_role(get_string('ccteacher', 'enrol_oss'), 'ccteacher',
                get_string('ccteacher_desc', 'enrol_oss'),
                'editingteacher');
                $contextlevels = get_default_contextlevels('editingteacher');
                $contextlevels[] = CONTEXT_COURSECAT;
                set_role_contextlevels($ccteacherid, $contextlevels);
        }
        upgrade_plugin_savepoint(true, '2019110201','enrol','oss');
    }

    if ($oldversion < '2021012601') {
        // Update the category teacher role (ccteacher), add missing context levels.
        if ($DB->record_exists('role', array('shortname' => 'ccteacher'))) {
            $ccteacher = $DB->get_record('role', array('shortname' => 'ccteacher'));
            $contextlevels = get_default_contextlevels('editingteacher');
            $contextlevels[] = CONTEXT_COURSECAT;
            $contextlevels = array_merge($contextlevels, get_default_contextlevels('ccteacher'));
            set_role_contextlevels($ccteacher->id, $contextlevels);
        }
        upgrade_plugin_savepoint(true, '2021012601','enrol','oss');
    }

    return true;
}
