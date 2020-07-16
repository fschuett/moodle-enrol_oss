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
 * User role filter
 *
 * @package   enrol_oss
 * @category  user
 * @copyright 2018 Frank Schütte
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_oss\parents;

require_once($CFG->dirroot.'/user/filters/lib.php');

/**
 * User filter based on user roles.
 * @copyright 2018 Frank Schütte
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_userrole extends \user_filter_type {

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     */
    public function __construct($name, $label, $advanced) {
        parent::__construct($name, $label, $advanced);
    }

    /**
     * Returns an array of available roles
     * @return array of availble roles
     */
    public function get_roles() {
        global $DB;

        $params = array('contextlevel' => CONTEXT_USER);

        $sql = "SELECT r.id, r.name, r.shortname
                  FROM {role} r
             LEFT JOIN {role_context_levels} rcl ON (rcl.roleid = r.id AND rcl.contextlevel = :contextlevel)
                 WHERE rcl.id IS NOT NULL
              ORDER BY sortorder DESC";

        $roles = $DB->get_records_sql($sql, $params);
        $roles = array(0 => get_string('anyrole', 'filters')) + role_fix_names($roles, null, ROLENAME_ALIAS, true);
        return $roles;
    }

    /**
     * Adds controls specific to this filter in the form.
     * @param object $mform a MoodleForm object to setup
     */
    public function setupForm(&$mform) {
        $objs = array();
        $objs[] = $mform->createElement('select', $this->_name.'_role', null, $this->get_roles());
        $objs[] = $mform->createElement('checkbox', $this->_name.'_not', ' ', get_string('userrole_inverted', 'enrol_oss'));
        $mform->addElement('group', $this->_name.'_grp', $this->_label, $objs, '', false);
        $mform->setDefault($this->_name, 0);
        if ($this->_advanced) {
            $mform->setAdvanced($this->_name.'_grp');
        }
    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submited with the form
     * @return mixed array filter data or false when filter not set
     */
    public function check_data($formdata) {
        $field = $this->_name.'_role';
        $inverted = $this->_name.'_not';

        if (array_key_exists($field, $formdata) and !empty($formdata->$field)) {
            if(array_key_exists($inverted, $formdata)) {
                return array('value' => (int)$formdata->$field,
                             'not'   => (string)$formdata->$inverted);
            } else {
                return array('value' => (int)$formdata->$field);
            }
        }
        return false;
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return array sql string and $params
     */
    public function get_sql_filter($data) {
        global $CFG;
        $value = (int)$data['value'];
        if( array_key_exists('not', $data) ){
            $not = 'not';
        } else {
            $not = '';
        }
        $timenow = round(time(), 100);

        $sql = "id $not IN (SELECT userid
                         FROM {role_assignments}
                         JOIN {context} ON {context}.id = {role_assignments}.contextid
                        WHERE contextlevel=".CONTEXT_USER." AND roleid=$value)";
        return array($sql, array());
    }

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    public function get_label($data) {
        global $DB;

        $role = $DB->get_record('role', array('id' => $data['value']));
        if( array_key_exists('not', $data) ){
            $not = get_string('userrole_inverted_label', 'enrol_oss');
        } else {
            $not = '';
        }
        $a = new \stdClass();
        $a->label = $this->_label;
        $a->value = $not.'"'.role_get_name($role).'"';
        return get_string('userrolelabel', 'enrol_oss', $a);
    }
}
