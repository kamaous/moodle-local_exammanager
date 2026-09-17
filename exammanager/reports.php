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
 * Room and supervisor scheduling conflict reports.
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
$PAGE->set_url(new moodle_url('/local/exammanager/reports.php'));
$PAGE->set_title(get_string('reports', 'local_exammanager'));
$PAGE->set_heading(get_string('pluginname', 'local_exammanager'));
global $DB, $OUTPUT;
$roomconflicts = $DB->get_records_sql("SELECT room, timeopen, timeclose, COUNT(*) AS total FROM {local_exammanager_codes} WHERE room <> '' GROUP BY room, timeopen, timeclose HAVING COUNT(*) > 1 ORDER BY timeopen ASC");
$teacherconflicts = $DB->get_records_sql("SELECT teacher, timeopen, timeclose, COUNT(*) AS total FROM {local_exammanager_codes} WHERE teacher <> '' GROUP BY teacher, timeopen, timeclose HAVING COUNT(*) > 1 ORDER BY timeopen ASC");
echo $OUTPUT->header();
echo html_writer::start_div('local-exammanager-app');
echo \local_exammanager\output\navbar::render('reports');
echo '<div class="local-exammanager-hero"><h2>' . get_string('reports', 'local_exammanager') . '</h2><div class="local-exammanager-muted">Détection automatique des conflits d’examens</div></div>';
echo '<div class="local-exammanager-grid">';
echo '<div class="local-exammanager-panel"><h3 class="local-exammanager-sectiontitle">' . get_string('conflictrooms', 'local_exammanager') . '</h3>';
if ($roomconflicts) {
    $t = new html_table(); $t->head = ['Salle', 'Ouverture', 'Fermeture', 'Total'];
    foreach ($roomconflicts as $c) $t->data[] = [s($c->room), s(userdate($c->timeopen, '%Y-%m-%d %H:%M')), s(userdate($c->timeclose, '%Y-%m-%d %H:%M')), s((string)$c->total)];
    echo html_writer::table($t);
} else echo html_writer::div('Aucun conflit de salle', 'local-exammanager-muted');
echo '</div>';
echo '<div class="local-exammanager-panel"><h3 class="local-exammanager-sectiontitle">' . get_string('conflictteachers', 'local_exammanager') . '</h3>';
if ($teacherconflicts) {
    $t = new html_table(); $t->head = ['Surveillant', 'Ouverture', 'Fermeture', 'Total'];
    foreach ($teacherconflicts as $c) $t->data[] = [s($c->teacher), s(userdate($c->timeopen, '%Y-%m-%d %H:%M')), s(userdate($c->timeclose, '%Y-%m-%d %H:%M')), s((string)$c->total)];
    echo html_writer::table($t);
} else echo html_writer::div('Aucun conflit de surveillant', 'local-exammanager-muted');
echo '</div></div>';
echo html_writer::end_div();
echo $OUTPUT->footer();
