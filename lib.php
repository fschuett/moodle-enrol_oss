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
 * Open LML enrolment plugin implementation.
 *
 * This plugin synchronises enrolment and roles with a Open LML server.
 *
 * @package    enrol
 * @subpackage openlml
 * @author     Frank Schütte based on code by Iñaki Arenaza
 * @copyright  1999 onwards Martin Dougiamas {@link http://moodle.com}
 * @copyright  2010 Iñaki Arenaza <iarenaza@eps.mondragon.edu>
 * @copyright  2013 Frank Schütte <fschuett@gymnasium-himmelsthuer.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class enrol_openlml_plugin extends enrol_plugin {
    protected $enroltype = 'enrol_openlml';
    protected $errorlogtag = '[ENROL OPENLML] ';
    protected $teacher_array=Array();
    protected $authldap;
    protected $teacher_obj;
    protected $attic_obj;

    /**
     * constructor since php5
     */
    public function __construct() {
        global $CFG;
        require_once($CFG->libdir . '/accesslib.php');
        require_once($CFG->libdir . '/ldaplib.php');
        require_once($CFG->libdir . '/moodlelib.php');
        require_once($CFG->libdir . '/enrollib.php');
        require_once($CFG->libdir . '/dml/moodle_database.php');
        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->dirroot . '/cohort/lib.php');
        require_once($CFG->dirroot . '/auth/ldap/auth.php');
        require_once($CFG->dirroot . '/course/lib.php');
        
        $this->load_config();
        // Make sure we get sane defaults for critical values.
        $this->config->ldapencoding = $this->get_config('ldapencoding', 'utf-8');
        $this->config->user_type = $this->get_config('user_type', 'default');

        $ldap_usertypes = ldap_supported_usertypes();
        $this->config->user_type_name = $ldap_usertypes[$this->config->user_type];
        unset($ldap_usertypes);

        $default = ldap_getdefaults();
        // Use defaults if values not given. Dont use this->get_config()
        // here to be able to check for 0 and false values too.
        foreach ($default as $key => $value) {
            // Watch out - 0, false are correct values too, so we can't use $this->get_config().
            if (!isset($this->config->{$key}) or $this->config->{$key} == '') {
                $this->config->{$key} = $value[$this->config->user_type];
            }
        }
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param object $instance
     * @return bool
     */
    public function instance_deleteable($instance) {
        if (!enrol_is_enabled('openlml')) {
            return true;
        }

        return false;
    }


    /**
     * Forces synchronisation of user enrolments with Open LML server.
     * It creates cohorts, removes cohorts and adds/removes course categories.
     *
     * @uses DB,CFG
     * @param object $user user record
     * @return void
     */
    public function sync_user_enrolments($user) {
        global $DB,$CFG;

        trigger_error($this->errorlogtag . 'sync_user_enrolments called', E_USER_NOTICE);
        // Correct the cohort subscriptions.
        $ldap_groups = $this->ldap_get_grouplist($user->idnumber);
        trigger_error($this->errorlogtag . 'user:' . $user->idnumber . ' ldap_groups:' . $ldap_groups, E_USER_NOTICE);
        $cohorts = $this->get_cohortlist($user->idnumber);
        trigger_error($this->errorlogtag . 'user:' . $user->idnumber . ' cohorts:' . $cohorts, E_USER_NOTICE);
        foreach ($ldap_groups as $group => $groupname) {
            if (!isset($cohorts[$groupname])) {
                $cohortid = $this->get_cohort_id($groupname);
                cohort_add_member($cohortid, $user->id);
                trigger_error($this->errorlogtag . 'add ' . $user->username . ' to cohort ' . $groupname, E_USER_NOTICE);
            }
        }

        foreach ($cohorts as $cohort) {
            if (!in_array($cohort->idnumber, $ldap_groups)) {
                cohort_remove_member($cohort->id, $user->id);
                trigger_error($this->errorlogtag . 'remove ' . $user->username . ' from cohort ' . $cohort->name, E_USER_NOTICE);
                if (!$DB->record_exists('cohort_members', array('cohortid'=>$cohort->id))) {
                    cohort_delete_cohort($cohortid);
                    trigger_error($this->errorlogtag . 'remove empty cohort ' . $cohort->name, E_USER_NOTICE);
                }
            }
        }

        // Autocreate/autoremove teacher category.
        if ($this->config->teachers_category_autocreate OR $this->config->teachers_category_autoremove) {
            trigger_error($this->errorlogtag . 'autocreate/autoremove teacher category for teacher ' . $user->username, E_USER_NOTICE);
            if (!isset($this->teacher_obj)) {
                $this->teacher_obj = $this->get_teacher_category();
            }
            if (!isset($this->attic_obj)) {
                $this->attic_obj = $this->get_teacher_attic_category();
            }
            if (empty($this->teacher_array)) {
                $this->init_teacher_array();
            }

            $edited = false;
            trigger_error($this->errorlogtag . 'Testing for autoremove. ', E_USER_NOTICE);
            if ($this->config->teachers_category_autoremove AND
                  (!$this->is_teacher($user->idnumber) OR $this->is_ignored_teacher($user->idnumber))) {
                if ($category = $DB->get_record('course_categories', array('name'=>$user->idnumber,
                        'parent'=>$this->teacher_obj->id),'*',IGNORE_MULTIPLE)) {
                    if ($DB->count_records('course_categories', array('name'=>$user->idnumber,
                	    'parent'=>$this->teacher_obj->id)) > 1) {
                	trigger_error($this->errorlogtag . ' There are more than one matching category named '.
                		$user->idnumber .' in '.$this->teacher_obj->name .". That is likely to cause problems.",E_USER_WARNING);
            	    }
                    if (!$cat->delete_move($this->attic_obj)) {
                        debugging($this->errorlogtag . 'could not move teacher category for user ' . $cat->name . ' to attic.');
                    }
                    trigger_error($this->errorlogtag . 'removed category of removed teacher ' . $cat->name, E_USER_NOTICE);
                    $edited = true;
                }
            }
            trigger_error($this->errorlogtag . 'Testing for autocreate. ', E_USER_NOTICE);
            if ($this->config->teachers_category_autocreate AND
                $this->is_teacher($user->idnumber) AND !$this->is_ignored_teacher($user->idnumber)) {
                trigger_error($this->errorlogtag . 'The teacher ' . $user->username . ' needs a course category.', E_USER_NOTICE);
                if (!$DB->get_record('course_categories', array('name'=>$user->idnumber,
                        'parent'=> $this->teacher_obj->id),'*',IGNORE_MULTIPLE)) {
                    trigger_error($this->errorlogtag . 'The teacher ' . $user->username . ' has no course category.', E_USER_NOTICE);
                    if (!$this->teacher_add_category($user)) {
                        debugging($this->errorlogtag . 'autocreate teacher category failed: ' . $user->username);
                    } else {
                        trigger_error($this->errorlogtag . 'autocreate course category for '. $user->username, E_USER_NOTICE);
                        $edited = true;
                    }
                } else if ($DB->count_records('course_categories', array('name'=>$user->idnumber,
            		'parent'=>$this->teacher_obj->id)) > 1) {
            	    debugging($this->errorlogtag . ' WARNING: there are more than one matching category named '.
        		    $user->idnumber .' in '.$this->teacher_obj->name .". That is likely to cause problems.");
            	}
            }
            trigger_error($this->errorlogtag . 'Resorting is necessary: ' . $edited, E_USER_NOTICE);
            if ($edited) {
                $this->resort_categories($this->teacher_obj->id);
            }
        }

        trigger_error($this->errorlogtag . 'sync_user_enrolments returns', E_USER_NOTICE);
        return true;
    }

    /**
     * Does synchronisation of user subscription to cohorts and
     * autocreate/autoremove of teacher course categories based on
     * the settings and the contents of the Open LML server.
     * @return boolean
     * @uses DB,CFG
     */
    public function sync_enrolments() {
        global $CFG, $DB;
        require_once($CFG->libdir . '/coursecatlib.php');
        
        trigger_error($this->errorlogtag . 'sync_enrolments called', E_USER_NOTICE);

        $ldap_groups = $this->ldap_get_grouplist();

        foreach ($ldap_groups as $group => $groupname) {
            trigger_error($this->errorlogtag . '  sync group:' . $groupname, E_USER_NOTICE);
            $cohortid = $this->get_cohort_id($groupname);
            trigger_error($this->errorlogtag . $cohortid, E_USER_NOTICE);
            $ldap_members = $this->ldap_get_group_members($groupname, $this->has_teachers_as_members($groupname));
            $cohort_members = $this->get_cohort_members($cohortid);

            foreach ($cohort_members as $userid => $user) {
                if (!isset ($ldap_members[$userid])) {
                    cohort_remove_member($cohortid, $userid);
                    trigger_error($this->errorlogtag . 'remove ' . $user->username . ' from cohort ' . $groupname, E_USER_NOTICE);
                }
            }

            foreach ($ldap_members as $userid => $username) {
                if (!$this->cohort_is_member($cohortid, $userid)) {
                    cohort_add_member($cohortid, $userid);
                    trigger_error($this->errorlogtag . 'add ' . $username . ' to cohorte ' . $groupname, E_USER_NOTICE);
                }
            }
        }

        // Remove unneeded cohorts.
        $toremove = array();
        $cohorts = $this->get_cohortlist();
        trigger_error($this->errorlogtag . 'cohorts list:' . $cohorts, E_USER_NOTICE);
        foreach ($cohorts as $cohort) {
            if (!in_array($cohort->idnumber, $ldap_groups)) {
                $toremove[] = $cohort->id;
            }
        }
        trigger_error($this->errorlogtag . 'remove cohorts list:' . $toremove, E_USER_NOTICE);
        if (!empty($toremove)) {
            $DB->delete_records_list('cohort_members', 'cohortid', $toremove);
            $DB->delete_records_list('cohort', 'id', $toremove);
        }

        if ($this->config->teachers_category_autocreate OR $this->config->teachers_category_autoremove) {
            trigger_error($this->errorlogtag . '== syncing teacher categories', E_USER_NOTICE);
            if (!isset($this->teacher_obj)) {
                $this->teacher_obj = $this->get_teacher_category();
            }
            if (!isset($this->attic_obj)) {
                $this->attic_obj = $this->get_teacher_attic_category();
            }
            if (empty($this->teacher_array)) {
                $this->init_teacher_array();
            }
        }

        $edited = false;
        // Autoremove teacher course categories of removed teachers if requested.
        if ($this->config->teachers_category_autoremove) {
            $teachercontext = coursecat::get($this->teacher_obj->id);
            if (empty($teachercontext)) {
	        debugging($this->errorlogtag . 'Could not get teacher context');
	        return false;
            }
            if ($categories = $teachercontext->get_children()) {
                foreach ($categories as $cat) {
                    if (!$this->is_teacher($cat->name) OR $this->is_ignored_teacher($cat->name)) {
                        if (!$cat->delete_move($this->attic_obj)) {
                            debugging($this->errorlogtag . 'could not move teacher category for user ' . $cat->name . ' to attic.');
                        }
                        trigger_error($this->errorlogtag . 'removed category of removed teacher ' . $cat->name, E_USER_NOTICE);
                        $edited = true;
                    }
                }
            }
        }

        // Autocreate teacher course categories for new teachers if requrested.
        if ($this->config->teachers_category_autocreate) {
            foreach ($this->teacher_array as $teacher) {
                if (empty($teacher) OR $this->is_ignored_teacher($teacher)) {
                    trigger_error($this->errorlogtag . 'teacher ' . $teacher . ' will be ignored.', E_USER_NOTICE);
                    continue;
                }
                $user = $DB->get_record('user', array('username'=>$teacher, 'auth' => 'ldap'));
                $cat_obj = $DB->get_record('course_categories',
                        array('name'=>$teacher, 'parent' => $this->teacher_obj->id),'*',IGNORE_MULTIPLE);

                // Autocreate/move teacher category.
                if (empty($cat_obj)) {
                    if (!$this->teacher_add_category($user)) {
                        debugging($this->errorlogtag . 'autocreate teacher category failed: ' . $teacher);
                        continue;
                    }
                    trigger_error($this->errorlogtag . 'autocreate course category for '. $teacher, E_USER_NOTICE);
                    $edited = true;
                } else if ($DB->count_records('course_categories',
                        array('name'=>$teacher, 'parent' => $this->teacher_obj->id)) > 1) {
            	    debugging($this->errorlogtag . ' WARNING: there are more than one matching category named '.
        		    $teacher .' in '.$this->teacher_obj->name .". That is likely to cause problems.");

                }
            }
        }
        if ($edited) {
            $this->resort_categories($this->teacher_obj->id);
        }

        $this->sync_cohort_enrolments();

        if (!empty($CFG->defaultcity)) {
            $this->update_city();
        }
        return true;
    }

    /**
     * This function checks all users created by auth ldap and updates the city field.
     * The config value $CFG->defaultcity must be non empty.
     * @uses DB,CFG
     * @returns void
     */
    public function update_city(&$user = NULL) {
        global $DB,$CFG;

        trigger_error($this->errorlogtag . 'update_city(' . $user . ') called.', E_USER_NOTICE);

        if (empty($user)) {
            $params = array('auth' => 'ldap', 'city' => '');
            if (!$DB->set_field('user', 'city', $CFG->defaultcity, $params)) {
                debugging($this->errorlogtag . "update of city field for many users failed.");
            }
            trigger_error($this->errorlogtag . ' updated city field with ' . $CFG->defaultcity .
                        " for many users.", E_USER_NOTICE);
        } else {
            if ($user->city == '') {
                if (!$DB->set_field('user', 'city', $CFG->defaultcity, array('id' => $user->id))) {
                    debugging($this->errorlogtag . 'update of city field for user ' . $user->username .
                            " failed.");
                }
                trigger_error($this->errorlogtag . 'updated city field for user ' . $user->username, E_USER_NOTICE);
            }
        }
        trigger_error($this->errorlogtag . 'update_city(' . $user . ') returns.', E_USER_NOTICE);
    }

    /**
     * This function checks all courses and enrols cohorts that are listed in the course id number.
     * The idnumber should contain shortname ':' class1,class2,... because the idnumber is a
     * unique key.
     * @uses DB
     * @returns void
     */
    public function sync_cohort_enrolments() {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/enrol/cohort/locallib.php');
        $edited = false;

        trigger_error($this->errorlogtag . 'sync_cohort_enrolments called', E_USER_NOTICE);
        $enrol = enrol_get_plugin('cohort');
        $courses = $DB->get_recordset_select('course', "idnumber != ''");
        foreach ($courses as $course) {
            trigger_error($this->errorlogtag . 'course shortname(' . $course->shortname .
                        ') idnumber('. $course->idnumber . ")", E_USER_NOTICE);
            if ((strpos($course->idnumber, $course->shortname . ':')) === 0) {
                $groups = explode(',', substr($course->idnumber, strlen($course->shortname . ':')));
            }
            else if (($pos=strpos($course->idnumber, ':')) !== false) {
                $groups = explode(',', substr($course->idnumber, $pos+1));
                $DB->set_field('course', 'idnumber',
                        $course->shortname . ':' . implode($groups), array('id'=>$course->id));
            }
            else {
                $DB->set_field('course', 'idnumber',
                        $course->shortname . ':' . $course->idnumber, array('id'=>$course->id));
                $groups = explode(',', $course->idnumber);
            }
            trigger_error($this->errorlogtag . 'groups ' . $group, E_USER_NOTICE);
            $cohorts = $this->get_cohortinstancelist($course->id);
            trigger_error($this->errorlogtag . 'enrol plugin instances ' . $cohorts, E_USER_NOTICE);
            foreach ($groups as $group) {
                trigger_error($this->errorlogtag . ' is group ' . $group . ' enroled?', E_USER_NOTICE);
                if (!isset($cohorts[$group]) AND $cohortid=$this->get_cohort_id($group, false)) {
                    if ($this->has_teachers_as_members($group)) {
                        $enrol->add_instance($course,
                                array('customint1' => $cohortid, 'roleid' => $this->config->teachers_role));
                    } else {
                        $enrol->add_instance($course,
                                array('customint1' => $cohortid, 'roleid' => $this->config->student_role));
                    }
                    trigger_error($this->errorlogtag . 'add cohort ' . $group . ' to course ' . $course->name, E_USER_NOTICE);
                    $edited = true;
                }
            }

            foreach ($cohorts as $cohort) {
                trigger_error($this->errorlogtag . ' is cohort ' . $cohort->idnumber . ' still necessary?', E_USER_NOTICE);
                if (!in_array($cohort->idnumber, $groups)) {
                    $instances = enrol_get_instances($course->id, false);
                    trigger_error($this->errorlogtag . 'enrolment instances ' . $instances, E_USER_NOTICE);
                    foreach ($instances as $instance) {
                        if ($instance->enrol == 'cohort' AND $instance->customint1 == $cohort->id) {
                            trigger_error($this->errorlogtag . 'remove cohort ' . $cohort->idnumber . ' from course ' . $course->shortname, E_USER_NOTICE);
                            $plugin = enrol_get_plugin($instance->enrol);
                            $plugin->delete_instance($instance);
                            break;
                        }
                    }
                    $edited = true;
                }
            }
        }
        $courses->close();
        if ($edited) {
            $trace = new null_progress_trace();
	    trigger_error($this->errorlogtag . 'call to enrol_cohort_sync...', E_USER_NOTICE);
            enrol_cohort_sync($trace);
        }
    }

    public function enrol_openlml_sync() {
        $this->sync_enrolments();
    }

    /**
     * Called from all enabled enrol plugins that returned true from is_cron_required
     * @return void
     */
    public function cron() {
        global $CFG;

        // The enrolment depends on user synchronization via auth_ldap.
        if (!is_enabled_auth('ldap')) {
            debugging('[AUTH LDAP] ' . get_string('pluginnotenabled', 'auth_ldap'));
            die;
        }

        if (!enrol_is_enabled('cohort')) {
            debugging('[ENROL COHORT]'.get_string('pluginnotenabled', 'enrol_cohort'));
            die;
        }

        if (!enrol_is_enabled('openlml')) {
            debugging('[ENROL OPENLML] '.get_string('pluginnotenabled', 'enrol_openlml'));
            die;
        }

        mtrace("Starting enrolments for openlml enrolments plugin...");
        $this->enrol_openlml_sync();
        mtrace("finished.");
    }

    /**
     * return all groups from LDAP which match search criteria defined in settings
     * @return string[]
     */
    private function ldap_get_grouplist($userid = "*") {
        global $CFG, $DB;
        trigger_error($this->errorlogtag . 'ldap_get_grouplist called', E_USER_NOTICE);
        if (!isset($authldap) or empty($authldap)) {
            $authldap = get_auth_plugin('ldap');
            trigger_error($this->errorlogtag . "auth plugin loaded", E_USER_NOTICE);
        }
        $ldapconnection = $authldap->ldap_connect();

        $fresult = array ();
        if ($userid !== "*") {
            $filter = '(' . $this->config->member_attribute . '=' . $userid . ')';
        } else {
            $filter = '';
        }
        $filter = '(&' . $this->ldap_generate_group_pattern() . $filter . '(objectclass=' . $this->config->object . '))';
        trigger_error($this->errorlogtag . 'filter defined:' . $filter, E_USER_NOTICE);
        $contexts = explode(';', $this->config->contexts);
        trigger_error($this->errorlogtag . 'contexts settings(' . $this->config->contexts .
                    ') contexts array(' . $contexts . ')', E_USER_NOTICE);
        foreach ($contexts as $context) {
            $context = trim($context);
            if (empty ($context)) {
                continue;
            }

            if ($authldap->config->search_sub) {
                // Use ldap_search to find first group from subtree.
                $ldap_result = ldap_search($ldapconnection, $context, $filter, array (
                    $this->config->attribute
                ));
            } else {
                // Search only in this context.
                $ldap_result = ldap_list($ldapconnection, $context, $filter, array (
                    $this->config->attribute
                ));
            }

            $groups = ldap_get_entries($ldapconnection, $ldap_result);

            // Add found groups to list.
            for ($i = 0; $i < count($groups) - 1; $i++) {
                array_push($fresult, ($groups[$i][$this->config->attribute][0]));
            }
        }
        $authldap->ldap_close();
        // Remove teachers from all but teachers groups.
        if ($userid != "*" AND $this->is_teacher($userid)) {
            foreach ($fresult as $i => $group) {
                if (!$this->has_teachers_as_members($group)) {
                    unset($fresult[$i]);
                }
            }
        }
        trigger_error($this->errorlogtag . 'found ldap groups:' . implode(', ', $fresult), E_USER_NOTICE);
        return $fresult;
    }

    /**
     * search for group members on a Open LML server with defined search criteria
     * @return string[] array of usernames
     */
    private function ldap_get_group_members($group, $teachers_ok = false) {
        global $CFG, $DB;

        trigger_error($this->errorlogtag . 'ldap_get_group_members called', E_USER_NOTICE);
        $ret = array ();
        $members = array ();
        if (!isset($authldap) or empty($authldap)) {
            $authldap = get_auth_plugin('ldap');
            trigger_error($this->errorlogtag . "auth plugin loaded", E_USER_NOTICE);
        }
        $ldapconnection = $authldap->ldap_connect();

        $group = textlib::convert($group, 'utf-8', $this->config->ldapencoding);

        trigger_error($this->errorlogtag . 'ldap connection:' . $ldapconnection, E_USER_NOTICE);
        if (!$ldapconnection) {
            return $ret;
        }
        $queryg = "(&(cn=" . trim($group) . ")(objectClass=" . $this->config->object . "))";
        trigger_error($this->errorlogtag . "query: " . $queryg, E_USER_NOTICE);
        $contexts = explode(';', $this->config->contexts);

        foreach ($contexts as $context) {
            $context = trim($context);
            if (empty ($context)) {
                continue;
            }

            $resultg = ldap_search($ldapconnection, $context, $queryg);

            if (!empty ($resultg) AND ldap_count_entries($ldapconnection, $resultg)) {
                $group = ldap_get_entries($ldapconnection, $resultg);

                for ($g = 0; $g < (count($group[0][$this->config->member_attribute]) - 1); $g++) {
                    $member = trim($group[0][$this->config->member_attribute][$g]);
                    if ($member != "" AND ($teachers_ok OR !$this->is_teacher($member))) {
                        $members[] = $member;
                    }
                }
            }
        }
        trigger_error($this->errorlogtag . "ldap_get_group_members returns " . $members, E_USER_NOTICE);
        $authldap->ldap_close();
        foreach ($members as $member) {
            $params = array (
                'username' => $member
            );
            if ($user = $DB->get_record('user', $params, 'id, username')) {
                $ret[$user->id] = $user->username;
            }
        }
        return $ret;
    }

    private function get_cohort_id($groupname, $autocreate = true) {
        global $DB;
        $params = array (
            'idnumber' => $groupname,
            'component' => 'enrol_openlml'
        );
        if (!$cohort = $DB->get_record('cohort', $params, '*')) {
            if (!$autocreate) {
                return false;
            }
            $cohort = new StdClass();
            $cohort->name = $cohort->idnumber = $groupname;
            $cohort->contextid = get_system_context()->id;
            $cohort->component='enrol_openlml';
            $cohort->description=get_string('sync_description', 'enrol_openlml');
            $cohortid = cohort_add_cohort($cohort);
            trigger_error($this->errorlogtag . 'cohort added:' . $cohort->name, E_USER_NOTICE);
        } else {
            $cohortid = $cohort->id;
        }
        return $cohortid;
    }

    private function get_cohortlist($userid = "*") {
        global $DB;
        if ($userid != "*") {
            $sql = " SELECT DISTINCT c.id, c.idnumber, c.name
                    FROM {cohort} c
                    JOIN {cohort_members} cm ON cm.cohortid = c.id
                    JOIN {user} u ON cm.userid = u.id
                            WHERE (c.component = 'enrol_openlml' AND u.idnumber = :userid)";
            $params['userid'] = $userid;
            $records = $DB->get_records_sql($sql, $params);
        } else {
            $sql = " SELECT DISTINCT c.id, c.idnumber, c.name
                    FROM {cohort} c
                            WHERE c.component = 'enrol_openlml'";
            $records = $DB->get_records_sql($sql);
        }
        trigger_error($this->errorlogtag . 'records for cohortlist:' . $records, E_USER_NOTICE);
        $ret = array();
        foreach ($records as $record) {
            $ret[$record->idnumber] = $record;
        }
        return $ret;
    }

    private function get_cohortinstancelist($courseid) {
        global $DB;
        $cohorts = enrol_get_instances($courseid, true);
        $ret = array();
        foreach (array_keys($cohorts) as $key) {
            if ($cohorts[$key]->enrol != 'cohort' OR !isset($cohorts[$key]->customint1)) {
                unset($cohorts[$key]);
            } else if ($record = $DB->get_record('cohort', array('id' => $cohorts[$key]->customint1))) {
                $ret[$record->idnumber] = $record;
            }
        }
        return $ret;
    }

    private function get_cohort_members($cohortid) {
        global $DB;
        $sql = " SELECT u.id, u.username
                          FROM {user} u
                         JOIN {cohort_members} cm ON (cm.userid = u.id AND cm.cohortid = :cohortid)
                        WHERE u.deleted=0";
        $params['cohortid'] = $cohortid;
        return $DB->get_records_sql($sql, $params);
    }

    private function cohort_is_member($cohortid, $userid) {
        global $DB;
        $params = array (
            'cohortid' => $cohortid,
            'userid' => $userid
        );
        return $DB->record_exists('cohort_members', $params);
    }

    private function has_teachers_as_members($group) {
        global $CFG;
        if (!empty($this->config->prefix_teacher_members)) {
            $ar = explode(',', $this->config->prefix_teacher_members);
            foreach ($ar as $prefix) {
                if (strpos($group, $prefix) === 0) {
                    return true;
                }
            }
        }
        if ($group == $this->config->teachers_group_name) {
            return true;
        }
        return false;
    }

    /**
     * generate a search pattern including teachers group, teacher member groups, all classes and projects
     * @return string
     */
    private function ldap_generate_group_pattern() {
        // Create the search pattern to search all classes and courses in LDAP.
        global $CFG;

        trigger_error($this->errorlogtag . ' generate_class_pattern called', E_USER_NOTICE);
        $pattern[] = '(' . $this->config->attribute . '=' . $this->config->teachers_group_name .')';
        if (!empty($this->config->prefix_teacher_members)) {
            $classes = explode(',', $this->config->prefix_teacher_members);
            foreach ($classes as $c) {
                $pattern[] = '(' . $this->config->attribute . '=' . $c . '*)';
            }
        }
        $classes = explode(',', $this->config->student_class_numbers);
        foreach ($classes as $c) {
            $pattern[] = '(' . $this->config->attribute . '=' . $c . '*)';
        }
        if (!empty($this->config->student_groups)) {
            $classes = explode(',', $this->config->student_groups);
            foreach ($classes as $c) {
                $pattern[] = '(' . $this->config->attribute . '=' . $c . '*)';
            }
        }
        $pattern[] = '(' . $this->config->attribute . '='. $this->config->student_project_prefix . '*)';
        trigger_error($this->errorlogtag . 'generated_class_pattern:' . $pattern, E_USER_NOTICE);
        $pattern = '(|' . implode($pattern) . ')';
        trigger_error($this->errorlogtag . 'generated_class_pattern:' . $pattern, E_USER_NOTICE);
        return $pattern;
    }

    /**
     * This function finds the user in the teacher array.
     *
     * @uses $CFG
     */
    private function is_teacher($userid) {
        if (empty($this->teacher_array)) {
            $this->init_teacher_array();
        }
        return in_array($userid, $this->teacher_array);
    }

    /**
     * This function inits the teacher_array once.
     *
     * @uses $CFG
     */
    private function init_teacher_array() {
        global $CFG;
        $this->teacher_array = $this->ldap_get_group_members($this->config->teachers_group_name, true);
        if (empty($this->teacher_array)) {
            return false;
        }
        return true;
    }

    /**
     * This function checks and creates the teacher category.
     *
     * @uses $CFG,$DB
     */
    private function get_teacher_category() {
        global $CFG, $DB;
        // Create teacher category if needed.
        $cat_obj = $DB->get_record( 'course_categories', array('name'=>$this->config->teachers_course_context, 'parent' => 0),'*',IGNORE_MULTIPLE);
        if (!$cat_obj) { // Category doesn't exist.
            trigger_error($this->errorlogtag . 'creating non-existing teachers course category ' .
                        $this->config->teachers_course_context, E_USER_NOTICE);
            $cat_obj = $this->create_category($this->config->teachers_course_context,
                    get_string('teacher_context_desc', 'enrol_openlml'));
            if (!$cat_obj) {
                debugging($this->errorlogtag . 'autocreate/autoremove could not create teacher course context');
            }
        }
        return $cat_obj;
    }

    /**
     * This function checks and creates the teacher attic category.
     *
     * @uses $CFG,$DB
     */
    private function get_teacher_attic_category() {
        global $CFG, $DB;
        $this->attic_obj = $DB->get_record( 'course_categories', array('name'=>$this->config->teachers_removed, 'parent' => 0),'*',IGNORE_MULTIPLE);
        if (!$this->attic_obj) { // Category for removed teachers doesn't exist.
            $this->attic_obj = $this->create_category($this->config->teachers_removed,
                    get_string('attic_description', 'enrol_openlml'),0,99999,0);
            if (!$this->attic_obj) {
                debugging($this->errorlogtag .'autocreate/autoremove could not create removed teachers context');
            }
        }
        return $this->attic_obj;
    }


    /**
     * This function resorts the teacher categories alphabetically.
     *
     */
    private function resort_categories($id) {
        global $CFG,$DB;
        require_once($CFG->libdir . '/coursecatlib.php');

        $teacher_cat = coursecat::get($id);
        if (empty($teacher_cat)) {
            debugging('Could not get teachers course category.');
            return false;
        }
        if ($categories = $teacher_cat->get_children()) {
            $count=$teacher_cat->sortorder + 1;
            foreach ($categories as $cat) {
                $DB->set_field('course_categories', 'sortorder', $count, array('id' => $cat->id));
                $count++;
            }
        }
        return true;
    }

    /**
     * This function checks if this teacher is to be ignored in autocreate / autoremove.
     *
     * @uses $CFG
     *
     */
    private function is_ignored_teacher(&$name) {
        global $CFG;
        $ignored_teachers = explode(',', $this->config->teachers_ignore);
        if (empty($ignored_teachers)) {
            return false;
        }
        return in_array($name, $ignored_teachers);
    }

    /**
     * This function adds a teacher course category for the teacher user.
     * @return false|category_id
     * @uses $CFG;
     */
    private function teacher_add_category(&$user) {
        global $CFG, $DB;
        require_once($CFG->libdir . '/coursecatlib.php');

        trigger_error($this->errorlogtag . 'Adding teacher category for teacher ' . $user->username, E_USER_NOTICE);
        if (!isset($this->attic_obj)) {
            $this->attic_obj = $this->get_teacher_attic_category();
        }
        if (!isset($this->teacher_obj)) {
            $this->teacher_obj = $this->get_teacher_category();
        }
        $cat_obj = $DB->get_record('course_categories', array('name'=>$user->idnumber, 'parent' => $this->attic_obj->id),
                '*',IGNORE_MULTIPLE);
        if ($cat_obj) {
            if (!$cat_obj->delete_move($this->teacher_obj)) {
                debugging($this->errorlogtag . 'could not move teacher category ' . $cat_obj->name . ' for user ' .
                        $user->idnumber . ' back from attic.');
                return false;
            }
        } else {
            $description = get_string('course_description', 'enrol_openlml') . ' ' .
                    $user->firstname . ' ' .$user->lastname . '(' . $user->idnumber. ').';
            trigger_error($this->errorlogtag . 'Calling create_category for ' . $user->username . ' with description ' . $description, E_USER_NOTICE);
            $cat_obj = $this->create_category($user->username, $description, $this->teacher_obj->id);
            if (!$cat_obj) {
                debugging('Could not create teacher category for teacher ' . $user->username);
                return false;
            }
        }

        // Set teachers role to course creator.
        $teacherscontext = context_coursecat::instance($cat_obj->id);
        if (!role_assign($this->config->teachers_course_role, $user->id, $teacherscontext, 'enrol_openlml')) {
            debugging($this->errorlogtag . 'could not assign role (' . $this->config->teachers_course_role . ') to user (' .
                    $user->idnumber . ') in context (' . $teacherscontext->id . ').');
            return false;
        }
        return true;
    }

    /**
     * This function creates a course category and fixes the category path.
     * name             the new category name
     * description      a descriptive text for the new course category
     * parent           the course_categories parent object or 0 for top level category
     * sortorder        special sort order, 99999 order at end
     * @return          false|category_object
     * @uses            $DB;
     */
    public function create_category ($name, $description, $parent = 0, $sortorder = 0, $visible = 1) {
        global $CFG,$DB;
        require_once($CFG->libdir . '/coursecatlib.php');

        trigger_error("Creating category $name ($description) with parent $parent and sortorder $sortorder",E_USER_NOTICE);
        $data = new stdClass();
        $data->name = $data->idnumber = $name;
        $data->description = $description;
        $data->parent = $parent;
        $data->visible = $visible;
        $cat = coursecat::create($data);
        if (!$cat) {
            debugging('Could not insert the new course category ' . $cat->name);
            return false;
        }
        if ($sortorder != 0) {
            trigger_error('Changing course sortorder to ' . $sortorder,E_USER_NOTICE);
            $DB->set_field('course_categories', 'sortorder', $sortorder, array('id' => $cat->id));
            context_coursecat::instance($cat->id)->mark_dirty();
            fix_course_sortorder();
        }
        return $cat;
    }

    public function test_sync_user_enrolments($userid) {
        global $DB;
        $user = $DB->get_record('user', array('idnumber' => $userid));
        if ($user) {
            $this->sync_user_enrolments($user);
        }
    }

} // End of class.
