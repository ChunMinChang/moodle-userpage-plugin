<?php

/*
 * Author 				: Chun-Min Chang
 * Last update date 	:
 * Note					: This file is encoded in UTF8
 *
 *
 */


//for debug
ini_set("display_errors", true);
//echo "<script type='text/javascript'>alert('debug!');</script>";

//Include jQury 
/*
echo '<link rel="stylesheet" href="http://code.jquery.com/ui/1.10.3/themes/smoothness/jquery-ui.css" />';
*/
echo '<link rel="stylesheet" href="http://140.114.69.143/~GimiChang/moodle19/nthu/nthu_my.css" />';
echo '<script src="http://code.jquery.com/jquery-1.9.1.js"></script>';
echo '<script src="http://code.jquery.com/ui/1.10.3/jquery-ui.js"></script>';
echo 
'<script>
  $(function() {
    $( "#accordion" ).accordion({
      collapsible: true
    });
  });
</script>';





// ========== For Assignments ======================================
require_once('../config.php');
require_once($CFG->libdir.'/blocklib.php');
require_once($CFG->dirroot.'/course/lib.php'); //for nthu_print_overview
require_once($CFG->dirroot.'/mod/assignment/lib.php'); //for nthu_print_overview
require_once('pagelib.php');
	
// ========== For Forum ============================================
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->dirroot.'/mod/forum/lib.php'); //for nthu_print_overview

define('FORUM_MODE_FLATOLDEST', 1);
define('FORUM_MODE_FLATNEWEST', -1);
define('FORUM_MODE_THREADED', 2);
define('FORUM_MODE_NESTED', 3);

define('FORUM_FORCESUBSCRIBE', 1);
define('FORUM_INITIALSUBSCRIBE', 2);
define('FORUM_DISALLOWSUBSCRIBE',3);

define('FORUM_TRACKING_OFF', 0);
define('FORUM_TRACKING_OPTIONAL', 1);
define('FORUM_TRACKING_ON', 2);

define('FORUM_UNSET_POST_RATING', -999);

define ('FORUM_AGGREGATE_NONE', 0); //no ratings
define ('FORUM_AGGREGATE_AVG', 1);
define ('FORUM_AGGREGATE_COUNT', 2);
define ('FORUM_AGGREGATE_MAX', 3);
define ('FORUM_AGGREGATE_MIN', 4);
define ('FORUM_AGGREGATE_SUM', 5);


// ========== For Resource =========================================



function nthu_resource_print_overview($courses,&$htmlarray) {

    global $USER, $CFG;
    //$LIKE = sql_ilike();//no longer using like in queries. MDL-20578

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }
	
    if (!$resources = get_all_instances_in_courses('resource',$courses)) {
        return;
    }
	
	
	//Sort the resource array ordered by timemodified
	function cmpTimemodified( $a, $b ){ 
		if(  $a->timemodified ==  $b->timemodified ){ return 0 ; } 
		//return ($a->timemodified < $b->timemodified) ? -1 : 1;
		return ($a->timemodified < $b->timemodified) ? 1 : -1;
	} 
	usort($resources,'cmpTimemodified');
	
	
	//Setup the threshold of resources 
	
	//Default Setting
	$timeThreshold = 8;//Unit : Day
	$maxNumOfResource = 3;
	
	// ===== Set $maxNumOfResource =====
	$sql = "SELECT * FROM {$CFG->prefix}user_info_field WHERE shortname = 'maxNumOfResource' ";
	$field = get_records_sql($sql);
	
	if( $field!=false ){
		
		$fieldId = key($field);
		if( $fieldId != $field[$fieldId]->id ){
			echo "<script type='text/javascript'>alert('sql error : id of user field');</script>";
			return;
		}
		
		//Set default value to $maxNumOfResource
		if( isset($field[$fieldId]->defaultdata) ){
			$maxNumOfResource = (int)($field[$fieldId]->defaultdata);
		}
		
		//Get the user setting of $maxNumOfResource
		$sql = "SELECT id,data FROM {$CFG->prefix}user_info_data WHERE fieldid = $fieldId AND userid ="."$USER->id";
		$fieldData = get_records_sql($sql);
		if( $fieldData!=false ){
		
			$fieldDataId = key($fieldData);
			if( $fieldDataId != $fieldData[$fieldDataId]->id ){
				echo "<script type='text/javascript'>alert('sql error : id of user data');</script>";
				return;
			}
			//Get the user's setting of $maxNumOfResource
			$maxNumOfResource = $fieldData[$fieldDataId]->data;
			
		}
	}
	//echo $maxNumOfResource."<br />";
	
	
	$countOfResource = array();
	$resourceStr = '資源';
	
	foreach ($resources as $resource) {
	
		if( $resource->timemodified > (time()-24*60*60*$timeThreshold) ){ //If the resource is posted in $timeThreshold day 
			$str = 
			'<div class="overview forum">
				<div class="name">'.$resourceStr.': <a title="'.'上傳日期:'.date('Y-m-d H:i:s',$resource->timemodified).'" href="'.$CFG->wwwroot .'/mod/resource/view.php?r='.$resource->id .'"><b>'.$resource->name .'</b></a></div>
			</div>';
		}else{
			$str = 
			'<div class="overview forum">
				<div class="name">'.$resourceStr.': <a title="'.'上傳日期:'.date('Y-m-d H:i:s',$resource->timemodified).'" href="'.$CFG->wwwroot .'/mod/resource/view.php?r='.$resource->id .'">'.$resource->name .'</a></div>
			</div>';
		}
			
        if (!empty($str)) {
			
			if( isset($countOfResource[$resource->course]) ){
				$countOfResource[$resource->course] += 1;
			}else{
				//Initualize the count of resources of this course
				$countOfResource[$resource->course] = 0;
			}
			
            if (!array_key_exists('resource',$htmlarray[$resource->course])) {
                $htmlarray[$resource->course]['resource'] = ''; // initialize, avoid warnings
            }
			
			if($countOfResource[$resource->course] < $maxNumOfResource ){
				$htmlarray[$resource->course]['resource'] .= $str;
			}
        }

    }
}



	
function nthu_forum_print_overview($courses,&$htmlarray) {

    global $USER, $CFG;
    //$LIKE = sql_ilike();//no longer using like in queries. MDL-20578

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }

    if (!$forums = get_all_instances_in_courses('forum',$courses)) {
        return;
    }
	

    // get all forum logs in ONE query (much better!)
    $sql = "SELECT instance,cmid,l.course,COUNT(l.id) as count FROM {$CFG->prefix}log l "
        ." JOIN {$CFG->prefix}course_modules cm ON cm.id = cmid "
        ." WHERE (";
    foreach ($courses as $course) {
        $sql .= '(l.course = '.$course->id.' AND l.time > '.$course->lastaccess.') OR ';
    }
    $sql = substr($sql,0,-3); // take off the last OR

    $sql .= ") AND l.module = 'forum' AND action = 'add post' "
        ." AND userid != ".$USER->id." GROUP BY cmid,l.course,instance";

    if (!$new = get_records_sql($sql)) {
        $new = array(); // avoid warnings
    }
	
    // also get all forum tracking stuff ONCE.
    $trackingforums = array();
    foreach ($forums as $forum) {
        //if (forum_tp_can_track_forums($forum)) {
            $trackingforums[$forum->id] = $forum;
        //}
    }
	
	
    if (count($trackingforums) > 0) {
        $cutoffdate = isset($CFG->forum_oldpostdays) ? (time() - ($CFG->forum_oldpostdays*24*60*60)) : 0;
        $sql = 'SELECT d.forum,d.course,COUNT(p.id) AS count '.
            ' FROM '.$CFG->prefix.'forum_posts p '.
            ' JOIN '.$CFG->prefix.'forum_discussions d ON p.discussion = d.id '.
            ' LEFT JOIN '.$CFG->prefix.'forum_read r ON r.postid = p.id AND r.userid = '.$USER->id.' WHERE (';
        foreach ($trackingforums as $track) {
            $sql .= '(d.forum = '.$track->id.' AND (d.groupid = -1 OR d.groupid = 0 OR d.groupid = '.get_current_group($track->course).')) OR ';
        }
        $sql = substr($sql,0,-3); // take off the last OR
        $sql .= ') AND p.modified >= '.$cutoffdate.' AND r.id is NULL GROUP BY d.forum,d.course';

        if (!$unread = get_records_sql($sql)) {
            $unread = array();
        }
    } else {
        $unread = array();
    }

    if (empty($unread) and empty($new)) {
        return;
    }
	

    $strforum = get_string('modulename','forum');
    $strnumunread = get_string('overviewnumunread','forum');
    $strnumpostssince = get_string('overviewnumpostssince','forum');

    foreach ($forums as $forum) {
        $str = '';
        $count = 0;
        $thisunread = 0;
        $showunread = false;
        // either we have something from logs, or trackposts, or nothing.
        if (array_key_exists($forum->id, $new) && !empty($new[$forum->id])) {
            $count = $new[$forum->id]->count;
        }
        if (array_key_exists($forum->id,$unread)) {
            $thisunread = $unread[$forum->id]->count;
            $showunread = true;
        }
        if ($count > 0 || $thisunread > 0) {
            $str .= '<div class="overview forum"><div class="name">'.$strforum.': <a title="'.$strforum.'" href="'.$CFG->wwwroot.'/mod/forum/view.php?f='.$forum->id.'">'.
                $forum->name.'</a></div>';
            $str .= '<div class="info">';
            $str .= $count.' '.$strnumpostssince;
            if (!empty($showunread)) {
                $str .= '<br />'.$thisunread .' '.$strnumunread;
            }
            $str .= '</div></div>';
        }
        if (!empty($str)) {
			//echo "<script type='text/javascript'>alert('debug!');</script>";
            if (!array_key_exists($forum->course,$htmlarray)) {
                $htmlarray[$forum->course] = array();
            }
            if (!array_key_exists('forum',$htmlarray[$forum->course])) {
                $htmlarray[$forum->course]['forum'] = ''; // initialize, avoid warnings
            }
            $htmlarray[$forum->course]['forum'] .= $str;
        }
    }
}	
	
	
function assignment_print_by_due($courses, &$assByDue) {

    global $USER, $CFG;

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }

    if (!$assignments = get_all_instances_in_courses('assignment',$courses)) {
        return;
    }

    $assignmentids = array();

    // Do assignment_base::isopen() here without loading the whole thing for speed
    foreach ($assignments as $key => $assignment) {
        $time = time();
        if ($assignment->timedue) {
            if ($assignment->preventlate) {
                $isopen = ($assignment->timeavailable <= $time && $time <= $assignment->timedue);
            } else {
                $isopen = ($assignment->timeavailable <= $time);
            }
        }
        if (empty($isopen) || empty($assignment->timedue)) {
            unset($assignments[$key]);
        }else{
            $assignmentids[] = $assignment->id;
        }
    }

    if(empty($assignmentids)){
        // no assigments to look at - we're done
        return true;
    }

    $strduedate = get_string('duedate', 'assignment');
    $strduedateno = get_string('duedateno', 'assignment');
    $strgraded = get_string('graded', 'assignment');
    $strnotgradedyet = get_string('notgradedyet', 'assignment');
    $strnotsubmittedyet = get_string('notsubmittedyet', 'assignment');
    $strsubmitted = get_string('submitted', 'assignment');
    $strassignment = get_string('modulename', 'assignment');
    $strreviewed = get_string('reviewed','assignment');


    // NOTE: we do all possible database work here *outside* of the loop to ensure this scales

    // build up and array of unmarked submissions indexed by assigment id/ userid
    // for use where the user has grading rights on assigment
    $rs = get_recordset_sql("SELECT id, assignment, userid
                            FROM {$CFG->prefix}assignment_submissions
                            WHERE teacher = 0 AND timemarked = 0
                            AND assignment IN (". implode(',', $assignmentids).")");

    $unmarkedsubmissions = array();
    while ($ra = rs_fetch_next_record($rs)) {
        $unmarkedsubmissions[$ra->assignment][$ra->userid] = $ra->id;
    }
    rs_close($rs);


    // get all user submissions, indexed by assigment id
    $mysubmissions = get_records_sql("SELECT assignment, timemarked, teacher, grade
                                      FROM {$CFG->prefix}assignment_submissions
                                      WHERE userid = {$USER->id} AND
                                      assignment IN (".implode(',', $assignmentids).")");
	
									  
    foreach ($assignments as $assignment) {
		
		$str = '<div class="nthu-assignment-table-row"><div class="nthu-assignment-table-col"><a '.($assignment->visible ? '':' class="dimmed"').
               'title="'.$strassignment.'" href="'.$CFG->wwwroot.
               '/mod/assignment/view.php?id='.$assignment->coursemodule.'">'.
               $assignment->name.'</a></div>';
		
		
        if ($assignment->timedue) {
			
			//$assignment->timedue is a UNIX timestamp, not a MySQL timestamp
			if( $assignment->timedue < $time ){
				$str .= '<div class="nthu-assignment-table-col">'.date('Y-m-d H:i:s',$assignment->timedue).'  <p class="nthu-assignment-delay-text">';
			}else{
				$str .= '<div class="nthu-assignment-table-col">'.date('Y-m-d H:i:s',$assignment->timedue);
			}
			
        } else {
			$str .= '<div class="nthu-assignment-table-col">'.$strduedateno;
        }
        $context = get_context_instance(CONTEXT_MODULE, $assignment->coursemodule);
        if (has_capability('mod/assignment:grade', $context)) {

            // count how many people can submit
            $submissions = 0; // init
            if (!empty($CFG->gradebookroles)) {
                $gradebookroles = explode(",", $CFG->gradebookroles);
            } else {
                $gradebookroles = '';
            }
            $students = get_role_users($gradebookroles, $context, true);
            if ($students) {
                foreach($students as $student){
                    if(isset($unmarkedsubmissions[$assignment->id][$student->id])){
                        $submissions++;
                    }
                }
            }

            if ($submissions) {
                $str .= get_string('submissionsnotgraded', 'assignment', $submissions);
            }
        } else {
            if(isset($mysubmissions[$assignment->id])){

                $submission = $mysubmissions[$assignment->id];
				

                if ($submission->teacher == 0 && $submission->timemarked == 0) {
                    $str .= $strsubmitted . ', ' . $strnotgradedyet;
                } else if ($submission->grade <= 0) {
                    $str .= $strsubmitted . ', ' . $strreviewed;
                } else {
                    $str .= $strsubmitted . ', ' . $strgraded;
                }
            } else {
                $str .= $strnotsubmittedyet . ' ' . assignment_display_lateness(time(), $assignment->timedue);
            }
        }
        
		$str .= '</p></div></div>';
		
        if (empty($assByDue[$assignment->timedue])) {
			if($mysubmissions[$assignment->id]){
				//echo '<script type="text/javascript">alert("已繳交!");</script>';
			}else{
				$assByDue[$assignment->timedue] = $str;
			}
        } else {
			if($mysubmissions[$assignment->id]){
				//echo '<script type="text/javascript">alert("已繳交!");</script>';
			}else{
				$assByDue[$assignment->timedue] = $str;
			}
        }
    }
	ksort($assByDue);
}	



function nthu_print_overview($courses) {

    global $CFG, $USER;

    $htmlarray = array();
    if ($modules = get_records('modules')) {
        foreach ($modules as $mod) {
            if (file_exists(dirname(dirname(__FILE__)).'/mod/'.$mod->name.'/lib.php')) {
                include_once(dirname(dirname(__FILE__)).'/mod/'.$mod->name.'/lib.php');
                $fname = $mod->name .'_print_overview';
				
				//Show all module name
				//echo $mod->name.'</br>';
				
                //For nthu forum
				if ($mod->name=='forum') {
					$fname = 'nthu_forum_print_overview';
					$fname($courses,$htmlarray);
                }else if($mod->name=='resource'){
					$fname = 'nthu_resource_print_overview';
					$fname($courses,$htmlarray);
				}else{
					if (function_exists($fname)) {
						$fname($courses,$htmlarray);
					}/*else{
						echo 'function '.$fname.' does not exist!</br>';
					}*/
				}
            }
        }
    }
	
	//=================  Assignment Overview  ======================
		
	$assByDue = array();
	assignment_print_by_due($courses, $assByDue);
	
	//language Choice
	$assignmentStr = get_string('nthuAssignmentStr', 'assignment');
	$duedateStr = get_string('duedate', 'assignment');
	$assignmentTitleStr = get_string('nthuAssignmentTitle','assignment');
	
	echo '<center>';

	echo '<h2>'.$assignmentTitleStr.'</h2>';
	echo '<div class="nthu-assignment-table">';
	echo '<div class="nthu-assignment-table-heading">
		<div class="nthu-assignment-table-col">'.$assignmentStr.'</div>
		<div class="nthu-assignment-table-col">'.$duedateStr.'</div>
	</div>';
	
    foreach ($assByDue as $dueNum => $asshtml) {
        echo $asshtml;
    }

	echo '</div>';
	echo '</center>';
	echo '</br></br>';
	
	//=================  Assignment Overview  ======================
	
	
	//====================== Course Overview =======================
	echo '<div id="accordion">';
	foreach ($courses as $course) {
		echo '<h3>'. $course->fullname .'</h3>';
		echo '<div><p>';
		if (array_key_exists($course->id,$htmlarray)) {
            foreach ($htmlarray[$course->id] as $modname => $html) {
                echo $html;
				echo "<br />";
            }
        }
		echo '</p></div>';
	}
	echo '</div>';
	
	echo "<br /><br /><br />";
	//====================== Course Overview =======================
	
	/*
    foreach ($courses as $course) {
		
        print_simple_box_start('center', '100%', '', 5, "coursebox");
        $linkcss = '';
        if (empty($course->visible)) {
            $linkcss = 'class="dimmed"';
        }
        print_heading('<a title="'. format_string($course->fullname).'" '.$linkcss.' href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">'. format_string($course->fullname).'</a>');
        if (array_key_exists($course->id,$htmlarray)) {
            foreach ($htmlarray[$course->id] as $modname => $html) {
                echo $html;
            }
        }
        print_simple_box_end();
    }
	*/
}

	
?>