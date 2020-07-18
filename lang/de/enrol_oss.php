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
 * Strings for component 'enrol_oss', language 'de', branch 'MOODLE_26_STABLE'
 *
 * @package   enrol_oss
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['attic_description'] = 'Kursbereich(e) von  entfernten Trainern';
$string['attribute'] = 'Das Gruppenattribut, normalerweise cn';
$string['attribute_key'] = 'Gruppenattribut';
$string['ccteacher'] = 'Kategorielehrer/in';
$string['ccteacher_desc'] = 'Kategorielehrer/innen dürfen in jedem Kurs einer Kurskategorie alles tun, incl. der Veränderung von Aktivitäten und der Beurteilung von Teilnehmer/innen.';
$string['class_age_groups'] = 'Jahrgangsklassen';
$string['class_age_groups_desc'] = 'Erzeuge zusätzlich eine Klasse für jeden Jahrgang';
$string['class_age_groups_shortname'] = 'jg';
$string['class_age_groups_localname'] = 'Jahrgang';
$string['class_all_students'] = 'Klasse mit allen Schülern';
$string['class_all_students_desc'] = 'Erzeuge zusätzlich eine Klasse mit allen Schülern';
$stirng['class_all_students_shortname'] = 'alle';
$string['class_all_students_localname'] = 'Alle Schüler';
$string['class_attribute'] = 'Klassenattribut';
$string['class_attribute_desc'] = 'Klassen werden durch dieses Attribut identifiziert';
$string['class_attribute_value'] = 'Attributwert';
$string['class_attribute_value_desc'] = 'Klassen werden durch diesen Wert für das Klassenattribut identifiziert';
$string['class_settings'] = 'Einstellungen für Klassen';
$string['class_settings_desc'] = '<p>Diese Einstellungen betreffen einen Kursbereich für Klassen. Klassen sind spezielle Kurse, in denen Lehrer, Schüler und Eltern automatisch mit unterschiedlichen Rollen und in unterschiedlichen Gruppen eingeschrieben werden.</p><p>Der Klassenkursbereich kann wie auch die einzelnen Klassen automatisch erstellt werden. Klassen können außerdem automatisch entfernt werden.</p><p>Zusätzlich können <b>Gruppen</b> aktiviert werden. Lehrer, Schüler und Eltern werden automatisch in die jeweilige Gruppe eingefügt. Das erleichtert die Kommunikation mit den einzelnen Gruppen. All das passiert während eines standardmäßig einmal am Tag ausgeführten Geplanten Vorgangs namens <b>oss_sync_classes_task</b></p>';
$string['classes_enabled'] = 'Klassen aktiviert';
$string['classes_enabled_desc'] = 'Aktivieren von automatischen Klassen';
$string['class_category_description'] = 'Alle Klassen';
$string['class_category'] = 'Klassenkursbereich';
$string['class_category_desc'] = 'Klassen werden in diesem Kursbereich angelegt';
$string['class_category_autocreate'] = 'Kursbereich automatisch erstellen';
$string['class_category_autocreate_desc'] = 'Markieren, um den Kursbereich automatisch erstellen zu lassen';
$string['class_autocreate'] = 'Klassen anlegen';
$string['class_autocreate_desc'] = 'Markieren, um Klassen automatisch anlegen zu lassen';
$string['class_autoremove'] = 'Klassen entfernen';
$string['class_autoremove_desc'] = 'Markieren, um nicht mehr benötigte Klassen automatisch entfernen zu lassen';
$string['class_localname'] = 'Klasse';
$string['class_template_none'] = 'keine';
$string['class_template'] = 'Klassenvorlage';
$string['class_template_desc'] = 'Kursvorlage (unsichtbar), die als Muster zur Erstellung neuer Klassen verwendet wird';
$string['class_use_prefixes'] = 'Präfixe verwenden';
$string['class_use_prefixes_desc'] = 'Präfixe an Stelle eines Attributwertes zur Identifizierung von Klassen verwenden';
$string['class_prefixes'] = 'Klassenpräfixe';
$string['class_prefixes_desc'] = 'Klassen werden durch diese LDAP-Wert-Präfixe identifiziert';
$string['class_teachers_group'] = 'Lehrer';
$string['class_teachers_group_description'] = 'Lehrer der Klasse ';
$string['class_teachers_group_description_desc'] = 'Diese Beschreibung wird zur Lehrergruppe hinzugefügt';
$string['class_teachers_role'] = 'Lehrerrolle';
$string['class_teachers_role_desc'] = 'Lehrer werden mit dieser Rolle in der Klasse eingeschrieben';
$string['class_students_group'] = 'Schüler';
$string['class_students_group_description'] = 'Schüler der Klasse ';
$string['class_students_group_description_desc'] = 'Diese Beschreibung wird zur Schülergruppe hinzugefügt';
$string['class_students_role'] = 'Schülerrolle';
$string['class_students_role_desc'] = 'Schüler werden mit dieser Rolle in der Klasse eingeschrieben';
$string['class_parents_group'] = 'Eltern';
$string['class_parents_group_description'] = 'Eltern der Klasse ';
$string['class_parents_group_description_desc'] = 'Diese Beschreibung wird der Elterngruppe hinzugefügt';
$string['class_parents_role'] = 'Elternrolle';
$string['class_parents_role_desc'] = 'Eltern werden mit dieser Rolle in der Klasse eingeschrieben';
$string['groups_enabled'] = 'Gruppen verwenden';
$string['groups_enabled_desc'] = 'Gruppen zur Vereinfachung der Kommunikation werden eingerichtet und automatisch aktualisiert';
$string['common_settings'] = 'Allgemeine LDAP-Einstellungen';
$string['contexts'] = 'LDAP-Teilbäume, in denen Gruppen zu finden sind (Trennzeichen ;).';
$string['contexts_key'] = 'Kontexte';
$string['course_description'] = 'Kursbereich des Trainers';
$string['enrolname'] = 'OSS';
$string['eventattic_category_created'] = 'Kursbereich für entfernte Trainer/innen erstellt';
$string['eventcohort_created'] = 'Globale Gruppe erstellt';
$string['eventcohort_enroled'] = 'Globale Gruppe in Kurs eingeschrieben';
$string['eventcohort_member_added'] = 'Teilnehmer/in zu globaler Gruppe hinzugefügt';
$string['eventcohort_member_removed'] = 'Teilnehmer/in aus globaler Gruppe entfernt';
$string['eventcohort_members_removed'] = 'Teilnehmer/innen aus globaler Gruppe entfernt';
$string['eventcohort_removed'] = 'Globale Gruppe entfernt';
$string['eventcohort_unenroled'] = 'Globale Gruppe aus Kurs ausgetragen';
$string['eventteacher_category_created'] = 'Kursbereich für Trainer/innen erzeugt';
$string['eventteacher_category_moved'] = 'Kursbereich für Trainer/innen verschoben';
$string['eventteacher_category_removed'] = 'Kursbereich für Trainer/innen entfernt';
$string['eventteacher_role_assigned'] = 'Lehrerrolle zum Kursbereich hinzugefügt';
$string['eventteacher_role_unassigned'] = 'Lehrerrolle aus dem Kursbereich entfernt';
$string['eventteachers_category_created'] = 'Kursbereich für Trainer/innen erzeugt';
$string['member_attribute'] = 'Mitgliedsattribut der Gruppen, normalerweise member';
$string['member_attribute_key'] = 'Mitgliedsattribut';
$string['object'] = 'Die Objektart, normalerweise SchoolGroup';
$string['object_key'] = 'Objektklasse';
$string['osssync'] = 'OSS-Einschreibung synchronisieren';
$string['ossclasssync'] = 'OSS-Klassen synchronisieren';
$string['ossparentssync'] = 'OSS Eltern-/Kind-Beziehungen synchronisieren';
$string['parents_all_label'] = 'Für alle Eltern...';
$string['parents_autocreate'] = 'Eltern-Konten erstellen';
$string['parents_autocreate_desc'] = 'Während einer <b>Aktualisierung</b> werden Eltern-Konten für elternlose Kinder autoatisch erzeugt.';
$string['parents_autoremove'] = 'Eltern-Konten entfernen';
$string['parents_autoremove_desc'] = 'Während einer <b>Aktualisierung</b> werden kinderlose Eltern-Konten automatisch entfernt.';
$string['parents_setup'] = 'Elternverwaltung';
$string['parents_not_enabled'] = 'Eltern-/Kindverwaltung muss unter Plugins -> Einschreibung -> OSS-Einschreibung im Abschnitt <b>Einstellungen für Eltern</b> aktiviert werden.';
$string['parents_list'] = 'Elternliste';
$string['parents_childless_list'] = 'Kinderlose Eltern';
$string['parents_orphaned_students_list'] = 'Elternlose Schüler';
$string['parents_settings'] = 'Einstellungen für Eltern';
$string['parents_settings_desc'] = '<p>Diese Einstellungen stellen eine Verwaltung von Eltern-/Kind-Beziehungen über die Seite <b>Elternverwaltung</b> im Bereich "Nutzerpflege" bereit. Es werden für Schülerkonten des LDAP-Servers automatisch Elternkonten angelegt bzw. auch wieder entfernt. Alternativ können diese Konten auch im Bereich Nutzerverwaltung importiert werden (siehe dazu die Datei README.md).</p><p>Dort ist auch angegeben, wie die Namen der Elternkonten aussehen müssen (u.a. müssen sie mit dem <b>Eltern-Präfix</b> beginnen und mit einem Text enden, der das zugehörige Kind eindeutig identifiziert).</p><p>Zur Verwendung dieser Einstellungen ist es sinnvoll, die Einstellung <b>Nutzerkonten mit gleicher E-Mailadresse erlauben</b> ("allowaccountssameemail"), da bei Geschwistern für jedes Kind ein eigenes Elternkonto verwaltet werden muss.</p>';
$string['parents_enabled'] = 'Eltern verwalten';
$string['parents_enabled_desc'] = 'Die Verwaltung von Eltern-/Kindbeziehungen wird aktiviert.';
$string['parents_prefix'] = 'Eltern-Präfix';
$string['parents_prefix_desc'] = 'Die Benutzernamen der Eltern beginnen mit diesem Präfix';
$string['parents_child_attribute'] = 'Kindattribut';
$string['parents_child_attribute_desc'] = 'Dieses Attribut identifiziert ein Kind im LDAP-Baum';
$string['parents_confirm_set_passwords'] = 'Ein neues Passwort wird für die Benutzer {$a} gesetzt.';
$string['parents_no_selected_users'] = 'Es sind keine Benutzer ausgewählt.';
$string['parents_password_empty'] = 'Das neue Passwort ist leer.';
$string['parents_role'] = 'Elternrolle';
$string['parents_role_desc'] = 'Die Elternrolle wird Eltern nutzerspezifisch zugewiesen (die Rolle muss wie auf der Moodle-Seite \'Parent role\' erstellt werden)';
$string['parents_set_password'] = 'Passwort setzen';
$string['parents_update'] = 'Aktualisieren';
$string['parents_update_desc'] = 'Für alle Eltern werden die Eltern-/Kind-Beziehungen aktualisiert.';
$string['parents_update_label'] = 'Für alle Eltern-Konten... ';
$string['pluginname'] = 'OSS-Einschreibung';
$string['pluginname_desc'] = '<p>Sie können einen OSS-Server nutzen, um die Anmeldung von Teilnehmer/innen in Kursen zu verwalten.</p><p>Es wird angenommen, dass der OSS-LDAP-Baum Gruppen enthält, die zu Kursen gehören und dass jede/r der Gruppen/Kurse Einträge von Teilnehmer/innen hat. Es wird weiterhin angenommen, dass die Lehrer (Trainer in der Moodle-Sprechweise) in der Gruppe teachers im LDAP-Baum definiert sind und in den Mitgliedsfeldern dieser Gruppe eingetragen sind.(<em>member</em> oder <em>memberUid</em>), die eine eindeutige Identifizierung des/der Nutzer/in ermöglichen.</p><p>Um das OSS-LDAP als Kurs-Anmeldeverfahren zu verwenden, <strong>muss</strong> jeder Nutzer eine gültige ID-Nummer(uid) besitzen. Die LDAP-Gruppen müssen diese ID-Nummer in den Mitgliedsfeldern aufweisen, um den/die Nutzer/in als Teilnehmer/in in den Kurs einzuschreiben. Dies funktioniert normalerweise sehr gut, wenn Sie LDAP auch zur Nutzerauthentifizierung nutzen.</p><p>Für die Kursanmeldungen wird die Kurs-ID verwendet. Da sie eindeutig sein muss, wird "Kurzname:" vorangestellt.</p><p>Kursanmeldungen werden aktualisiert, wenn der Nutzer sich in Moodle einloggt. Sie können auch ein Skript nutzen, um Kursanmeldungen zu synchronisieren. Moodle liefert ein solches Skript: <em>enrol/oss/cli/sync.php</em>. Sie können das OSS-Anmeldeverfahren auch so konfigurieren, dass automatisch neue Kurse angelegt werden, wenn neue Gruppen in LDAP eingerichtet werden.</p>';
$string['pluginnotenabled'] = 'Modul nicht aktiviert!';
$string['prefix_teacher_members'] = 'In Kursen mit diesen Präfixen können auch Trainer Mitglieder sein (z.B. für Fachgruppen), es handelt sich um eine kommagetrennte Präfixliste.';
$string['prefix_teacher_members_key'] = 'Präfix Trainerkurse';
$string['student_class_numbers'] = 'Die Klassenstufen, normalerweise 5,6,7,8,9,10,11,12';
$string['student_class_numbers_key'] = 'Klassenstufen';
$string['students_group_name'] = 'Schülergruppe';
$string['students_group_name_desc'] = 'Name der Schülergruppe';
$string['student_groups'] = 'Weitere Gruppen, die weder Klassenstufen noch Projekte sind (Trennzeichen ,)';
$string['student_groups_key'] = 'Weitere Gruppen';
$string['student_project_prefix'] = 'Der Gruppenbezeichnung in der OSS wird normalerweise ein P_ vorangestellt.';
$string['student_project_prefix_key'] = 'Projekt-Präfix';
$string['student_role'] = 'Die Rolle, mit der Nutzer/innen in einen Kurs eingetragen werden, normalerweise Teilnehmer/in';
$string['student_role_key'] = 'Teilnehmerrolle';
$string['students_settings'] = 'Teilnehmereinstellungen';
$string['students_settings_desc'] = 'Diese Einstellungen beeinflussen die Einschreibung von Schülern.';
$string['sync_description'] = 'Synchronisiert mit OSS-Server';
$string['teacher_context_desc'] = 'Bereich für automatische Trainerkurse.';
$string['teachers_category_autocreate'] = 'Der Kursbereich eines Trainers wird automatisch beim ersten Anmelden angelegt.';
$string['teachers_category_autocreate_key'] = 'automatisch erzeugen';
$string['teachers_category_autoremove'] = 'Der Kursbereich eines Benutzers wird entfernt, wenn er kein Trainer ist.';
$string['teachers_category_autoremove_key'] = 'automatisch entfernen';
$string['teachers_context_settings'] = 'Trainer-Kursbereichseinstellungen';
$string['teachers_course_context'] = 'Name des Kursbereichs mit Kursen einzelner Trainer, normalerweise Trainer';
$string['teachers_course_context_key'] = 'Trainer-Kursbereich';
$string['teachers_course_role'] = 'Rolle des Trainers in seinem Kursbereich, normalerweise Kursverwalter/in';
$string['teachers_course_role_key'] = 'Trainerrolle';
$string['teachers_editingteacher_role'] = 'Rolle des Trainers in den Kursen seines Kursbereichs, normalerweise Kategorielehrer/in';
$string['teachers_editingteacher_role_key'] = 'Trainer-Kursrolle';
$string['teacher_settings'] = 'Trainergruppen-Einstellungen';
$string['teachers_group_name'] = 'Name der Trainergruppe, normalerweise TEACHERS';
$string['teachers_group_name_key'] = 'Trainergruppe';
$string['teachers_ignore'] = 'Ausnahmen: Benutzer, die Trainer sind, für die aber trotz Einstellung weiter oben keine Kursbereiche automatisch angelegt oder entfernt werden sollen.';
$string['teachers_ignore_key'] = 'ignorierte Trainer';
$string['teachers_removed'] = 'Dieser Kursbereich nimmt die automatisch entfernten Kursbereiche auf, normalerweise attic';
$string['teachers_removed_key'] = 'Kursbereich entfernter Lehrer';
$string['teachers_role'] = 'Rolle der Trainer in Trainergruppen, normalerweise Teilnehmer/in';
$string['teachers_role_key'] = 'Trainerrolle im Kurs';
$string['userrole'] = 'Benutzerrolle';
$string['userrolelabel'] = 'Benutzerrolle ist {$a->value}';
$string['userrole_inverted'] = 'invertiert';
$string['userrole_inverted_label'] = 'nicht ';
$string['eventcohort_created'] = 'Globale Gruppe erstellt';
$string['eventcohort_removed'] = 'Globale Gruppe entfernt';
$string['eventcohort_enroled'] = 'Globale Gruppe in Kurs eingeschrieben';
$string['eventcohort_unenroled'] = 'Einschreibung für globale Gruppe im Kurs beendet';
$string['eventcohort_member_added'] = 'Mitglied zur globalen Gruppe hinzugefügt';
$string['eventcohort_member_removed'] = 'Mitglied aus der globalen Gruppe entfernt';
$string['eventcohort_members_removed'] = 'Mitglieder aus der globalen Gruppe entfernt';
$string['eventteacher_role_assigned'] = 'Lehrerrolle im Kurs zugewiesen';
$string['eventteacher_role_unassigned'] = 'Lehrerrolle im Kurs entfernt';
$string['eventteacher_category_created'] = 'Lehrerkursbereich erstellt';
$string['eventteacher_category_removed'] = 'Lehrerkursbereich entfernt';
$string['eventteacher_category_moved'] = 'Lehrerkursbereich verschoben';
$string['eventteachers_category_created'] = 'Kursbereich für Lehrer erstellt';
$string['eventattic_category_created'] = 'Attic-Kursbereich erstellt';
$string['member_attribute_isdn'] = 'Das Attribut enthält eine komplette LDAP DN';
$string['member_attribute_isdn_key'] = 'Attribut ist eine DN';
