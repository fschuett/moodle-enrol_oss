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
 * OSS enrolment plugin version specification.
 *
 * @package    enrol
 * @subpackage oss
 * @author     Frank Schütte
 * @copyright  2020 Frank Schütte <fschuett@gymhim.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2024100304;        // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires  = 2015051100;        // Requires Moodle version 2.9
$plugin->component = 'enrol_oss';   // Full name of the plugin (used for diagnostics).
$plugin->maturity  = MATURITY_BETA;     // Beta, may contain errors.
$plugin->release   = '2.4.3 (Build: 2024100304)';
$plugin->dependencies = array('auth_ldap' => ANY_VERSION, 'enrol_cohort' => ANY_VERSION);
