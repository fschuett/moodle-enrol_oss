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
    "Execute teachers contexts repair.

Options:
-h, --help            Print out this help

Example:
\$sudo -u www-data /usr/bin/php enrol/oss/cli/repair_teachers_contexts.php

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

// Repair teachers contexts.
$enrol = enrol_get_plugin('oss');
$enrol->repair_teachers_contexts();

exit(0);
