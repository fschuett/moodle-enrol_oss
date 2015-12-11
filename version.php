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
 * OpenML enrolment plugin version specification.
 *
 * @package    enrol
 * @subpackage openlml
 * @author     Frank Schütte
 * @copyright  2012 Frank Schütte <fschuett@gymnasium-himmelsthuer.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2015121101;        // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires  = 2013111800;        // Requires Moodle version 2.6.
$plugin->component = 'enrol_openlml';   // Full name of the plugin (used for diagnostics).
$plugin->cron      = 60*60;             // Run cron every hour, because it is time consuming.
$plugin->maturity  = MATURITY_BETA;     // Beta, nees testing.
$plugin->release   = '1.0 (Build: 2015121101)';
$plugin->dependencies = array('auth_ldap'=>ANY_VERSION, 'enrol_cohort'=>ANY_VERSION);
