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
// You should// This file is part of Moodle - http://moodle.org/
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
 * Cohort parent filter.
 *
 * @package   enrol_oss
 * @category  user
 * @copyright 2018 Frank Schütte
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_oss\parents;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/user/filters/cohort.php');

/**
 * Parent filter for cohort membership.
 * @copyright 2018 Frank Schütte
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_cohort extends \user_filter_cohort {
    /**
     * Constructor
     * @param boolean $advanced advanced form element flag
     */
    public function __construct($advanced) {
        parent::__construct('cohort', get_string('idnumber', 'core_cohort'), $advanced);
    }

    /**
     * Returns the condition to be used with SQL where
     * @param array $data filter settings
     * @return array sql string and $params
     */
    public function get_sql_filter($data) {
        global $DB;
        static $counter = 0;
        $name = 'ex_pcohort'.$counter++;

        $operator = $data['operator'];
        $value    = $data['value'];

        $params = array();

        if ($value === '') {
            return '';
        }

        $not = '';
        switch($operator) {
            case 0: // Contains.
                $res = $DB->sql_like('c.idnumber', ":$name", false, false);
                $params[$name] = "%$value%";
                break;
            case 1: // Does not contain.
                $not = 'NOT';
                $res = $DB->sql_like('c.idnumber', ":$name", false, false);
                $params[$name] = "%$value%";
                break;
            case 2: // Equal to.
                $res = $DB->sql_like('c.idnumber', ":$name", false, false);
                $params[$name] = "$value";
                break;
            case 3: // Starts with.
                $res = $DB->sql_like('c.idnumber', ":$name", false, false);
                $params[$name] = "$value%";
                break;
            case 4: // Ends with.
                $res = $DB->sql_like('c.idnumber', ":$name", false, false);
                $params[$name] = "%$value";
                break;
            case 5: // Empty.
                $not = 'NOT';
                $res = '(c.idnumber IS NOT NULL AND c.idnumber <> :'.$name.')';
                $params[$name] = '';
                break;
            default:
                return '';
        }

        $sql = "id $not IN (SELECT p.id AS id
                        FROM {user} p
                        JOIN {role_assignments} ra ON p.id = ra.userid
                        JOIN {context} cx ON ra.contextid = cx.id
                        JOIN {cohort_members} cm ON cm.userid = cx.instanceid
                        JOIN {cohort} c ON cm.cohortid = c.id
                        WHERE cx.contextlevel=30 AND $res)";

        return array($sql, $params);
    }
}
