moodle-enrol_openlml
====================

enrolment plugin for moodle from 2.x to autoenrol in 
conjunction with the open linux Musterlösungplugin enrol_openlml.

This module superseedes the module enrol_lml(Moodle 1.x).
It is supposed to work with the

Linux Musterlösung(paedML) Ba-Wü Germany 

and with the 

Open Linux Musterlösung(openLML) http://www.linuxmuster.net

servers.

It is crafted for their LDAP structure.

It needs the auth_ldap module working to get the users authenticated
through LDAP and enrols those users to courses depending on the
field $course->idnumber.

Dependencies
------------
This module needs the Auth-Plugin auth_ldap and the enrolment plugin
enrol_cohort to be active to work properly.

Hildesheim, Germany
Frank Schütte,2012(fschuett@gymnasium-himmelsthuer.de)
