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
 * @subpackage oss
 * @author     Frank Schütte
 * @copyright  2012 Frank Schütte <fschuett@gymhim.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    // Initializing.
    require_once($CFG->dirroot.'/enrol/ldap/settingslib.php');
    $yesno = array(get_string('no'), get_string('yes'));

    // Heading.
    $settings->add(new admin_setting_heading('enrol_oss_settings', '',
            get_string('pluginname_desc', 'enrol_oss')));

    // Common settings.
    $settings->add(new admin_setting_heading('enrol_oss_common_settings',
            get_string('common_settings', 'enrol_oss'), ''));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_oss/contexts',
            get_string('contexts_key', 'enrol_oss'),
            get_string('contexts', 'enrol_oss'), 'ou=group,dc=oss,dc=lokal'));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_oss/object',
            get_string('object_key', 'enrol_oss'), get_string('object', 'enrol_oss'), 'posixGroup'));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_oss/attribute',
            get_string('attribute_key', 'enrol_oss'), get_string('attribute', 'enrol_oss'), 'cn'));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_oss/member_attribute',
            get_string('member_attribute_key', 'enrol_oss'), get_string('member_attribute', 'enrol_oss'), 'member'));
    $options = $yesno;
    $settings->add(new admin_setting_configselect('enrol_oss/member_attribute_isdn',
            get_string('member_attribute_isdn_key', 'enrol_oss'),
            get_string('member_attribute_isdn', 'enrol_oss'), 0, $options));

    // Teachers settings.
    $settings->add(new admin_setting_heading('enrol_oss_teacher_settings',
            get_string('teacher_settings', 'enrol_oss'), ''));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_oss/teachers_group_name',
            get_string('teachers_group_name_key', 'enrol_oss'),
            get_string('teachers_group_name', 'enrol_oss'), 'teachers'));
    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_oss/teachers_role',
            get_string('teachers_role_key', 'enrol_oss'),
            get_string('teachers_role', 'enrol_oss'), $student->id, $options));
    }
    $settings->add(new admin_setting_configtext_trim_lower('enrol_oss/prefix_teacher_members',
            get_string('prefix_teacher_members_key', 'enrol_oss'),
            get_string('prefix_teacher_members', 'enrol_oss'), 'p_teachers_'));

    // Teachers context settings.
    $settings->add(new admin_setting_heading('enrol_oss_teachers_context_settings',
            get_string('teachers_context_settings', 'enrol_oss'), ''));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_oss/teachers_course_context',
            get_string('teachers_course_context_key', 'enrol_oss'),
            get_string('teachers_course_context', 'enrol_oss'), 'Lehrer'));
    if (!during_initial_install()) {
        $options = get_assignable_roles(context_system::instance());
        $coursecreator = get_archetype_roles('coursecreator');
        $coursecreator = reset($coursecreator);
        $settings->add(new admin_setting_configselect('enrol_oss/teachers_course_role',
            get_string('teachers_course_role_key', 'enrol_oss'),
            get_string('teachers_course_role', 'enrol_oss'), $coursecreator->id, $options));
    }
    $options = $yesno;
    $settings->add(new admin_setting_configselect('enrol_oss/teachers_category_autocreate',
            get_string('teachers_category_autocreate_key', 'enrol_oss'),
            get_string('teachers_category_autocreate', 'enrol_oss'), 0, $options));
    $options = $yesno;
    $settings->add(new admin_setting_configselect('enrol_oss/teachers_category_autoremove',
            get_string('teachers_category_autoremove_key', 'enrol_oss'),
            get_string('teachers_category_autoremove', 'enrol_oss'), 0, $options));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_oss/teachers_removed',
            get_string('teachers_removed_key', 'enrol_oss'), get_string('teachers_removed', 'enrol_oss'), 'attic'));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_oss/teachers_ignore',
            get_string('teachers_ignore_key', 'enrol_oss'), get_string('teachers_ignore', 'enrol_oss'), 'administrator'));

    // Students settings.
    $settings->add(new admin_setting_heading('enrol_oss_students_settings',
            get_string('students_settings', 'enrol_oss'), ''));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_oss/student_class_numbers',
            get_string('student_class_numbers_key', 'enrol_oss'),
            get_string('student_class_numbers', 'enrol_oss'), '5,6,7,8,9,10,11,12,extra'));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_oss/student_groups',
            get_string('student_groups_key', 'enrol_oss'), get_string('student_groups', 'enrol_oss'), ''));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_oss/student_project_prefix',
            get_string('student_project_prefix_key', 'enrol_oss'),
            get_string('student_project_prefix', 'enrol_oss'), 'p_'));
    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_oss/student_role',
            get_string('student_role_key', 'enrol_oss'),
            get_string('student_role', 'enrol_oss'), $student->id, $options));
    }
}
