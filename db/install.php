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
 * Database enrolment plugin installation.
 *
 * @package    enrol
 * @subpackage oss
 * @copyright  2012 Frank Schütte <fschuett@gymnasium-himmelsthuer.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_enrol_oss_install() {
    global $CFG, $DB;

    require_once($CFG->libdir . '/accesslib.php');

    // Move coursecreator roles from oss to enrol_oss.
    echo "move old coursecreator assignments from oss to enrol_oss\n";
    $role = $DB->get_record('role', array('shortname' => 'coursecreator'));
    if ($role) {
        if($records = $DB->get_recordset_select('role_assignments', "(roleid='" . $role->id . "' and component='oss')")) {
            foreach ($records as $record) {
                $record->component = 'enrol_oss';
            }
            $records->close();
        }
    }

    // Remove role assignments oss.
    echo "remove all remaining oss assignments\n";
    $DB->delete_records('role_assignments', array('component' => 'oss'));

    // Create the category teacher role (ccteacher).
    if (!$DB->record_exists('role', array('shortname' => 'ccteacher'))) {
        $ccteacherid = create_role(get_string('ccteacher', 'enrol_oss'), 'ccteacher',
                                    get_string('ccteacher_desc', 'enrol_oss'),
                                    'editingteacher');
        $contextlevels = get_default_contextlevels('editingteacher');
        $contextlevels[] = CONTEXT_COURSECAT;
        set_role_contextlevels($ccteacherid, $contextlevels);
    }

    return true;
}
