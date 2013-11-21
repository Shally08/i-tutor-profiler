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
 * Form for editing block_itutor_chat instances.
 *
 * @package   block_itutor_profiler
 * @copyright 2013 Karsten Lundqvist
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_itutor_profiler_edit_form extends block_edit_form {
	
    protected function specific_definition($mform) {
        global $CFG, $DB;
		
		$alerttypes = array ( //TODO get_string
			0 => 'NONE',
			1 => 'In Zero Cluster',
			2 => 'Lowest 10% scores in Cluster'
		);
		
		$tabletypes = array ( //TODO get_string
			'overall' => 'All activities and marks(Overall)',
			'overall_activities' => 'All activities(Overall Activities)',
			'overall_marks' => 'Overall marks received (Overall Marks)',
			'log' => 'Any activity (Log)',
			'post' => 'Post activity (Post)',
			'comments' => 'Comments activity (Comments)',
			'forum_discussions' => 'Forum discussions (Forum Discussions)',
			'forum_posts' => 'Forum posts (Forum Posts)',
			'forum_postsResponder' => 'Responding to forum posts(Forum Posts Responder)',
			'user_lastaccess' => 'User last access (User Lastaccess)',
			'quiz_attempts' => 'Attempting quizzes (Quiz Attempt)',
			'quiz_grades' => 'Grade in quizzes (Quiz Grades)',
			'grade_grades' => 'Grades received (Grade Grades)',
			'grade_gradesParticipation' => 'Participation in grade activity (Grade Grades Participation)',
			'assignment_submissions' => 'Submission of assignment (Assignment Submisstions)',
			'lesson_grades' => 'Grades received from lesson (Lesson Grades)',
			'question_attempt_steps' => 'Attempts at question (Question Attempt Steps)',
			'message' => 'Unread message created by user (Message)',
			'messageTo' => 'Unread message created to user (Message To)',
			'message_read' => 'Read message created by user (Message Read)',
			'message_readTo' => 'Read message created to user (Lesson Grades)',
			'chat_messages' => 'Chat messages (Chat message)',
			'tag' => 'Tags (Tags)',
			'files' => 'Files created (Files)',
			'my_pages' => 'My page created by user(My Pages)',
			'wiki_pages' => 'Created wiki pages (Wiki |Pages)',
			'event' => 'Events created (Events)'
		);


		$today = getdate();
		$courseid = $this->page->course->id;
		
		
        $mform->addElement('header', 'configheader', get_string('alerts', 'block_itutor_profiler'));
		
		$alerts = $DB->get_records( 'block_itutor_profiler_alerts', array('course_id'=>$courseid));
		foreach($alerts as $alert) {
			$dateOfAlert = getdate($alert->alert_date);
			
			if(($today['yday'] > $dateOfAlert['yday'] && $today['year'] == $dateOfAlert['year']) || $today['year'] < $dateOfAlert['year']) {
				$DB->delete_records('block_itutor_profiler_alerts', array('id'=>$alert->id));
			}
			else {
				$s = 'config_isalerting'.$alert->id;
				$mform->addElement('checkbox', $s , "DATE: {$dateOfAlert['mday']}/{$dateOfAlert['mon']}/{$dateOfAlert['year']} TYPE: {$alerttypes[$alert->alert_type]} DATA: {$alert->alert_data}");
				$mform->setDefault($s, true);
				$mform->setType($s, PARAM_BOOL);
			}
		}
		
        $mform->addElement('header', 'addalertheader', get_string('addalert', 'block_itutor_profiler'));
		$mform->addElement('hidden', 'config_courseid', $courseid);
		$mform->addElement('date_selector', 'config_alertdate', get_string('alertday', 'block_itutor_profiler'), array('optional' => false));
		
		$mform->addElement('select', 'config_alerttype', get_string('alerttype', 'block_itutor_profiler'), $alerttypes);
		$mform->addElement('select', 'config_alertdata', get_string('alertdata', 'block_itutor_profiler'), $tabletypes);
		$mform->addElement('textarea', 'config_alertmessage', get_string('alertmessage', 'block_itutor_profiler'), 'wrap="virtual" rows="20" cols="50"');
		
		$mform->addElement('checkbox', 'config_sendtostaff' , get_string('alerttostaff', 'block_itutor_profiler'));
		$mform->setDefault('config_sendtostaff', true);
		$mform->setType('config_sendtostaff', PARAM_BOOL);
    }
}
