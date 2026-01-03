# OSS Enrolment
## Description

This plugin is supposed to be used with german <strong>OSS</strong> school server and is tailored to it\'s LDAP structure.

This plugin enrols users into courses based on the course <strong>idnumber</strong> (note: idnumber is a unique field, so make it unique by prepending course "shortname:")

## Class settings

These settings provide a class category, where classes with specified prefixes are created as moodle courses and teachers, students are enroled with different roles.

Classes can be identified either by class attribute and corresponding value or name prefixes can be used, in which case attribute and attribute value are not used.

The category can be autocreated and also the classes. Classes can be autoremoved. All this is done in a scheduled task named <b>oss_sync_classes_task</b>

## Parents settings

These settings provide parent/child relationships for moodle where students accounts are taken from LDAP and parents accounts are created/deleted on the parent accounts management pages.

The parents usernames need to have a certain structure, namely start with the specified <b>parents prefix</b>  and end with a string that identifies the child in the ldap tree.

For these settings it is advisable to activate "allowaccountssameemail" setting to allow multiple accounts to point to the same email address, because each student is related to one parent account, for siblings multiple parent accounts are created.
