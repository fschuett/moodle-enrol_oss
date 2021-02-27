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
 * OSS enrolment plugin implementation.
 *
 * This plugin synchronises enrolment and roles with a Open OSS server.
 *
 * @package    enrol
 * @subpackage oss
 * @author     Frank Schütte based on code by Iñaki Arenaza
 * @copyright  1999 onwards Martin Dougiamas {@link http://moodle.com}
 * @copyright  2010 Iñaki Arenaza <iarenaza@eps.mondragon.edu>
 * @copyright  2020 Frank Schütte <fschuett@gymhim.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class enrol_oss_plugin extends enrol_plugin {
    protected $enroltype = 'enrol_oss';
    static protected $errorlogtag = '[ENROL OSS] ';
    protected $idnumber_teachers_cat = 'teachercat';
    protected $idnumber_attic_cat = 'atticcat';
    static protected $idnumber_class_cat = 'classescat';
    protected $groupids = array('teachers', 'students', 'parents');
    protected $teacher_array = Array();
    protected $authldap;
    protected $teacher_obj;
    protected $attic_obj;
    protected $class_obj;
    protected $userid_regex;


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
        if (!enrol_is_enabled('oss')) {
            return true;
        }

        return false;
    }


    /**
     * Forces synchronisation of user enrolments for an ldap user
     * with OSS server.
     * It creates cohorts, removes cohorts and adds/removes course categories.
     *
     * @uses DB,CFG
     * @param object $user user record
     * @return void
     */
    public function sync_user_enrolments($user) {
        global $DB,$CFG;

        // is this a ldap user?
        if ($user->auth != 'ldap') {
            return true;
        }

        // Correct the cohort subscriptions.
        $ldap_groups = $this->ldap_get_grouplist($user->username);
        if(!$ldap_groups){
            debugging(self::$errorlogtag . ' no ldap connection available, sync_user_enrolments aborted.');
            return true;
        }
        $cohorts = $this->get_cohortlist($user->username);
        foreach ($ldap_groups as $group => $groupname) {
            if (!isset($cohorts[$groupname])) {
                $cohortid = $this->get_cohort_id($groupname);
                cohort_add_member($cohortid, $user->id);
                mtrace(self::$errorlogtag."added ".$user->id." to cohort ".$cohortid);
            }
        }

        foreach ($cohorts as $cohort) {
            if (!in_array($cohort->idnumber, $ldap_groups)) {
                cohort_remove_member($cohort->id, $user->id);
                mtace("    removed ".$user->id." from cohort ".$cohort->id);
                if (!$DB->record_exists('cohort_members', array('cohortid' => $cohort->id))) {
                    cohort_delete_cohort($cohortid);
                    mtrace ("    removed cohort " . $cohortid );
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
                  (!$this->is_teacher($user->username) OR $this->is_ignored_teacher($user->username))) {
                if ($cat = $DB->get_record('course_categories', array('idnumber' => $user->username,
                        'parent' => $this->teacher_obj->id),'*',IGNORE_MULTIPLE)) {
                    if ($DB->count_records('course_categories', array('idnumber' => $user->username,
                        'parent' => $this->teacher_obj->id)) > 1) {
                        if (debugging()) {
                            trigger_error(' There are more than one matching category with idnumber '.
                            $user->username .' in '.$this->teacher_obj->name .". That is likely to cause problems.",E_USER_WARNING);
                        }
                    }
                    if (!$this->delete_move_teacher_to_attic($cat)) {
                        debugging(self::$errorlogtag . 'could not move teacher category for user ' . $cat->idnumber . ' to attic.');
                    }
                    $edited = true;
                }
            }
            if ($this->config->teachers_category_autocreate AND
                $this->is_teacher($user->username) AND !$this->is_ignored_teacher($user->username)) {
                $cat = $DB->get_record('course_categories', array('idnumber' => $user->username,
                        'parent' => $this->teacher_obj->id),'*',IGNORE_MULTIPLE);
                if (!$cat) {
                    debugging(self::$errorlogtag . 'about to add teacher category for ' . $user->username."\n");
                    $cat = $this->teacher_add_category($user);
                    if (!$cat) {
                        debugging(self::$errorlogtag . 'autocreate teacher category failed: ' . $user->username."\n");
                    } else {
                        $edited = true;
                    }
                } else if ($DB->count_records('course_categories', array('idnumber' => $user->username,
                'parent' => $this->teacher_obj->id)) > 1) {
                    debugging(self::$errorlogtag . ' WARNING: there are more than one matching category with idnumber '.
                    $user->username .' in '.$this->teacher_obj->name .". That is likely to cause problems.");
                }
                if ($cat AND !$this->teacher_has_role($user,$cat)) {
                    $this->teacher_assign_role($user,$cat);
                }
            }
            if ($edited) {
                $this->resort_categories($this->teacher_obj->id);
            }
        }

        return true;
    }

    /**
     * Does synchronisation of user subscription to cohorts and
     * autocreate/autoremove of teacher course categories based on
     * the settings and the contents of the OSS server
     * for all users.
     * @return boolean
     * @uses DB,CFG
     */
    public function sync_enrolments() {
        global $CFG, $DB;

        debugging(self::$errorlogtag.'sync_enrolments... started '.date("H:i:s"),
            DEBUG_DEVELOPER);
        $ldap_groups = $this->ldap_get_grouplist();
        if (!$ldap_groups) {
            debugging(self::$errorlogtag.' ldap connection not available, sync_enrolments aborted.');
            throw new dml_connection_exception('no ldap connection available - sync_enrolments failed!');
        }
        foreach ($ldap_groups as $group => $groupname) {
            $cohortid = $this->get_cohort_id($groupname);
            $ldap_members = $this->ldap_get_group_members($groupname, $this->has_teachers_as_members($groupname));
            $cohort_members = $this->get_cohort_members($cohortid);

            foreach ($cohort_members as $userid => $user) {
                if (!isset ($ldap_members[$userid])) {
                    cohort_remove_member($cohortid, $userid);
                    mtrace("    removed ".$userid." from cohort ".$cohortid);
                }
            }

            foreach ($ldap_members as $userid => $username) {
                if (!$this->cohort_is_member($cohortid, $userid)) {
                    cohort_add_member($cohortid, $userid);
                    mtrace("    added ".$userid." to cohort ".$cohortid);
                }
            }
        }

        debugging(self::$errorlogtag.'sync_enrolments: remove unneeded cohorts... started '
            . date("H:i:s"), DEBUG_DEVELOPER);
        $toremove = array();
        $cohorts = $this->get_cohortlist();
        foreach ($cohorts as $cohort) {
            if (!in_array($cohort->idnumber, $ldap_groups)) {
                $toremove[] = $cohort->id;
            }
        }
        if (!empty($toremove)) {
            $DB->delete_records_list('cohort_members', 'cohortid', $toremove);
            mtrace("    removed all users from cohorts ".$toremove);
            $DB->delete_records_list('cohort', 'id', $toremove);
            mtrace("    remove cohorts ".$toremove);
        }

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
        }

        $edited = false;

        debugging(self::$errorlogtag.'sync_enrolments: autoremove teacher course categories '
            . ' of removed teachers if requested... started '.date("H:i:s"), DEBUG_DEVELOPER);
        if ($this->config->teachers_category_autoremove) {
            $teachercontext = \core_course_category::get($this->teacher_obj->id);
            if (empty($teachercontext)) {
                debugging(self::$errorlogtag . 'Could not get teacher context');
                return false;
            }
            if ($categories = $teachercontext->get_children()) {
                foreach ($categories as $cat) {
                    if (empty($cat->idnumber)) {
                        debugging(self::$errorlogtag .
                          sprintf('teacher category %s number %d without teacher userid',
                         $cat->name, $cat->id));
                        continue;
                    }
                    if (!$this->is_teacher($cat->idnumber) OR $this->is_ignored_teacher($cat->idnumber)) {
                        if (!$this->delete_move_teacher_to_attic($cat)) {
                            debugging(self::$errorlogtag . 'could not move teacher category for user ' . $cat->idnumber . ' to attic.');
                        }
                        $edited = true;
                    }
                }
            }
        }

        debugging(self::$errorlogtag.'sync_enrolments: autocreate teacher course categories '
            . 'for new teachers if requested... started '.date("H:i:s"), DEBUG_DEVELOPER);
        if ($this->config->teachers_category_autocreate) {
            foreach ($this->teacher_array as $teacher) {
                if (empty($teacher) OR $this->is_ignored_teacher($teacher)) {
                    continue;
                }
                $user = $DB->get_record('user', array('username' => $teacher, 'auth' => 'ldap'));
                $cat_obj = $DB->get_record('course_categories',
                        array('idnumber' => $teacher, 'parent' => $this->teacher_obj->id),'*',IGNORE_MULTIPLE);

                // Autocreate/move teacher category.
                if (empty($cat_obj)) {
                    $cat_obj = $this->teacher_add_category($user);
                    if (!$cat_obj) {
                        debugging(self::$errorlogtag . 'autocreate teacher category failed: ' . $teacher);
                        continue;
                    }
                    $edited = true;
                } else if ($DB->count_records('course_categories',
                        array('idnumber' => $teacher, 'parent' => $this->teacher_obj->id)) > 1) {
                    debugging(self::$errorlogtag . ' WARNING: there are more than one matching category with idnumber '.
                    $teacher .' in '.$this->teacher_obj->name .". That is likely to cause problems.");

                }
                if (!$this->teacher_has_role($user,$cat_obj)) {
                    $this->teacher_assign_role($user,$cat_obj);
                }
            }
        }
        if ($edited) {
            debugging(self::$errorlogtag.'sync_enrolments: resort categories... started '
                . date("H:i:s"), DEBUG_DEVELOPER);
            $this->resort_categories($this->teacher_obj->id);
        }

        $this->sync_cohort_enrolments();

        if (!empty($CFG->defaultcity)) {
            $this->update_city();
        }
        return true;
    }

    /** This function returns an array of all chilren (visible and invisible).
     * @global DB
     * @param core_course_category $cat
     * @return array(core_course_category)
     */
    private function category_get_all_children($cat = null) {
        global $DB;

        $ret = array();
        if (!isset($cat)) {
            return $ret;
        }
        $records = $DB->get_records('course_categories', array('parent' => $cat->id), 'sortorder','id,sortorder');
        if (empty($records)) {
            return $ret;
        }
        $ret = core_course_category::get_many(array_keys($records));

        return $ret;
    }

    /** This function checks all teacher context for correct enrolments.
     * @uses DB
     * @return void
     */
    public function repair_teachers_contexts() {
        global $DB;

        debugging(self::$errorlogtag.'repair_teacher_context: reconstruct missing roles '
            . ' ... started '.date("H:i:s"), DEBUG_DEVELOPER);
        if (!isset($this->teacher_obj)) {
            $this->teacher_obj = $this->get_teacher_category();
        }
        $teachercontext = \core_course_category::get($this->teacher_obj->id);
        if (empty($teachercontext)) {
            debugging(self::$errorlogtag . 'Could not get teacher context');
            return;
        }
        if ($categories = $this->category_get_all_children($teachercontext)) {
            foreach ($categories as $cat) {
                debugging(sprintf(self::$errorlogtag."repair_teacher_context: visit context %s ",$cat->name).date("H:i:s")."\n", DEBUG_DEVELOPER);
                $teacher = $cat->idnumber;
                if (empty($teacher)) {
                    debugging(self::$errorlogtag .
                        sprintf('teacher category %s number %d without teacher userid',
                            $cat->name, $cat->id));
                    continue;
                }
                if ($this->is_teacher($teacher)) {
                    if (! $cat->visible) {
                        $cat->show();
                    }
                    $user = $DB->get_record('user', array('username' => $teacher, 'auth' => 'ldap'));
                    if (!empty($user) && !$this->teacher_has_role($user,$cat)) {
                        debugging(self::$errorlogtag . sprintf('   ... assign course creator and editing teacher role in %s to %s',$cat->name, $user->username));
                        $this->teacher_assign_role($user,$cat);
                    }
                }
            }
        }

    }

    /**
     * This function checks all users created by auth ldap and updates the city field.
     * The config value $CFG->defaultcity must be non empty.
     * @uses DB,CFG
     * @returns void
     */
    public function update_city(&$user = null) {
        global $DB,$CFG;

        if (empty($user)) {
            $params = array('auth' => 'ldap', 'city' => '');
            if (!$DB->set_field('user', 'city', $CFG->defaultcity, $params)) {
                debugging(self::$errorlogtag . "update of city field for many users failed.");
            }
        } else {
            if ($user->city == '') {
                if (!$DB->set_field('user', 'city', $CFG->defaultcity, array('id' => $user->id))) {
                    debugging(self::$errorlogtag . 'update of city field for user ' . $user->username .
                            " failed.");
                }
            }
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
        require_once($CFG->dirroot . '/enrol/cohort/locallib.php');
        debugging(self::$errorlogtag.'sync_cohort_enrolments... started '.date("H:i:s"), DEBUG_DEVELOPER);
        $edited = false;

        $enrol = enrol_get_plugin('cohort');
        // add cohorts for idcohorts
        $courses = $DB->get_recordset_select('course', "idnumber != ''");
        foreach ($courses as $course) {
            $idcohort[$course->id] = $this->get_idnumber_cohorts($course->id,$course->idnumber,$course->shortname);
            $cohorts = $this->get_coursecohortlist($course->id);
            foreach ($idcohort[$course->id] as $group) {
                if (!isset($cohorts[$group]) AND $cohortid = $this->get_cohort_id($group, false)) {
                    if ($this->has_teachers_as_members($group)) {
                        $enrol->add_instance($course,
                                array('customint1' => $cohortid, 'customchar1' => $this->enroltype,
                                        'roleid' => $this->config->teachers_role));
                        mtrace("    enroled cohort ".$cohortid." to course ".$course->id." as teachers");
                    } else {
                        $enrol->add_instance($course,
                                array('customint1' => $cohortid, 'customchar1' => $this->enroltype,
                                        'roleid' => $this->config->student_role));
                        mtrace("    enroled cohort ".$cohortid." to course ".$course->id." as students");
                    }
                    $edited = true;
                }
            }
        }
        $courses->close();

        // remove cohorts not in idcohorts
        $coursecohorts = $this->get_cohortinstancelist();
        foreach ($coursecohorts as $courseid => $instances) {
            if (!isset($idcohort[$courseid])) {
                foreach ($instances as $cohort => $instance) {
                    $enrol->delete_instance($instance);
                    mtrace("    unenroled cohort ".$cohort." from course ".$courseid);
                    $edited = true;
                }
            } else {
                foreach ($instances as $cohort => $instance) {
                    if (!in_array($cohort, $idcohort[$courseid])) {
                        $enrol->delete_instance($instance);
                        mtrace("    unenroled cohort ".$cohort." from course ".$courseid);
                        $edited = true;
                    }
                }
            }
        }
        if ($edited) {
            $trace = new null_progress_trace();
            enrol_cohort_sync($trace);
        }
    }

    public function enrol_oss_sync() {
        $this->sync_enrolments();
    }

    /**
     * return an array of groups, which are defined in course idnumber
     * @parameters $courseid : $course->id, $idnumber : $course->idnumber
     *             $shortname : $course->shortname
     * @return array()
     */

    private function get_idnumber_cohorts($courseid, $idnumber, $shortname) {
        global $DB;
        $groups = array();
        if (!isset($idnumber) or empty($idnumber)
                or !isset($shortname) or empty($shortname)
                or !isset($courseid) or empty($courseid)) {
            return $groups;
        }
        if ((strpos($idnumber, $shortname . ':')) === 0) {
            $groups = explode(',', substr($idnumber, strlen($shortname . ':')));
        }
        else if (($pos = strpos($idnumber, ':')) !== false) {
            $groups = explode(',', substr($idnumber, $pos + 1));
            $DB->set_field('course', 'idnumber',
                    $shortname . ':' . implode($groups), array('id' => $courseid));
        }
        else {
            $DB->set_field('course', 'idnumber',
                    $shortname . ':' . $idnumber, array('id' => $courseid));
            $groups = explode(',', $idnumber);
        }
        $changed = false;
        for($i = 0; $i < count($groups); $i++) {
            if( $groups[$i] != strtoupper(trim($groups[$i])) ) {
                $changed = true;
                $groups[$i] = strtoupper(trim($groups[$i]));
            }
        }
        if ($changed) {
            $DB->set_field('course', 'idnumber',
                $shortname . ':' . implode($groups), array('id' => $courseid));
        }
        return $groups;
    }


    /**
     * Function to collect students parents_child_attribute from ldap
     *
     * on success:
     * @return array(attr#1 => uid#1, ...)
     * on failure:
     * @return false
     */
    private function ldap_get_children() {
        global $CFG, $DB;

        debugging(self::$errorlogtag.'ldap_get_children... started '.date("H:i:s"),
            DEBUG_DEVELOPER);
        if (!isset($this->authldap) or empty($this->authldap)) {
            $this->authldap = get_auth_plugin('ldap');
        }
        $ldapconnection = $this->ldap_connect_ul($this->authldap);
        $fresult = array ();
        if (!$ldapconnection) {
            return false;
        }
        $filter = 'memberOf=CN=STUDENTS*';
        $contexts = explode(';', $this->authldap->config->contexts);
        foreach ($contexts as $context) {
            $context = trim($context);
            if (empty ($context)) {
                continue;
            }

            if ($this->authldap->config->search_sub) {
                // Use ldap_search to find first child from subtree.
                $ldap_result = ldap_search($ldapconnection, $context, $filter, array (
                    $this->config->parents_child_attribute, 'cn'
                ));
            } else {
                // Search only in this context.
                $ldap_result = ldap_list($ldapconnection, $context, $filter, array (
                    $this->config->parents_child_attribute, 'cn'
                ));
            }

            $children = ldap_get_entries($ldapconnection, $ldap_result);
            // Add found children to list.
            for ($i = 0; $i < count($children) - 1; $i++) {
                if(array_key_exists($this->config->parents_child_attribute, $children[$i])) {
                    $fresult[$children[$i][$this->config->parents_child_attribute][0]] =
                        $children[$i]['cn'][0];
                } else {
                    debugging(self::$errorlogtag."ldap_get_children(): entry ".$children[$i]['cn'][0]." has no  attribute ".$this->config->parents_child_attribute."\n");
                }
            }
        }
        $this->authldap->ldap_close();
        return $fresult;
    }


    /**
     * return all groups from LDAP which match search criteria defined in settings
     *
     * A $group_pattern of the form "(|(cn=05*)(cn=06*)...)" can be provided, otherwise
     * a default $group_pattern is generated.
     *
     * For $all_teachers = true all groups include teachers, otherwise only special groups
     * include teachers.
     *
     * on success:
     * @return string[]
     * on failure:
     * @return false
     */
    private function ldap_get_grouplist($username = "*", $group_pattern = null, $all_teachers = false) {
        global $CFG, $DB;

        debugging(self::$errorlogtag.'ldap_get_grouplist... started '.date("H:i:s"),
            DEBUG_DEVELOPER);
        if (!isset($this->authldap) or empty($this->authldap)) {
            $this->authldap = get_auth_plugin('ldap');
        }
        debugging(self::$errorlogtag.'ldap_get_grouplist... ldap_connect '.date("H:i:s"),
            DEBUG_DEVELOPER);
        $ldapconnection = $this->ldap_connect_ul($this->authldap);
        debugging(self::$errorlogtag.'ldap_get_grouplist... ldap_connected '.date("H:i:s"),
            DEBUG_DEVELOPER);
        $fresult = array ();
        if (!$ldapconnection) {
            return false;
        }
        if ($username !== "*") {
            $filter = '(' . $this->config->member_attribute . '=' . $username . ')';
        } else {
            $filter = '';
        }
        if (is_null ( $group_pattern )) {
            $group_pattern = $this->ldap_generate_group_pattern ();
        }
        $filter = '(&' . $group_pattern . $filter . '(objectclass=' . $this->config->object . '))';
        $contexts = explode(';', $this->config->contexts);
        foreach ($contexts as $context) {
            $context = trim($context);
            if (empty ($context)) {
                continue;
            }

            if ($this->authldap->config->search_sub) {
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
        debugging(self::$errorlogtag.'ldap_get_grouplist... ldap_close '.date("H:i:s"),
            DEBUG_DEVELOPER);
        $this->authldap->ldap_close();
        debugging(self::$errorlogtag.'ldap_get_grouplist... ldap_closed '.date("H:i:s"),
            DEBUG_DEVELOPER);
        // Remove teachers from all but teachers groups.
        if ($username != "*" AND $this->is_teacher($username)) {
            foreach ($fresult as $i => $group) {
                if (!$this->has_teachers_as_members($group)) {
                    unset($fresult[$i]);
                }
            }
        }
        return $fresult;
    }

    /**
     * search for group members on a OSS server with defined search criteria
     * @return string[] array of usernames or dns
     */
    private function ldap_get_group_members($group, $teachers_ok = false) {
        global $CFG, $DB;

        debugging(self::$errorlogtag.'ldap_get_group_members('.$group.')... started '.date("H:i:s"),
            DEBUG_DEVELOPER);
        $ret = array ();
        $members = array ();
        if (!isset($this->authldap) or empty($authldap)) {
            $this->authldap = get_auth_plugin('ldap');
        }
        debugging(self::$errorlogtag.'ldap_get_groupmembers... ldap_connect '.date("H:i:s"),
            DEBUG_DEVELOPER);
        $ldapconnection = $this->ldap_connect_ul($this->authldap);
        debugging(self::$errorlogtag.'ldap_get_groupmembers... ldap_connected '.date("H:i:s"),
            DEBUG_DEVELOPER);

        $group = core_text::convert($group, 'utf-8', $this->config->ldapencoding);

        if (!$ldapconnection) {
            return $ret;
        }
        debugging(self::$errorlogtag.'ldap_get_group_members... connected to ldap '.date("H:i:s"),
            DEBUG_DEVELOPER);
        $queryg = "(&(cn=" . trim($group) . ")(objectClass=" . $this->config->object . "))";
        $contexts = explode(';', $this->config->contexts);

        foreach ($contexts as $context) {
            $context = trim($context);
            if (empty ($context)) {
                continue;
            }

            debugging(self::$errorlogtag .
                sprintf('ldap_get_group_members... ldap_search(%s|%s) %s',
                    $context, $queryg, date("H:i:s")), DEBUG_DEVELOPER);
            $resultg = ldap_search($ldapconnection, $context, $queryg);

            if (!empty ($resultg) AND ldap_count_entries($ldapconnection, $resultg)) {
                debugging(self::$errorlogtag.'ldap_get_group_members... ldap_get_entries()'
                    .date("H:i:s"), DEBUG_DEVELOPER);
                $entries = ldap_get_entries($ldapconnection, $resultg);

                if (isset($entries[0][$this->config->member_attribute])) {
                    debugging(self::$errorlogtag .
                        sprintf('ldap_get_group_members... entries(%s)(%d) %s',
                            $this->config->member_attribute,
                            count($entries[0][$this->config->member_attribute]),
                            date("H:i:s")), DEBUG_DEVELOPER);
                    for ($g = 0; $g < (count($entries[0][$this->config->member_attribute]) - 1); $g++) {
                        $member = trim($entries[0][$this->config->member_attribute][$g]);
                        debugging(self::$errorlogtag . sprintf('ldap_get_group_members... found member=%s', $member), DEBUG_DEVELOPER);
                        if ($this->config->member_attribute_isdn) {
                            $member = $this->userid_from_dn($member);
                        }
                        debugging(self::$errorlogtag . sprintf('ldap_get_group_members... member cn=%s', $member), DEBUG_DEVELOPER);
                        if ($member != "" AND ($teachers_ok OR !$this->is_teacher($member))) {
                            $members[] = $member;
                        }
                    }
                }
            }
        }
        debugging(self::$errorlogtag.'ldap_get_group_members... ldap_close '.date("H:i:s"),
            DEBUG_DEVELOPER);
        $this->authldap->ldap_close();
        debugging(self::$errorlogtag.'ldap_get_groupmembers... ldap_closed '.date("H:i:s"),
            DEBUG_DEVELOPER);
        foreach ($members as $member) {
            if (isset($select)) {
                $select = $select . ",'".$member."'";
            } else {
                $select = "'" . $member . "'";
            }
        }
        if (isset($select)) {
            debugging(self::$errorlogtag."ldap_get_group_members... (".$select. ") ".date("H:i:s"),
                DEBUG_DEVELOPER);
        }
        else {
            debugging(self::$errorlogtag."ldap_get_group_members... (no selected users) ".date("H:i:s"),
            DEBUG_DEVELOPER);
        }
        if (isset($select)) {
            $select = "username IN (" . $select . ")";
            $members = $DB->get_recordset_select('user',$select,null,null,'id,username');
            foreach ($members as $member) {
                $ret[$member->id] = $member->username;
            }
        } else {
            if (debugging()) {
                error_log(self::$errorlogtag.'ldap_get_group_members... '.$group.' is empty. '
                    .date("H:i:s"));
            }
        }
        return $ret;
    }

    /**
     * get_cohort_id search for cohort id of a given ldap group name, create cohort, if autocreate = true
     *
     * @param string $groupname
     * @param boolean $autocreate
     * @return boolean|integer
     */
    private function get_cohort_id($groupname, $autocreate = true) {
        global $DB;

        debugging(self::$errorlogtag.'get_cohort_id('.$groupname.')... started '.date("H:i:s"),
            DEBUG_DEVELOPER);
        $params = array (
            'idnumber' => $groupname,
            'component' => 'enrol_oss',
            'contextid' => SYSCONTEXTID,
        );
        if (!$cohort = $DB->get_record('cohort', $params, '*', IGNORE_MULTIPLE)) {
            if (!$autocreate) {
                return false;
            }
            $cohort = new StdClass();
            $cohort->name = $cohort->idnumber = $groupname;
            $cohort->contextid = SYSCONTEXTID;
            $cohort->component = 'enrol_oss';
            $cohort->description = get_string('sync_description', 'enrol_oss');
            $cohortid = cohort_add_cohort($cohort);
        } else {
            if ($DB->count_records('cohort', $params) > 1) {
                if (debugging()) {
                    trigger_error(' There are more than one matching cohort with idnumber '.
                        $groupname .'. That is likely to cause problems.',E_USER_WARNING);
                }
            }
            $cohortid = $cohort->id;
        }
        return $cohortid;
    }

    /**
     * get_cohortlist
     * create list of moodle cohorts, in which $userid is member, without $userid return all cohorts.
     *
     * @param string $userid
     * @return multitype:array
     */
    private function get_cohortlist($userid = "*") {
        global $DB;
        if ($userid != "*") {
            $sql = "SELECT DISTINCT c.id, c.idnumber, c.name
                    FROM {cohort} c
                    JOIN {cohort_members} cm ON cm.cohortid = c.id
                    JOIN {user} u ON cm.userid = u.id
                            WHERE (c.component = 'enrol_oss'
                                    AND u.username = :userid
                                    AND u.auth = 'ldap'
                                    AND c.contextid = ".SYSCONTEXTID.")";
            $params['userid'] = $userid;
            $records = $DB->get_records_sql($sql, $params);
        } else {
            $sql = " SELECT DISTINCT c.id, c.idnumber, c.name
                    FROM {cohort} c
                            WHERE c.component = 'enrol_oss'
                            AND c.contextid = ".SYSCONTEXTID;
            $records = $DB->get_records_sql($sql);
        }
        $ret = array();
        foreach ($records as $record) {
            $ret[$record->idnumber] = $record;
        }
        return $ret;
    }

    /**
     * return a two dimensional array with $courseid as first and
     * $cohortname as second index
     * content are cohort enrol instances created by enrol_oss
     * @return array of array
     */
    private function get_cohortinstancelist() {
        global $DB;
        $ret = array();
        // fill $cohortname: id => idnumber
        $sql = "SELECT DISTINCT c.id,c.idnumber
                FROM {cohort} c WHERE c.component = 'enrol_oss'
                AND c.contextid=".SYSCONTEXTID;
        $records = $DB->get_records_sql($sql);
        $cohortname = array();
        foreach($records as $record) {
            $cohortname[$record->id] = $record->idnumber;
        }
        // get cohort enrol instances created from enrol_oss
        $sql = " SELECT e.id,e.enrol,e.courseid,e.customint1,e.customchar1
                FROM {enrol} e
                        WHERE e.enrol='cohort' AND e.customchar1='".$this->enroltype."'";
        $records = $DB->get_records_sql($sql);
        // fill array
        foreach ($records as $record) {
            if (!isset($ret[$record->courseid])) {
                $ret[$record->courseid] = array();
            }
            $ret[$record->courseid][$cohortname[$record->customint1]] = $record;
        }
        return $ret;
    }

    /**
     * return an array of cohort instances used in the course with the
     * given course id and created by enrol_oss
     * @return array
     */
    private function get_coursecohortlist($courseid) {
        global $DB;
        $ret = array();
        $cohorts = enrol_get_instances($courseid, true);
        foreach (array_keys($cohorts) as $key) {
            if ($cohorts[$key]->enrol != 'cohort' OR !isset($cohorts[$key]->customint1)
                    OR !isset($cohorts[$key]->customchar1) OR $cohorts[$key]->customchar1 != $this->enroltype) {
                unset($cohorts[$key]);
            } else if ($record = $DB->get_record('cohort',
                    array('id' => $cohorts[$key]->customint1))) {
                $ret[$record->idnumber] = $record;
            }
        }
        return $ret;
    }

    private function get_cohort_members($cohortid) {
        global $DB;

        debugging(self::$errorlogtag.'get_cohort_members('.$cohortid.')... started '.date("H:i:s"),
            DEBUG_DEVELOPER);
        $sql = " SELECT u.id, u.username
                          FROM {user} u
                         JOIN {cohort_members} cm ON (cm.userid = u.id AND cm.cohortid = :cohortid)
                        WHERE u.deleted=0 AND u.auth='ldap'";
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
            $ar = array_map('ltrim',$ar);
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

        $pattern[] = '(' . $this->config->attribute . '=' . $this->config->teachers_group_name .')';
        $pattern[] = '(' . $this->config->attribute . '=' . $this->config->students_group_name .')';
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
        $pattern = '(|' . implode($pattern) . ')';
        return $pattern;
    }

    /**
     * This function finds the user in the teacher array.
     *
     * @uses $CFG
     */
    private function is_teacher($userid) {
        debugging(self::$errorlogtag.'is_teacher('.$userid.')... started '.date("H:i:s"),
            DEBUG_DEVELOPER);
        if (empty($userid)) {
            debugging(self::$errorlogtag.'is_teacher called with empty userid.');
            return false;
        }
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
        debugging(self::$errorlogtag.'init_teacher_array... started '.date("H:i:s"),
            DEBUG_DEVELOPER);
        $this->teacher_array = $this->ldap_get_group_members($this->config->teachers_group_name, true);
        debugging(self::$errorlogtag.'init_teacher_array... ended '.date("H:i:s"),
            DEBUG_DEVELOPER);
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
        $cat_obj = $DB->get_record( 'course_categories', array('idnumber' => $this->idnumber_teachers_cat, 'parent' => 0),'*',IGNORE_MULTIPLE);
        if (!$cat_obj) { // Category doesn't exist.
            $cat_obj = self::create_category($this->config->teachers_course_context,$this->idnumber_teachers_cat,
                    get_string('teacher_context_desc', 'enrol_oss'));
            debugging(self::$errorlogtag."created teachers course category ".$cat_obj->id, DEBUG_DEVELOPER);
            if (!$cat_obj) {
                debugging(self::$errorlogtag . 'autocreate/autoremove could not create teacher course context');
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
        $this->attic_obj = $DB->get_record( 'course_categories', array('idnumber' => $this->idnumber_attic_cat, 'parent' => 0),'*',IGNORE_MULTIPLE);
        if (!$this->attic_obj) { // Category for removed teachers doesn't exist.
            $this->attic_obj = self::create_category($this->config->teachers_removed, $this->idnumber_attic_cat,
                    get_string('attic_description', 'enrol_oss'),0,99999,0);
            debugging(self::$errorlogtag."created attic course category ".$cat_obj->id, DEBUG_DEVELOPER);
            if (!$this->attic_obj) {
                debugging(self::$errorlogtag .'autocreate/autoremove could not create removed teachers context');
            }
        }
        return $this->attic_obj;
    }

    /*------------------------------------------------------
     * Class functions
     * -----------------------------------------------------
     */
    /**
     * This function checks and creates the class category.
     *
     * @uses $CFG,$DB
     */
    public static function get_class_category($config) {
        global $CFG, $DB;
        // Create class category if needed.
        $cat_obj = $DB->get_record ( 'course_categories', array (
          'idnumber' => self::$idnumber_class_cat,
          'parent' => 0
        ), 'id', IGNORE_MULTIPLE );
        if ( ! $cat_obj ) {
            if ( isset($config->class_category_autocreate) && $config->class_category_autocreate ) {
                $cat_obj = self::create_category( $config->class_category, self::$idnumber_class_cat, get_string ( 'class_category_description', 'enrol_oss' ) );
                if ($cat_obj) {
                       debugging ( self::$errorlogtag . "created class course category " . $cat_obj->id, DEBUG_DEVELOPER );
                } else {
                       debugging ( self::$errorlogtag . 'autocreate/autoremove could not create class course context' );
                }
            }
        } else {
            $cat_obj = \core_course_category::get($cat_obj->id);
        }
        if (! $cat_obj) {
            debugging ( self::$errorlogtag . "class category ".self::$idnumber_class_cat." not found." );
        }
        return $cat_obj;
    }

    /**
     * returns an array of class \core_course_list_element objects (for the $userid)
     *
     * @param string $userid
     * @return multitype:array
     */
    private function get_classes_moodle($userid = "*") {
        global $CFG;

        $ret = array ();
        if ($userid != "*") {
            $user = get_user_by_username($userid, 'id', null, IGNORE_MISSING);
            if (!$user) {
                debugging( self::$errorlogtag . " get_classes_moodle ( $userid ): user not found." );
                return $ret;
            }
        } else {
            $user = null;
        }
        $classcat = self::get_class_category($this->config);
        if (!$classcat) {
            return $ret;
        }
        $courselist = $classcat->get_courses();
        $regexp = $this->config->class_prefixes;
        $regexp = "/^(" . implode("|", explode(',', $regexp)) . ")/";

        foreach ($courselist as $record) {
            if ($record->visible && preg_match($regexp, $record->shortname)) {
                $context = context_course::instance($record->id);
                if (is_null($user) || is_enrolled($context, $user, '', true)) {
                    $ret [$record->shortname] = $record;
                }
            }
        }
        return $ret;
    }

    /**
     * search all classes in ldap according to class_prefixes (for a specific user id)
     * and add all_students and age_group class
     *
     * @return array
     */
    private function get_classes_ldap($userid = "*") {
        global $CFG;
        // create class filter
        if ( $this->config->class_use_prefixes ) {
            $classes = explode ( ',', $this->config->class_prefixes );
            foreach ($classes as $c) {
                $pattern [] = '(' . $this->config->attribute . '=' . $c . '*)';
            }
            $pattern = '(|' . implode ( $pattern ) . ')';
        } else {
            $pattern = '(' . $this->config->class_attribute . '=' . $this->config->class_attribute_value . ')';
        }
        return $this->ldap_get_grouplist($userid, $pattern, true);
    }

    /**
     * create a list of class courses, whose names are given in an array
     *
     * @param array $classes
     */
    private function create_classes($classes = array ()) {
        global $CFG, $DB;
        if (empty($classes)) {
            return;
        }
        $classcat = self::get_class_category($this->config);
        if (!$classcat) {
            return;
        }
        $template = $DB->get_record('course', array('id' => $this->config->class_template),'*', IGNORE_MISSING);
        if ($template) {
            $template = $template->id;
        }
        foreach ($classes as $c) {
            $this->create_class($c, $classcat->id, $template);
            $newclasses = true;
        }
        if ($newclasses) {
            context_coursecat::instance ( $classcat->id )->mark_dirty ();
        }
    }

    /**
     * remove all classes given in array from class category
     *
     * @param array $classes
     */
    private function remove_classes($classes = array()) {
        global $CFG, $DB;
        if (empty($classes)) {
            return;
        }
        $classcat = self::get_class_category($this->config);
        if (!$classcat) {
            return;
        }
        $ids = array();
        foreach ($classes as $c) {
            $records = $DB->get_record('course', array(
             'shortname' => $class,
             'category' => $classcat->id),'*');
            foreach ($records as $r) {
                   $ids[] = $r->id;
            }
        }
        foreach ($ids as $id) {
            $this->remove_class($id);
        }
    }

    private function get_localname($class) {
        debugging(self::$errorlogtag . "get_localname($class) started...\n");
        if (preg_match("/^".get_string("class_all_students_shortname", "enrol_oss")."$/", $class)) {
            return get_string("class_all_students_localname", "enrol_oss");
        } elseif (preg_match("/^".get_string("class_age_groups_shortname", "enrol_oss")."/", $class)
            && preg_match("/(" . implode("|", explode(',', $this->config->class_prefixes)) . ")$/")) {
            return get_string("class_age_groups_localname", "enrol_oss"). " "
                . str_replace(get_string("class_age_groups_shortname", "enrol_oss"),"", $class);
        } else {
            return get_string("class_localname","enrol_oss") . " " . $class;
        }
    }

    /**
     * create a new class either as duplicate from $template or as new empty course.
     *
     * @param string $class
     * @param integer $catid
     * @param number $template
     * @return course object|false
     */
    private function create_class($class, $catid, $template = 0) {
        global $CFG;
        require_once ($CFG->dirroot . '/course/externallib.php');
        require_once ($CFG->dirroot . '/course/lib.php');
        $course = false;
        $fullname = $this->get_localname($class);
        debugging(self::$errorlogtag . "create_class (shortname:$class,category:$catid,template:$template) with fullname $fullname started...\n");
        if (!$template) {
            $data = new stdclass();
            $data->shortname = $class;
            $data->fullname = $fullname;
            $data->visible = 1;
            $data->category = $catid;
            try {
                $course = create_course($data);
                mtrace(self::$errorlogtag . "create_class (shortname:$class,fullname:$fullname) completed.\n");
            } catch ( Exception $e ) {
                debugging(self::$errorlogtag . "create_class (shortname:$class,fullname:$fullname) failed: ".$e->getMessage()."\n");
            }
        } else {
            try {
                $course = core_course_external::duplicate_course($template, $fullname, $class, $catid, 1);
                $course = get_course($course["id"], false);
                mtrace(self::$errorlogtag . "duplicate_course(shortname:$class,fullname:$fullname) completed.\n");
            } catch ( Exception $e ) {
                debugging(self::$errorlogtag . "duplicate_course (shortname:$class,fullname:$fullname) failed: ".$e->getMessage()."\n");
            }
        }
        return $course;
    }

    /**
     * remove given $classid
     *
     * @param int $classid
     */
    private function remove_class($classid) {
        global $CFG;
        require_once ($CFG->libdir . '/moodlelib.php');
        delete_course($classid);
    }

    /**
     * read classes from ldap and classes from moodle and sync in moodle,
     * first add/remove classes in moodle, afterwards sync encolments (for $userid)
     *
     * @param string $userid
     */
    function sync_classes($userid = "*") {
        global $CFG;
        if (!$this->config->classes_enabled) {
            return;
        }
        if ($this->config->class_autocreate || $this->config->class_autoremove) {
            debugging(self::$errorlogtag . "sync_classes($userid)...", DEBUG_DEVELOPER);
            $ldap_classes = $this->get_classes_ldap();
            $mdl_classes = $this->get_classes_moodle();
            if ($this->config->class_autocreate) {
                $to_add = array_diff($ldap_classes,array_keys($mdl_classes));
                if (!empty($to_add)) {
                    $this->create_classes($to_add);
                }
            }
            if ($this->config->class_autoremove) {
                $to_remove = array_diff(array_keys($mdl_classes),$ldap_classes);
                if (!empty($to_remove)) {
                    $this->remove_classes($to_remove);
                }
            }
        }
        if ($userid && strcmp($userid,"*") !== 0) {
            $this->sync_classes_enrolments_user($userid);
        } else {
            $this->sync_classes_enrolments();
            $this->sync_collections_enrolments();
        }
    }

    function sync_classes_enrolments_user($userid) {
        global $CFG;
        if (!$userid) {
            return;
        }
        if ($this->is_teacher($userid)) {
            $role = $this->config->class_teachers_role;
        }    else {
            $role = $this->config->class_students_role;
        }
        $ldap_classes = $this->get_classes_ldap($userid);
        $mdl_classes = $this->get_classes_moodle($userid);
        $to_enrol = array_diff($ldap_classes,array_keys($mdl_classes));
        $to_unenrol = array_diff(array_keys($mdl_classes), $ldap_classes);
        foreach($to_enrol as $class) {
            $this->class_enrol($userid, $mdl_classes[$class]->id, $role);
        }
        foreach($to_unenrol as $class) {
            $this->class_unenrol($user, $mdl_classes[$class]->id);
        }
        if ($this->config->groups_enabled) {
            $this->sync_user_groups($userid);
        }
    }

    private function get_enrol_instance($course) {
        global $DB;
        $enrol_instance = $DB->get_record('enrol', array('enrol' => $this->get_name(), 'courseid' => $course->id));
        if (!$enrol_instance) {
            $instanceid = $this->add_default_instance($course);
            if ($instanceid === null) {
                $instanceid = $this->add_instance($course);
            }
            $enrol_instance = $DB->get_record('enrol', array('id' => $instanceid));
        }
        return $enrol_instance;
    }

    private function class_enrolunenrol ($course, $enrol_instance, $ist, $soll) {
        $to_enrol = array_diff($soll, $ist);
        $to_unenrol = array_diff($ist, $soll);
        $to_enrol_teachers = array();
        $to_enrol_students = array();
        $to_enrol_parents = array();
        foreach($to_enrol as $user) {
            if ( $groupid = $this->get_groupid($user) ) {
                switch ( $groupid ) {
                    case 'teachers':
                        $to_enrol_teachers[] = $user;
                        break;
                    case 'students':
                        $to_enrol_students[] = $user;
                        break;
                    case 'parents':
                        $to_enrol_parents[] = $user;
                        break;
                }
            }
        }
        if (!empty($to_enrol) || !empty($to_unenrol)) {
            mtrace(self::$errorlogtag . "class_enrolunenrol(" . $course->shortname . "): "
                . "enrol(" . implode(",", $to_enrol) . ") "
                . "unenrol(" . implode(",", $to_unenrol) . ")\n");
        }
        if (!empty($to_enrol_teachers)) {
            $this->class_enrol($course, $enrol_instance, $to_enrol_teachers, $this->config->class_teachers_role);
        }
        if (!empty($to_enrol_students)) {
            $this->class_enrol($course, $enrol_instance, $to_enrol_students, $this->config->class_students_role);
        }
        if (!empty($to_enrol_parents)) {
            $this->class_enrol($course, $enrol_instance, $to_enrol_parents, $this->config->class_parents_role);
        }
        if (!empty($to_unenrol)) {
            $this->class_unenrol($course, $enrol_instance, $to_unenrol);
        }
        if ($this->config->groups_enabled) {
            $this->sync_class_groups($course->id);
        }
    }

    function sync_classes_enrolments() {
        $class_obj = self::get_class_category($this->config);
        if (!$class_obj) {
            return;
        }
        $mdl_classes = $this->get_classes_moodle();
        foreach($mdl_classes as $class => $course) {
            $ldap_members = $this->ldap_get_group_members($class, true);
            $ldap_members = $ldap_members + $this->get_class_parents($class);
            $context = context_course::instance($course->id);
            $enrol_instance = $this->get_enrol_instance($course);
            if (!$enrol_instance) {
                debugging(self::$errorlogtag . "sync_classes_enrolments($class): cannot get enrol_instance, ignoring.\n");
                continue;
            }
            $mdl_members = self::get_enrolled_usernames($context);
            $this->class_enrolunenrol($course, $enrol_instance, $mdl_members, $ldap_members);
        }
    }

    function get_class_parents($class = null) {
        global $DB;

        if( $class == null ){
            debugging(self::$errorlogtag . "get_class_parents(null) called!\n");
            return array();
        }
        $sql = "SELECT p.id AS id, p.username AS username
                   FROM {user} p
                   JOIN {role_assignments} ra ON p.id = ra.userid
                   JOIN {context} cx ON ra.contextid = cx.id
                   JOIN {cohort_members} cm ON cm.userid = cx.instanceid
                   JOIN {cohort} c ON cm.cohortid = c.id
                   WHERE cx.contextlevel=" . CONTEXT_USER . " AND c.idnumber like '" . $class . "'";
        $result = $DB->get_records_sql($sql, array());
        $ret = array();
        foreach($result as $id => $user) {
            $ret[$id] = $user->username;
        }
        return $ret;
    }

    function is_ldap_or_manual($username) {
        $pattern = '/^'.$this->config->parents_prefix.'/';
        if(preg_match($pattern, $username)){
            return 'manual';
        } else {
            return 'ldap';
        }
    }

    function class_enrol($course, $enrol_instance, $users, $role) {
        global $DB;
        if (!is_array($users)) {
            $users = array($users);
        }
        foreach ($users as $username) {
            $user = $DB->get_record ( 'user', array (
            'username' => $username,
            'auth' => $this->is_ldap_or_manual($username)
             ) );
            if (!$user) {
                   debugging ( self::$errorlogtag . "class_enrol($username) not found in ".$this->is_ldap_or_manual($username)." users!");
                   continue;
            }
            $this->enrol_user($enrol_instance, $user->id, $role);
            mtrace ( self::$errorlogtag . "enrolled role id $role for " . $username
             . "(" . $user->id . ") in ".$course->shortname."(".$course->id.")\n" );
        }
    }

    function class_unenrol($course, $enrol_instance, $users) {
        global $DB;
        if (!is_array($users)) {
            $users = array($users);
        }
        foreach ($users as $username) {
            $user = $DB->get_record ( 'user', array (
            'username' => $username,
            'auth' => $this->is_ldap_or_manual($username)
             ) );
            if (!$user) {
                   debugging ( self::$errorlogtag . "class_unenrol($username) not found in ".$this->is_ldap_or_manual($username)." users!\n");
                   continue;
            }
            $this->unenrol_user($enrol_instance, $user->id);
            debugging ( self::$errorlogtag . "unenrolled user ".$username."(".$user->id.") from ".$course->shortname."(".$course->id.")\n", DEBUG_DEVELOPER );
        }
    }

    /*
    * Add user to class groups for classes he is enrolled in.
    * Remove user from class groups he is not enrolled in?
    *
    * @param $userid id of the user
    *
    */
    private function sync_user_groups($userid, $classes) {
        global $CFG;
        require_once $CFG->libdir . '/enrollib.php';
        if ( $groupid = $this->get_groupid($userid) ) {
            foreach($classes as $courseid => $course) {
                if ( ! $this->class_group_is_member($courseid, $groupid, $userid) ) {
                    $this->class_group_add_member($courseid, $groupid, $userid);
                }
            }
        }
    }

    /*
    * Add all enrolled users to their respective groups, if they are missing.
    *
    */
    private function sync_class_groups($courseid) {
        global $CFG;
        require_once $CFG->libdir . '/enrollib.php';
        debugging(self::$errorlogtag." sync_class_groups($courseid) started...\n", DEBUG_DEVELOPER);
        $context = context_course::instance($courseid);
        $users = array_keys(get_enrolled_users($context));
        $teachers = array();
        $students = array();
        $parents = array();
        if ( $group = $this->get_group($courseid, 'teachers') ) {
            $teachers = array_keys(get_enrolled_users($context, '', $group->id));
        }
        if ( $group = $this->get_group($courseid, 'students') ) {
            $students = array_keys(get_enrolled_users($context, '', $group->id));
        }
        if ( $group = $this->get_group($courseid, 'parents') ) {
            $parents = array_keys(get_enrolled_users($context, '', $group->id));
        }
        $users = array_diff($users, $teachers, $students, $parents);
        foreach($users as $key => $userid) {
            if( $groupid = $this->get_groupid($userid) ) {
                $this->class_group_add_member($courseid, $groupid, $userid);
            }
        }
        debugging(self::$errorlogtag." sync_class_groups($courseid) ended.\n", DEBUG_DEVELOPER);
    }

    /*
    * return the groupid, the user can possibly be member of or false
    *
    * @param $userid id of the user
    * @return $string|false groupid name
    *
    */
    private function get_groupid($userid) {
        global $DB;
        if ( is_numeric($userid) ) {
            $username = $DB->get_field('user', 'username', array('id' => $userid), MUST_EXIST);
        } else {
            $username = $userid;
        }
           debugging(self::$errorlogtag." get_groupid($userid|$username) started ...\n", DEBUG_DEVELOPER);
        if( $this->is_teacher($username) ) {
            debugging(self::$errorlogtag." get_groupid($username) returns: teachers\n", DEBUG_DEVELOPER);
            return 'teachers';
        } else if ( $DB->record_exists('user', array('username' => $username, 'auth' => 'ldap')) ) {
            debugging(self::$errorlogtag." get_groupid($username) returns: students\n", DEBUG_DEVELOPER);
            return 'students';
        } else if ( strpos($username, 'eltern_') === 0 ) {
            debugging(self::$errorlogtag." get_groupid($username) returns: parents\n", DEBUG_DEVELOPER);
            return 'parents';
        } else {
            return false;
        }
    }

    private function get_group($courseid, $groupid, $options = IGNORE_MULTIPLE) {
        global $DB;
        debugging(self::$errorlogtag." get_group($courseid, $groupid, $options) started ... \n", DEBUG_DEVELOPER);
        if ( ! in_array($groupid, $this->groupids) ) {
            trigger_error(self::$errorlogtag . ' get_group: impossible groupid ('. $groupid .')');
        }
        $group = $DB->get_record('groups', array('courseid' => $courseid,'idnumber' => $groupid));
        if ( ! $group ) {
            $this->class_create_group($courseid, $groupid);
            $group = $DB->get_record('groups', array('courseid' => $courseid, 'idnumber' => $groupid), '*', $options);
        }
        debugging(self::$errorlogtag." get_group($courseid, $groupid, $options) ended.\n", DEBUG_DEVELOPER);
        return $group;
    }

    private function class_create_group($courseid, $groupid) {
        global $CFG,$DB;
        require_once $CFG->dirroot . '/group/lib.php';
        debugging(self::$errorlogtag." class_create_group($courseid, $groupid) started ... \n", DEBUG_DEVELOPER);
        if ( ! in_array($groupid, $this->groupids) ) {
            trigger_error(self::$errorlogtag . 'create_group: impossible groupid ('. $groupid .')');
        }
        $name = $DB->get_field('course', 'shortname', array('id' => $courseid), MUST_EXIST);
        $data = new stdClass;
        $data->courseid = $courseid;
        $groupdescription = 'class_'.$groupid.'_group_description';
        $data->description = '<p>'.$this->config->$groupdescription.$name.'</p>';
        $data->descriptionformat = 1;
        $groupname = 'class_'.$groupid.'_group';
        $data->name = get_string($groupname, 'enrol_oss');
        $data->idnumber = $groupid;
        $ret = groups_create_group($data);
        return $ret;
    }

    private function class_delete_group($courseid, $groupid) {
        require_once $CFG->dirroot . '/group/lib.php';
        if ( ! in_array($groupid, $this->groupids) ) {
            trigger_error(self::$errorlogtag . 'delete_group: impossible groupid ('. $groupid .')');
        }
        $group = $this->get_group($courseid, $groupid, IGNORE_MISSING);
        if ( $group ) {
            groups_delete_group( $group );
        }
    }

    private function class_group_is_member($courseid, $groupid, $userid) {
        global $CFG;
        require_once ($CFG->libdir . '/grouplib.php');
        if ( ! in_array($groupid, $this->groupids) ) {
            trigger_error(self::$errorlogtag . 'class_group_is_member: impossible groupid ('. $groupid .')');
        }
        $group = $this->get_group($courseid, $groupid);
        return groups_is_member($group->id, $userid);
    }

    private function class_group_add_member($courseid, $groupid, $userid) {
        global $CFG;
        require_once $CFG->dirroot . '/group/lib.php';
        debugging(self::$errorlogtag." class_group_add_member($courseid,$groupid,$userid) started...\n", DEBUG_DEVELOPER);
        if ( ! in_array($groupid, $this->groupids) ) {
            trigger_error(self::$errorlogtag . 'class_group_add_member: impossible groupid ('. $groupid .')');
        }
        $group = $this->get_group($courseid, $groupid);
        groups_add_member($group->id, $userid);
        debugging(self::$errorlogtag." class_group_add_member($courseid,$groupid,$userid) ended.\n", DEBUG_DEVELOPER);
    }

    private function class_group_remove_member($courseid, $groupid, $userid) {
        global $CFG;
        require_once $CFG->dirroot . '/groups/lib.php';
        if ( ! in_array($groupid, $this->groupids) ) {
            trigger_error(self::$errorlogtag . 'class_group_remove_member: impossible groupid ('. $groupid .')');
        }
        $group = $this->get_group($courseid, $groupid);
        groups_remove_member($group->id, $userid);
    }

    /* ------------------------------------------------------
     * collections function, i.e. all students and age groups
     * ------------------------------------------------------
     */
    /**
     * get array of in course enrolled users
     * @param course_context $context
     * @return string[]
     */
    private static function get_enrolled_usernames($context) {
        $mdl_user_objects = get_enrolled_users($context);
        $enrolled = array();
        foreach($mdl_user_objects as $user) {
            $enrolled[] = $user->username;
        }
        return $enrolled;
    }
    /**
     * get enrolled users in classes that match $regexp
     * @param course array $courselist
     * @param string $regexp
     * @return string[]
     */
    private static function classes_get_enrolled($courselist, $regexp = "") {
        $enrolled = array();
        mtrace(self::$errorlogtag . "classes_get_enrolled(\"".$regexp."\" started.)\n");
        foreach ($courselist as $classrecord) {
            if ($classrecord->visible && $regexp != "" && preg_match($regexp, $classrecord->shortname)) {
                mtrace(self::$errorlogtag . "classes_get_enrolled: matches ".$classrecord->shortname."\n");
                $context = context_course::instance($classrecord->id);
                $enrolled = array_merge($enrolled, self::get_enrolled_usernames($context));
            }
        }
        return $enrolled;
    }
    /**
     * get course by shortname from class category with optional autocreate
     * from template
     *
     * @param course_category $classcat
     * @param course $template
     * @return course|boolean
     */
    private function classes_get_course($classcat, $shortname, $template) {
        // find all students class
        $courselist = $classcat->get_courses();
        foreach ($courselist as $record) {
            if ($record->visible && $record->shortname == $shortname) {
                debugging(self::$errorlogtag . "classes_get_course: found course $shortname with id ".$record->id.".\n");
                return $record;
            }
        }
        if ($this->config->class_autocreate) {
            $course = $this->create_class($shortname, $classcat->id, $template);
            if ($course) {
                debugging(self::$errorlogtag . "classes_get_course: created course $shortname with id ".$record->id.".\n");
                return $course;
            } else {
                trigger_error(self::$errorlogtag . 'get_course with autocreate: '.$shortname.' is missing and autocreation is disabled or failed.');
                return false;
            }
        }

    }

    /**
     * This function syncs all collections enrollments for all users.
     */
    function sync_collections_enrolments(){
        global $DB;

        $course_allstds = NULL;

        if (! $this->config->class_all_students && ! $this->config->class_age_groups) {
            return;
        }
        $classcat = self::get_class_category($this->config);
        if (!$classcat) {
            return;
        }
        $template = $DB->get_record('course', array('id' => $this->config->class_template),'*', IGNORE_MISSING);
        if ($template) {
            $template = $template->id;
        }
        mtrace(self::$errorlogtag . " starting collection classes synchronization...");
        $courselist = $classcat->get_courses();
        // process all students class
        if ($this->config->class_all_students) {
            $course_allstds = $this->classes_get_course($classcat, get_string("class_all_students_shortname","enrol_oss"), $template);
            if ($course_allstds) {
                // collect users from class allstds
                $context = context_course::instance($course_allstds->id);
                $members_ist = self::get_enrolled_usernames($context);
                // now collect users from all classes
                $regexp = $this->config->class_prefixes;
                $regexp = "/^(" . implode("|", explode(',', $regexp)) . ")/";
                $members_soll = self::classes_get_enrolled($courselist, $regexp);
                $enrol_instance = $this->get_enrol_instance($course_allstds);
                if (!$enrol_instance) {
                    debugging(self::$errorlogtag . "sync_collections_enrolments(".get_string("class_all_students_shortname","enrol_oss")."): cannot get enrol_instance, ignoring.\n");
                } else {
                    $this->class_enrolunenrol($course_allstds, $enrol_instance, $members_ist, $members_soll);
                }
            }
            mtrace(self::$errorlogtag . " all students class synchronized.");
        }
        // process age groups classes
        if ($this->config->class_age_groups) {
            $ages = explode(',', $this->config->class_prefixes);
            foreach ($ages as $age) {
                // get age group class
                $shortname = get_string("class_age_groups_shortname","enrol_oss") . $age;
                $course = $this->classes_get_course($classcat, $shortname, $template);
                if ($course) {
                    // get members ist
                    $context = context_course::instance($course->id);
                    $members_ist = self::get_enrolled_usernames($context);
                    // get members soll
                    $regexp = "/^".$age."/";
                    $members_soll = self::classes_get_enrolled($courselist, $regexp);
                    // enrol/unenrol
                    $enrol_instance = $this->get_enrol_instance($course);
                    if (!$enrol_instance) {
                        debugging(self::$errorlogtag . "sync_collections_enrolments($shortname): cannt get enrol_instance, ignoring.\n");
                    } else {
                        $this->class_enrolunenrol($course, $enrol_instance, $members_ist, $members_soll);
                    }
                }
                mtrace(self::$errorlogtag . " age group $shortname synchronized.");
            }
        }
        mtrace(self::$errorlogtag . " collection classes synchronization finished.");
    }

    /*------------------------------------------------------
     * parents functions
     * -----------------------------------------------------
     */

    /**
     * This function reads parents_child_attribute and username from ldap and
     * user id from moodle user database and returns an array indexed with the ldap attribute.
     *
     * @return array (attr#1 => uid#1, attr#2 => uid#2, ...)
     */
    private function parents_get_children_uids() {
        global $DB;
        $ldap_students = $this->ldap_get_children();
        $studentgroup = $this->get_cohort_id($this->config->students_group_name);
        $result = $this->get_cohort_members($studentgroup);
        $mdl_students = array();
        foreach($result as $key => $obj) {
            $mdl_students[$obj->username] = $obj->id;
        }
        $result = array();
        foreach($ldap_students as $attr => $uid) {
            $result[$attr] = $mdl_students[$uid];
        }
        return $result;
    }

    /**
     * This function adds the parent -> child relation to moodle database.
     *
     * @param $parent - user id of the parent
     * @param $child  - user id of the child
     * @return true   - success, false otherwise
     */
    private function parents_add_relationship($parent, $child) {
        if( empty($parent) or empty($child) or $parent == 0 or $child == 0) {
             debugging(self::$errorlogtag . "parents_add_relationship(parent=$parent,child=$child) - ungültige Werte\n");
             return false;
        }
        $context = context_user::instance($child);
        $roleid = $this->config->parents_role;
        role_assign($roleid, $parent, $context, 'enrol_oss');
    }

    /**
     * This function removes the parent -> child relation from moodle database.
     *
     * @param $parent - user id of the parent
     * @param $child  - user id of the child
     * @return true   - success, false otherwise
     */
    private function parents_remove_relationship($parent, $child) {
        if( empty($parent) or empty($child) or $parent == 0 or $child == 0) {
             debugging(self::$errorlogtag . "parents_add_relationship(parent=$parent,child=$child) - ungültige Werte\n");
             return false;
        }
        $context = context_user::instance($child);
        $roleid = $this->config->parents_role;
        role_unassign($roleid, $parent, $context, 'enrol_oss');
    }


    /**
     * This function creates / removes relationships between parents and children.
     * The parents are identified by parents_prefix and the matching child is found
     * from parents_child_attribute in ldap structure following the prefix.
     */
    public function parents_sync_relationships() {
        global $DB, $CFG;
        $sql = "id<>".$CFG->siteguest." AND deleted<>1 AND auth='manual'"
                    ." AND username LIKE '".$this->config->parents_prefix."%'";
        $rs = $DB->get_recordset_select('user', $sql, array(),
                    'username', 'username,id');
        $parents = array();
        foreach($rs as $user) {
            $childid = substr($user->username, strlen($this->config->parents_prefix));
            $parents[$childid] = $user->id;
        }
        $children = $this->parents_get_children_uids();
        $orphans = array_diff( array_keys ( $children ) , array_keys ( $parents ) );
        $childless = array_diff( array_keys( $parents ), array_keys( $children ));
        if( $this->config->parents_autocreate ) {
            // foreach $orphan create parent and modify $parents
        }
        if( $this->config->parents_autoremove ) {
            // foreach $childless remove parent and modify $parents
        }
        // sync relations beween parents and children
        $relation_to_be = array_intersect( array_keys( $parents ), array_keys( $children ));
        $sql = "SELECT userid AS parentid, instanceid AS childid
                     FROM {role_assignments}
                     JOIN {context} ON {context}.id = {role_assignments}.contextid
                     WHERE contextlevel=".CONTEXT_USER." AND roleid=".$this->config->parents_role;
        $records = $DB->get_records_sql($sql);
        $relations = array();
        foreach($records as $record) {
            $relations[$record->parentid] = $record->childid;
        }
        $to_create = array_diff( $parents, array_keys( $relations ));
        $to_delete = array_diff( array_keys( $relations ), $parents );
        foreach($to_create as $attr => $id) {
            if(array_key_exists($attr, $children)) {
                $this->parents_add_relationship($id, $children[$attr]);
            } else {
                debugging(self::$errorlogtag."parents_sync_relationships(): children array has no key $attr.\n");
            }
        }
        foreach($to_delete as $attr => $parentid) {
            if(array_key_exists($attr, $children)) {
                $this->parents_remove_relationship($parentid, $children[$attr]);
            } else {
                debugging(self::$errorlogtag."parents_sync_relationships(): children array has no key $attr.\n");
            }
        }
    }

    /*------------------------------------------------------
     * teachers functions
     * -----------------------------------------------------
     */
    /**
     * This function deletes an empty teacher category or moves it to attic if not empty.
     * @uses $CFG;
     */
    private function delete_move_teacher_to_attic($teacher) {
        global $CFG;
        require_once($CFG->libdir . '/questionlib.php');

        if (empty($attic_obj)) {
            $attic_obj = $this->get_teacher_attic_category();
        }

        if (empty($teacher)) {
            debugging(self::$errorlogtag . 'delete_move_teacher_to_attic called with empty parameter.');
            return false;
        }

        $deletable = true;
        if (!$teachercat = \core_course_category::get($teacher->id, MUST_EXIST, true)) { // alwaysreturnhidden
            debugging($this->errorlotag . "delete_move_teacher_to_attic could not get category $teacher.");
            return false;
        }

        if (!$teachercontext = context_coursecat::instance($teachercat->id)) {
            debugging(self::$errorlogtag . "delete_move_teacher_to_attic could not get category context for category $teachercat.");
            return false;
        }

        if ($teachercat->has_children()) {
            $deletable = false;
        }
        if ($teachercat->has_courses()) {
            $deletable = false;
        }
        if (question_context_has_any_questions($teachercontext)) {
            $deletable = false;
        }

        if ($deletable) {
            $teachercat->delete_full(true);
            debugging(self::$errorlogtag."removed teacher category ".$teachercat->id, DEBUG_DEVELOPER);
        }
        else {
            $teachercat->change_parent($this->attic_obj);
            debugging(self::$errorlogtag."moved teacher category ".$teachercat->id." to attic", DEBUG_DEVELOPER);
        }

        return true;
    }

    /**
     * This function resorts the subcategories of the given category alphabetically.
     *
     */
    private function resort_categories($id) {
        global $CFG,$DB;

        $cat = \core_course_category::get($id, MUST_EXIST, true); // alwaysreturnhidden
        if (empty($cat)) {
            debugging("Could not get $id course category for sorting.\n");
            return false;
        }
        if ($categories = $cat->get_children()) {
            $property = 'name';
            $sortflag = core_collator::SORT_STRING;
            if (!core_collator::asort_objects_by_property($categories, $property, $sortflag)) {
                debugging(self::$errorlogtag . 'Sorting with asort_objects_by_property error.');
                return false;
            }
            $count = $cat->sortorder + 1;
            foreach ($categories as $cat) {
                if ($cat->sortorder != $count) {
                    $DB->set_field('course_categories', 'sortorder', $count, array('id' => $cat->id));
                    context_coursecat::instance($cat->id)->mark_dirty();
                    $count++;
                }
            }
        }
        context_coursecat::instance($cat->id)->mark_dirty();
        cache_helper::purge_by_event('changesincoursecat');
        return true;
    }

    /**
     * This function checks if this teacher is to be ignored in autocreate / autoremove.
     *
     * @uses $CFG
     *
     */
    private function is_ignored_teacher($name) {
        global $CFG;
        if (empty($name)) {
            debugging(self::$errorlogtag . 'is_ignored_teacher was called with empty userid');
            return false;
        }
        $ignored_teachers = explode(',', $this->config->teachers_ignore);
        if (empty($ignored_teachers)) {
            return false;
        }
        return in_array($name, $ignored_teachers);
    }

    /**
     * This function adds a teacher course category for the teacher user.
     * @return false | int category_id
     * @uses $CFG;
     */
    private function teacher_add_category(&$user) {
        global $CFG, $DB;

        if (empty($user->username) || empty($user->firstname) || empty($user->lastname)) {
            debugging(self::$errorlogtag .
            sprintf('teacher_add_category: data missing(userid:%s|firstname:%s|lastname:%s)',
                            $user->username, $user->firstname, $user->lastname));
            return false;
        }
        if (!isset($this->attic_obj)) {
            $this->attic_obj = $this->get_teacher_attic_category();
        }
        if (!isset($this->teacher_obj)) {
            $this->teacher_obj = $this->get_teacher_category();
        }
        $cat_obj = $DB->get_record('course_categories', array('idnumber' => $user->username, 'parent' => $this->attic_obj->id),
                '*',IGNORE_MULTIPLE);
        if ($cat_obj) {
            $coursecat = \core_course_category::get($cat_obj->id, MUST_EXIST, true);// alwaysreturnhidden
            $coursecat->change_parent($this->teacher_obj->id);
            debugging(self::$errorlogtag."moved teacher category ".$cat_obj->id." to teachers category", DEBUG_DEVELOPER);
        } else {
            $description = get_string('course_description', 'enrol_oss') . ' ' .
                    $user->firstname . ' ' .$user->lastname . '(' . $user->username . ').';
            $cat_obj = self::create_category($user->lastname.",".$user->firstname, $user->username,
                    $description, $this->teacher_obj->id);
            if (!$cat_obj) {
                debugging(self::$errorlogtag.'Could not create teacher category for teacher ' . $user->username);
                return false;
            }
            debugging(self::$errorlogtag."created teacher category ".$cat_obj->id." for ".$user->id."(".$user->lastname.",".$user->firstname.")", DEBUG_DEVELOPER);
        }
        return $cat_obj;
    }

    /**
     * This function tests if the roles teachers_course_role and teachers_editingteacher_role for the
     * teacher $user is given to category $cat.
     *
     * @param object $user teacher
     * @param object $cat category
     * @return false|true
     * @uses $CFG;
     */
    private function teacher_has_role($user, $cat) {
        global $CFG,$DB;

        if (empty($user)){
            throw new coding_exception('Invalid call to teacher_has_role(), user cannot be empty.');
        }
        if (empty($cat)) {
            throw new coding_exception('Invalid call to teacher_has_role(), cat cannot be empty.');
        }

        // Tests for teachers role.
        $teacherscontext = context_coursecat::instance($cat->id);
        $teacher_coursecreator = $DB->record_exists('role_assignments', array('roleid' => $this->config->teachers_course_role,
                        'contextid' => $teacherscontext->id, 'userid' => $user->id, 'component' => 'enrol_oss'));
        $teacher_editingteacher = $DB->record_exists('role_assignments', array('roleid' => $this->config->teachers_editingteacher_role,
            'contextid' => $teacherscontext->id, 'userid' => $user->id, 'component' => 'enrol_oss'));
        return $teacher_coursecreator && $teacher_editingteacher;
    }

    /**
     * This function adds the roles teachers_course_role and teachers_editingteacher_role for the
     * teacher $user to the given category $cat.
     *
     * @param object $user teacher for whom the coursecreator and editingteacher roles will be added
     * @param object $cat category for whom to add the teacher as roles teachers_course_role and editingteacher_role
     * @return false|true
     * @uses $CFG;
     */
    private function teacher_assign_role($user, $cat) {
        global $CFG;
        if (empty($user)){
            throw new coding_exception('Invalid call to teacher_assign_role(), user cannot be empty.');
        }
        if (empty($cat)) {
            throw new coding_exception('Invalid call to teacher_assign_role(), cat cannot be empty.');
        }

        // Set teachers role to configured teachers course role.
        $teacherscontext = context_coursecat::instance($cat->id);
        if (!role_assign($this->config->teachers_course_role, $user->id, $teacherscontext, 'enrol_oss')) {
            debugging(self::$errorlogtag . 'could not assign role (' . $this->config->teachers_course_role . ') to user (' .
                    $user->username . ') in context (' . $teacherscontext->id . ').');
            return false;
        }
        if (!role_assign($this->config->teachers_editingteacher_role, $user->id, $teacherscontext, 'enrol_oss')) {
            debugging(self::$errorlogtag . 'could not assign role (' . $this->config->teachers_editiingteacher_role . ') to user (' .
                $user->username . ') in context (' . $teacherscontext->id . ').');
            return false;
        }
        debugging(self::$errorlogtag."assign teacher roles for ".$user->id." in category ".$teacherscontext->id, DEBUG_DEVELOPER);
        return true;
    }

    /**
     * This function removes the teachers_course_role and teachers_editingteacher_role for the
     * teacher $user from the given category $cat.
     *
     * @param object $user teacher
     * @param object $cat category
     * @return false|true
     * @uses $CFG;
     */
    private function teacher_unassign_role($user, $cat) {
        global $CFG;

        if (empty($user)){
            throw new coding_exception('Invalid call to teacher_unassign_role(), user cannot be empty.');
        }
        if (empty($cat)) {
            throw new coding_exception('Invalid call to teacher_unassign_role(), cat cannot be empty.');
        }

        // Removes teachers configured course role.
        $teacherscontext = context_coursecat::instance($cat->id);
        role_unassign($this->config->teachers_course_role, $user->id, $teacherscontext, 'enrol_oss');
        role_unassign($this->config->teachers_editingteacher_role, $user->id, $teacherscontext, 'enrol_oss');
        debugging(self::$errorlogtag."unassign teacher role for ".$user->id." in category ".$teacherscontext->id, DEBUG_DEVELOPER);
        return true;
    }

    /**
     * This function creates a course category and fixes the category path.
     * name             the new category name
     * idnumber         the new category idnumber
     * description      a descriptive text for the new course category
     * parent           the course_categories parent object or 0 for top level category
     * sortorder        special sort order, 99999 order at end
     * @return          false | object category_object
     * @uses            $DB;
     */
    public static function create_category ($name, $idnumber, $description, $parent = 0, $sortorder = 0, $visible = 1) {
        global $CFG,$DB;

        debugging(self::$errorlogtag . sprintf("create_category... %s (%s),\n                sortorder(%s) %s",
                 $name, $description, $sortorder, date("H:i:s")), DEBUG_DEVELOPER);
        $data = new stdClass();
        $data->name = $name;
        $data->idnumber = $idnumber;
        $data->description = $description;
        $data->parent = $parent;
        $data->visible = $visible;
        $cat = \core_course_category::create($data);
        if (!$cat) {
            debugging('Could not insert the new course category '.$cat->name.'('.$cat->idnumber.')');
            return false;
        }
        if ($sortorder != 0) {
            debugging(self::$errorlogtag.'Changing course sortorder('.$sortorder.') '
                . date("H:i:s"), DEBUG_DEVELOPER);
            $DB->set_field('course_categories', 'sortorder', $sortorder, array('id' => $cat->id));
            context_coursecat::instance($cat->id)->mark_dirty();
            fix_course_sortorder();
        }
        return $cat;
    }

    private function userid_from_dn($dn = '') {
        if ($dn == '') {
                return '';
        }
        if (!isset($this->userid_regex) or empty($this->userid_regex)) {
            if (!isset($this->authldap) or empty($this->authldap)) {
                $this->authldap = get_auth_plugin('ldap');
            }
            $this->userid_regex = "/^". $this->authldap->config->field_map_idnumber. "=([^,]+),/i";
            debugging(self::$errorlogtag . sprintf('userid_from_dn: Match userid with %s from %s',$this->userid_regex,$dn),
            DEBUG_DEVELOPER);
        }
        if (preg_match($this->userid_regex, $dn, $matches)) {
                return $matches[1];
        } else {
                return '';
        }
    }

    public function test_sync_user_enrolments($userid) {
        global $DB;
        $user = $DB->get_record('user', array('username' => $userid, 'auth' => 'ldap'));
        if ($user) {
            $this->sync_user_enrolments($user);
        }
    }

    /**
     * WORKAROUND: auth_ldap->ldap_connect dies
     * Connect to the LDAP server, using the plugin configured
     * settings. It's actually a wrapper around ldap_connect_moodle()
     *
     * @return resource A valid LDAP connection or false
     */
    private function ldap_connect_ul ($authldap) {
        // Cache ldap connections. They are expensive to set up
        // and can drain the TCP/IP ressources on the server if we
        // are syncing a lot of users (as we try to open a new connection
        // to get the user details). This is the least invasive way
        // to reuse existing connections without greater code surgery.
        if(!empty($authldap->ldapconnection)) {
            $authldap->ldapconns++;
            return $authldap->ldapconnection;
        }
        if (isset($authldap->config->bind_dn)) {
            $binddn = $authldap->config->bind_dn;
        }
        else {
            $binddn = '';
        }
        if (isset($authldap->config->bind_pw)) {
            $bindpw = $authldap->config->bind_pw;
        }
        else {
            $bindpw = '';
        }
        if (isset($authldap->config->opt_deref)) {
            $optderef = $authldap->config->opt_deref;
        }
        else {
            $optderef = false;
        }
        if (isset($authldap->config->start_tls)) {
            $starttls = $authldap->config->start_tls;
        }
        else {
            $starttls = false;
        }
        if($ldapconnection = ldap_connect_moodle($authldap->config->host_url, $authldap->config->ldap_version,
                                                 $authldap->config->user_type, $binddn,
                                                 $bindpw, $optderef,
                                                 $debuginfo, $starttls)) {
            $authldap->ldapconns = 1;
            $authldap->ldapconnection = $ldapconnection;
            return $ldapconnection;
        }

        debugging(get_string('auth_ldap_noconnect_all', 'auth_ldap'));
        return false;
    }

} // End of class.
