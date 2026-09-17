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
 * Plugin dashboard with exam scheduling usage metrics.
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
$PAGE->set_url(new moodle_url('/local/exammanager/dashboard.php'));
$PAGE->set_title(get_string('pluginname', 'local_exammanager'));
$PAGE->set_heading(get_string('pluginname', 'local_exammanager'));
$PAGE->requires->js(new moodle_url('https://cdn.jsdelivr.net/npm/chart.js'));
global $DB, $OUTPUT;
$totlexams = $DB->count_records('local_exammanager_codes');
$roomsused = (int)$DB->count_records_sql("SELECT COUNT(DISTINCT room) FROM {local_exammanager_codes}");
$teachersused = (int)$DB->count_records_sql("SELECT COUNT(DISTINCT teacher) FROM {local_exammanager_codes}");
$todaystart = strtotime(date('Y-m-d 00:00:00'));
$todayend = strtotime(date('Y-m-d 23:59:59'));
$todaysexams = $DB->count_records_select('local_exammanager_codes', 'timeopen >= :s AND timeopen <= :e', ['s' => $todaystart, 'e' => $todayend]);

// Total number of questions across every quiz scheduled by the plugin.
$totalquestions = (int)$DB->count_records_sql(
    "SELECT COUNT(qs.id)
       FROM {quiz_slots} qs
       JOIN {local_exammanager_codes} c ON c.quizid = qs.quizid"
);

// Students (student role only) enrolled in the courses targeted by the plugin.
$enrolledstudents = (int)$DB->count_records_sql(
    "SELECT COUNT(DISTINCT ra.userid)
       FROM {role_assignments} ra
       JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :ctxcourse
       JOIN {role} r ON r.id = ra.roleid AND r.archetype = 'student'
       JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
      WHERE ctx.instanceid IN (SELECT DISTINCT courseid FROM {local_exammanager_codes})",
    ['ctxcourse' => CONTEXT_COURSE]
);

// Average number of users who made an attempt, per quiz scheduled by the plugin.
$avgparticipantsraw = $DB->get_field_sql(
    "SELECT AVG(t.cnt)
       FROM (SELECT c.quizid, COUNT(DISTINCT qa.userid) AS cnt
               FROM {local_exammanager_codes} c
          LEFT JOIN {quiz_attempts} qa ON qa.quiz = c.quizid AND qa.preview = 0
           GROUP BY c.quizid) t"
);
$avgparticipants = round((float)$avgparticipantsraw, 1);

// Average participation rate per course: for each course targeted by the
// plugin, (distinct students who attempted at least one scheduled quiz) /
// (enrolled students), then average of those rates.
$enrolledbycourse = $DB->get_records_sql(
    "SELECT ctx.instanceid AS courseid, COUNT(DISTINCT ra.userid) AS enrolled
       FROM {role_assignments} ra
       JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :ctxcourse
       JOIN {role} r ON r.id = ra.roleid AND r.archetype = 'student'
       JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
      WHERE ctx.instanceid IN (SELECT DISTINCT courseid FROM {local_exammanager_codes})
   GROUP BY ctx.instanceid",
    ['ctxcourse' => CONTEXT_COURSE]
);
$participantsbycourse = $DB->get_records_sql(
    "SELECT c.courseid, COUNT(DISTINCT qa.userid) AS participants
       FROM {local_exammanager_codes} c
       JOIN {quiz_attempts} qa ON qa.quiz = c.quizid AND qa.preview = 0
       JOIN {context} ctx ON ctx.instanceid = c.courseid AND ctx.contextlevel = :ctxcourse
       JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = qa.userid
       JOIN {role} r ON r.id = ra.roleid AND r.archetype = 'student'
   GROUP BY c.courseid",
    ['ctxcourse' => CONTEXT_COURSE]
);
$rates = [];
foreach ($enrolledbycourse as $courseid => $info) {
    if ((int)$info->enrolled <= 0) {
        continue;
    }
    $participants = isset($participantsbycourse[$courseid]) ? (int)$participantsbycourse[$courseid]->participants : 0;
    $rates[] = $participants / (int)$info->enrolled;
}
$avgcourseratelabel = empty($rates) ? '-' : (round(array_sum($rates) / count($rates) * 100, 1) . ' %');

// Average duration (finished attempts, previews excluded) on quizzes scheduled by the plugin.
$avgdurationraw = $DB->get_field_sql(
    "SELECT AVG(qa.timefinish - qa.timestart)
       FROM {quiz_attempts} qa
       JOIN {local_exammanager_codes} c ON c.quizid = qa.quiz
      WHERE qa.preview = 0 AND qa.state = 'finished' AND qa.timefinish > qa.timestart"
);
$avgdurationseconds = (int)round((float)$avgdurationraw);
$avgdurationminutes = (int)round($avgdurationseconds / 60);
if ($avgdurationseconds <= 0) {
    $avgdurationlabel = '-';
} else if ($avgdurationseconds < 3600) {
    $avgdurationlabel = $avgdurationminutes . ' min';
} else {
    $avgdurationlabel = intdiv($avgdurationseconds, 3600) . ' h ' . str_pad((string)intdiv($avgdurationseconds % 3600, 60), 2, '0', STR_PAD_LEFT) . ' min';
}
$chartcanvasid = 'exammanager-chart';
$PAGE->requires->js_call_amd('local_exammanager/dashboard_chart', 'init', [
    $chartcanvasid,
    [
        get_string('totlexams', 'local_exammanager'),
        get_string('roomsused', 'local_exammanager'),
        get_string('teachersused', 'local_exammanager'),
        get_string('todaysexams', 'local_exammanager'),
    ],
    [(int)$totlexams, (int)$roomsused, (int)$teachersused, (int)$todaysexams],
]);

echo $OUTPUT->header();
echo html_writer::start_div('local-exammanager-app');
echo \local_exammanager\output\navbar::render('dashboard');
echo $OUTPUT->render_from_template('local_exammanager/hero', [
    'title' => get_string('pluginname', 'local_exammanager'),
    'subtitle' => get_string('helptext', 'local_exammanager'),
    'hasactions' => true,
    'actions' => [
        ['url' => (new moodle_url('/local/exammanager/index.php'))->out(false), 'label' => get_string('quickprogram', 'local_exammanager')],
        ['url' => (new moodle_url('/local/exammanager/activities.php'))->out(false), 'label' => get_string('activitiesplanning', 'local_exammanager')],
        ['url' => (new moodle_url('/local/exammanager/calendar.php'))->out(false), 'label' => get_string('viewcalendar', 'local_exammanager')],
        ['url' => (new moodle_url('/local/exammanager/reports.php'))->out(false), 'label' => get_string('reports', 'local_exammanager')],
    ],
]);

$metrics = [
    [get_string('totlexams', 'local_exammanager'), $totlexams],
    [get_string('roomsused', 'local_exammanager'), $roomsused],
    [get_string('teachersused', 'local_exammanager'), $teachersused],
    [get_string('todaysexams', 'local_exammanager'), $todaysexams],
    [get_string('totalquestions', 'local_exammanager'), $totalquestions],
    [get_string('enrolledstudents', 'local_exammanager'), $enrolledstudents],
    [get_string('avgparticipants', 'local_exammanager'), $avgparticipants],
    [get_string('avgcourseparticipation', 'local_exammanager'), $avgcourseratelabel],
    [get_string('avgtestduration', 'local_exammanager'), $avgdurationlabel],
];
echo $OUTPUT->render_from_template('local_exammanager/metric_grid', [
    'metrics' => array_map(function (array $metric): array {
        return ['label' => $metric[0], 'value' => (string)$metric[1]];
    }, $metrics),
]);

echo $OUTPUT->render_from_template('local_exammanager/chart_panel', [
    'title' => get_string('analyticschart', 'local_exammanager'),
    'canvasid' => $chartcanvasid,
]);

echo html_writer::end_div();
echo $OUTPUT->footer();
