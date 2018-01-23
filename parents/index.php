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
# debug function
function kill($data){ var_dump($data); exit; }
@ini_set('display_errors','1');

/**
 * @package    local_cohortrole
 * @copyright  2013 Paul Holden (pholden@greenhead.ac.uk)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/enrol/oss/parents/locallib.php');

require_login();
admin_externalpage_setup('enrol_oss_parents');
require_capability('moodle/role:assign', context_system::instance());

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('parents_setup','enrol_oss'));

if (!parents_enabled()) {

	echo $OUTPUT->error_text(get_string('parents_not_enabled','enrol_oss'));

} else {

echo $OUTPUT->heading(get_string('parents_childless_list', 'enrol_oss'));
// orphaned parents list - action: remove orphaned parents
// orphaned students list - action: add parents
// parents list - select, update classes, print passwords, export passwords, set passwords (randomly)
if ($records = local_parents_list()) {
    $table = new flexible_table('enrol_oss');
    $table->define_columns(array('cohort', 'role', 'edit'));
    $table->define_headers(array(get_string('cohort', 'enrol_oss'), get_string('role', 'enrol_oss'), get_string('edit')));
    $table->define_baseurl($PAGE->url);
    $table->setup();

    $icon = new pix_icon('t/delete', get_string('delete'), 'core', array('class' => 'iconsmall'));

    foreach ($records as $record) {
        $delete = $OUTPUT->action_icon(new moodle_url('/local/cohortrole/edit.php', array('id' => $record->id, 'delete' => 1)), $icon);

        $table->add_data(array($record->name, $record->role, $delete));
    }

    $table->print_html();
} else {
    echo $OUTPUT->notification(get_string('nothingtodisplay'));
}

echo $OUTPUT->single_button(new moodle_url('/local/cohortrole/edit.php'), get_string('add'), 'get', array('class' => 'continuebutton'));

}
echo $OUTPUT->footer();
