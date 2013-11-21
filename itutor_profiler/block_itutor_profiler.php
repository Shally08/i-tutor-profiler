<?php

/**
 * Form for editing HTML block instances.
 *
 * @package   block_itutor_profiler
 * @copyright 2012 onwards Karsten Øster Lundqvist, University of Reading, ITUTOR
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_itutor_profiler extends block_base {
	
    function init() {
        $this->title = get_string('I-TUTORProfiler', 'block_itutor_profiler');
    }
	
	function has_config() {return true;}

    function get_content() {

        if ($this->content !== NULL) {
            return $this->content;
        }
		
		$this->content =  new stdClass;		
		$this->content->text = "Profiling running: ";
		return $this->content;
    }
	
	
	public function instance_config_save($data) {
		global $DB, $USER;
		$alerts =  $DB->get_records( 'block_itutor_profiler_alerts', array('course_id'=>$data->courseid));
		
		foreach($alerts as $alert) {
			$s = 'isalerting'.$alert->id;
			
			//alert has been unticked
			if(!isset($data->$s)) {
				//remove alert from database
				$DB->delete_records('block_itutor_profiler_alerts', array('id'=>$alert->id));
			}
			
			unset($data->$s);
		}
		
		//If new alert create => add it to the DB
		if($data->alerttype != 0) {
			$record = new stdClass();
			$record->course_id = $data->courseid;
			$record->alert_date = $data->alertdate;
			$record->alert_type = $data->alerttype;
			$record->alert_data = $data->alertdata;
			$record->alert_message = $data->alertmessage;
			if(isset($data->sendtostaff)) {
				$record->alert_sendto = $USER->id;
			}
			else {
				$record->alert_sendto = null;
			}
			$DB->insert_record('block_itutor_profiler_alerts', $record, false); //false, no return, no bulk
		}
		
		unset($data->courseid);
		unset($data->alertdate);
		unset($data->alerttype);
		unset($data->alertdata);
		unset($data->alertmessage);
		unset($data->sendtostaff);
	
		//Call normal save functionality
		return parent::instance_config_save($data);
	}

    public function cron() {
		global $DB;
		
		$this->run_profiler();
		
		// Get the instances of the block
		$instances = $DB->get_records( 'block_instances', array('blockname'=>'itutor_profiler') );
		
		foreach ($instances as $instance) {
			$block = block_instance('itutor_profiler', $instance);
			
			$courses = $DB->get_records('course', array());
			foreach($courses as $course) {
					$this->run_alerter($course);
			}
		}
    }

    private function run_profiler() {
        global $CFG;

		copy($CFG->dirroot."/blocks/itutor_profiler/ClusterRun.jar", "ClusterRun.jar");
        copy($CFG->dirroot."/blocks/itutor_profiler/DatabaseUtils.props", "DatabaseUtils.props");
        $this->copy_directory($CFG->dirroot."/blocks/itutor_profiler/ClusterRun_lib", "ClusterRun_lib");
		
        mtrace("\nRunning block_itutor_profiler cron\n");
        $dummy = "java -jar ClusterRun.jar >> ".$CFG->dirroot."/blocks/itutor_profiler/clusterrun.log";
        mtrace($dummy."\n");
        exec($dummy);
		mtrace("block_itutor_profiler cron finished\n");
    }

    private function run_alerter($course) {
		global $CFG, $DB;
			
		$today = getdate();
		
		mtrace("Alerter started\n");
		
		$alerts = $DB->get_records( 'block_itutor_profiler_alerts', array('course_id'=>$course->id));
		
		foreach($alerts as $alert) {
			mtrace("alert course: " . $course->id . "\n");
			$dateOfAlert = getdate($alert->alert_date);
			
			//TODO: too old alert - should it run anyway?
			if(($today['yday'] > $dateOfAlert['yday'] && $today['year'] == $dateOfAlert['year']) || $today['year'] < $dateOfAlert['year']) {		
				mtrace("Delete old alert: ".$alert->id."\n");
				$DB->delete_records('block_itutor_profiler_alerts', array('id'=>$alert->id));
			}
			
			//day of alert
			if($today['yday'] == $dateOfAlert['yday'] && $today['year'] == $dateOfAlert['year']) {
				mtrace("Do alert: ".$alert->id."\n");

				//the sql used to find userid of "violating" students
				$final_sql = null;
				
				//Find "violating" students depending on alert type 
				switch($alert->alert_type) {
					case 1:
						//Get cluster according to alert data
						$s = "SELECT r.id FROM cluster_runs r inner join (select id, max(date) as maxdate from cluster_runs group by id) tm on r.id = tm.id and r.date = tm.maxdate WHERE clusteringname = '{$alert->alert_data}' and courseid={$course->id}";
						$clusters = $DB->get_records_sql($s);
						if(empty($clusters)) {
							//User made a mistake
							//TODO give proper feedback when mistakes happen
							mtrace("sql returned empty result: " . $s . "\n");
						}
						else {
							foreach($clusters as $cl) {
								mtrace("Finding Zero cluster students in cluster: ".$cl->id . "\n");
								$final_sql = "SELECT userid FROM cluster_user_clusters WHERE clusterid = ".$cl->id." and cluster_user_clusters.cluster = 0";
							}
						}
						break;
					case 2:
						//Get cluster according to alert data
						$s = "SELECT r.id FROM cluster_runs r inner join (select id, max(date) as maxdate from cluster_runs group by id) tm on r.id = tm.id and r.date = tm.maxdate WHERE clusteringname = '{$alert->alert_data}' and courseid={$course->id}";
						$clusters = $DB->get_records_sql($s);
						if(empty($clusters)) {
							//User made a mistake
							//TODO give proper feedback when mistakes happen
							mtrace("sql returned empty result" . $s . "\n");
						}
						else {
							mtrace($s . "\n");
							foreach($clusters as $cl) {
								mtrace("Finding lowest 10% students in cluster: ".$cl->id . "\n");
								$final_sql = "SELECT userid FROM cluster_user_clusters WHERE clusterid = ".$cl->id." and cluster_user_clusters.value<=(
												SELECT MAX(value)
												FROM 
												(
													SELECT cluster_user_clusters.*, @counter := @counter +1 AS counter
													FROM (select @counter:=0) AS initvar, cluster_user_clusters 
													WHERE clusterid = ".$cl->id." 
													ORDER BY value
												) as t
												WHERE counter <= (10/100 * @counter)
											);";
							}
						}
						break;
				}
				
				mtrace("sql statement: " . $final_sql);
				
				if($final_sql != null) {
					$users = $DB->get_records_sql($final_sql);
					if(empty($users)) {
						mtrace("Empty result from: ".$final_sql); 
					}
					else {
						foreach($users as $violating_user) {
							if($alert->alert_sendto == null) {
								mtrace("Mail user: ". $violating_user->userid);
								$result = message_send($this->getMailData($violating_user, $alert->alert_message));
							}
							else {
								mtrace("Mail user: ". $alert->alert_sendto . " about " . $violating_user->userid);
								$result = message_send($this->getMailDataToStaff($alert->alert_sendto, $violating_user, $alert->alert_message));
							}
							mtrace("Alerter sent: result = ".$result."\n");
						}
					}
				}
				
				//Alert has run, so remove it from db now
				$DB->delete_records('block_itutor_profiler_alerts', array('id'=>$alert->id));
			}
		}
    }
	
	private function getMailDataToStaff($sendtoUser, $user, $message) {
		global $DB;
		$admin_user_object = $DB->get_record('user', array('id'=>'2'));
		$user_object = $DB->get_record('user', array('id'=>$user->userid));
		$sendto_user_object = $DB->get_record('user', array('id'=>$sendtoUser));
		
		$eventdata = new object();
		$eventdata->component         = 'block_itutor_profiler';
		$eventdata->name              = 'alert_mail';
		$eventdata->userfrom          = $admin_user_object;
		$eventdata->userto            = $sendto_user_object;
		$eventdata->subject           = "I-TUTOR alerter";
		$eventdata->fullmessage       = $user_object->username . " should be alerted: " .$message;
		$eventdata->fullmessageformat = FORMAT_PLAIN;
		$eventdata->fullmessagehtml   = $message;
		$eventdata->smallmessage      = $message; //TODO add small message functionality?
		
		return $eventdata;
	}
	
	private function getMailData($user, $message) {
		global $DB;
		$admin_user_object = $DB->get_record('user', array('id'=>'2'));
		$user_object = $DB->get_record('user', array('id'=>$user->userid));
		
		$eventdata = new object();
		$eventdata->component         = 'block_itutor_profiler';
		$eventdata->name              = 'alert_mail';
		$eventdata->userfrom          = $admin_user_object;
		$eventdata->userto            = $user_object;
		$eventdata->subject           = "I-TUTOR alerter";
		$eventdata->fullmessage       = $message;
		$eventdata->fullmessageformat = FORMAT_PLAIN;
		$eventdata->fullmessagehtml   = $message;
		$eventdata->smallmessage      = $message; //TODO add small message functionality?
		
		return $eventdata;
	}
	
	private function copy_directory( $source, $destination ) {
		if ( is_dir( $source ) ) {
			@mkdir( $destination );
			$directory = dir( $source );
			while ( FALSE !== ( $readdirectory = $directory->read() ) ) {
				if ( $readdirectory == '.' || $readdirectory == '..' ) {
					continue;
				}
				$PathDir = $source . '/' . $readdirectory; 
				if ( is_dir( $PathDir ) ) {
					copy_directory( $PathDir, $destination . '/' . $readdirectory );
					continue;
				}
				copy( $PathDir, $destination . '/' . $readdirectory );
			}
	 
			$directory->close();
		}else {
			copy( $source, $destination );
		}
	}
}


