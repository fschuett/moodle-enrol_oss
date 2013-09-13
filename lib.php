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

        if ($this->verbose) {
            print($this->errorlogtag . 'sync_user_enrolments called' . "\n");
        }
        // Correct the cohort subscriptions.
        $ldap_groups = $this->ldap_get_grouplist($user->idnumber);
        if ($this->verbose) {
            print($this->errorlogtag . 'user:' . $user->idnumber . ' ldap_groups:' . $ldap_groups . "\n");
        }
        $cohorts = $this->get_cohortlist($user->idnumber);
        if ($this->verbose) {
            print($this->errorlogtag . 'user:' . $user->idnumber . ' cohorts:' . $cohorts . "\n");
        }
        foreach ($ldap_groups as $group => $groupname) {
            if (!isset($cohorts[$groupname])) {
                $cohortid = $this->get_cohort_id($groupname);
                cohort_add_member($cohortid, $user->id);
                if ($this->verbose) {
                    print($this->errorlogtag . 'add ' . $user->username . ' to cohort ' . $groupname . "\n");
                }
            }
        }

        foreach ($cohorts as $cohort) {
            if (!in_array($cohort->idnumber, $ldap_groups)) {
                cohort_remove_member($cohort->id, $user->id);
                if ($this->verbose) {
                    print($this->errorlogtag . 'remove ' . $user->username . ' from cohort ' . $cohort->name . "\n");
                }
                if (!$DB->record_exists('cohort_members', array('cohortid'=>$cohort->id))) {
                    cohort_delete_cohort($cohortid);
                    if ($this->verbose) {
                        print($this->errorlogtag . 'remove empty cohort ' . $cohort->name . "\n");
                    }
                }
            }
        }

        // Autocreate/autoremove teacher category.
        if ($this->config->teachers_category_autocreate OR $this->config->teachers_category_autoremove) {
            if ($this->verbose) {
                print($this->errorlogtag . 'autocreate/autoremove teacher category for teacher ' . $user->username);
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

            $edited = false;
            if ($this->verbose) {
                print($this->errorlogtag . 'Testing for autoremove. ');
            }
            if ($this->config->teachers_category_autoremove AND
                  (!$this->is_teacher($user->idnumber) OR $this->is_ignored_teacher($user->idnumber))) {
                if ($category = $DB->get_record('course_categories', array('name'=>$user->idnumber,
                        'parent'=>$this->teacher_obj->id),'*',IGNORE_MULTIPLE)) {
                    if ($DB->count_records('course_categories', array('name'=>$user->idnumber,
                	    'parent'=>$this->teacher_obj->id)) > 1) {
                	print($this->errorlogtag . ' WARNING: there are more than one matching category named '.
                		$user->idnumber .' in '.$this->teacher_obj->name .". That is likely to cause problems.\n");
            	    }
                    if (!move_category($cat, $this->attic_obj)) {
                        print($this->errorlogtag . 'could not move teacher category for user ' . $cat->name . ' to attic.' . "\n");
                    } else if ($this->verbose) {
                        print($this->errorlogtag . 'removed category of removed teacher ' . $cat->name . "\n");
                    }
                    $edited = true;
                }
            }
            if ($this->verbose) {
                print($this->errorlogtag . 'Testing for autocreate. ');
            }
            if ($this->config->teachers_category_autocreate AND
                $this->is_teacher($user->idnumber) AND !$this->is_ignored_teacher($user->idnumber)) {
                if ($this->verbose) {
                    print($this->errorlogtag . 'The teacher ' . $user->username . ' needs a course category.');
                }
                if (!$DB->get_record('course_categories', array('name'=>$user->idnumber,
                        'parent'=> $this->teacher_obj->id),'*',IGNORE_MULTIPLE)) {
                    if ($this->verbose) {
                        print($this->errorlogtag . 'The teacher ' . $user->username . ' has no course category.');
                    }
                    if (!$this->teacher_add_category($user)) {
                        print($this->errorlogtag . 'autocreate teacher category failed: ' . $user->username . "\n");
                    } else {
                        if ($this->verbose) {
                            print($this->errorlogtag . 'autocreate course category for '. $user->username . "\n");
                        }
                        $edited = true;
                    }
                } else if ($DB->count_records('course_categories', array('name'=>$user->idnumber,
            		'parent'=>$this->teacher_obj->id)) > 1) {
            	    print($this->errorlogtag . ' WARNING: there are more than one matching category named '.
        		    $user->idnumber .' in '.$this->teacher_obj->name .". That is likely to cause problems.\n");
            	}
            }
            if ($this->verbose) {
                print($this->errorlogtag . 'Resorting is necessary: ' . $edited);
            }
            if ($edited) {
                $this->resort_categories($this->teacher_obj->id);
            }
        }

        if ($this->verbose) {
            print($this->errorlogtag . 'sync_user_enrolments returns' . "\n");
        }
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
        if ($this->verbose) {
            print($this->errorlogtag . 'sync_enrolments called' . "\n");
        }

        $ldap_groups = $this->ldap_get_grouplist();

        foreach ($ldap_groups as $group => $groupname) {
            if ($this->verbose) {
                print($this->errorlogtag . '  sync group:' . $groupname ."\n");
            }
            $cohortid = $this->get_cohort_id($groupname);
            if ($this->verbose) {
                print($this->errorlogtag . $cohortid . "\n");
            }
            $ldap_members = $this->ldap_get_group_members($groupname, $this->has_teachers_as_members($groupname));
            $cohort_members = $this->get_cohort_members($cohortid);

            foreach ($cohort_members as $userid => $user) {
                if (!isset ($ldap_members[$userid])) {
                    cohort_remove_member($cohortid, $userid);
                    if ($this->verbose) {
                        print($this->errorlogtag . 'remove ' . $user->username . ' from cohort ' . $groupname . "\n");
                    }
                }
            }

            foreach ($ldap_members as $userid => $username) {
                if (!$this->cohort_is_member($cohortid, $userid)) {
                    cohort_add_member($cohortid, $userid);
                    if ($this->verbose) {
                        print($this->errorlogtag . 'add ' . $username . ' to cohorte ' . $groupname . "\n");
                    }
                }
            }
        }

        // Remove unneeded cohorts.
        $toremove = array();
        $cohorts = $this->get_cohortlist();
        if ($this->verbose) {
            print($this->errorlogtag . 'cohorts list:' . $cohorts . "\n");
        }
        foreach ($cohorts as $cohort) {
            if (!in_array($cohort->idnumber, $ldap_groups)) {
                $toremove[] = $cohort->id;
            }
        }
        if ($this->verbose) {
            print($this->errorlogtag . 'remove cohorts list:' . $toremove . "\n");
        }
        if (!empty($toremove)) {
            $DB->delete_records_list('cohort_members', 'cohortid', $toremove);
            $DB->delete_records_list('cohort', 'id', $toremove);
        }

        if ($this->config->teachers_category_autocreate OR $this->config->teachers_category_autoremove) {
            if ($this->verbose) {
                print($this->errorlogtag . '== syncing teacher categories' . "\n");
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
                            print($this->errorlogtag . 'could not move teacher category for user ' . $cat->name . ' to attic.' . "\n");
                        } else if ($this->verbose) {
                            print($this->errorlogtag . 'removed category of removed teacher ' . $cat->name . "\n");
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
                        print($this->errorlogtag . 'teacher ' . $teacher . ' will be ignored.' . "\n");
                    }
                    continue;
                }
                $user = $DB->get_record('user', array('username'=>$teacher, 'auth' => 'ldap'));
                $cat_obj = $DB->get_record('course_categories',
                        array('name'=>$teacher, 'parent' => $this->teacher_obj->id),'*',IGNORE_MULTIPLE);
                if ($this->verbose) {
                    print($this->errorlogtag . 'teacher(' . $teacher . ') category(' .
                            $cat_obj->name . ') user(' . $user->id . ')' . "\n");
                }

                // Autocreate/move teacher category.
                if (empty($cat_obj)) {
                    if (!$this->teacher_add_category($user)) {
                        print($this->errorlogtag . 'autocreate teacher category failed: ' . $teacher . "\n");
                        continue;
                    }
                    if ($this->verbose) {
                        print($this->errorlogtag . 'autocreate course category for '. $teacher . "\n");
                    }
                    $edited = true;
                } else if ($DB->count_records('course_categories',
                        array('name'=>$teacher, 'parent' => $this->teacher_obj->id)) > 1) {
            	    print($this->errorlogtag . ' WARNING: there are more than one matching category named '.
        		    $teacher .' in '.$this->teacher_obj->name .". That is likely to cause problems.\n");

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

        if ($this->verbose) {
            print($this->errorlogtag . 'update_city(' . $user . ') called.' . "\n");
        }

        if (empty($user)) {
            $params = array('auth' => 'ldap', 'city' => '');
            if (!$DB->set_field('user', 'city', $CFG->defaultcity, $params)) {
                print($this->errorlogtag . "update of city field for many users failed.\n");
            } else if ($this->verbose) {
                print($this->errorlogtag . ' updated city field with ' . $CFG->defaultcity .
                        " for many users.\n");
            }
        } else {
            if ($user->city == '') {
                if (!$DB->set_field('user', 'city', $CFG->defaultcity, array('id' => $user->id))) {
                    print($this->errorlogtag . 'update of city field for user ' . $user->username .
                            " failed.\n");
                } else if ($this->verbose) {
                    print($this->errorlogtag . 'updated city field for user ' . $user->username . "\n");
                }
            }
        }
        if ($this->verbose) {
            print($this->errorlogtag . 'update_city(' . $user . ') returns.' . "\n");
        }
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
        $edited = false;
        require_once($CFG->dirroot . '/enrol/cohort/locallib.php');
        if ($this->verbose) {
            print($this->errorlogtag . 'sync_cohort_enrolments called' . "\n");
        }
        $enrol = enrol_get_plugin('cohort');
        if ($this->verbose) {
            print($this->errorlogtag . 'enrol plugin loaded ' . $enrol->id . "\n");
        }
        $courses = $DB->get_recordset_select('course', "idnumber != ''");
        foreach ($courses as $course) {
            if ($this->verbose) {
                print($this->errorlogtag . 'course shortname(' . $course->shortname .
                        ') idnumber('. $course->idnumber . ")\n");
            }
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
            if ($this->verbose) {
                print($this->errorlogtag . 'groups ' . $groups . "\n");
            }
            $cohorts = $this->get_cohortinstancelist($course->id);
            if ($this->verbose) {
                print($this->errorlogtag . 'enrol plugin instances ' . $cohorts . "\n");
            }
            foreach ($groups as $group) {
                if ($this->verbose) {
                    print($this->errorlogtag . ' is group ' . $group . ' enroled?' . "\n");
                }
                if (!isset($cohorts[$group]) AND $cohortid=$this->get_cohort_id($group, false)) {
                    if ($this->has_teachers_as_members($group)) {
                        $enrol->add_instance($course,
                                array('customint1' => $cohortid, 'roleid' => $this->config->teachers_role));
                    } else {
                        $enrol->add_instance($course,
                                array('customint1' => $cohortid, 'roleid' => $this->config->student_role));
                    }
                    if ($this->verbose) {
                        print($this->errorlogtag . 'add cohort ' . $group . ' to course ' . $course->name . "\n");
                    }
                    $edited = true;
                }
            }

            foreach ($cohorts as $cohort) {
                if ($this->verbose) {
                    print($this->errorlogtag . ' is cohort ' . $cohort->idnumber . ' still necessary?' . "\n");
                }
                if (!in_array($cohort->idnumber, $groups)) {
                    $instances = enrol_get_instances($course->id, false);
                    if ($this->verbose) {
                        print($this->errorlogtag . 'enrolment instances ' . $instances . "\n");
                    }
                    foreach ($instances as $instance) {
                        if ($instance->enrol == 'cohort' AND $instance->customint1 == $cohort->id) {
                            if ($this->verbose) {
                                print($this->errorlogtag . 'remove cohort ' . $cohort->idnumber . ' from course ' . $course->shortname . "\n");
                            }
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
            $trace = null_progress_trace();
            enrol_cohort_sync($trace);
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
            print('[AUTH LDAP] ' . get_string('pluginnotenabled', 'auth_ldap') . "\n");
            die;
        }

        if (!enrol_is_enabled('cohort')) {
            print('[ENROL COHORT]'.get_string('pluginnotenabled', 'enrol_cohort') . "\n");
            die;
        }

        if (!enrol_is_enabled('openlml')) {
            print('[ENROL OPENLML] '.get_string('pluginnotenabled', 'enrol_openlml') . "\n");
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
            print($this->errorlogtag . 'ldap_get_grouplist called' . "\n");
        }
        if (!isset($authldap) or empty($authldap)) {
            $authldap = get_auth_plugin('ldap');
            if ($this->verbose) {
                print($this->errorlogtag . "auth plugin loaded\n");
            }
        }
        $ldapconnection = $authldap->ldap_connect();

        $fresult = array ();
        if ($userid !== "*") {
            $filter = '(' . $this->config->member_attribute . '=' . $userid . ')';
        } else {
            $filter = '';
        }
        $filter = '(&' . $this->ldap_generate_group_pattern() . $filter . '(objectclass=' . $this->config->object . '))';
        if ($this->verbose) {
            print($this->errorlogtag . 'filter defined:' . $filter . "\n");
        }
        $contexts = explode(';', $this->config->contexts);
        if ($this->verbose) {
            print($this->errorlogtag . 'contexts settings(' . $this->config->contexts .
                    ') contexts array(' . $contexts . ')' . "\n");
        }
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
        if ($this->verbose) {
            print($this->errorlogtag . 'found ldap groups:' . implode(', ', $fresult) . "\n");
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
            print($this->errorlogtag . 'ldap_get_group_members called' . "\n");
        }
        $ret = array ();
        $members = array ();
        if (!isset($authldap) or empty($authldap)) {
            $authldap = get_auth_plugin('ldap');
            if ($this->verbose) {
                print($this->errorlogtag . "auth plugin loaded\n");
            }
        }
        $ldapconnection = $authldap->ldap_connect();

        $textlib = textlib_get_instance();
        $group = $textlib->convert($group, 'utf-8', $this->config->ldapencoding);

        if ($this->verbose) {
            print($this->errorlogtag . 'ldap connection:' . $ldapconnection . "\n");
        }
        if (!$ldapconnection) {
            return $ret;
        }
        $queryg = "(&(cn=" . trim($group) . ")(objectClass=" . $this->config->object . "))";
        if ($this->verbose) {
            print($this->errorlogtag . "query: " . $queryg . "\n");
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
            print($this->errorlogtag . "ldap_get_group_members returns " . $members . "\n");
        }
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
            if ($this->verbose) {
                print($this->errorlogtag . 'cohort added:' . $cohort->name . "\n");
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
                    FROM {cohort} c
                            WHERE c.component = 'enrol_openlml'";
            $records = $DB->get_records_sql($sql);
        }
        if ($this->verbose) {
            print($this->errorlogtag . 'records for cohortlist:' . $records . "\n");
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

        if ($this->verbose) {
            print($this->errorlogtag . ' generate_class_pattern called' . "\n");
        }
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
        if ($this->verbose) {
            print($this->errorlogtag . 'generated_class_pattern:' . $pattern . "\n");
        }
        $pattern = '(|' . implode($pattern) . ')';
        if ($this->verbose) {
            print($this->errorlogtag . 'generated_class_pattern:' . $pattern . "\n");
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
     * @uses $CFG,$DB
     */
    private function get_teacher_category() {
        global $CFG, $DB;
        // Create teacher category if needed.
        $cat_obj = $DB->get_record( 'course_categories', array('name'=>$this->config->teachers_course_context, 'parent' => 0),'*',IGNORE_MULTIPLE);
        if (!$cat_obj) { // Category doesn't exist.
            if ($this->verbose) {
                print($this->errorlogtag . 'creating non-existing teachers course category ' .
                        $this->config->teachers_course_context . "\n");
            }
            $cat_obj = $this->create_category($this->config->teachers_course_context,
                    get_string('teacher_context_desc', 'enrol_openlml'));
            if (!$cat_obj) {
                print($this->errorlogtag . 'autocreate/autoremove could not create teacher course context' . "\n");
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
            if ($this->verbose) {
                print($this->errorlogtag . 'creating non-existing removed teachers category ' . $this->config->teachers_removed . "\n");
            }
            $this->attic_obj = $this->create_category($this->config->teachers_removed,
                    get_string('attic_description', 'enrol_openlml'));
            if (!$this->attic_obj) {
                print($this->errorlogtag .'autocreate/autoremove could not create removed teachers context' . "\n");
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
        if ($this->verbose) {
            print($this->errorlogtag . 'Adding teacher category for teacher ' . $user->username . "\n");
        }
        if (!isset($this->attic_obj)) {
            $this->attic_obj = $this->get_teacher_attic_category();
        }
        if (!isset($this->teacher_obj)) {
            $this->teacher_obj = $this->get_teacher_category();
        }
        $cat_obj = $DB->get_record('course_categories', array('name'=>$user->idnumber, 'parent' => $this->attic_obj->id),
                '*',IGNORE_MULTIPLE);
        if ($cat_obj) {
            if (!move_category($cat_obj, $this->teacher_obj)) {
                print($this->errorlogtag . 'could not move teacher category ' . $cat_obj->name . ' for user ' .
                        $user->idnumber . ' back from attic.' . "\n");
                return false;
            }
        } else {
            $description = get_string('course_description', 'enrol_openlml') . ' ' .
                    $user->firstname . ' ' .$user->lastname . '(' . $user->idnumber. ').';
            if ($this->verbose) {
                print($this->errorlogtag . 'Calling create_category for ' . $user->username . ' with description ' . $description . "\n");
            }
            $cat_obj = $this->create_category($user->username, $description, $this->teacher_obj);
            if (!$cat_obj) {
                return false;
            }
        }
        // Update category data and roles.
        $path = $this->teacher_obj->path.'/'.$cat_obj->id;
        if ($cat_obj->path !== $path) {
            $cat_obj->path = $this->teacher_obj->path.'/'.$cat_obj->id;
            if (!$DB->update_record('course_categories', $cat_obj)) {
                print("Could not update the new teacher course category '$cat_obj->name'.\n");
                return false;
            }
        }
        $cat_obj->context = get_context_instance(CONTEXT_COURSECAT, $cat_obj->id);
        mark_context_dirty($cat_obj->context->path);
        // Set teachers role to course creator.
        if (!role_assign($this->config->teachers_course_role, $user->id, $cat_obj->context->id, 'enrol_openlml')) {
            print($this->errorlogtag . 'could not assign role (' . $this->config->teachers_course_role . ') to user (' .
                    $user->idnumber . ') in context (' . $cat_obj->context->id . ').' . "\n");
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
    public function create_category ($name, $description, $parent = 0, $sortorder = 99999) {
        global $DB;
        if(empty($name)) {
            print($this->errorlogtag . 'Could not create category: Category name ' .
                    $name . ' is empty.' . "\n");
            return false;
        }
        if ($this->verbose) {
            print($this->errorlogtag . ' Creating category ' . $name);
        }
        $cat = new stdClass();
        $cat->name = $cat->idnumber = $name;
        $cat->description = $description;
        $cat->sortorder = $sortorder;
        if ($parent == 0) {
            $cat->parent = 0;
            $cat->depth = 1;
        } else {
            $cat->parent = $parent->id; // Parent category.
            $cat->depth = $parent->depth+1;
        }
        if (!$cat->id = $DB->insert_record('course_categories', $cat)) {
            print($this->errorlogtag . 'Could not insert the new course category ' . $cat->name . "\n");
            return false;
        }
        if ($parent == 0) {
            $cat->path = '/' . $cat->id;
        } else {
            $cat->path = $parent->path . '/' . $cat->id;
        }
        if (!$DB->update_record('course_categories', $cat)) {
            print($this->errorlogtag . 'Could not update the new course categories ' .
                    $cat->name . ' path ' . $cat->path . "\n");
            return false;
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
