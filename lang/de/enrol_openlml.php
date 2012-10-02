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
 * Strings for component 'enrol_openlml', language 'de'
 *
 * @package    enrol
 * @subpackage openlml
 * @copyright  Frank Schütte <fschuett@gymnasium-himmelsthuer.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['attribute'] = 'Das Gruppenattribut, normalerweise cn';
$string['attribute_key'] = 'Gruppenattribut';
$string['common_settings'] = 'Allgemeine LDAP-Einstellungen';
$string['contexts'] = 'LDAP-Teilbäume, in denen Gruppen zu finden sind(Trennzeichen ;).';
$string['contexts_key'] = 'Kontexte';
$string['course_description'] = 'Kursbereich des Lehrers ';
$string['enrolname'] = 'openlml';
$string['member_attribute'] = 'Mitgliedsattribut der Gruppen, normalerweise memberuid';
$string['member_attribute_key'] = 'Mitgliedsattribut';
$string['object'] = 'Die Objektart, normalerweise posixGroup';
$string['object_key'] = 'Objektart';
$string['pluginname_desc'] = '<p>Sie können einen openLML-Server nutzen, um die Anmeldung von Teilnehmer/innen in Kursen zu verwalten.</p><p>Es wird angenommen, dass der openLML-LDAP-Baum Gruppen enthält, die zu Kursen gehören und dass jede/r der Gruppen/Kurse Einträge von Teilnehmer/innen hat. Es wird weiterhin angenommen, dass die Lehrer in der Gruppe teachers im openLML definiert sind und in den Mitgliedsfeldern dieser Gruppe eingetragen sind.(<em>member</em> oder <em>memberUid</em>), die eine eindeutige Identifizierung des/der Nutzer/in ermöglichen.</p><p>Um das openLML-LDAP als Kurs-Anmeldeverfahren zu verwenden, <strong>muss</strong> jeder Nutzer eine gültige ID-Nummer(uid) besitzen. Die LDAP-Gruppen müssen diese ID-Nummer in den Mitgliedsfeldern aufweisen, um den/die Nutzer/in als Teilnehmer/in in den Kurs einzuschreiben. Dies funktioniert normalerweise sehr gut, wenn Sie LDAP auch zur Nutzerauthentifizierung nutzen.</p><p>Kursanmeldungen werden aktualisiert, wenn der Nutzer sich in Moodle einloggt. Sie können auch ein Skript nutzen, um Kursanmeldungen zu synchronisieren. Moodle liefert ein solches Skript: <em>enrol/openlml/cli/sync.php</em>. Sie können das openLML-Anmeldeverfahren auch so konfigurieren, dass automatisch neue Kurse angelegt werden, wenn neue Gruppen in LDAP eingerichtet werden.</p>';
$string['pluginname'] = 'Open LML Einschreibung';
$string['pluginnotenabled'] = 'Modul nicht aktiviert!';
$string['prefix_teacher_members'] = 'In Kursen mit diesem Präfix können auch Lehrer Mitglieder sein (z.B. für Fachgruppen)';
$string['prefix_teacher_members_key'] = 'Lehrerkurse';
$string['student_class_numbers'] = 'Die Klassenstufen, normalerweise 5,6,7,8,9,10,11,12';
$string['student_class_numbers_key'] = 'Klassenstufen';
$string['student_groups'] = 'Weitere Gruppen, die weder Klassenstufen noch Projekte sind (Trennzeichen ,)';
$string['student_groups_key'] = 'Weitere Gruppen';
$string['student_project_prefix'] = 'Der Gruppenbezeichnung in der openLML wird normalerweise ein p_ vorangestellt.';
$string['student_project_prefix_key'] = 'Projekt-Präfix';
$string['student_role'] = 'Die Rolle, mit der Schüler in einen Kurs eingetragen werden, normalerweise Teilnehmer/in';
$string['student_role_key'] = 'Schülerrolle';
$string['students_settings'] = 'Schülereinstellungen';
$string['sync_description'] = 'Synchronisiert mit OpenLML-Server';
$string['teacher_context_desc'] = 'Bereich für automatische Lehrerkurse.';
$string['teachers_category_autocreate'] = 'Der Kursbereich eines Lehrer wird automatisch beim ersten Anmelden angelegt.';
$string['teachers_category_autocreate_key'] = 'automatisch erzeugen';
$string['teachers_category_autoremove'] = 'Der Kursbereich eines Benutzers wird entfernt, wenn er kein Lehrer ist.';
$string['teachers_category_autoremove_key'] = 'automatisch entfernen';
$string['teachers_context_settings'] = 'Lehrer-Kursbereicheinstellungen';
$string['teachers_course_context'] = 'Name des Kursbereichs mit Kursen einzelner Lehrer, normalerweise Lehrer';
$string['teachers_course_context_key'] = 'Lehrer-Kursbereich';
$string['teachers_course_role'] = 'Rolle des Lehrers in seinem Kursbereich, normalerweise Kursverwalter/in';
$string['teachers_course_role_key'] = 'Lehrerrolle';
$string['teacher_settings'] = 'Lehrergruppen-Einstellungen';
$string['teachers_group_name'] = 'Name der Lehrergruppe, normalerweise teachers';
$string['teachers_group_name_key'] = 'Lehrergruppe';
$string['teachers_ignore'] = 'Ausnahmen: Benutzer, die Lehrer sind, für die aber trotz Einstellung weiter oben keine Kursbereiche automatisch angelegt oder entfernt werden sollen.';
$string['teachers_ignore_key'] = 'ignorierte Lehrer';
$string['teachers_removed'] = 'Dieser Kursbereich nimmt die automatisch entfernten Kursbereiche auf, normalerweise attic';
$string['teachers_removed_key'] = 'entfernte Kursbereiche';
$string['teachers_role'] = 'Rolle der Lehrer in Lehrergruppen, normalerweise Teilnehmer/in';
$string['teachers_role_key'] = 'Lehrerrolle';
