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
 * Interactive calendar view of scheduled exams.
 *
 * @package    local_exammanager
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require('../../config.php');
require_login();
$context = context_system::instance();
require_capability('local/exammanager:manage', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/exammanager/calendar.php'));
$PAGE->set_title(get_string('calendar', 'local_exammanager'));
$PAGE->set_heading(get_string('pluginname', 'local_exammanager'));
$PAGE->requires->js(new moodle_url('https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js'));
global $DB, $OUTPUT;
$records = $DB->get_records('local_exammanager_codes', null, 'timeopen ASC');
$events = [];
foreach ($records as $r) {
    $events[] = [
        'title' => $r->quizname . (!empty($r->room) ? ' [' . $r->room . ']' : ''),
        'start' => date('c', (int)$r->timeopen),
        'end' => date('c', (int)$r->timeclose),
        'extendedProps' => [
            'course' => $r->course_shortname,
            'room' => $r->room,
            'teacher' => $r->teacher,
            'session' => $r->sessionname,
        ],
    ];
}

$calendarelementid = 'exammanager-fullcalendar';
$locale = substr(current_language(), 0, 2);
$PAGE->requires->js_call_amd('local_exammanager/exam_calendar', 'init', [$calendarelementid, $events, $locale]);

echo $OUTPUT->header();
echo html_writer::start_div('local-exammanager-app');
echo \local_exammanager\output\navbar::render('calendar');
echo $OUTPUT->render_from_template('local_exammanager/hero', [
    'title' => get_string('calendarview', 'local_exammanager'),
    'subtitle' => get_string('calendar_hero_subtitle', 'local_exammanager'),
]);
echo $OUTPUT->render_from_template('local_exammanager/calendar_panel', [
    'elementid' => $calendarelementid,
]);
echo html_writer::end_div();
echo $OUTPUT->footer();
