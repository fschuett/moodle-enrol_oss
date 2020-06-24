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
 * Scheduled task for processing parents relationships.
 *
 * @package    enrol_oss
 * @copyright  2016 Frank Schütte
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_oss\task;

defined('MOODLE_INTERNAL') || die;

/**
 * Simple task to run sync classes enrolments.
 *
 * @copyright  2016 Frank Schütte
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_parents_task extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('ossparentssync', 'enrol_oss');
    }

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {
        global $CFG;

        require_once($CFG->dirroot . '/enrol/oss/lib.php');

        // The enrolment depends on user synchronization via auth_ldap.
        if (!is_enabled_auth('ldap')) {
            debugging('[AUTH LDAP] ' . get_string('pluginnotenabled', 'auth_ldap'));
            return;
        }

        if (!enrol_is_enabled('cohort')) {
            debugging('[ENROL COHORT]'.get_string('pluginnotenabled', 'enrol_cohort'));
            return;
        }

        if (!enrol_is_enabled('oss')) {
            debugging('[ENROL oss] '.get_string('pluginnotenabled', 'enrol_oss'));
            return;
        }

        // Instance of enrol_flatfile_plugin.
        $plugin = enrol_get_plugin('oss');
        $result = $plugin->parents_sync_relationships();
        return $result;

    }

}
