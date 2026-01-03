# OSS-Einschreibung
## Allgemeine Beschreibung

Sie können einen OSS-Server nutzen, um die Anmeldung von Teilnehmer/innen in Kursen zu verwalten. Es wird angenommen, dass der OSS-LDAP-Baum Gruppen enthält, die zu Kursen gehören und dass jede/r der Gruppen/Kurse Einträge von Teilnehmer/innen hat. Es wird weiterhin angenommen, dass die Lehrer (Trainer in der Moodle-Sprechweise) in der Gruppe teachers im LDAP-Baum definiert sind und in den Mitgliedsfeldern dieser Gruppe eingetragen sind.(**member** oder **memberUid**), die eine eindeutige Identifizierung des/der Nutzer/in ermöglichen.

Um das OSS-LDAP als Kurs-Anmeldeverfahren zu verwenden, <strong>muss</strong> jeder Nutzer eine gültige ID-Nummer(uid) besitzen. Die LDAP-Gruppen müssen diese ID-Nummer in den Mitgliedsfeldern aufweisen, um den/die Nutzer/in als Teilnehmer/in in den Kurs einzuschreiben. Dies funktioniert normalerweise sehr gut, wenn Sie LDAP auch zur Nutzerauthentifizierung nutzen.

Für die Kursanmeldungen wird die Kurs-ID verwendet. Da sie eindeutig sein muss, wird "Kurzname:" vorangestellt.</p><p>Kursanmeldungen werden aktualisiert, wenn der Nutzer sich in Moodle einloggt. Sie können auch ein Skript nutzen, um Kursanmeldungen zu synchronisieren. Moodle liefert ein solches Skript: <em>enrol/oss/cli/sync.php</em>. Sie können das OSS-Anmeldeverfahren auch so konfigurieren, dass automatisch neue Kurse angelegt werden, wenn neue Gruppen in LDAP eingerichtet werden.


## Einstellungen für Klassen

Diese Einstellungen betreffen einen Kursbereich für Klassen. Klassen sind spezielle Kurse, in denen Lehrer, Schüler und Eltern automatisch mit unterschiedlichen Rollen und in unterschiedlichen Gruppen eingeschrieben werden.

Der Klassenkursbereich kann wie auch die einzelnen Klassen automatisch erstellt werden. Klassen können außerdem automatisch entfernt werden.

Zusätzlich können <b>Gruppen</b> aktiviert werden. Lehrer, Schüler und Eltern werden automatisch in die jeweilige Gruppe eingefügt. Das erleichtert die Kommunikation mit den einzelnen Gruppen. All das passiert während eines standardmäßig einmal am Tag ausgeführten Geplanten Vorgangs namens <b>oss_sync_classes_task</b>.

## Einstellungen für Eltern

Diese Einstellungen stellen eine Verwaltung von Eltern-/Kind-Beziehungen über die Seite <b>Elternverwaltung</b> im Bereich "Nutzerpflege" bereit. Es werden für Schülerkonten des LDAP-Servers automatisch Elternkonten angelegt bzw. auch wieder entfernt. Alternativ können diese Konten auch im Bereich Nutzerverwaltung importiert werden (siehe dazu die Datei <a href="README.md">README.md</a>).

Dort ist auch angegeben, wie die Namen der Elternkonten aussehen müssen (u.a. müssen sie mit dem <b>Eltern-Präfix</b> beginnen und mit einem Text enden, der das zugehörige Kind eindeutig identifiziert).

Zur Verwendung dieser Einstellungen ist es sinnvoll, die Einstellung <b>Nutzerkonten mit gleicher E-Mailadresse erlauben</b> ("allowaccountssameemail").

Es kann entweder der Name des Elternkontos auf das Kind verweisen oder aber im Feld Beschreibung des Elternkontos kann eine Komma separierte Liste der Kinde-IDs gespeichert werden. In letzterem Fall kann es zu einem Kind mehrere Elternkonten und zu einem Elternkonto mehrere Kindkonten geben.
