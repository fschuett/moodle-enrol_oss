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
 * CLI sync for full OSS synchronisation.
 *
 * This script is meant to be called from a cronjob to sync moodle with the OSS
 * server to pickup groups as moodle global groups (cohorts).
 *
 * Sample cron entry:
 * # 5 minutes past every full hour
 * 5 * * * * $sudo -u www-data /usr/bin/php /var/www/moodle/enrol/oss/cli/sync.php
 *
 * Notes:
 *   - it is required to use the web server account when executing PHP CLI scripts
 *   - you need to change the "www-data" to match the apache user account
 *   - use "su" if "sudo" not available
 *   - If you have a large number of users, you may want to raise the memory limits
 *     by passing -d momory_limit=256M
 *   - For debugging & better logging, you are encouraged to use in the command line:
 *     -d log_errors=1 -d error_reporting=E_ALL -d display_errors=0 -d html_errors=0
 *
 * @package    enrol
 * @subpackage oss
 * @author     Frank Schütte - based on code of ldap enrol and sync_cohorts.php
 * @copyright  2012 Frank Schütte <fschuett@gymnasium-himmelsthuer.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->libdir.'/clilib.php');

// Now get cli options.
list($options, $unrecognized) = cli_get_params(array('help' => false), array('h' => 'help'));

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknownoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help =
    "Execute enrol sync with external OSS server.
The enrol_oss plugin must be enabled and properly configured.

Options:
-h, --help            Print out this help

Example:
\$sudo -u www-data /usr/bin/php enrol/oss/cli/sync.php

Sample cron entry:
# 5 minutes past every full hour
5 * * * * \$sudo -u www-data /usr/bin/php /var/www/moodle/enrol/oss/cli/sync.php
";

    echo $help;
    die;
}

// Ensure errors are well explained.
$CFG->debug = DEBUG_NORMAL;

// The enrolment depends on user synchronization via auth_ldap.
if (!is_enabled_auth('ldap')) {
    print('[AUTH LDAP] ' . get_string('pluginnotenabled', 'auth_ldap'));
    die;
}


if (!enrol_is_enabled('oss')) {
    print('[ENROL OSS] '.get_string('pluginnotenabled', 'enrol_oss'));
    die;
}

$result = 0;

// Update enrolments.
$enrol = enrol_get_plugin('oss');
$result = $enrol->enrol_oss_sync();

exit($result);
