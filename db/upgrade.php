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
 * Open LML enrolment plugin implementation.
 *
 * This file keeps track of upgrades to the openlml enrol plugin
 *
 * @package    enrol
 * @subpackage openlml
 * @author     Frank Schütte
 * @copyright  2012 Frank Schütte <fschuett@gymnasium-himmelsthuer.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function xmldb_enrol_openlml_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if($oldversion < 2012101900){
        // Course categories sortorder may be messed up.
        require_once($CFG->libdir . '/datalib.php');

        fix_course_sortorder();

        upgrade_plugin_savepoint(true, 2012101900, 'enrol', 'openlml');
    }

    if ($oldversion < 2013100500) {
        // Teacher category sortorder must be resorted
        require_once($CFG->libdir . '/coursecatlib.php');
        $teachercontext = get_config('enrol_openlml', 'teachers_course_context');
        if ($teachercontext) {
            $teachercat = $DB->get_record( 'course_categories', array('name'=>$teachercontext, 'parent' => 0),'*',IGNORE_MULTIPLE);
            if ($teachercat) {
                $teachercat = coursecat::get($teachercat->id);
                if ($categories = $teachercat->get_children()) {
                    $count=$teachercat->sortorder + 1;
                    foreach ($categories as $cat) {
                        $DB->set_field('course_categories', 'sortorder', $count, array('id' => $cat->id));
                        $count++;
                    }
                }
            }
        }

        upgrade_plugin_savepoint(true, 2013100500, 'enrol', 'openlml');
    }

    if ($oldversion < 2013100800) {
        // change faulty coursecreator roles
        echo "move old coursecreator assignments from lml to enrol_openlml\n";
        $role = $DB->get_record('role', array('shortname'=>'coursecreator'));
        if ($role) {
            if($records = $DB->get_recordset_select('role_assignments', "(roleid='" . $role->id . "' and component='enrol_lml')")) {
                foreach ($records as $record) {
                    $record->component = 'enrol_openlml';
                }
                $records->close();
            }
        }

        // remove faulty 'lml' enrolments and assignments
        $DB->delete_records('enrol', array('enrol'=>'lml'));
        $DB->delete_records('role_assignments', array('component'=>'enrol_lml'));
        $DB->delete_records('role_assignments', array('component'=>'auth_lml'));

        upgrade_plugin_savepoint(true, 2013100800, 'enrol', 'openlml');
    }
    
    if ($oldversion < 2013110500) {
	// add identifying enrol_openlml to customchar1
	$DB->execute("UPDATE {enrol} SET customchar1 = 'enrol_openlml' WHERE enrol = 'cohort'");
	upgrade_plugin_savepoint(true, 2013110500, 'enrol', 'openlml');
    }
    
    return true;
}
