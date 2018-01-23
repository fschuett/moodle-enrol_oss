moodle-enrol_oss
====================

enrolment plugin for moodle from 3.x to autoenrol in
conjunction with the open school server enrol_oss.

It is supposed to work with the

Open School Server (www.openschoolserver.net)

servers.

It is crafted for their LDAP structure.

It needs the auth_ldap module working to get the users authenticated
through LDAP and enrols those users to courses depending on the
field $course->idnumber.

Dependencies
------------
This module needs the Auth-Plugin auth_ldap and the enrolment plugin
enrol_cohort to be active to work properly.

Cron-Job
--------
This module is processed by cron on an hourly basis, as is enrol_cohort
equally.

The module auth_ldap is not automatically processed by cron.
There is a scheduled task available but it is deactivated by default.

Activate it and schedule it for hourly execution.

Parents
-------
To use this new feature you need to create the parent role as outlined
in https://docs.moodle.org/34/en/Parent_role. The role shortname needs to
match configuration on the settings page.

Changelog
---------
2017-11-28 add classes creation/removing and enrolment
2017-08-19 fixed dn handling, coursecat movements
2016-11-30 added option member_attribute_isdn for dn handling
2016-11-30 initial port from enrol_openlml

Hildesheim, Germany
Frank Schütte,2018(fschuett@gymhim.de)
