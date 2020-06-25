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
 * This plugin synchronises enrolment and roles with a Open OSS server.
 *
 * @package    enrol
 * @subpackage oss
 * @author     Frank Schütte based on code by Iñaki Arenaza
 * @copyright  1999 onwards Martin Dougiamas {@link http://moodle.com}
 * @copyright  2010 Iñaki Arenaza <iarenaza@eps.mondragon.edu>
 * @copyright  2020 Frank Schütte <fschuett@gymhim.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


function enrol_oss_settings_class_category_updated($full_name) {
    global $CFG;

    require_once $CFG->dirroot.'/enrol/oss/lib.php';

    $config = get_config('enrol_oss');
    $classcat = enrol_oss_plugin::get_class_category($config);
    if ( $classcat ) {
        $classcat->update(array('name' => $config->class_category));
    }
}

function enrol_oss_description_updated($groupname, $newvalue) {
    global $DB;

    $data = new stdClass;
    $records = $DB->get_records_sql("SELECT g.id, g.courseid, c.shortname FROM {groups} g JOIN {course} c ON g.courseid = c.id WHERE g.name = ? ", array($groupname));
    foreach($records as $group) {
        $data->id = $group->id;
        $data->description = '<p>'.trim($newvalue).' '.$group->shortname.'</p>';
        $DB->update_record('groups', $data);
    }
}

function enrol_oss_settings_class_teachers_group_description_updated($full_name) {
    $newvalue = get_config('enrol_oss', 'class_teachers_group_description');
    enrol_oss_description_updated('teachers', $newvalue);
}

function enrol_oss_settings_class_students_group_description_updated($full_name) {
    $newvalue = get_config('enrol_oss', 'class_students_group_description');
    enrol_oss_description_updated('students', $newvalue);
}

function enrol_oss_settings_class_parents_group_description_updated($full_name) {
    $newvalue = get_config('enrol_oss', 'class_parents_group_description');
    enrol_oss_description_updated('parents', $newvalue);
}
