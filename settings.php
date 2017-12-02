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

    global $DB;

    // Initializing.
    require_once($CFG->dirroot.'/enrol/ldap/settingslib.php');
    require_once($CFG->dirroot.'/enrol/oss/settings_callbacks.php');
    $yesno = array(get_string('no'), get_string('yes'));

    // Heading.
    $settings->add(new admin_setting_heading('enrol_oss_settings',
    		get_string('pluginname','enrol_oss'),
            get_string('pluginname_desc', 'enrol_oss')));

    // Common settings.
    $settings->add(new admin_setting_heading('enrol_oss_common_settings',
            get_string('common_settings', 'enrol_oss'),
    		get_string('common_settings_desc', 'enrol_oss')));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_oss/contexts',
            get_string('contexts_key', 'enrol_oss'),
            get_string('contexts', 'enrol_oss'), 'ou=group,dc=oss,dc=local'));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_oss/object',
            get_string('object_key', 'enrol_oss'), get_string('object', 'enrol_oss'), 'SchoolGroup'));
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
            get_string('teacher_settings', 'enrol_oss'),
    		get_string('teacher_settings_desc','enrol_oss')));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_oss/teachers_group_name',
            get_string('teachers_group_name_key', 'enrol_oss'),
            get_string('teachers_group_name', 'enrol_oss'), 'TEACHERS'));
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
            get_string('prefix_teacher_members', 'enrol_oss'), 'P_TEACHERS_'));

    // Teachers context settings.
    $settings->add(new admin_setting_heading('enrol_oss_teachers_context_settings',
            get_string('teachers_context_settings', 'enrol_oss'),
    		get_string('teachers_context_settings_desc','enrol_oss')));
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
            get_string('students_settings', 'enrol_oss'),
    		get_string('students_settings_desc', 'enrol_oss')));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_oss/student_class_numbers',
            get_string('student_class_numbers_key', 'enrol_oss'),
            get_string('student_class_numbers', 'enrol_oss'), '05,06,07,08,09,10,11,12,EXTRA'));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_oss/student_groups',
            get_string('student_groups_key', 'enrol_oss'), get_string('student_groups', 'enrol_oss'), ''));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_oss/student_project_prefix',
            get_string('student_project_prefix_key', 'enrol_oss'),
            get_string('student_project_prefix', 'enrol_oss'), 'P_'));
    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_oss/student_role',
            get_string('student_role_key', 'enrol_oss'),
            get_string('student_role', 'enrol_oss'), $student->id, $options));
    }

    // Class settings.
    $settings->add(new admin_setting_heading('enrol_oss_class_settings',
    		get_string('class_settings','enrol_oss'),
    		get_string('class_settings_desc','enrol_oss')));
    $settings->add(new admin_setting_configcheckbox('enrol_oss/classes_enabled',
		get_string('classes_enabled', 'enrol_oss'),
		get_string('classes_enabled_desc', 'enrol_oss'), 0));
    $settings_class_category = new admin_setting_configtext_trim_lower('enrol_oss/class_category',
    		get_string('class_category', 'enrol_oss'),
    		get_string('class_category_desc', 'enrol_oss'), get_string('class_category', 'enrol_oss'));
    $settings_class_category->set_updatedcallback('settings_class_category_updated');
    $settings->add($settings_class_category);
    $settings->add(new admin_setting_configcheckbox('enrol_oss/class_category_autocreate',
    		get_string('class_category_autocreate','enrol_oss'),
    		get_string('class_category_autocreate_desc','enrol_oss'),1));
    $settings->add(new admin_setting_configcheckbox('enrol_oss/class_autocreate',
    		get_string('class_autocreate','enrol_oss'),
    		get_string('class_autocreate_desc','enrol_oss'),1));
    $settings->add(new admin_setting_configcheckbox('enrol_oss/class_autoremove',
    		get_string('class_autoremove','enrol_oss'),
    		get_string('class_autoremove_desc','enrol_oss'),1));
    $enrol_oss_courses = array();
    $enrol_oss_coursenames = $DB->get_records_sql('SELECT * FROM {course} ORDER BY fullname');
    foreach ($enrol_oss_coursenames as $key => $coursename) {
	$enrol_oss_courses[$coursename->id] = $coursename->fullname . '(' . $coursename->id . ')';
    }
    $enrol_oss_courses[0] = get_string('class_template_none','enrol_oss');
    $settings->add(new admin_setting_configselect('enrol_oss/class_template',
    		get_string('class_template', 'enrol_oss'),
    		get_string('class_template_desc', 'enrol_oss'), 0, $enrol_oss_courses));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_oss/class_attribute',
		get_string('class_attribute','enrol_oss'),
		get_string('class_attribute_desc','enrol_oss'), 'groupType'));
    $settings->add(new admin_setting_configcheckbox('enrol_oss/class_use_prefixes',
		get_string('class_use_prefixes','enrol_oss'),
		get_string('class_use_prefixes_desc','enrol_oss'),0));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_oss/class_prefixes',
    		get_string('class_prefixes', 'enrol_oss'),
    		get_string('class_prefixes_desc', 'enrol_oss'), '05,06,07,08,09,10,11,12,13'));
    $settings->add(new admin_setting_configtext_trim_lower('enrol_oss/class_attribute_value',
		get_string('class_attribute_value','enrol_oss'),
		get_string('class_attribute_value_desc','enrol_oss'), 'class'));
    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $teacher = get_archetype_roles('editingteacher');
        $teacher = reset($teacher);
        $settings->add(new admin_setting_configselect('enrol_oss/class_teachers_role',
            get_string('class_teachers_role', 'enrol_oss'),
            get_string('class_teachers_role_desc', 'enrol_oss'), $teacher->id, $options));
    }
    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_oss/class_students_role',
            get_string('class_students_role', 'enrol_oss'),
            get_string('class_students_role_desc', 'enrol_oss'), $student->id, $options));
    }
    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_oss/class_parents_role',
            get_string('class_parents_role', 'enrol_oss'),
            get_string('class_parents_role_desc', 'enrol_oss'), $student->id, $options));
    }
    // class group settings
    $settings->add(new admin_setting_configcheckbox('enrol_oss/groups_enabled',
		get_string('groups_enabled', 'enrol_oss'),
		get_string('groups_enabled_desc', 'enrol_oss'), 0));
    $settings_class_teachers_group_description = new admin_setting_configtext('enrol_oss/class_teachers_group_description',
        get_string('class_teachers_group_description', 'enrol_oss'),
        get_string('class_teachers_group_description_desc', 'enrol_oss'),
        'teachers of class ', PARAM_TEXT);
    $settings_class_teachers_group_description->set_updatedcallback('settings_class_teachers_group_description_updated');
    $settings->add($settings_class_teachers_group_description);
    $settings_class_students_group_description = new admin_setting_configtext('enrol_oss/class_students_group_description',
        get_string('class_students_group_description', 'enrol_oss'),
        get_string('class_students_group_description_desc', 'enrol_oss'),
        'students of class ', PARAM_TEXT);
    $settings_class_students_group_description->set_updatedcallback('settings_class_students_group_description_updated');
    $settings->add($settings_class_students_group_description);
    $settings_class_parents_group_description = new admin_setting_configtext('enrol_oss/class_parents_group_description',
        get_string('class_parents_group_description', 'enrol_oss'),
        get_string('class_parents_group_description_desc', 'enrol_oss'),
        'parents of class ', PARAM_TEXT);
    $settings_class_parents_group_description->set_updatedcallback('settings_class_parents_group_description_updated');
    $settings->add($settings_class_parents_group_description);
}
