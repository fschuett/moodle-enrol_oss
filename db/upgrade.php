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
    return true;
}
