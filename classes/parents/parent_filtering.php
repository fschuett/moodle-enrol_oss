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
 * This file contains the Parent Filter API.
 *
 * @package   enrol_oss
 * @category  user
 * @copyright 2018 Frank Schütte extends user_filtering
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Parent filtering wrapper class based on user_filtering wrapper class.
 *
 * @copyright 2018 Frank Schütte
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_oss\parents;

require_once($CFG->dirroot.'/user/filters/lib.php');

class parent_filtering extends \user_filtering {

    /**
     * Contructor
     * @param array $fieldnames array of visible user fields
     * @param string $baseurl base url used for submission/return, null if the same of current page
     * @param array $extraparams extra page parameters
     */
    public function __construct($fieldnames = null, $baseurl = null, $extraparams = null) {
        if(empty($fieldnames)) {
            $fieldnames = array('realname' => 0, 'lastname' => 1, 'firstname' => 1, 'username' => 1, 'email' => 1, 'city' => 1, 'country' => 1,
                                'confirmed' => 1, 'suspended' => 1, 'profile' => 1, 'courserole' => 1, 'systemrole' => 1, 'userrole' => 1,
                                'cohort' => 1, 'firstaccess' => 1, 'lastaccess' => 1, 'neveraccessed' => 1, 'timemodified' => 1,
                                'nevermodified' => 1, 'auth' => 1, 'mnethostid' => 1, 'idnumber' => 1);
        }
        parent::__construct($fieldnames, $baseurl, $extraparams);
    }

    /**
     * Creates known user filter if present
     * @param string $fieldname
     * @param boolean $advanced
     * @return object filter
     */
    public function get_field($fieldname, $advanced) {
        global $USER, $CFG, $DB, $SITE;

        switch ($fieldname) {
            case 'userrole':
return new \enrol_oss\parents\filter_userrole('userrole', get_string('userrole', 'enrol_oss'), $advanced);
            case 'cohort':
return new \enrol_oss\parents\filter_cohort($advanced);
            default:
return parent::get_field($fieldname, $advanced);
        }
    }

    /**
     * Returns sql where statement based on active user filters
     * and base filter to filter parents only
     * @param string $extra sql
     * @param array $params named params (recommended prefix ex)
     * @return array sql string and $params
     */
    public function get_sql_filter($extra='', array $params=null) {
        global $SESSION;

        $parents_prefix = get_config('enrol_oss','parents_prefix');
        if ( ! $parents_prefix ) {
            $parents_prefix = 'eltern_';
        }
        $sqls = array();
        if ($extra != '') {
            $sqls[] = $extra;
        }
        $params = (array)$params;

        $sqls[] = "id IN (SELECT id FROM {user} WHERE auth = 'manual' AND username like '".$parents_prefix."%')";

        if (!empty($SESSION->user_filtering)) {
            foreach ($SESSION->user_filtering as $fname => $datas) {
                if (!array_key_exists($fname, $this->_fields)) {
                    continue; // Filter not used.
                }
                $field = $this->_fields[$fname];
                foreach ($datas as $i => $data) {
                    list($s, $p) = $field->get_sql_filter($data);
                    $sqls[] = $s;
                    $params = $params + $p;
                }
            }
        }

        if (empty($sqls)) {
            return array('', array());
        } else {
            $sqls = implode(' AND ', $sqls);
            return array($sqls, $params);
        }
    }

    /**
     * Print the add filter form.
     */
    public function display_add() {
        $this->_addform->display();
    }

    /**
     * Print the active filter form.
     */
    public function display_active() {
        $this->_activeform->display();
    }


}
