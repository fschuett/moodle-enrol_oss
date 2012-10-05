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

    // Move coursecreator roles from lml to enrol_openlml.
    echo 'move old coursecreator assignments from lml to enrol_openlml';
    $role = $DB->get_record('role', array('shortname'=>'coursecreator'));
    if ($role) {
        $records = $DB->get_recordset_select('role_assignments', array('roleid'=>$role->id, 'component'=>'lml'));
        foreach ($records as $record) {
            $record->component = 'enrol_openlml';
        }
        $records->close();
    }

    // Remove role assignments lml.
    echo 'remove all remaining lml assignments';
    $DB->role_unassign_all(array('component'=>'lml'), true);

}
