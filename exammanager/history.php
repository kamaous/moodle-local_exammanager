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
 * Filterable, paginated history of scheduled exams.
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
$PAGE->set_url(new moodle_url('/local/exammanager/history.php'));
$PAGE->set_title(get_string('history', 'local_exammanager'));
$PAGE->set_heading(get_string('pluginname', 'local_exammanager'));

global $DB, $OUTPUT;

$q = trim(optional_param('q', '', PARAM_TEXT));
$courseidfilter = optional_param('courseid', 0, PARAM_INT);
$shortnamefilter = trim(optional_param('shortname', '', PARAM_TEXT));
$teacherfilter = trim(optional_param('teacher', '', PARAM_TEXT));
$roomfilter = trim(optional_param('room', '', PARAM_TEXT));
$sessionfilter = trim(optional_param('session', '', PARAM_TEXT));
$statusfilter = trim(optional_param('status', '', PARAM_ALPHA));
$datefrom = trim(optional_param('datefrom', '', PARAM_TEXT));
$dateto = trim(optional_param('dateto', '', PARAM_TEXT));
$export = optional_param('export', '', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 25, PARAM_INT);
$perpage = min(max($perpage, 10), 100);

$now = time();

$actualcourseid = "CASE WHEN ec.courseid <> 0 THEN ec.courseid ELSE qz.course END";
$actualtimeopen = "CASE WHEN qz.timeopen IS NOT NULL AND qz.timeopen <> 0 THEN qz.timeopen ELSE ec.timeopen END";
$actualtimeclose = "CASE WHEN qz.timeclose IS NOT NULL AND qz.timeclose <> 0 THEN qz.timeclose ELSE ec.timeclose END";
$actualtimelimit = "CASE WHEN qz.timelimit IS NOT NULL THEN qz.timelimit ELSE ec.timelimit END";

$fromsql = "
    FROM {local_exammanager_codes} ec
    LEFT JOIN {quiz} qz ON qz.id = ec.quizid
    LEFT JOIN {course} c ON c.id = $actualcourseid
    LEFT JOIN {modules} md ON md.name = :modulename
    LEFT JOIN {course_modules} cm ON cm.module = md.id AND cm.instance = ec.quizid AND cm.course = c.id
    LEFT JOIN {course_sections} cs ON cs.id = cm.section
";

$where = [];
$params = ['modulename' => 'quiz'];

if ($q !== '') {
    $likesql = $DB->sql_like(
        $DB->sql_concat('ec.quizname', "' '", 'ec.course_shortname', "' '", 'ec.teacher', "' '", 'ec.room', "' '", 'ec.sessionname', "' '", 'c.fullname', "' '", 'c.shortname', "' '", 'qz.name'),
        ':q',
        false,
        false
    );
    $where[] = $likesql;
    $params['q'] = '%' . $DB->sql_like_escape($q) . '%';
}

if ($courseidfilter > 0) {
    $where[] = "$actualcourseid = :courseidfilter";
    $params['courseidfilter'] = $courseidfilter;
}

if ($shortnamefilter !== '') {
    $where[] = $DB->sql_like('COALESCE(c.shortname, ec.course_shortname)', ':shortnamefilter', false, false);
    $params['shortnamefilter'] = $DB->sql_like_escape($shortnamefilter);
}

if ($teacherfilter !== '') {
    $where[] = 'ec.teacher = :teacherfilter';
    $params['teacherfilter'] = $teacherfilter;
}

if ($roomfilter !== '') {
    $where[] = 'ec.room = :roomfilter';
    $params['roomfilter'] = $roomfilter;
}

if ($sessionfilter !== '') {
    $where[] = 'ec.sessionname = :sessionfilter';
    $params['sessionfilter'] = $sessionfilter;
}

if ($datefrom !== '') {
    $fromts = strtotime($datefrom . ' 00:00:00');
    if ($fromts !== false) {
        $where[] = "$actualtimeopen >= :datefromts";
        $params['datefromts'] = $fromts;
    }
}

if ($dateto !== '') {
    $tots = strtotime($dateto . ' 23:59:59');
    if ($tots !== false) {
        $where[] = "$actualtimeopen <= :datetots";
        $params['datetots'] = $tots;
    }
}

$statusconditions = [
    'error' => 'cm.id IS NULL',
    'hidden' => '(cm.id IS NOT NULL AND (COALESCE(c.visible, 1) = 0 OR COALESCE(cs.visible, 1) = 0 OR COALESCE(cm.visible, 1) = 0))',
    'finished' => "(cm.id IS NOT NULL AND COALESCE(c.visible, 1) = 1 AND COALESCE(cs.visible, 1) = 1 AND COALESCE(cm.visible, 1) = 1 AND $actualtimeclose > 0 AND $now > $actualtimeclose)",
    'running' => "(cm.id IS NOT NULL AND COALESCE(c.visible, 1) = 1 AND COALESCE(cs.visible, 1) = 1 AND COALESCE(cm.visible, 1) = 1 AND $actualtimeopen > 0 AND $actualtimeclose > 0 AND $now BETWEEN $actualtimeopen AND $actualtimeclose)",
    'scheduled' => "(cm.id IS NOT NULL AND COALESCE(c.visible, 1) = 1 AND COALESCE(cs.visible, 1) = 1 AND COALESCE(cm.visible, 1) = 1 AND NOT ($actualtimeclose > 0 AND $now > $actualtimeclose) AND NOT ($actualtimeopen > 0 AND $actualtimeclose > 0 AND $now BETWEEN $actualtimeopen AND $actualtimeclose))",
];

if ($statusfilter !== '' && isset($statusconditions[$statusfilter])) {
    $where[] = $statusconditions[$statusfilter];
}

$wheresql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$selectsql = "
    SELECT ec.*,
           c.id AS actualcourseid,
           c.fullname AS coursefullname,
           c.shortname AS courseshortnamecurrent,
           c.visible AS coursevisible,
           qz.name AS quiznamecurrent,
           $actualtimeopen AS actualtimeopen,
           $actualtimeclose AS actualtimeclose,
           $actualtimelimit AS actualtimelimit,
           cm.id AS cmid,
           cm.visible AS cmvisible,
           cs.section AS sectionnum,
           cs.name AS sectionnamecurrent,
           cs.visible AS sectionvisible
    $fromsql
    $wheresql
    ORDER BY ec.timemodified DESC, actualtimeopen DESC, ec.id DESC
";

$countsql = "SELECT COUNT(1) $fromsql $wheresql";
$totalrows = (int)$DB->count_records_sql($countsql, $params);

$statssql = "
    SELECT
        COUNT(1) AS total,
        SUM(CASE WHEN {$statusconditions['running']} THEN 1 ELSE 0 END) AS running,
        SUM(CASE WHEN {$statusconditions['hidden']} THEN 1 ELSE 0 END) AS hidden,
        SUM(CASE WHEN {$statusconditions['finished']} THEN 1 ELSE 0 END) AS finished,
        SUM(CASE WHEN {$statusconditions['error']} THEN 1 ELSE 0 END) AS errors
    $fromsql
    $wheresql
";
$statsrecord = $DB->get_record_sql($statssql, $params);
$stats = [
    'total' => (int)($statsrecord->total ?? 0),
    'running' => (int)($statsrecord->running ?? 0),
    'hidden' => (int)($statsrecord->hidden ?? 0),
    'finished' => (int)($statsrecord->finished ?? 0),
    'errors' => (int)($statsrecord->errors ?? 0),
];

$courseoptions = [0 => get_string('all')];
$courseoptionrecords = $DB->get_records_sql("
    SELECT DISTINCT c.id, c.fullname, c.shortname
      FROM {local_exammanager_codes} ec
      JOIN {quiz} qz ON qz.id = ec.quizid
      JOIN {course} c ON c.id = CASE WHEN ec.courseid <> 0 THEN ec.courseid ELSE qz.course END
     ORDER BY c.fullname ASC
", [], 0, 500);
foreach ($courseoptionrecords as $courseoption) {
    $courseoptions[(int)$courseoption->id] = format_string($courseoption->fullname) . ' (' . s($courseoption->shortname) . ')';
}

$teachers = $DB->get_fieldset_sql("SELECT DISTINCT teacher FROM {local_exammanager_codes} WHERE teacher IS NOT NULL AND teacher <> '' ORDER BY teacher ASC", [], 0, 500);
$rooms = $DB->get_fieldset_sql("SELECT DISTINCT room FROM {local_exammanager_codes} WHERE room IS NOT NULL AND room <> '' ORDER BY room ASC", [], 0, 500);
$sessions = $DB->get_fieldset_sql("SELECT DISTINCT sessionname FROM {local_exammanager_codes} WHERE sessionname IS NOT NULL AND sessionname <> '' ORDER BY sessionname ASC", [], 0, 500);

$records = ($export === 'csv')
    ? $DB->get_records_sql($selectsql, $params, 0, 10000)
    : $DB->get_records_sql($selectsql, $params, $page * $perpage, $perpage);

$buildrow = function($record) use ($now) {
    $quizname = trim((string)($record->quiznamecurrent ?? '')) !== '' ? (string)$record->quiznamecurrent : (string)$record->quizname;
    $quizlink = s($quizname !== '' ? $quizname : ('Quiz #' . (int)$record->quizid));
    if (!empty($record->cmid)) {
        $quizlink = html_writer::link(new moodle_url('/course/modedit.php', ['update' => (int)$record->cmid, 'return' => 0]), s($quizname), ['target' => '_blank', 'rel' => 'noopener']); 
    }

    $coursename = trim((string)($record->coursefullname ?? '')) !== '' ? format_string($record->coursefullname) : ('Cours #' . (int)$record->courseid);
    $courselink = '-';
    if (!empty($record->actualcourseid)) {
        $courselink = html_writer::link(new moodle_url('/course/view.php', ['id' => (int)$record->actualcourseid]), $coursename, ['target' => '_blank', 'rel' => 'noopener']);
    }

    $sectionname = '-';
    if (trim((string)($record->sectionnamecurrent ?? '')) !== '') {
        $sectionname = format_string($record->sectionnamecurrent);
    } else if (isset($record->sectionnum) && $record->sectionnum !== null) {
        $sectionname = get_string('section') . ' ' . (int)$record->sectionnum;
    }

    $actualtimeopen = (int)($record->actualtimeopen ?? 0);
    $actualtimeclose = (int)($record->actualtimeclose ?? 0);
    $actualtimelimit = (int)($record->actualtimelimit ?? 0);

    $coursevisible = !isset($record->coursevisible) || (int)$record->coursevisible === 1;
    $sectionvisible = !isset($record->sectionvisible) || (int)$record->sectionvisible === 1;
    $quizvisible = !isset($record->cmvisible) || (int)$record->cmvisible === 1;
    $visibilityok = $coursevisible && $sectionvisible && $quizvisible;

    $statuskey = 'scheduled';
    if (empty($record->cmid)) {
        $statuskey = 'error';
    } else if (!$visibilityok) {
        $statuskey = 'hidden';
    } else if (!empty($actualtimeclose) && $now > $actualtimeclose) {
        $statuskey = 'finished';
    } else if (!empty($actualtimeopen) && !empty($actualtimeclose) && $now >= $actualtimeopen && $now <= $actualtimeclose) {
        $statuskey = 'running';
    }

    return [
        'record' => $record,
        'quizlink' => $quizlink,
        'courselink' => $courselink,
        'courseshortname' => s((string)($record->courseshortnamecurrent ?? $record->course_shortname ?? '')),
        'sectionname' => $sectionname,
        'timeopen_display' => !empty($actualtimeopen) ? userdate($actualtimeopen, '%Y-%m-%d %H:%M') : '-',
        'timeclose_display' => !empty($actualtimeclose) ? userdate($actualtimeclose, '%Y-%m-%d %H:%M') : '-',
        'timelimit_display' => !empty($actualtimelimit) ? (string)round($actualtimelimit / 60) . ' min' : '0 min',
        'lastprogrammed' => !empty($record->timemodified) ? userdate((int)$record->timemodified, '%Y-%m-%d %H:%M') : '-',
        'statuskey' => $statuskey,
    ];
};

$rows = [];
foreach ($records as $record) {
    $rows[] = $buildrow($record);
}

if ($export === 'csv') {
    require_sesskey();
    $filename = 'exammanager_history_v7_4_secure_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Cours', 'Shortname', 'Quiz/Test', 'Section', 'Ouverture', 'Fermeture', 'Duree', 'Derniere programmation'], ';');
    foreach ($rows as $row) {
        fputcsv($out, [
            trim(strip_tags($row['courselink'])),
            trim(strip_tags($row['courseshortname'])),
            trim(strip_tags($row['quizlink'])),
            trim(strip_tags($row['sectionname'])),
            $row['timeopen_display'],
            $row['timeclose_display'],
            $row['timelimit_display'],
            $row['lastprogrammed'],
        ], ';');
    }
    fclose($out);
    exit;
}

echo $OUTPUT->header();
echo html_writer::start_div('local-exammanager-app');
echo \local_exammanager\output\navbar::render('history');

echo '<div class="local-exammanager-hero"><h2>' . get_string('history', 'local_exammanager') . '</h2><div class="local-exammanager-muted">Vue d’historique filtrée côté SQL avec pagination, sans exposition des codes de sécurité.</div></div>';

echo '<div class="local-exammanager-grid">';
echo '<div class="local-exammanager-card"><h3>' . get_string('totlexams', 'local_exammanager') . '</h3><div class="metric">' . (int)$stats['total'] . '</div></div>';
echo '<div class="local-exammanager-card"><h3>' . get_string('historystatsrunning', 'local_exammanager') . '</h3><div class="metric">' . (int)$stats['running'] . '</div></div>';
echo '<div class="local-exammanager-card"><h3>' . get_string('historystatshidden', 'local_exammanager') . '</h3><div class="metric">' . (int)$stats['hidden'] . '</div></div>';
echo '<div class="local-exammanager-card"><h3>' . get_string('historystatsfinished', 'local_exammanager') . '</h3><div class="metric">' . (int)$stats['finished'] . '</div></div>';
echo '<div class="local-exammanager-card"><h3>' . get_string('rowsinerror', 'local_exammanager') . '</h3><div class="metric">' . (int)$stats['errors'] . '</div></div>';
echo '</div>';

$baseparams = [
    'q' => $q,
    'courseid' => $courseidfilter,
    'shortname' => $shortnamefilter,
    'teacher' => $teacherfilter,
    'room' => $roomfilter,
    'session' => $sessionfilter,
    'status' => $statusfilter,
    'datefrom' => $datefrom,
    'dateto' => $dateto,
    'perpage' => $perpage,
];
$exporturl = new moodle_url('/local/exammanager/history.php', $baseparams + ['sesskey' => sesskey(), 'export' => 'csv']);
$reseturl = new moodle_url('/local/exammanager/history.php');
$pageurl = new moodle_url('/local/exammanager/history.php', $baseparams);

echo '<div class="local-exammanager-panel">';
echo '<h3 class="local-exammanager-sectiontitle">Filtres et recherche</h3>';
echo '<form method="get" action="' . new moodle_url('/local/exammanager/history.php') . '">';
echo '<div class="local-exammanager-formrow">';
echo '<div><label for="id_q">Recherche</label><input class="form-control" type="text" id="id_q" name="q" value="' . s($q) . '" placeholder="Cours, quiz, salle, surveillant, session"></div>';
echo '<div><label for="id_courseid">Cours</label><select class="custom-select" id="id_courseid" name="courseid">';
foreach ($courseoptions as $value => $label) {
    $selected = ((int)$value === (int)$courseidfilter) ? ' selected' : '';
    echo '<option value="' . (int)$value . '"' . $selected . '>' . s($label) . '</option>';
}
echo '</select></div>';
echo '<div><label for="id_shortname">Shortname</label><input class="form-control" type="text" id="id_shortname" name="shortname" value="' . s($shortnamefilter) . '"></div>';
echo '<div><label for="id_status">Statut</label><select class="custom-select" id="id_status" name="status">';
$statusoptions = [
    '' => get_string('all'),
    'scheduled' => get_string('historystatusscheduled', 'local_exammanager'),
    'running' => get_string('historystatusrunning', 'local_exammanager'),
    'finished' => get_string('historystatusfinished', 'local_exammanager'),
    'hidden' => get_string('historystatushidden', 'local_exammanager'),
    'error' => get_string('historystatuserror', 'local_exammanager'),
];
foreach ($statusoptions as $value => $label) {
    $selected = ($value === $statusfilter) ? ' selected' : '';
    echo '<option value="' . s($value) . '"' . $selected . '>' . s($label) . '</option>';
}
echo '</select></div>';
echo '<div><label for="id_teacher">Surveillant</label><select class="custom-select" id="id_teacher" name="teacher"><option value="">' . get_string('all') . '</option>';
foreach ($teachers as $value) {
    $selected = ($value === $teacherfilter) ? ' selected' : '';
    echo '<option value="' . s($value) . '"' . $selected . '>' . s($value) . '</option>';
}
echo '</select></div>';
echo '<div><label for="id_room">Salle</label><select class="custom-select" id="id_room" name="room"><option value="">' . get_string('all') . '</option>';
foreach ($rooms as $value) {
    $selected = ($value === $roomfilter) ? ' selected' : '';
    echo '<option value="' . s($value) . '"' . $selected . '>' . s($value) . '</option>';
}
echo '</select></div>';
echo '<div><label for="id_session">Session</label><select class="custom-select" id="id_session" name="session"><option value="">' . get_string('all') . '</option>';
foreach ($sessions as $value) {
    $selected = ($value === $sessionfilter) ? ' selected' : '';
    echo '<option value="' . s($value) . '"' . $selected . '>' . s($value) . '</option>';
}
echo '</select></div>';
echo '<div><label for="id_datefrom">Date début</label><input class="form-control" type="date" id="id_datefrom" name="datefrom" value="' . s($datefrom) . '"></div>';
echo '<div><label for="id_dateto">Date fin</label><input class="form-control" type="date" id="id_dateto" name="dateto" value="' . s($dateto) . '"></div>';
echo '<div><label for="id_perpage">Lignes/page</label><select class="custom-select" id="id_perpage" name="perpage">';
foreach ([10, 25, 50, 100] as $option) {
    $selected = ((int)$option === (int)$perpage) ? ' selected' : '';
    echo '<option value="' . (int)$option . '"' . $selected . '>' . (int)$option . '</option>';
}
echo '</select></div>';
echo '</div>';
echo '<div class="local-exammanager-actions">';
echo '<input type="submit" value="Filtrer">';
echo html_writer::link($reseturl, 'Réinitialiser');
echo html_writer::link($exporturl, 'Exporter CSV');
echo '</div>';
echo '</form>';
echo '</div>';

echo '<div class="local-exammanager-panel">';
echo '<h3 class="local-exammanager-sectiontitle">Historique des programmations</h3>';
echo html_writer::div('Les codes d’accès et codes de sortie Safe Exam Browser ne sont pas affichés dans cette vue.', 'local-exammanager-muted');

echo $OUTPUT->paging_bar($totalrows, $page, $perpage, $pageurl);

if ($rows) {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable local-exammanager-history-table';
    $table->head = ['Cours', 'Shortname', 'Quiz/Test', 'Section', 'Ouverture', 'Fermeture', 'Durée', 'Dernière programmation'];
    foreach ($rows as $row) {
        $table->data[] = [$row['courselink'], $row['courseshortname'] !== '' ? $row['courseshortname'] : '-', $row['quizlink'], $row['sectionname'], $row['timeopen_display'], $row['timeclose_display'], $row['timelimit_display'], $row['lastprogrammed']];
    }
    echo '<div class="local-exammanager-tablewrap">';
    echo html_writer::table($table);
    echo '</div>';
} else {
    echo html_writer::div('Aucun enregistrement ne correspond aux filtres actuels.', 'local-exammanager-muted');
}

echo $OUTPUT->paging_bar($totalrows, $page, $perpage, $pageurl);
echo '</div>';
echo html_writer::end_div();
echo $OUTPUT->footer();
