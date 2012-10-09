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
 * OML enrolment plugin settings and presets.
 *
 * @package    enrol
 * @subpackage openlml
 * @author     Frank Schütte
 * @copyright  2012 Frank Schütte <fschuett@gymnasium-himmelsthuer.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    // Initializing.
    require_once($CFG->dirroot.'/enrol/ldap/settingslib.php');
    $yesno = array(get_string('no'), get_string('yes'));

    // Heading.
    $settings->add(new admin_setting_heading('enrol_openlml_settings', '',
            get_string('pluginname_desc', 'enrol_openlml')));

    // Common settings.
    $settings->add(new admin_setting_heading('enrol_openlml_common_settings',
            get_string('common_settings', 'enrol_openlml'), ''));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_openlml/contexts',
            get_string('contexts_key', 'enrol_openlml'),
            get_string('contexts', 'enrol_openlml'), 'ou=groups,dc=linuxmuster,dc=lokal'));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_openlml/object',
            get_string('object_key', 'enrol_openlml'), get_string('object', 'enrol_openlml'), 'posixGroup'));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_openlml/attribute',
            get_string('attribute_key', 'enrol_openlml'), get_string('attribute', 'enrol_openlml'), 'cn'));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_openlml/member_attribute',
            get_string('member_attribute_key', 'enrol_openlml'), get_string('member_attribute', 'enrol_openlml'), 'memberuid'));
    $settings->add(new admin_setting_configtext('enrol_openlml/city',
            get_string('city_key', 'enrol_openlml'), get_string('city', 'enrol_openlml'), 'Musterstadt'));

    // Teachers settings.
    $settings->add(new admin_setting_heading('enrol_openlml_teacher_settings',
            get_string('teacher_settings', 'enrol_openlml'), ''));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_openlml/teachers_group_name',
            get_string('teachers_group_name_key', 'enrol_openlml'),
            get_string('teachers_group_name', 'enrol_openlml'), 'teachers'));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_openlml/teachers_course_id',
            get_string('teachers_course_id_key', 'enrol_openlml'),
            get_string('teachers_course_id', 'enrol_openlml'), 'teachers'));
    if (!during_initial_install()) {
        $options = get_default_enrol_roles(get_context_instance(CONTEXT_SYSTEM));
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_openlml/teachers_role',
            get_string('teachers_role_key', 'enrol_openlml'),
            get_string('teachers_role', 'enrol_openlml'), $student->id, $options));
    }
    $settings->add(new admin_setting_configtext_trim_lower('enrol_openlml/prefix_teacher_members',
            get_string('prefix_teacher_members_key', 'enrol_openlml'),
            get_string('prefix_teacher_members', 'enrol_openlml'), 'p_teachers_'));

    // Teachers context settings.
    $settings->add(new admin_setting_heading('enrol_openlml_teachers_context_settings',
            get_string('teachers_context_settings', 'enrol_openlml'), ''));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_openlml/teachers_course_context',
            get_string('teachers_course_context_key', 'enrol_openlml'),
            get_string('teachers_course_context', 'enrol_openlml'), 'Lehrer'));
    if (!during_initial_install()) {
        $options = get_assignable_roles(get_context_instance(CONTEXT_SYSTEM));
        $coursecreator = get_archetype_roles('coursecreator');
        $coursecreator = reset($coursecreator);
        $settings->add(new admin_setting_configselect('enrol_openlml/teachers_course_role',
            get_string('teachers_course_role_key', 'enrol_openlml'),
            get_string('teachers_course_role', 'enrol_openlml'), $coursecreator->id, $options));
    }
    $options = $yesno;
    $settings->add(new admin_setting_configselect('enrol_openlml/teachers_category_autocreate',
            get_string('teachers_category_autocreate_key', 'enrol_openlml'),
            get_string('teachers_category_autocreate', 'enrol_openlml'), 0, $options));
    $options = $yesno;
    $settings->add(new admin_setting_configselect('enrol_openlml/teachers_category_autoremove',
            get_string('teachers_category_autoremove_key', 'enrol_openlml'),
            get_string('teachers_category_autoremove', 'enrol_openlml'), 0, $options));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_openlml/teachers_removed',
            get_string('teachers_removed_key', 'enrol_openlml'), get_string('teachers_removed', 'enrol_openlml'), 'attic'));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_openlml/teachers_ignore',
            get_string('teachers_ignore_key', 'enrol_openlml'), get_string('teachers_ignore', 'enrol_openlml'), 'administrator'));

    // Students settings.
    $settings->add(new admin_setting_heading('enrol_openlml_students_settings',
            get_string('students_settings', 'enrol_openlml'), ''));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_openlml/student_class_numbers',
            get_string('student_class_numbers_key', 'enrol_openlml'),
            get_string('student_class_numbers', 'enrol_openlml'), '5,6,7,8,9,10,11,12'));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_openlml/student_groups',
            get_string('student_groups_key', 'enrol_openlml'), get_string('student_groups', 'enrol_openlml'), ''));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_openlml/student_project_prefix',
            get_string('student_project_prefix_key', 'enrol_openlml'),
            get_string('student_project_prefix', 'enrol_openlml'), 'p_'));
    if (!during_initial_install()) {
        $options = get_default_enrol_roles(get_context_instance(CONTEXT_SYSTEM));
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_openlml/student_role',
            get_string('student_role_key', 'enrol_openlml'),
            get_string('student_role', 'enrol_openlml'), $student->id, $options));
    }
}
