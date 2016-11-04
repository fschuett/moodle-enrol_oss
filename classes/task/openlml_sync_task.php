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
 * Scheduled task for processing openlml enrolments.
 *
 * @package    enrol_openlml
 * @copyright  2016 Frank Schütte
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_openlml\task;

defined('MOODLE_INTERNAL') || die;

/**
 * Simple task to run sync enrolments.
 *
 * @copyright  2016 Frank Schütte
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class openlml_sync_task extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('openlmlsync', 'enrol_openlml');
    }

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {
        global $CFG;

        require_once($CFG->dirroot . '/enrol/openlml/lib.php');

        // The enrolment depends on user synchronization via auth_ldap.
        if (!is_enabled_auth('ldap')) {
            debugging('[AUTH LDAP] ' . get_string('pluginnotenabled', 'auth_ldap'));
            return;
        }

        if (!enrol_is_enabled('cohort')) {
            debugging('[ENROL COHORT]'.get_string('pluginnotenabled', 'enrol_cohort'));
            return;
        }

        if (!enrol_is_enabled('openlml')) {
            debugging('[ENROL OPENLML] '.get_string('pluginnotenabled', 'enrol_openlml'));
            return;
        }

        // Instance of enrol_flatfile_plugin.
        $plugin = enrol_get_plugin('openlml');
        $result = $plugin->enrol_openlml_sync();
        return $result;

    }

}
