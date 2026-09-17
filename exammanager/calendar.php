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
        'session' => $r->sessionname
    ]
];
}
echo $OUTPUT->header();
echo html_writer::start_div('local-exammanager-app');
echo \local_exammanager\output\navbar::render('calendar');
echo '<div class="local-exammanager-hero"><h2>' . get_string('calendarview', 'local_exammanager') . '</h2><div class="local-exammanager-muted">FullCalendar interactif des examens</div></div>';
echo '<div class="local-exammanager-panel local-exammanager-calendar-shell"><div id="exammanager-fullcalendar" style="min-height:700px;"></div></div>';
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('exammanager-fullcalendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'fr',
        height: 720,
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
        events: <?php echo json_encode($events); ?>,
        eventClick: function(info) {
            const e = info.event, p = e.extendedProps || {};
            let details = 'Quiz : ' + e.title + '\nDébut : ' + e.start.toLocaleString() + '\n';
            if (e.end) {
                details += 'Fin : ' + e.end.toLocaleString() + '\n';
            }
            details += 'Cours : ' + (p.course || '')
                + '\nSalle : ' + (p.room || '')
                + '\nSurveillant : ' + (p.teacher || '')
                + '\nSession : ' + (p.session || '');
            alert(details);
        }
    });
    calendar.render();
});
</script>
<?php
echo html_writer::end_div();
echo $OUTPUT->footer();
