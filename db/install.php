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
 * @subpackage openlml
 * @copyright  2012 Frank Schütte <fschuett@gymnasium-himmelsthuer.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_enrol_openlml_install() {
    global $CFG, $DB;

    require_once($CFG->libdir . '/accesslib.php');
    
    // Move coursecreator roles from lml to enrol_openlml.
    echo "move old coursecreator assignments from lml to enrol_openlml\n";
    $role = $DB->get_record('role', array('shortname'=>'coursecreator'));
    if ($role) {
        if($records = $DB->get_recordset_select('role_assignments', "(roleid='" . $role->id . "' and component='lml')")) {
            foreach ($records as $record) {
                $record->component = 'enrol_openlml';
            }
            $records->close();
        }
    }

    // Remove role assignments lml.
    echo "remove all remaining lml assignments\n";
    $DB->delete_records('role_assignments', array('component'=>'lml'));

    return true;
}
