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
 * Main exam planning workflow: upload, preview, edit and program a batch of quizzes.
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
$PAGE->set_url(new moodle_url('/local/exammanager/index.php'));
$PAGE->set_title(get_string('planning', 'local_exammanager'));
$PAGE->set_heading(get_string('pluginname', 'local_exammanager'));

$PAGE->requires->js_call_amd('local_exammanager/planning_dropzone', 'init');

global $SESSION, $OUTPUT, $USER, $DB;

$userid = $USER->id;
$sessionkey = 'local_exammanager_rows_' . $userid;
$sharedcodeskey = 'local_exammanager_sharedcodes_' . $userid;
$lockkey = 'local_exammanager_locked_' . $userid;
$action = optional_param('action', '', PARAM_ALPHA);

// "Allow shared codes" option: remembered in session, enabled by default.
if (!isset($SESSION->$sharedcodeskey)) {
    $SESSION->$sharedcodeskey = 1;
}
if (in_array($action, ['preview', 'refreshrows', 'program'], true) && confirm_sesskey()) {
    $SESSION->$sharedcodeskey = optional_param('allow_shared_codes', 0, PARAM_BOOL) ? 1 : 0;
}
$allowsharedcodes = !empty($SESSION->$sharedcodeskey);

$rows = [];
$downloads = [];

$apply_row_overrides = function(array $rows, array $postedrows): array {
    foreach ($rows as $idx => &$row) {
        if (!isset($postedrows[$idx]) || !is_array($postedrows[$idx])) {
            continue;
        }

        $editablefields = [
            'course_shortname',
            'quiz_name',
            'open_time',
            'close_time',
            'time_limit',
            'access_code_action',
            'seb_action'
        ];

        foreach ($editablefields as $field) {
            if (array_key_exists($field, $postedrows[$idx])) {
                $value = trim((string)$postedrows[$idx][$field]);

                if (in_array($field, ['open_time', 'close_time'], true)) {
                    if ($value === '' && !empty($row[$field])) {
                        continue;
                    }

                    $normalized = \local_exammanager\util::to_datetime_local_value($value);
                    $row[$field] = $normalized !== '' ? $normalized : $value;
                    continue;
                }

                $row[$field] = $value;
            }
        }
    }
    unset($row);

    return $rows;
};

echo $OUTPUT->header();
echo html_writer::start_div('local-exammanager-app');
echo \local_exammanager\output\navbar::render('programming');

echo $OUTPUT->render_from_template('local_exammanager/hero', [
    'title' => get_string('index_hero_title', 'local_exammanager'),
    'subtitle' => get_string('index_hero_subtitle', 'local_exammanager'),
]);

echo '<div class="local-exammanager-panel">';

/* ========================
UPLOAD FORM
======================== */
echo '<br><br>';
echo '<a href="' . new moodle_url('/local/exammanager/download_template.php', ['sesskey' => sesskey()]) . '" class="btn btn-secondary">';
echo get_string('downloadexceltemplate', 'local_exammanager');
echo '</a>';

echo '<form method="post" enctype="multipart/form-data">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<input type="hidden" name="action" value="preview">';

echo '<div class="local-exammanager-dropzone">';
echo '<div><strong>' . get_string('dropzonelabel', 'local_exammanager') . '</strong></div>';
echo '<input type="file" name="planningfile" accept=".csv,.xlsx,.xls" required>';
echo '</div>';

echo '<div class="form-check" style="margin-top:10px;">';
echo '<input type="checkbox" class="form-check-input" id="allow-shared-codes-upload" name="allow_shared_codes" value="1"' . ($allowsharedcodes ? ' checked' : '') . '>';
echo '<label class="form-check-label" for="allow-shared-codes-upload">' . get_string('allowsharedcodes', 'local_exammanager') . '</label>';
echo '<div class="local-exammanager-muted small">' . get_string('allowsharedcodes_help', 'local_exammanager') . '</div>';
echo '</div>';

echo '<br>';
echo '<button class="btn btn-primary">' . get_string('previewplanning', 'local_exammanager') . '</button>';
echo '</form>';


/* ========================
PREVIEW
======================== */
if ($action === 'preview' && confirm_sesskey()) {

    if (!isset($_FILES['planningfile']) || empty($_FILES['planningfile']['tmp_name'])) {
        echo $OUTPUT->notification(get_string('nofile', 'local_exammanager'), 'notifyproblem');
    } else {

        try {

            // =======================
            // RESET PREVIOUS EXPORTS
            // =======================
            $base = make_temp_directory('local_exammanager/' . $USER->id);

            $oldfiles = [
                $base . '/examens.csv',
                $base . '/examens.xlsx',
                $base . '/execution.txt'
            ];

            foreach ($oldfiles as $f) {
                if (file_exists($f)) {
                    unlink($f);
                }
            }

            // =======================
            // FILE PREPARATION
            // =======================
            $tmp = $_FILES['planningfile']['tmp_name'];
            $name = $_FILES['planningfile']['name'];

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (!$ext) {
                throw new moodle_exception(get_string('cannotdetectfiletype', 'local_exammanager'));
            }

            // Create a temporary file with the correct extension.
            $newfile = $tmp . '.' . $ext;

            if (!copy($tmp, $newfile)) {
                throw new moodle_exception(get_string('filecopyerror', 'local_exammanager'));
            }

            // =======================
            // FILE READING
            // =======================
            $rows = \local_exammanager\reader::read_rows($newfile);

            if (empty($rows)) {
                throw new moodle_exception(get_string('fileemptyorinvalid', 'local_exammanager'));
            }

            $used = [];

            // =======================
            // VALIDATION + GENERATION
            // =======================
            foreach ($rows as &$row) {

                list($valid, $msg) = \local_exammanager\util::validate_row($row);

                if (!$valid) {
                    $row['status'] = 'ERROR';
                    $row['message'] = $msg;
                    $row['access_code'] = '';
                    $row['seb_exit_code'] = '';
                    continue;
                }

                $row['status'] = 'READY';
                $row['message'] = get_string('previewok', 'local_exammanager');
                $row['access_code_action'] = \local_exammanager\util::normalize_access_action($row);
                $row['seb_action'] = \local_exammanager\util::normalize_seb_action($row);
            }
            unset($row);

            $rows = \local_exammanager\util::assign_shared_generated_codes($rows, false, $allowsharedcodes);

            // =======================
            // SESSION STORAGE
            // =======================
            $SESSION->$sessionkey = $rows;
            $SESSION->$lockkey = 0; // New file uploaded: new operation, unlock.

            echo $OUTPUT->notification(
                get_string('previewcompletedmsg', 'local_exammanager'),
                'notifysuccess'
            );

        } catch (Throwable $e) {

            debugging('ExamManager preview error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            echo $OUTPUT->notification(get_string('previewerrormsg', 'local_exammanager'), 'notifyproblem');
        }
    }
}


if ($action === 'refreshrows' && confirm_sesskey()) {

    if (!empty($SESSION->$lockkey)) {
        echo $OUTPUT->notification(get_string('alreadylockedmsg', 'local_exammanager'), 'notifywarning');
    } else if (!empty($SESSION->$sessionkey) && is_array($SESSION->$sessionkey)) {
        $rows = $SESSION->$sessionkey;
        $editedrows = $_POST['rowedit'] ?? [];
        if (!is_array($editedrows)) {
            $editedrows = [];
        }
        $rows = $apply_row_overrides($rows, $editedrows);

        $selectedquizids = $_POST['selected_quizid'] ?? [];
        if (!is_array($selectedquizids)) {
            $selectedquizids = [];
        }
        foreach ($rows as $idx => &$row) {
            if (isset($selectedquizids[$idx]) && (int)$selectedquizids[$idx] > 0) {
                $row['selected_quizid'] = (int)$selectedquizids[$idx];
                $selectedquiz = $DB->get_record('quiz', ['id' => (int)$selectedquizids[$idx]], 'id, name', IGNORE_MISSING);
                if ($selectedquiz) {
                    $row['quiz_name'] = $selectedquiz->name;
                }
            } else {
                unset($row['selected_quizid']);
            }

            list($valid, $msg) = \local_exammanager\util::validate_row($row);
            if (!$valid) {
                $row['status'] = 'ERROR';
                $row['message'] = $msg;
            } else if (($row['status'] ?? '') !== 'PROGRAMMÉ') {
                $row['status'] = 'READY';
                $row['message'] = get_string('previewrefreshedmsg', 'local_exammanager');
            }
        }
        unset($row);

        $rows = \local_exammanager\util::assign_shared_generated_codes($rows, true, $allowsharedcodes);
        $SESSION->$sessionkey = $rows;
        echo $OUTPUT->notification(get_string('previewupdatedmsg', 'local_exammanager'), 'notifysuccess');
    }
}

/* ========================
PROGRAM
======================== */
if ($action === 'program' && confirm_sesskey()) {

    if (!empty($SESSION->$lockkey)) {
        echo $OUTPUT->notification(get_string('alreadylockedmsg', 'local_exammanager'), 'notifywarning');
    } else if (!empty($SESSION->$sessionkey) && is_array($SESSION->$sessionkey)) {

        $rows = $SESSION->$sessionkey;
        $used = [];

        $editedrows = $_POST['rowedit'] ?? [];
        if (!is_array($editedrows)) {
            $editedrows = [];
        }
        $rows = $apply_row_overrides($rows, $editedrows);

        $selectedquizids = $_POST['selected_quizid'] ?? [];
        if (!is_array($selectedquizids)) {
            $selectedquizids = [];
        }

        foreach ($rows as $idx => &$row) {
            if (isset($selectedquizids[$idx]) && (int)$selectedquizids[$idx] > 0) {
                $row['selected_quizid'] = (int)$selectedquizids[$idx];

                $selectedquiz = $DB->get_record('quiz', ['id' => (int)$selectedquizids[$idx]], 'id, name', IGNORE_MISSING);
                if ($selectedquiz) {
                    $row['quiz_name'] = $selectedquiz->name;
                }
            }

            list($valid, $msg) = \local_exammanager\util::validate_row($row);
            if (!$valid) {
                $row['status'] = 'ERROR';
                $row['message'] = $msg;
                $row['access_code'] = '';
                $row['seb_exit_code'] = '';
            } else if (($row['status'] ?? '') !== 'PROGRAMMÉ') {
                $row['status'] = 'READY';
                $row['message'] = get_string('readytoprogrammsg', 'local_exammanager');
            }
        }
        unset($row);

        $rows = \local_exammanager\util::assign_shared_generated_codes($rows, true, $allowsharedcodes);

        foreach ($rows as $idx => &$row) {
            if (($row['status'] ?? '') === 'ERROR') {
                continue;
            }

            $out = \local_exammanager\manager::program_row($row, $used);
            $row['status'] = $out['status'] ?? 'ERROR';
            $row['message'] = $out['message'] ?? get_string('unknownerror', 'local_exammanager');
            $row['access_code'] = $out['access_code'] ?? ($row['access_code'] ?? '');
            $row['seb_exit_code'] = $out['seb_exit_code'] ?? ($row['seb_exit_code'] ?? '');
            if (!empty($out['quiz_name'])) {
                $row['quiz_name'] = $out['quiz_name'];
            }
        }
        unset($row);

        $SESSION->$sessionkey = $rows;

        /* =======================
        EXPORT FILES
        ======================= */
        $base = make_temp_directory('local_exammanager/' . $USER->id);

        $csv = $base . '/examens.csv';
        $excel = $base . '/examens.xlsx';
        $log = $base . '/execution.txt';

        try {
            \local_exammanager\exporter::export_csv($rows, $csv);
        } catch (Throwable $e) {
        }

        try {
            \local_exammanager\exporter::export_excel($rows, $excel);
        } catch (Throwable $e) {
        }

        try {
            \local_exammanager\exporter::export_log($rows, $log);
        } catch (Throwable $e) {
        }

        $downloads = [
            'csv' => new moodle_url('/local/exammanager/download.php', ['type' => 'csv', 'sesskey' => sesskey()]),
            'excel' => new moodle_url('/local/exammanager/download.php', ['type' => 'excel', 'sesskey' => sesskey()]),
            'log' => new moodle_url('/local/exammanager/download.php', ['type' => 'log', 'sesskey' => sesskey()]),
        ];

        // Lock: no further editing or reprogramming without uploading a new file.
        $SESSION->$lockkey = 1;

        echo $OUTPUT->notification(get_string('processingdone', 'local_exammanager'), 'notifysuccess');
    }
}

/* ========================
LOAD SESSION RESULTS
======================== */
if (empty($rows) && !empty($SESSION->$sessionkey) && is_array($SESSION->$sessionkey)) {
    $rows = $SESSION->$sessionkey;
}

/* ========================
DISPLAY RESULTS
======================== */
if (!empty($rows)) {

    echo '<h3>' . get_string('results', 'local_exammanager') . '</h3>';

    $islocked = !empty($SESSION->$lockkey);

    $isprogrammode = !$islocked && ($canprogram = array_reduce($rows, function($carry, $row) {
        return $carry || (($row['status'] ?? '') === 'READY');
    }, false));

    echo '<form method="post" id="exammanager-preview-form">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';

    echo '<div class="local-exammanager-preview-wrap">';
    echo '<table class="generaltable local-exammanager-preview-table">';
    echo '<thead><tr>';
    $previewheadings = [
        [get_string('course', 'local_exammanager'), 'col-course'],
        [get_string('col_quizdetected', 'local_exammanager'), 'col-quizname'],
        [get_string('col_quiztoprogram', 'local_exammanager'), 'col-quizselect'],
        [get_string('open', 'local_exammanager'), 'col-open'],
        [get_string('close', 'local_exammanager'), 'col-close'],
        [get_string('col_durationminutes', 'local_exammanager'), 'col-duration'],
        [get_string('col_accessaction', 'local_exammanager'), 'col-accessaction'],
        [get_string('col_sebaction', 'local_exammanager'), 'col-sebaction'],
        [get_string('accesscode', 'local_exammanager'), 'col-accesscode'],
        [get_string('sebexitcode', 'local_exammanager'), 'col-sebcode'],
        [get_string('message', 'local_exammanager'), 'col-message'],
    ];
    foreach ($previewheadings as [$heading, $class]) {
        echo html_writer::tag('th', s($heading), ['class' => $class]);
    }
    echo '</tr></thead><tbody>';

    $actionlabels = [
        'keep' => get_string('action_keep', 'local_exammanager'),
        'generate' => get_string('action_generate', 'local_exammanager'),
        'disable' => get_string('action_disable', 'local_exammanager'),
    ];

    foreach ($rows as $idx => $r) {
        $coursevalue = trim((string)($r['course_shortname'] ?? ''));
        $selectedquizid = !empty($r['selected_quizid']) ? (int)$r['selected_quizid'] : 0;
        $quizselector = '-';
        $coursevalidity = 'missing';
        $quizrequired = '1';
        $datesvalid = '1';
        $prestatus = (string)($r['status'] ?? '');
        $premessage = (string)($r['message'] ?? '');

        // Locked row: operation finished, or this exam is already programmed -> read-only.
        $rowlocked = $islocked || in_array($prestatus, ['PROGRAMMÉ', 'PROGRAMMED'], true);
        if ($rowlocked) {
            $lockedaccess = \local_exammanager\util::normalize_access_action($r);
            $lockedseb = \local_exammanager\util::normalize_seb_action($r);
            $opendisplay = str_replace('T', ' ', \local_exammanager\util::to_datetime_local_value($r['open_time'] ?? ''));
            $closedisplay = str_replace('T', ' ', \local_exammanager\util::to_datetime_local_value($r['close_time'] ?? ''));

            echo '<tr class="exammanager-locked-row" data-row-index="' . $idx . '">';
            echo html_writer::tag('td', s($coursevalue), ['class' => 'col-course']);
            echo html_writer::tag('td', s((string)($r['quiz_name'] ?? '')), ['class' => 'col-quizname']);
            echo html_writer::tag('td', s((string)($r['quiz_name'] ?? '')), ['class' => 'col-quizselect']);
            echo html_writer::tag('td', s($opendisplay), ['class' => 'col-open']);
            echo html_writer::tag('td', s($closedisplay), ['class' => 'col-close']);
            echo html_writer::tag('td', s((string)($r['time_limit'] ?? '')), ['class' => 'col-duration']);
            echo html_writer::tag('td', s($actionlabels[$lockedaccess] ?? $lockedaccess), ['class' => 'col-accessaction']);
            echo html_writer::tag('td', s($actionlabels[$lockedseb] ?? $lockedseb), ['class' => 'col-sebaction']);
            echo html_writer::tag('td', s($r['access_code'] ?? ''), ['class' => 'col-accesscode']);
            echo html_writer::tag('td', s($r['seb_exit_code'] ?? ''), ['class' => 'col-sebcode']);
            echo html_writer::tag('td', '<strong>' . s($prestatus) . '</strong> ' . s($premessage), ['class' => 'col-message']);
            echo '</tr>';
            continue;
        }

        $courseinput = html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'rowedit[' . $idx . '][course_shortname]',
            'value' => $coursevalue,
            'class' => 'form-control exammanager-course-input',
            'style' => 'min-width:180px;'
        ]);

        $quiznameinput = html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'rowedit[' . $idx . '][quiz_name]',
            'value' => (string)($r['quiz_name'] ?? ''),
            'class' => 'form-control exammanager-quizname-input',
            'style' => 'min-width:220px;'
        ]);

        $openinput = html_writer::empty_tag('input', [
            'type' => 'datetime-local',
            'name' => 'rowedit[' . $idx . '][open_time]',
            'value' => \local_exammanager\util::to_datetime_local_value($r['open_time'] ?? ''),
            'class' => 'form-control exammanager-open-input',
            'style' => 'min-width:210px;'
        ]);

        $closeinput = html_writer::empty_tag('input', [
            'type' => 'datetime-local',
            'name' => 'rowedit[' . $idx . '][close_time]',
            'value' => \local_exammanager\util::to_datetime_local_value($r['close_time'] ?? ''),
            'class' => 'form-control exammanager-close-input',
            'style' => 'min-width:210px;'
        ]);

        $timelimitinput = html_writer::empty_tag('input', [
            'type' => 'number',
            'name' => 'rowedit[' . $idx . '][time_limit]',
            'value' => (string)($r['time_limit'] ?? ''),
            'class' => 'form-control exammanager-duration-input',
            'min' => '0',
            'step' => '1',
            'style' => 'width:100px;'
        ]);

        $accessaction = (string)($r['access_code_action'] ?? \local_exammanager\util::normalize_access_action($r));
        $accessoptions = [];
        foreach ($actionlabels as $value => $label) {
            $attrs = ['value' => $value];
            if ($accessaction === $value) {
                $attrs['selected'] = 'selected';
            }
            $accessoptions[] = html_writer::tag('option', $label, $attrs);
        }
        $accessselect = html_writer::tag('select', implode('', $accessoptions), [
            'name' => 'rowedit[' . $idx . '][access_code_action]',
            'class' => 'form-select custom-select',
            'style' => 'min-width:120px;'
        ]);

        $sebaction = (string)($r['seb_action'] ?? \local_exammanager\util::normalize_seb_action($r));
        $seboptions = [];
        foreach ($actionlabels as $value => $label) {
            $attrs = ['value' => $value];
            if ($sebaction === $value) {
                $attrs['selected'] = 'selected';
            }
            $seboptions[] = html_writer::tag('option', $label, $attrs);
        }
        $sebselect = html_writer::tag('select', implode('', $seboptions), [
            'name' => 'rowedit[' . $idx . '][seb_action]',
            'class' => 'form-select custom-select',
            'style' => 'min-width:120px;'
        ]);

        if ($coursevalue !== '') {
            $course = $DB->get_record_sql(
                "SELECT id, shortname FROM {course} WHERE LOWER(TRIM(shortname)) = LOWER(TRIM(?))",
                [$coursevalue]
            );
            if ($course) {
                $coursevalidity = 'valid';
                $quizzes = $DB->get_records_sql(
                    "SELECT DISTINCT q.id, q.name
                       FROM {quiz} q
                       JOIN {modules} m ON m.name = 'quiz'
                       JOIN {course_modules} cm ON cm.instance = q.id
                            AND cm.module = m.id
                            AND cm.course = q.course
                            AND cm.deletioninprogress = 0
                      WHERE q.course = ?
                   ORDER BY q.name ASC, q.id DESC",
                    [$course->id]
                );
                if ($quizzes) {
                    $options = [];
                    $options[] = html_writer::tag('option', get_string('choosecorrectquiz', 'local_exammanager'), ['value' => '']);
                    foreach ($quizzes as $quiz) {
                        $attrs = ['value' => (int)$quiz->id];
                        if ($selectedquizid > 0 && $selectedquizid === (int)$quiz->id) {
                            $attrs['selected'] = 'selected';
                        } else if ($selectedquizid <= 0 && core_text::strtolower(trim((string)$quiz->name)) === core_text::strtolower(trim((string)($r['quiz_name'] ?? '')))) {
                            $attrs['selected'] = 'selected';
                        }
                        $options[] = html_writer::tag('option', format_string($quiz->name), $attrs);
                    }
                    $quizselector = html_writer::tag('select', implode('', $options), [
                        'name' => 'selected_quizid[' . $idx . ']',
                        'class' => 'form-select custom-select exammanager-quiz-select',
                        'style' => 'min-width:260px;'
                    ]);
                } else {
                    $quizrequired = '0';
                    $quizselector = html_writer::span(get_string('noquizfoundincourse', 'local_exammanager'), 'text-muted');
                }
            } else {
                $coursevalidity = 'invalid';
                $quizselector = html_writer::span(get_string('shortnamenotfound', 'local_exammanager'), 'text-danger');
            }
        }

        echo '<tr class="exammanager-preview-row" data-row-index="' . $idx . '" data-course-valid="' . s($coursevalidity) . '" data-quiz-required="' . s($quizrequired) . '">';
        echo html_writer::tag('td', $courseinput, ['class' => 'col-course']);
        echo html_writer::tag('td', $quiznameinput, ['class' => 'col-quizname']);
        echo html_writer::tag('td', $quizselector, ['class' => 'col-quizselect']);
        echo html_writer::tag('td', $openinput, ['class' => 'col-open']);
        echo html_writer::tag('td', $closeinput, ['class' => 'col-close']);
        echo html_writer::tag('td', $timelimitinput, ['class' => 'col-duration']);
        echo html_writer::tag('td', $accessselect, ['class' => 'col-accessaction']);
        echo html_writer::tag('td', $sebselect, ['class' => 'col-sebaction']);
        echo html_writer::tag('td', s($r['access_code'] ?? ''), ['class' => 'col-accesscode']);
        echo html_writer::tag('td', s($r['seb_exit_code'] ?? ''), ['class' => 'col-sebcode']);
        echo html_writer::tag('td', '<span class="exammanager-status-text" style="display:none;">' . s($prestatus) . '</span><span class="exammanager-message-text">' . s($premessage) . '</span><div class="exammanager-inline-errors text-danger small" style="margin-top:4px;"></div>', ['class' => 'col-message']);
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';


    if ($islocked) {
        echo $OUTPUT->notification(get_string('lockednoticemsg', 'local_exammanager'), 'notifymessage');
    } else {
        echo '<div class="form-check" style="margin-top:12px;">';
        echo '<input type="checkbox" class="form-check-input" id="allow-shared-codes-preview" name="allow_shared_codes" value="1"' . ($allowsharedcodes ? ' checked' : '') . '>';
        echo '<label class="form-check-label" for="allow-shared-codes-preview">' . get_string('allowsharedcodes', 'local_exammanager') . '</label>';
        echo '<div class="local-exammanager-muted small">' . get_string('allowsharedcodes_help', 'local_exammanager') . '</div>';
        echo '</div>';

        echo '<div style="margin-top:12px; display:flex; gap:12px; flex-wrap:wrap;">';
        echo '<button class="btn btn-outline-secondary" type="submit" name="action" value="refreshrows">' . get_string('refreshpreview', 'local_exammanager') . '</button>';
        echo '<button class="btn btn-success" type="submit" name="action" value="program" id="exammanager-program-btn" ' . ($isprogrammode ? '' : 'disabled') . '>' . get_string('programexams', 'local_exammanager') . '</button>';
        echo '</div>';

        $PAGE->requires->js_call_amd('local_exammanager/preview_validation', 'init');
    }

    echo '</form>';

    /* Also show the download links if the files already exist. */
    if (empty($downloads) && $action !== 'preview') {
        $base = make_temp_directory('local_exammanager/' . $USER->id);

        if (file_exists($base . '/examens.csv') || file_exists($base . '/examens.xlsx') || file_exists($base . '/execution.txt')) {
            $downloads = [
                'csv' => new moodle_url('/local/exammanager/download.php', ['type' => 'csv', 'sesskey' => sesskey()]),
                'excel' => new moodle_url('/local/exammanager/download.php', ['type' => 'excel', 'sesskey' => sesskey()]),
                'log' => new moodle_url('/local/exammanager/download.php', ['type' => 'log', 'sesskey' => sesskey()]),
            ];
        }
    }

    if (!empty($downloads)) {
        echo '<br><h3>' . get_string('downloadssection', 'local_exammanager') . '</h3>';
        echo html_writer::link($downloads['csv'], get_string('downloadcsv', 'local_exammanager')) . '<br>';
        echo html_writer::link($downloads['excel'], get_string('downloadexcel', 'local_exammanager')) . '<br>';
        echo html_writer::link($downloads['log'], get_string('downloadlog', 'local_exammanager')) . '<br>';
    }
}

echo '</div>';
echo html_writer::end_div();
echo $OUTPUT->footer();
