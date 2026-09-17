<?php
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

// Nombre total de questions sur l'ensemble des quiz programmés par le plugin.
$totalquestions = (int)$DB->count_records_sql(
    "SELECT COUNT(qs.id)
       FROM {quiz_slots} qs
       JOIN {local_exammanager_codes} c ON c.quizid = qs.quizid"
);

// Étudiants (rôle étudiant uniquement) inscrits dans les cours touchés par le plugin.
$enrolledstudents = (int)$DB->count_records_sql(
    "SELECT COUNT(DISTINCT ra.userid)
       FROM {role_assignments} ra
       JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = :ctxcourse
       JOIN {role} r ON r.id = ra.roleid AND r.archetype = 'student'
       JOIN {user} u ON u.id = ra.userid AND u.deleted = 0
      WHERE ctx.instanceid IN (SELECT DISTINCT courseid FROM {local_exammanager_codes})",
    ['ctxcourse' => CONTEXT_COURSE]
);

// Nombre moyen d'utilisateurs ayant fait une tentative, par quiz programmé par le plugin.
$avgparticipantsraw = $DB->get_field_sql(
    "SELECT AVG(t.cnt)
       FROM (SELECT c.quizid, COUNT(DISTINCT qa.userid) AS cnt
               FROM {local_exammanager_codes} c
          LEFT JOIN {quiz_attempts} qa ON qa.quiz = c.quizid AND qa.preview = 0
           GROUP BY c.quizid) t"
);
$avgparticipants = round((float)$avgparticipantsraw, 1);

// Taux moyen de participation par cours : pour chaque cours touché par le plugin,
// (étudiants distincts ayant tenté au moins un quiz programmé) / (étudiants inscrits), puis moyenne des taux.
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

// Durée moyenne (tentatives terminées, hors aperçus) sur les quiz programmés par le plugin.
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
echo $OUTPUT->header();
echo html_writer::start_div('local-exammanager-app');
echo \local_exammanager\output\navbar::render('dashboard');
echo '<div class="local-exammanager-hero"><h2>' . get_string('pluginname', 'local_exammanager') . '</h2><div class="local-exammanager-muted">' . get_string('helptext', 'local_exammanager') . '</div><div class="local-exammanager-actions">';
echo html_writer::link(new moodle_url('/local/exammanager/index.php'), get_string('quickprogram', 'local_exammanager'));
echo html_writer::link(new moodle_url('/local/exammanager/activities.php'), get_string('activitiesplanning', 'local_exammanager'));
echo html_writer::link(new moodle_url('/local/exammanager/calendar.php'), get_string('viewcalendar', 'local_exammanager'));
echo html_writer::link(new moodle_url('/local/exammanager/reports.php'), get_string('reports', 'local_exammanager'));
echo '</div></div>';
echo '<div class="local-exammanager-grid">';
foreach ([[get_string('totlexams', 'local_exammanager'), $totlexams],[get_string('roomsused', 'local_exammanager'), $roomsused],[get_string('teachersused', 'local_exammanager'), $teachersused],[get_string('todaysexams', 'local_exammanager'), $todaysexams],[get_string('totalquestions', 'local_exammanager'), $totalquestions],[get_string('enrolledstudents', 'local_exammanager'), $enrolledstudents],[get_string('avgparticipants', 'local_exammanager'), $avgparticipants],[get_string('avgcourseparticipation', 'local_exammanager'), $avgcourseratelabel],[get_string('avgtestduration', 'local_exammanager'), $avgdurationlabel]] as $metric) {
    echo '<div class="local-exammanager-card"><h3>' . s($metric[0]) . '</h3><div class="metric">' . s((string)$metric[1]) . '</div></div>';
}
echo '</div>';
echo '<div class="local-exammanager-panel"><h3 class="local-exammanager-sectiontitle">Graphique analytique</h3><canvas id="exammanager-chart" height="110"></canvas></div>';
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('exammanager-chart');
    new Chart(ctx, { type: 'bar', data: { labels: ['Examens','Salles','Surveillants','Aujourd’hui'], datasets: [{ label: 'ExamManager', data: [<?php echo (int)$totlexams; ?>, <?php echo (int)$roomsused; ?>, <?php echo (int)$teachersused; ?>, <?php echo (int)$todaysexams; ?>] }] }, options: { responsive: true, plugins: { legend: { display: false } } } });
});
</script>
<?php
echo html_writer::end_div();
echo $OUTPUT->footer();
