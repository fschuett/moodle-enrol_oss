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
 * @copyright  2012 Frank Schütte <fschuett@gymnasium-himmelsthuer.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class enrol_openlml_plugin extends enrol_plugin {
    // Idnumber: Moodle "Kurs-ID".
    protected $enrol_localcoursefield = 'idnumber';
    protected $enroltype = 'enrol_openlml';
    protected $errorlogtag = '[ENROL OPENLML] ';
    protected $teacher_array=Array();
    public $verbose = false;
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
        require_once($CFG->libdir . '/dml/moodle_database.php');
        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->dirroot . '/cohort/lib.php');
        require_once($CFG->dirroot . '/auth/ldap/auth.php');

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
     * @param object $user user record
     * @return void
     */
    public function sync_user_enrolments($user) {
        global $DB;

        if ($this->verbose) {
            mtrace($this->errorlogtag . 'sync_user_enrolments called');
        }
        // Correct the cohort subscriptions.
        $ldap_groups = $this->ldap_get_grouplist($user->idnumber);
        if ($this->verbose) {
            mtrace($this->errorlogtag . 'user:' . $user->idnumber . ' ldap_groups:' . print($ldap_groups));
        }
        $cohorts = $this->get_cohortlist($user->idnumber);
        if ($this->verbose) {
            mtrace($this->errorlogtag . 'user:' . $user->idnumber . ' cohorts:' . print($cohorts));
        }
        foreach ($ldap_groups as $group => $groupname) {
            if (!isset($cohorts[$groupname])) {
                $cohortid = $this->get_cohort_id($groupname);
                cohort_add_member($cohortid, $user->id);
                if ($this->verbose) {
                    mtrace($this->errorlogtag . 'add ' . $user->username . ' to cohort ' . $groupname);
                }
            }
        }

        foreach ($cohorts as $cohort) {
            if (!in_array($cohort->idnumber, $ldap_groups)) {
                cohort_remove_member($cohort->id, $user->id);
                if ($this->verbose) {
                    mtrace($this->errorlogtag . 'remove ' . $user->username . ' from cohort ' . $cohort->name);
                }
                if (!$DB->record_exists('cohort_members', array('cohortid'=>$cohort->id))) {
                    cohort_delete_cohort($cohortid);
                    if ($this->verbose) {
                        mtrace($this->errorlogtag . 'remove empty cohort ' . $cohort->name);
                    }
                }
            }
        }

        // Autocreate/autoremove teacher category.
        if ($this->config->teachers_category_autocreate OR $this->config->teachers_category_autoremove) {
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
            if ($this->config->teachers_category_autoremove AND
                  (!$this->is_teacher($user->idnumber) OR $this->is_ignored_teacher($user->idnumber))) {
                if ($category = $DB->get_record('course_categories', array('name'=>$user->idnumber,
                        'parent'=>$this->teacher_obj->id))) {
                    if (!move_category($cat, $this->attic_obj)) {
                        print($this->errorlogtag . 'could not move teacher category for user ' . $cat->name . ' to attic.');
                    } else if ($this->verbose) {
                        mtrace($this->errorlogtag . 'removed category of removed teacher ' . $cat->name);
                    }
                    $edited = true;
                }
            }
            if ($this->config->teachers_category_autocreate AND
                $this->is_teacher($user->idnumber) AND !$this->is_ignored_teacher($user->idnumber)) {
                if (!$DB->get_record('course_categories', array('idnumber'=>$user->idnumber,
                        'parent'=> $this->teacher_obj->id))) {
                    if (!$this->teacher_add_category($user)) {
                        print($this->errorlogtag . 'autocreate teacher category failed: ' . $user->username);
                    } else {
                        if ($this->verbose) {
                            mtrace($this->errorlogtag . 'autocreate course category for '. $user->username);
                        }
                        $edited = true;
                    }
                }
            }
            if ($edited) {
                $this->resort_categories($this->teacher_obj->id);
            }
        }
        if ($this->verbose) {
            mtrace($this->errorlogtag . 'sync_user_enrolments returns');
        }
        return true;
    }

    /**
     * Does synchronisation of user subscription to cohorts and
     * autocreate/autoremove of teacher course categories based on
     * the settings and the contents of the Open LML server.
     * @return boolean
     */
    public function sync_enrolments() {
        global $CFG, $DB;
        if ($this->verbose) {
            mtrace($this->errorlogtag . 'sync_enrolments called');
        }

        $ldap_groups = $this->ldap_get_grouplist();

        foreach ($ldap_groups as $group => $groupname) {
            if ($this->verbose) {
                mtrace($this->errorlogtag . '  sync group:' . $groupname);
            }
            $cohortid = $this->get_cohort_id($groupname);
            if ($this->verbose) {
                mtrace($this->errorlogtag . $cohortid . ' ');
            }
            $ldap_members = $this->ldap_get_group_members($groupname, $this->has_teachers_as_members($groupname));
            $cohort_members = $this->get_cohort_members($cohortid);

            foreach ($cohort_members as $userid => $user) {
                if (!isset ($ldap_members[$userid])) {
                    cohort_remove_member($cohortid, $userid);
                    if ($this->verbose) {
                        mtrace($this->errorlogtag . 'remove ' . $user->username . ' from cohort ' . $groupname);
                    }
                }
            }

            foreach ($ldap_members as $userid => $username) {
                if (!$this->cohort_is_member($cohortid, $userid)) {
                    cohort_add_member($cohortid, $userid);
                    if ($this->verbose) {
                        mtrace($this->errorlogtag . 'add ' . $username . ' to cohorte ' . $groupname);
                    }
                }
            }
        }

        // Remove empty cohorts.
        $this->cohort_remove_empty_cohorts();

        if ($this->config->teachers_category_autocreate OR $this->config->teachers_category_autoremove) {
            if ($this->verbose) {
                mtrace($this->errorlogtag . '== syncing teacher categories');
            }
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
            if ($categories = get_categories($this->teacher_obj->id, 'name')) {
                foreach ($categories as $cat) {
                    if (!$this->is_teacher($cat->name) OR $this->is_ignored_teacher($cat->name)) {
                        if (!move_category($cat, $this->attic_obj)) {
                            print($this->errorlogtag . 'could not move teacher category for user ' . $cat->name . ' to attic.');
                        } else if ($this->verbose) {
                            mtrace($this->errorlogtag . 'removed category of removed teacher ' . $cat->name);
                        }
                        $edited = true;
                    }
                }
            }
        }

        // Autocreate teacher course categories for new teachers if requrested.
        if ($this->config->teachers_category_autocreate) {
            foreach ($this->teacher_array as $teacher) {
                if (empty($teacher) OR $this->is_ignored_teacher($teacher)) {
                    if ($this->verbose) {
                        mtrace($this->errorlogtag . 'teacher ' . $teacher . ' will be ignored.');
                    }
                    continue;
                }
                $user = $DB->get_record('user', array('username'=>$teacher, 'auth' => 'ldap'));
                $cat_obj = $DB->get_record('course_categories',
                        array('name'=>$teacher, 'idnumber' => $teacher, 'parent' => $this->teacher_obj->id));
                if ($this->verbose) {
                    mtrace($this->errorlogtag . 'teacher(' . $teacher . ') category(' .
                            print($cat_obj) . ') user(' . print($user) . ')');
                }

                // Autocreate/move teacher category.
                if (empty($cat_obj)) {
                    if (!$this->teacher_add_category($user)) {
                        print($this->errorlogtag . 'autocreate teacher category failed: ' . $teacher);
                        continue;
                    }
                    if ($this->verbose) {
                        mtrace($this->errorlogtag . 'autocreate course category for '. $teacher);
                    }
                    $edited = true;
                }
            }
        }
        if ($edited) {
            $this->resort_categories($this->teacher_obj->id);
        }

        $this->sync_cohort_enrolments();

        return true;
    }

    /**
     * This function checks all courses and enrols cohorts that are listed in the course id number.
     * @uses DB
     * @returns void
     */
    public function sync_cohort_enrolments() {
        global $DB, $CFG;
        $edited = false;
        require_once($CFG->dirroot . '/enrol/cohort/locallib.php');
        if ($this->verbose) {
            mtrace($this->errorlogtag . 'sync_cohort_enrolments called');
        }
        $role = $DB->get_record('role', array('shortname' => $this->config->student_role));
        if ($this->verbose) {
            mtrace($this->errorlogtag . 'role ' . print($role));
        }
        $enrol = enrol_get_plugin('cohort');
        if ($this->verbose) {
            mtrace($this->errorlogtag . 'enrol plugin loaded ' . print($enrol));
        }
        $courses = $DB->get_recordset_select('course', "idnumber != ''");
        foreach ($courses as $course) {
            $groups = explode(',', $course->idnumber);
            if ($this->verbose) {
                mtrace($this->errorlogtag . 'groups ' . print($groups));
            }
            $cohorts = $this->get_cohortinstancelist($course->id);
            if ($this->verbose) {
                mtrace($this->errorlogtag . 'enrol plugin instances ' . print($cohorts));
            }
            foreach ($groups as $group) {
                if (!isset($cohorts[$group]) AND $cohortid=$this->get_cohort_id($group, false)) {
                    $enrol->add_instance($course,
                            array('customint1'=>$cohortid,
                                  'roleid'=>$role->id));
                    if ($this->verbose) {
                        mtrace($this->errorlogtag . 'add cohort ' . $group . ' to course ' . $course->name);
                    }
                    $edited = true;
                }
            }
            foreach ($cohorts as $cohort) {
                if (!in_array($cohort->idnumber, $groups)) {
                    delete_instance($cohort->id);
                    if ($this->verbose) {
                        mtrace($this->errorlogtag . 'remove cohort ' . $group . ' from course ' . $course->name);
                    }
                    $edited = true;
                }
            }
        }
        $courses->close();
        if ($edited) {
            enrol_cohort_sync();
        }
    }

    public function enrol_openlml_sync($verbose = false) {
        $this->verbose = $verbose;
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
            print('[AUTH LDAP] ' . get_string('pluginnotenabled', 'auth_ldap'));
            die;
        }

        if (!enrol_is_enabled('cohort')) {
            print('[ENROL COHORT]'.get_string('pluginnotenabled', 'enrol_cohort'));
            die;
        }

        if (!enrol_is_enabled('openlml')) {
            print('[ENROL OPENLML] '.get_string('pluginnotenabled', 'enrol_openlml'));
            die;
        }

        print("Starting enrolments for openlml enrolments plugin...");
        $this->enrol_openlml_sync(false);
        print("finished.\n");
    }

    /**
     * return all groups from LDAP which match search criteria defined in settings
     * @return string[]
     */
    private function ldap_get_grouplist($userid = "*") {
        global $CFG, $DB;
        if ($this->verbose) {
            mtrace($this->errorlogtag . 'ldap_get_grouplist called');
        }
        if (!isset($ldapauth) or empty($ldapauth)) {
            $ldapauth = get_auth_plugin('ldap');
            if ($this->verbose) {
                mtrace($this->errorlogtag . "auth plugin loaded");
            }
        }
        $ldapconnection = $ldapauth->ldap_connect();

        $fresult = array ();
        if ($userid !== "*") {
            $filter = '(' . $this->config->member_attribute . '=' . $userid . ')';
        } else {
            $filter = '';
        }
        $filter = '(&' . $this->ldap_generate_group_pattern() . $filter . '(objectclass=' . $this->config->object . '))';
        if ($this->verbose) {
            mtrace($this->errorlogtag . 'filter defined:' . $filter);
        }
        $contexts = explode(';', $this->config->contexts);
        if ($this->verbose) {
            mtrace($this->errorlogtag . 'contexts settings(' . $this->config->contexts .
                    ') contexts array(' . print($contexts) . ')');
        }
        foreach ($contexts as $context) {
            $context = trim($context);
            if (empty ($context)) {
                continue;
            }

            if ($ldapauth->config->search_sub) {
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
        $ldapauth->ldap_close();
        // Remove teachers from all but teachers groups.
        if ($userid != "*" AND $this->is_teacher($userid)) {
            foreach ($fresult as $i => $group) {
                if (!$this->has_teachers_as_members($group)) {
                    unset($fresult[$i]);
                }
            }
        }
        if ($this->verbose) {
            mtrace($this->errorlogtag . 'found ldap groups:' . implode(', ', $fresult));
        }
        return $fresult;
    }

    /**
     * search for group members on a Open LML server with defined search criteria
     * @return string[] array of usernames
     */
    private function ldap_get_group_members($group, $teachers_ok = false) {
        global $CFG, $DB;

        if ($this->verbose) {
            mtrace($this->errorlogtag . 'ldap_get_group_members called');
        }
        $ret = array ();
        $members = array ();
        if (!isset($ldapauth) or empty($ldapauth)) {
            $ldapauth = get_auth_plugin('ldap');
            if ($this->verbose) {
                mtrace($this->errorlogtag . "auth plugin loaded");
            }
        }
        $ldapconnection = $ldapauth->ldap_connect();

        $textlib = textlib_get_instance();
        $group = $textlib->convert($group, 'utf-8', $this->config->ldapencoding);

        if ($this->verbose) {
            mtrace($this->errorlogtag . 'ldap connection:' . print($ldapconnection));
        }
        if (!$ldapconnection) {
            return $ret;
        }
        $queryg = "(&(cn=" . trim($group) . ")(objectClass=" . $this->config->object . "))";
        if ($this->verbose) {
            mtrace($this->errorlogtag . "query: " . $queryg);
        }
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
        if ($this->verbose) {
            mtrace($this->errorlogtag . "ldap_get_group_members returns " . print($members));
        }
        $ldapauth->ldap_close();
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

    private function cohort_remove_empty_cohorts() {
        global $DB;
        $empty = array();
        $cohorts = $this->get_cohortlist();
        if ($this->verbose) {
            mtrace($this->errorlogtag . 'cohorts list:' . print($cohorts));
        }
        foreach ($cohorts as $cohort) {
            if (!$DB->record_exists('cohort_members', array('cohortid'=>$cohort->id))) {
                $empty[] = $cohort->id;
            }
        }
        if ($this->verbose) {
            mtrace($this->errorlogtag . 'empty cohorts list:' . print($empty));
        }
        if (!empty($empty)) {
            $DB->delete_records_list('cohort', 'id', $empty);
        }
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
            if ($this->verbose) {
                mtrace($this->errorlogtag . 'cohort added:' . print($cohort));
            }
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
                    FROM {cohort} c";
            $records = $DB->get_records_sql($sql);
        }
        if ($this->verbose) {
            mtrace($this->errorlogtag . 'records for cohortlist:' . print($records));
        }
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
        if (!empty($this->config->prefix_teacher_members) AND
              (strpos($group, $this->config->prefix_teacher_members) === 0)) {
            return true;
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

        if ($this->verbose) {
            mtrace($this->errorlogtag . ' generate_class_pattern called');
        }
        $pattern[] = '(' . $this->config->attribute . '=' . $this->config->teachers_group_name .')';
        $pattern[] = '(' . $this->config->attribute . '=' . $this->config->prefix_teacher_members .'*)';
        $classes = explode(',', $this->config->student_class_numbers);
        foreach ($classes as $c) {
            $pattern[] = '(' . $this->config->attribute . '=' . $c . '*)';
        }
        $classes = explode(',', $this->config->student_groups);
        foreach ($classes as $c) {
            $pattern[] = '(' . $this->config->attribute . '=' . $c . '*)';
        }
        $pattern[] = '(' . $this->config->attribute . '='. $this->config->student_project_prefix . '*)';
        if ($this->verbose) {
            mtrace($this->errorlogtag . 'generated_class_pattern:' . $pattern);
        }
        $pattern = '(|' . implode($pattern) . ')';
        if ($this->verbose) {
            mtrace($this->errorlogtag . 'generated_class_pattern:' . $pattern);
        }
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
     * @uses $CFG
     */
    private function get_teacher_category() {
        global $CFG, $DB;
        // Create teacher category if needed.
        $cat_obj = $DB->get_record( 'course_categories', array('name'=>$this->config->teachers_course_context, 'parent' => 0));
        if (!$cat_obj) { // Category doesn't exist.
            if ($this->verbose) {
                mtrace($this->errorlogtag . 'creating non-existing teachers course category ' .
                        $this->config->teachers_course_context);
            }
            $newcategory = new stdClass();
            $newcategory->name = $this->config->teachers_course_context;
            $newcategory->description = get_string('teacher_context_desc', 'enrol_openlml');
            $newcategory->sortorder = 999;
            $newcategory->parent = 0; // Top level category.
            if (!$DB->insert_record('course_categories', $newcategory)) {
                print($this->errorlogtag . 'could not insert the new category ' . $newcategory->name);
            }
            $cat_obj = $DB->get_record( 'course_categories',
                    array('name'=>$this->config->teachers_course_context, 'parent' => 0));
        }
        if (!$cat_obj) {
            print($this->errorlogtag . 'autocreate/autoremove could not create teacher course context');
        }
        return $cat_obj;
    }

    /**
     * This function checks and creates the teacher attic category.
     *
     * @uses $CFG
     */
    private function get_teacher_attic_category() {
        global $CFG, $DB;
        $this->attic_obj = $DB->get_record( 'course_categories', array('name'=>$this->config->teachers_removed, 'parent' => 0));
        if (!$this->attic_obj) { // Category for removed teachers doesn't exist.
            if ($this->verbose) {
                mtrace($this->errorlogtag . 'creating non-existing removed teachers category ' . $this->config->teachers_removed);
            }
            $newcategory = new stdClass();
            $newcategory->name = $this->config->teachers_removed;
            $newcategory->description = get_string('attic_description', 'enrol_openlml');
            $newcategory->sortorder = 999;
            $newcategory->parent = 0; // Top level category.
            if (!$DB->insert_record('course_categories', $newcategory)) {
                print($this->errorlogtag . 'Could not insert the new category ' . $newcategory->name);
            }
            $this->attic_obj = $DB->get_record( 'course_categories',
                    array('name' => $this->config->teachers_removed, 'parent' => 0));
            if (!$this->attic_obj) {
                print($this->errorlogtag .'autocreate/autoremove could not create removed teachers context');
            }
        }
        return $this->attic_obj;
    }


    /**
     * This function resorts the teacher categories alphabetically.
     *
     */
    private function resort_categories($id) {
        global $DB;
        if ($categories = get_categories($id, 'name')) {
            $count=1;
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
        if (!isset($this->attic_obj)) {
            $this->attic_obj = $this->get_teacher_attic_category();
        }
        if (!isset($this->teacher_obj)) {
            $this->teacher_obj = $this->get_teacher_category();
        }
        $cat_obj = $DB->get_record('course_categories', array('idnumber'=>$user->idnumber, 'parent' => $this->attic_obj->id));
        if ($cat_obj) {
            if (!move_category($cat, $this->teacher_obj)) {
                print($this->errorlogtag . 'could not move teacher category for user ' . $cat->name . ' back from attic.');
                return false;
            }
        } else {
            $cat_obj = new stdClass();
            $cat_obj->name = $cat_obj->idnumber = $user->username;
            $cat_obj->description = get_string('course_description', 'enrol_openlml') . ' ' .
                    $user->firstname . ' ' .$user->lastname . '(' . $user->idnumber. ').';
            $cat_obj->parent = $this->teacher_obj->id; // Top level category.
            $cat_obj->depth = $this->teacher_obj->depth+1;
            if (!$cat_obj->id = $DB->insert_record('course_categories', $cat_obj)) {
                print($this->errorlogtag . "Could not insert the new teacher course category '$cat_obj->name' ".print($cat_obj));
                return false;
            }
        }
        // Update category data and roles.
        $path = $this->teacher_obj->path.'/'.$cat_obj->id;
        if ($cat_obj->path !== $path) {
            $cat_obj->path = $this->teacher_obj->path.'/'.$cat_obj->id;
            if (!$DB->update_record('course_categories', $cat_obj)) {
                print("Could not update the new teacher course category '$cat_obj->name'.");
                return false;
            }
        }
        $cat_obj->context = get_context_instance(CONTEXT_COURSECAT, $cat_obj->id);
        mark_context_dirty($cat_obj->context->path);
        // Set teachers role to course creator.
        $role = $DB->get_record('role', array('shortname' => $this->config->teachers_course_role));
        if (empty($role)) {
            print($this->errorlogtag . 'could not get teachers course role (' . $this->config->teachers_course_role . ').');
            return false;
        }
        if (!role_assign($role->id, $user->id, $cat_obj->context->id, 'enrol_openlml')) {
            print($this->errorlogtag . 'could not assign role (' . $role->id . ') to user (' .
                    $user->idnumber . ') in context (' . $cat_obj->context->id . ').');
            return false;
        }
        return true;
    }

    public function test_sync_user_enrolments($userid) {
        global $DB;
        $user = $DB->get_record('user', array('idnumber' => $userid));
        if ($user) {
            $this->sync_user_enrolments($user);
        }
    }

} // End of class.
