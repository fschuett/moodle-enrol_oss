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
    protected $idnumber_teachers_cat = 'teachercat';
    protected $idnumber_attic_cat = 'atticcat';
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
     * Forces synchronisation of user enrolments for all ldap users 
     * with Open LML server.
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
        $cohorts = $this->get_cohortlist($user->username);
        foreach ($ldap_groups as $group => $groupname) {
            if (!isset($cohorts[$groupname])) {
                $cohortid = $this->get_cohort_id($groupname);
                cohort_add_member($cohortid, $user->id);
                mtrace($this->errorlogtag."added ".$user->id." to cohort ".$cohortid);
            }
        }

        foreach ($cohorts as $cohort) {
            if (!in_array($cohort->idnumber, $ldap_groups)) {
                cohort_remove_member($cohort->id, $user->id);
                mtace("    removed ".$user->id." from cohort ".$cohort->id);
                if (!$DB->record_exists('cohort_members', array('cohortid'=>$cohort->id))) {
                    cohort_delete_cohort($cohortid);
                    mtrace("    removed cohort ".$cohortid);
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
                if ($cat = $DB->get_record('course_categories', array('idnumber'=>$user->username,
                        'parent'=>$this->teacher_obj->id),'*',IGNORE_MULTIPLE)) {
                    if ($DB->count_records('course_categories', array('idnumber'=>$user->username,
                	    'parent'=>$this->teacher_obj->id)) > 1) {
                	if (debugging())
                            trigger_error(' There are more than one matching category with idnumber '.
                		$user->username .' in '.$this->teacher_obj->name .". That is likely to cause problems.",E_USER_WARNING);
            	    }
            	    if (!$this->delete_move_teacher_to_attic($cat)) {
                        debugging($this->errorlogtag . 'could not move teacher category for user ' . $cat->idnumber . ' to attic.');
                    }
                    $edited = true;
                }
            }
            if ($this->config->teachers_category_autocreate AND
                $this->is_teacher($user->username) AND !$this->is_ignored_teacher($user->username)) {
                $cat = $DB->get_record('course_categories', array('idnumber'=>$user->username,
                        'parent'=> $this->teacher_obj->id),'*',IGNORE_MULTIPLE);
                if (!$cat) {
                    $cat = $this->teacher_add_category($user);
                    if (!$cat) {
                        debugging($this->errorlogtag . 'autocreate teacher category failed: ' . $user->username);
                    } else {
                        $edited = true;
                    }
                } else if ($DB->count_records('course_categories', array('idnumber'=>$user->username,
            		'parent'=>$this->teacher_obj->id)) > 1) {
            	    debugging($this->errorlogtag . ' WARNING: there are more than one matching category with idnumber '.
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
     * the settings and the contents of the Open LML server.
     * @return boolean
     * @uses DB,CFG
     */
    public function sync_enrolments() {
        global $CFG, $DB;
        require_once($CFG->libdir . '/coursecatlib.php');
        
        debugging($this->errorlogtag.'sync_enrolments... started '.date("H:i:s"),
            DEBUG_DEVELOPER);
        $ldap_groups = $this->ldap_get_grouplist();

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

        debugging($this->errorlogtag.'sync_enrolments: remove unneeded cohorts... started '
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
        
        debugging($this->errorlogtag.'sync_enrolments: autoremove teacher course categories '
            . ' of removed teachers if requested... started '.date("H:i:s"), DEBUG_DEVELOPER);
        if ($this->config->teachers_category_autoremove) {
            $teachercontext = coursecat::get($this->teacher_obj->id);
            if (empty($teachercontext)) {
	        debugging($this->errorlogtag . 'Could not get teacher context');
	        return false;
            }
            if ($categories = $teachercontext->get_children()) {
                foreach ($categories as $cat) {
                	if (empty($cat->idnumber)) {
                		debugging($this->errorlogtag . 
                				sprintf('teacher category %s number %d without teacher userid',
                						$cat->name, $cat->id));
                		continue;
                	}
                    if (!$this->is_teacher($cat->idnumber) OR $this->is_ignored_teacher($cat->idnumber)) {
                        if (!$this->delete_move_teacher_to_attic($cat)) {
                            debugging($this->errorlogtag . 'could not move teacher category for user ' . $cat->idnumber . ' to attic.');
                        }
                        $edited = true;
                    }
                }
            }
        }

        debugging($this->errorlogtag.'sync_enrolments: autocreate teacher course categories '
            . 'for new teachers if requested... started '.date("H:i:s"), DEBUG_DEVELOPER);
        if ($this->config->teachers_category_autocreate) {
            foreach ($this->teacher_array as $teacher) {
                if (empty($teacher) OR $this->is_ignored_teacher($teacher)) {
                    continue;
                }
                $user = $DB->get_record('user', array('username'=>$teacher, 'auth' => 'ldap'));
                $cat_obj = $DB->get_record('course_categories',
                        array('idnumber'=>$teacher, 'parent' => $this->teacher_obj->id),'*',IGNORE_MULTIPLE);

                // Autocreate/move teacher category.
                if (empty($cat_obj)) {
                    $cat_obj = $this->teacher_add_category($user);
                    if (!$cat_obj) {
                        debugging($this->errorlogtag . 'autocreate teacher category failed: ' . $teacher);
                        continue;
                    }
                    $edited = true;
                } else if ($DB->count_records('course_categories',
                        array('idnumber'=>$teacher, 'parent' => $this->teacher_obj->id)) > 1) {
            	    debugging($this->errorlogtag . ' WARNING: there are more than one matching category with idnumber '.
        		    $teacher .' in '.$this->teacher_obj->name .". That is likely to cause problems.");

                }
                if (!$this->teacher_has_role($user,$cat_obj)) {
                    $this->teacher_assign_role($user,$cat_obj);
                }
            }
        }
        if ($edited) {
            debugging($this->errorlogtag.'sync_enrolments: resort categories... started '
                . date("H:i:s"), DEBUG_DEVELOPER);
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

        if (empty($user)) {
            $params = array('auth' => 'ldap', 'city' => '');
            if (!$DB->set_field('user', 'city', $CFG->defaultcity, $params)) {
                debugging($this->errorlogtag . "update of city field for many users failed.");
            }
        } else {
            if ($user->city == '') {
                if (!$DB->set_field('user', 'city', $CFG->defaultcity, array('id' => $user->id))) {
                    debugging($this->errorlogtag . 'update of city field for user ' . $user->username .
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
        debugging($this->errorlogtag.'sync_cohort_enrolments... started '.date("H:i:s"), DEBUG_DEVELOPER);
        $edited = false;

        $enrol = enrol_get_plugin('cohort');
        //add cohorts for idcohorts
        $courses = $DB->get_recordset_select('course', "idnumber != ''");
        foreach ($courses as $course) {
            $idcohort[$course->id] = $this->get_idnumber_cohorts($course->id,$course->idnumber,$course->shortname);
            $cohorts = $this->get_coursecohortlist($course->id);
            foreach ($idcohort[$course->id] as $group) {
                if (!isset($cohorts[$group]) AND $cohortid=$this->get_cohort_id($group, false)) {
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

        //remove cohorts not in idcohorts
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
        else if (($pos=strpos($idnumber, ':')) !== false) {
            $groups = explode(',', substr($idnumber, $pos+1));
            $DB->set_field('course', 'idnumber',
                    $shortname . ':' . implode($groups), array('id'=>$courseid));
        }
        else {
            $DB->set_field('course', 'idnumber',
                    $shortname . ':' . $idnumber, array('id'=>$courseid));
            $groups = explode(',', $idnumber);
        }
        return $groups;
    }

    /**
     * return all groups from LDAP which match search criteria defined in settings
     * @return string[]
     */
    private function ldap_get_grouplist($username = "*") {
        global $CFG, $DB;
        
        debugging($this->errorlogtag.'ldap_get_grouplist... started '.date("H:i:s"),
            DEBUG_DEVELOPER);
        if (!isset($authldap) or empty($authldap)) {
            $authldap = get_auth_plugin('ldap');
        }
        debugging($this->errorlogtag.'ldap_get_grouplist... ldap_connect '.date("H:i:s"),
            DEBUG_DEVELOPER);
        $ldapconnection = $this->ldap_connect_ul($authldap);
        debugging($this->errorlogtag.'ldap_get_grouplist... ldap_connected '.date("H:i:s"),
            DEBUG_DEVELOPER);
        $fresult = array ();
        if (!$ldapconnection) {
            return $fresult;
        }
        if ($username !== "*") {
            $filter = '(' . $this->config->member_attribute . '=' . $username . ')';
        } else {
            $filter = '';
        }
        $filter = '(&' . $this->ldap_generate_group_pattern() . $filter . '(objectclass=' . $this->config->object . '))';
        $contexts = explode(';', $this->config->contexts);
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
        debugging($this->errorlogtag.'ldap_get_grouplist... ldap_close '.date("H:i:s"),
            DEBUG_DEVELOPER);
        $authldap->ldap_close();
        debugging($this->errorlogtag.'ldap_get_grouplist... ldap_closed '.date("H:i:s"),
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
     * search for group members on a Open LML server with defined search criteria
     * @return string[] array of usernames
     */
    private function ldap_get_group_members($group, $teachers_ok = false) {
        global $CFG, $DB;

        debugging($this->errorlogtag.'ldap_get_group_members('.$group.')... started '.date("H:i:s"),
            DEBUG_DEVELOPER);
        $ret = array ();
        $members = array ();
        if (!isset($authldap) or empty($authldap)) {
            $authldap = get_auth_plugin('ldap');
        }
        debugging($this->errorlogtag.'ldap_get_groupmembers... ldap_connect '.date("H:i:s"),
            DEBUG_DEVELOPER);
        $ldapconnection = $this->ldap_connect_ul($authldap);
        debugging($this->errorlogtag.'ldap_get_grouplist... ldap_connected '.date("H:i:s"),
            DEBUG_DEVELOPER);

        $group = core_text::convert($group, 'utf-8', $this->config->ldapencoding);

        if (!$ldapconnection) {
            return $ret;
        }
        debugging($this->errorlogtag.'ldap_get_group_members... connected to ldap '.date("H:i:s"),
            DEBUG_DEVELOPER);
        $queryg = "(&(cn=" . trim($group) . ")(objectClass=" . $this->config->object . "))";
        $contexts = explode(';', $this->config->contexts);

        foreach ($contexts as $context) {
            $context = trim($context);
            if (empty ($context)) {
                continue;
            }
            
            debugging($this->errorlogtag . 
                sprintf('ldap_get_group_members... ldap_search(%s|%s) %s',
                    $context, $queryg, date("H:i:s")), DEBUG_DEVELOPER);
            $resultg = ldap_search($ldapconnection, $context, $queryg);

            if (!empty ($resultg) AND ldap_count_entries($ldapconnection, $resultg)) {
                debugging($this->errorlogtag.'ldap_get_group_members... ldap_get_entries()'
                    .date("H:i:s"), DEBUG_DEVELOPER);
                $entries = ldap_get_entries($ldapconnection, $resultg);

                if (isset($entries[0][$this->config->member_attribute])) {
                    debugging($this->errorlogtag . 
                        sprintf('ldap_get_group_members... entries(%s)(%d) %s',
                            $this->config->member_attribute,
                            count($entries[0][$this->config->member_attribute]),
                            date("H:i:s")), DEBUG_DEVELOPER);
                    for ($g = 0; $g < (count($entries[0][$this->config->member_attribute]) - 1); $g++) {
                        $member = trim($entries[0][$this->config->member_attribute][$g]);
                        if ($member != "" AND ($teachers_ok OR !$this->is_teacher($member))) {
                            $members[] = $member;
                        }
                    }
                }
            }
        }
        debugging($this->errorlogtag.'ldap_get_group_members... ldap_close '.date("H:i:s"),
            DEBUG_DEVELOPER);
        $authldap->ldap_close();
        debugging($this->errorlogtag.'ldap_get_groupmembers... ldap_closed '.date("H:i:s"),
            DEBUG_DEVELOPER);
        foreach ($members as $member) {
            if (isset($select)) {
                $select = $select . ",'".$member."'";
            } else {
                $select = "'" . $member . "'";
            }
        }
        if (isset($select)) {
        	debugging($this->errorlogtag."ldap_get_group_members... (".$select. ") ".date("H:i:s"),
            	DEBUG_DEVELOPER);
        }
        else {
        	debugging($this->errorlogtag."ldap_get_group_members... (no selected users) ".date("H:i:s"),
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
                error_log($this->errorlogtag.'ldap_get_group_members... '.$group.' is empty. '
                    .date("H:i:s"));
            }
        }
        return $ret;
    }

    private function get_cohort_id($groupname, $autocreate = true) {
        global $DB;
        
        debugging($this->errorlogtag.'get_cohort_id('.$groupname.')... started '.date("H:i:s"),
            DEBUG_DEVELOPER);
        $params = array (
            'idnumber' => $groupname,
            'component' => 'enrol_openlml',
            'contextid' => SYSCONTEXTID,
        );
        if (!$cohort = $DB->get_record('cohort', $params, '*', IGNORE_MULTIPLE)) {
            if (!$autocreate) {
                return false;
            }
            $cohort = new StdClass();
            $cohort->name = $cohort->idnumber = $groupname;
            $cohort->contextid = SYSCONTEXTID;
            $cohort->component='enrol_openlml';
            $cohort->description=get_string('sync_description', 'enrol_openlml');
            $cohortid = cohort_add_cohort($cohort);
        } else {
            if ($DB->count_records('cohort', $params) > 1) {
                if (debugging())
                    trigger_error(' There are more than one matching cohort with idnumber '.
                        $groupname .'. That is likely to cause problems.',E_USER_WARNING);
            }
            $cohortid = $cohort->id;
        }
        return $cohortid;
    }

    private function get_cohortlist($userid = "*") {
        global $DB;
        if ($userid != "*") {
            $sql = "SELECT DISTINCT c.id, c.idnumber, c.name
                    FROM {cohort} c
                    JOIN {cohort_members} cm ON cm.cohortid = c.id
                    JOIN {user} u ON cm.userid = u.id
                            WHERE (c.component = 'enrol_openlml' 
                                    AND u.username = :userid 
                                    AND u.auth = 'ldap'
                                    AND c.contextid = ".SYSCONTEXTID.")";
            $params['userid'] = $userid;
            $records = $DB->get_records_sql($sql, $params);
        } else {
            $sql = " SELECT DISTINCT c.id, c.idnumber, c.name
                    FROM {cohort} c
                            WHERE c.component = 'enrol_openlml'
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
     * content are cohort enrol instances created by enrol_openlml
     * @return array of array
     */
    private function get_cohortinstancelist() {
        global $DB;
        $ret = array();
        // fill $cohortname: id => idnumber
        $sql = "SELECT DISTINCT c.id,c.idnumber
                FROM {cohort} c WHERE c.component = 'enrol_openlml'
                AND c.contextid=".SYSCONTEXTID;
        $records = $DB->get_records_sql($sql);
        $cohortname = array();
        foreach($records as $record) {
            $cohortname[$record->id] = $record->idnumber;
        }
        // get cohort enrol instances created from enrol_openlml
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
     * given course id and created by enrol_openlml
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
        
        debugging($this->errorlogtag.'get_cohort_members('.$cohortid.')... started '.date("H:i:s"),
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
        debugging($this->errorlogtag.'is_teacher('.$userid.')... started '.date("H:i:s"),
            DEBUG_DEVELOPER);
        if (empty($userid)) {
        	debugging($this->errorlogtag.'is_teacher called with empty userid.');
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
        debugging($this->errorlogtag.'init_teacher_array... started '.date("H:i:s"),
            DEBUG_DEVELOPER);
        $this->teacher_array = $this->ldap_get_group_members($this->config->teachers_group_name, true);
        debugging($this->errorlogtag.'init_teacher_array... ended '.date("H:i:s"),
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
        $cat_obj = $DB->get_record( 'course_categories', array('idnumber'=>$this->idnumber_teachers_cat, 'parent' => 0),'*',IGNORE_MULTIPLE);
        if (!$cat_obj) { // Category doesn't exist.
            $cat_obj = $this->create_category($this->config->teachers_course_context,$this->idnumber_teachers_cat,
                    get_string('teacher_context_desc', 'enrol_openlml'));
            debugging($this->errorlogtag."created teachers course category ".$cat_obj->id, DEBUG_DEVELOPER);
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
        $this->attic_obj = $DB->get_record( 'course_categories', array('idnumber'=>$this->idnumber_attic_cat, 'parent' => 0),'*',IGNORE_MULTIPLE);
        if (!$this->attic_obj) { // Category for removed teachers doesn't exist.
            $this->attic_obj = $this->create_category($this->config->teachers_removed, $this->idnumber_attic_cat,
                    get_string('attic_description', 'enrol_openlml'),0,99999,0);
            debugging($this->errorlogtag."created attic course category ".$cat_obj->id, DEBUG_DEVELOPER);
            if (!$this->attic_obj) {
                debugging($this->errorlogtag .'autocreate/autoremove could not create removed teachers context');
            }
        }
        return $this->attic_obj;
    }


    /**
     * This function deletes an empty teacher category or moves it to attic if not empty.
     * @uses $CFG;
     */
    private function delete_move_teacher_to_attic($teacher) {
        global $CFG;
        require_once($CFG->libdir . '/coursecatlib.php');
        require_once($CFG->libdir . '/questionlib.php');
        
        if (empty($attic_obj)) {
            $attic_obj = $this->get_teacher_attic_category();
        }
        
        if (empty($teacher)) {
            debugging($this->errorlogtag . 'delete_move_teacher_to_attic called with empty parameter.');
            return false;
        }
        
        $deletable = true;
        if (!$teachercat = coursecat::get($teacher->id)) {
            debugging($this->errorlotag . "delete_move_teacher_to_attic could not get category $teacher.");
            return false;
        }
        
        if (!$teachercontext = context_coursecat::instance($teachercat->id)) {
            debugging($this->errorlogtag . "delete_move_teacher_to_attic could not get category context for category $teachercat.");
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
            debugging($this->errorlogtag."removed teacher category ".$teachercat->id, DEBUG_DEVELOPER);
        }
        else {
            $teachercat->change_parent($this->attic_obj);
            debugging($this->errorlogtag."moved teacher category ".$teachercat->id." to attic", DEBUG_DEVELOPER);
        }
        
        return true;
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
            $property = 'name';
            $sortflag = core_collator::SORT_STRING;
            if (!core_collator::asort_objects_by_property($categories, $property, $sortflag)) {
                debugging($this->errorlogtag . 'Sorting with asort_objects_by_property error.');
                return false;
            }
            $count=$teacher_cat->sortorder + 1;
            foreach ($categories as $cat) {
                if ($cat->sortorder != $count) {
                    $DB->set_field('course_categories', 'sortorder', $count, array('id' => $cat->id));
                    context_coursecat::instance($cat->id)->mark_dirty();
                    $count++;
                }
            }
        }
        context_coursecat::instance($teacher_cat->id)->mark_dirty();
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
        	debugging($this->errorlogtag . 'is_ignored_teacher was called with empty userid');
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
     * @return false|category_id
     * @uses $CFG;
     */
    private function teacher_add_category(&$user) {
        global $CFG, $DB;
        require_once($CFG->libdir . '/coursecatlib.php');

        if (empty($user->username) || empty($user->firstname) || empty($user->lastname)) {
        	debugging($this->errorlogtag . 
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
        $cat_obj = $DB->get_record('course_categories', array('idnumber'=>$user->username, 'parent' => $this->attic_obj->id),
                '*',IGNORE_MULTIPLE);
        if ($cat_obj) {
            $coursecat = coursecat::get($cat_obj->id);
            if ($coursecat->can_change_parent($this->teacher_obj->id)) {
                $coursecat->change_parent($this->teacher_obj->id);
                debugging($this->errorlogtag."moved teacher category ".$cat_obj->id." to teachers category", DEBUG_DEVELOPER);
            }
        } else {
            $description = get_string('course_description', 'enrol_openlml') . ' ' .
                    $user->firstname . ' ' .$user->lastname . '(' . $user->username . ').';
            $cat_obj = $this->create_category($user->lastname.",".$user->firstname, $user->username,
                    $description, $this->teacher_obj->id);
            if (!$cat_obj) {
                debugging($this->errorlogtag.'Could not create teacher category for teacher ' . $user->username);
                return false;
            }
            debugging($this->errorlogtag."created teacher category ".$cat_obj->id." for ".$user->id."(".$user->lastname.",".$user->firstname.")", DEBUG_DEVELOPER);
        }
        return $cat_obj;
    }

    /**
     * This function tests if the teachers_course_role for the teacher $user is given to category $cat.
     * @param $user teacher
     * @param $cat category
     * @return false|true
     * @uses $CFG;
     */
    private function teacher_has_role($user, $cat) {
        global $CFG,$DB;
        require_once($CFG->libdir . '/coursecatlib.php');

        if (empty($user)){
            throw new coding_exception('Invalid call to teacher_has_role(), user cannot be empty.');
        }
        if (empty($cat)) {
           throw new coding_exception('Invalid call to teacher_has_role(), cat cannot be empty.');
        }

        // Tests for teachers role.
        $teacherscontext = context_coursecat::instance($cat->id);
        return $DB->record_exists('role_assignments', array('roleid'=>$this->config->teachers_course_role,
                        'contextid'=>$teacherscontext->id, 'userid'=>$user->id, 'component'=>'enrol_openlml'));
    }

    /**
     * This function adds the teachers_course_role for the teacher $user to the given category $cat.
     * @param $user teacher for whom the coursecreator role will be added
     * @param $cat category for whom to add the teacher as role teachers_course_role
     * @return false|true
     * @uses $CFG;
     */
    private function teacher_assign_role($user, $cat) {
        global $CFG;
        require_once($CFG->libdir . '/coursecatlib.php');
        if (empty($user)){
            throw new coding_exception('Invalid call to teacher_assign_role(), user cannot be empty.');
        }
        if (empty($cat)) {
           throw new coding_exception('Invalid call to teacher_assign_role(), cat cannot be empty.');
        }

        // Set teachers role to configured teachers course role.
        $teacherscontext = context_coursecat::instance($cat->id);
        if (!role_assign($this->config->teachers_course_role, $user->id, $teacherscontext, 'enrol_openlml')) {
            debugging($this->errorlogtag . 'could not assign role (' . $this->config->teachers_course_role . ') to user (' .
                    $user->username . ') in context (' . $teacherscontext->id . ').');
            return false;
        }
        debugging($this->errorlogtag."assign teacher role for ".$user->id." in category ".$teacherscontext->id, DEBUG_DEVELOPER);
        return true;
    }

    /**
     * This function removes the teachers_course_role for the teacher $user from the given category $cat.
     * @param $user teacher for whom the coursecreator role will be removed
     * @param $cat category for whom to remove the teacher as role teachers_course_role
     * @return false|true
     * @uses $CFG;
     */
    private function teacher_unassign_role($user, $cat) {
        global $CFG;
        require_once($CFG->libdir . '/coursecatlib.php');

        if (empty($user)){
            throw new coding_exception('Invalid call to teacher_unassign_role(), user cannot be empty.');
        }
        if (empty($cat)) {
           throw new coding_exception('Invalid call to teacher_unassign_role(), cat cannot be empty.');
        }

        // Removes teachers configured course role.
        $teacherscontext = context_coursecat::instance($cat->id);
        role_unassign($this->config->teachers_course_role, $user->id, $teacherscontext, 'enrol_openlml');
        debugging($this->errorlogtag."unassign teacher role for ".$user->id." in category ".$teacherscontext->id, DEBUG_DEVELOPER);
        return true;
    }

    /**
     * This function creates a course category and fixes the category path.
     * name             the new category name
     * idnumber         the new category idnumber
     * description      a descriptive text for the new course category
     * parent           the course_categories parent object or 0 for top level category
     * sortorder        special sort order, 99999 order at end
     * @return          false|category_object
     * @uses            $DB;
     */
    public function create_category ($name, $idnumber, $description, $parent = 0, $sortorder = 0, $visible = 1) {
        global $CFG,$DB;
        require_once($CFG->libdir . '/coursecatlib.php');

        debugging($this->errorlogtag . sprintf("create_category... %s (%s),\n                sortorder(%s) %s",
                 $name, $description, $sortorder, date("H:i:s")), DEBUG_DEVELOPER);
        $data = new stdClass();
        $data->name = $name;
        $data->idnumber = $idnumber;
        $data->description = $description;
        $data->parent = $parent;
        $data->visible = $visible;
        $cat = coursecat::create($data);
        if (!$cat) {
            debugging('Could not insert the new course category '.$cat->name.'('.$cat->idnumber.')');
            return false;
        }
        if ($sortorder != 0) {
            debugging($this->errorlogtag.'Changing course sortorder('.$sortorder.') '
                . date("H:i:s"), DEBUG_DEVELOPER);
            $DB->set_field('course_categories', 'sortorder', $sortorder, array('id' => $cat->id));
            context_coursecat::instance($cat->id)->mark_dirty();
            fix_course_sortorder();
        }
        return $cat;
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
