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
 * Definition of oss enrolment scheduled tasks.
 *
 * @package    enrol_oss
 * @copyright  2016 Frank Schütte
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = array(
    array(
        'classname' => '\enrol_oss\task\sync_enrolments_task',
        'blocking' => 0,
        'minute' => '51',
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*'
    ),
    array(
        'classname' => '\enrol_oss\task\sync_classes_task',
        'blocking' => 0,
        'minute' => '17',
        'hour' => '22',
        'day' => '9',
        'dayofweek' => '*',
        'month' => '*'
    ),
    array(
        'classname' => '\enrol_oss\task\sync_parents_task',
        'blocking' => 0,
        'minute' => '31',
        'hour' => '21',
        'day' => '11',
        'dayofweek' => '*',
        'month' => '*'
    )
);
