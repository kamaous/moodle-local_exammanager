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

// Build the simple_table context for one conflict record set; the template
// HTML-escapes every cell itself, so values here must stay unescaped.
$buildconflicttable = function (array $records, string $labelfield, string $emptymessage): array {
    $rows = [];
    foreach ($records as $record) {
        $rows[] = ['cells' => [
            (string)$record->$labelfield,
            userdate($record->timeopen, '%Y-%m-%d %H:%M'),
            userdate($record->timeclose, '%Y-%m-%d %H:%M'),
            (string)$record->total,
        ]];
    }

    return [
        'hasrows' => !empty($rows),
        'headers' => [
            get_string($labelfield, 'local_exammanager'),
            get_string('open', 'local_exammanager'),
            get_string('close', 'local_exammanager'),
            get_string('total', 'local_exammanager'),
        ],
        'rows' => $rows,
        'emptymessage' => $emptymessage,
    ];
};

echo $OUTPUT->header();
echo html_writer::start_div('local-exammanager-app');
echo \local_exammanager\output\navbar::render('reports');
echo $OUTPUT->render_from_template('local_exammanager/hero', [
    'title' => get_string('reports', 'local_exammanager'),
    'subtitle' => get_string('reports_hero_subtitle', 'local_exammanager'),
]);
echo $OUTPUT->render_from_template('local_exammanager/reports', [
    'roomstitle' => get_string('conflictrooms', 'local_exammanager'),
    'teacherstitle' => get_string('conflictteachers', 'local_exammanager'),
    'rooms' => $buildconflicttable($roomconflicts, 'room', get_string('noroomconflict', 'local_exammanager')),
    'teachers' => $buildconflicttable($teacherconflicts, 'teacher', get_string('noteacherconflict', 'local_exammanager')),
]);
echo html_writer::end_div();
echo $OUTPUT->footer();
