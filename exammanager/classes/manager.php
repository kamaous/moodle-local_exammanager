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
 * Applies one planning row to a Moodle quiz (dates, access codes, Safe Exam Browser, visibility).
 *
 * @package    local_exammanager
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_exammanager;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->dirroot . '/course/modlib.php');
require_once($GLOBALS['CFG']->dirroot . '/course/lib.php');

class manager {

    /**
     * Automatically makes the programmed quiz/test activity visible.
     */
    private static function reveal_quiz_activity(\stdClass $course, \stdClass $cm): void {
        global $DB;

        $updates = new \stdClass();
        $updates->id = $cm->id;
        $needsupdate = false;

        if (property_exists($cm, 'visible') && (int)$cm->visible !== 1) {
            $updates->visible = 1;
            $needsupdate = true;
        }

        if (property_exists($cm, 'visibleoncoursepage') && (int)$cm->visibleoncoursepage !== 1) {
            $updates->visibleoncoursepage = 1;
            $needsupdate = true;
        }

        if ($needsupdate) {
            $DB->update_record('course_modules', $updates);
        }

        if (function_exists('set_coursemodule_visible')) {
            try {
                set_coursemodule_visible($cm->id, 1);
            } catch (\Throwable $e) {
                debugging('Quiz visibility update via set_coursemodule_visible failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        // The course cache is purged once, at the end of program_row().
    }

    /**
     * Automatically makes the section containing the programmed quiz visible.
     */
    private static function reveal_quiz_section(\stdClass $course, \stdClass $cm): void {
        global $DB;

        if (empty($cm->section)) {
            return;
        }

        $section = $DB->get_record('course_sections', ['id' => (int)$cm->section], '*', IGNORE_MISSING);
        if (!$section) {
            return;
        }

        $updates = new \stdClass();
        $updates->id = $section->id;
        $needsupdate = false;

        if (property_exists($section, 'visible') && (int)$section->visible !== 1) {
            $updates->visible = 1;
            $needsupdate = true;
        }

        if (property_exists($section, 'visibleoncoursepage') && (int)$section->visibleoncoursepage !== 1) {
            $updates->visibleoncoursepage = 1;
            $needsupdate = true;
        }

        if ($needsupdate) {
            $DB->update_record('course_sections', $updates);
        }

        if (function_exists('course_get_format')) {
            try {
                $format = course_get_format($course);
                if ($format && method_exists($format, 'set_section_visible')) {
                    $format->set_section_visible($section->section, 1);
                }
            } catch (\Throwable $e) {
                debugging('Section visibility update via course format failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }

    /**
     * Retourne le code SEB courant et le record de configuration si disponible.
     */
    private static function get_current_seb_state(\moodle_database $DB, \stdClass $quiz, \stdClass $cm): array {
        $mgr = $DB->get_manager();
        $sebtable = new \xmldb_table('quizaccess_seb_quizsettings');

        if (!$mgr->table_exists($sebtable)) {
            return [null, '', []];
        }

        $cols = $DB->get_columns('quizaccess_seb_quizsettings');
        $existingseb = null;

        if (isset($cols['cmid'])) {
            $existingseb = $DB->get_record(
                'quizaccess_seb_quizsettings',
                ['cmid' => $cm->id],
                '*',
                IGNORE_MISSING
            );
        }

        if (!$existingseb && isset($cols['quizid'])) {
            $existingseb = $DB->get_record(
                'quizaccess_seb_quizsettings',
                ['quizid' => $quiz->id],
                '*',
                IGNORE_MISSING
            );
        }

        $currentsebcode = '';
        if ($existingseb && property_exists($existingseb, 'quitpassword')) {
            $currentsebcode = (string)$existingseb->quitpassword;
        }

        return [$existingseb, $currentsebcode, $cols];
    }

    /**
     * Applique l'action SEB sur la table quizaccess_seb_quizsettings si disponible.
     */
    private static function apply_seb_action(\moodle_database $DB, \stdClass $quiz, \stdClass $cm, string $sebaction, string $sebcode): void {
        if ($sebaction === 'keep') {
            return;
        }

        // Moodle 3.9+: go through the SEB persistent API, which keeps the SEB caches
        // (quiz settings, config, config key) and the generated SEB configuration in sync.
        // Writing the table directly would leave stale cached settings in force.
        if (class_exists('\quizaccess_seb\seb_quiz_settings') && class_exists('\quizaccess_seb\settings_provider')) {
            self::apply_seb_action_with_api($quiz, $cm, $sebaction, $sebcode);
            return;
        }

        self::apply_seb_action_legacy($DB, $quiz, $cm, $sebaction, $sebcode);
    }

    /**
     * SEB update through \quizaccess_seb\seb_quiz_settings (same behaviour as the quiz settings form).
     */
    private static function apply_seb_action_with_api(\stdClass $quiz, \stdClass $cm, string $sebaction, string $sebcode): void {
        $provider = '\quizaccess_seb\settings_provider';
        $settings = \quizaccess_seb\seb_quiz_settings::get_record(['quizid' => (int)$quiz->id]);

        if ($sebaction === 'disable') {
            // As in core: "SEB not required" means no settings record at all.
            if ($settings) {
                $settings->delete();
            }
            $provider::delete_uploaded_config_file((string)$cm->id);
            return;
        }

        // Generate: SEB required, configured manually, with the generated quit password.
        if (!$settings) {
            $settings = new \quizaccess_seb\seb_quiz_settings(0, (object)[
                'quizid' => (int)$quiz->id,
                'cmid' => (int)$cm->id,
            ]);
        }
        $settings->set('cmid', (int)$cm->id);
        $settings->set('requiresafeexambrowser', $provider::USE_SEB_CONFIG_MANUALLY);
        $settings->set('allowuserquitseb', 1);
        $settings->set('quitpassword', $sebcode);
        $settings->set('linkquitseb', '');
        $settings->set('userconfirmquit', 1);
        $settings->set('showsebdownloadlink', 1);
        $settings->save();

        $provider::delete_uploaded_config_file((string)$cm->id);
    }

    /**
     * Fallback for sites without the SEB persistent API: direct table update, then cache purge.
     */
    private static function apply_seb_action_legacy(\moodle_database $DB, \stdClass $quiz, \stdClass $cm, string $sebaction, string $sebcode): void {
        [$existingseb, , $cols] = self::get_current_seb_state($DB, $quiz, $cm);
        if (empty($cols) || $sebaction === 'keep') {
            return;
        }

        $s = new \stdClass();

        if (isset($cols['cmid'])) {
            $s->cmid = $cm->id;
        }
        if (isset($cols['quizid'])) {
            $s->quizid = $quiz->id;
        }
        if (isset($cols['quitpassword'])) {
            $s->quitpassword = ($sebaction === 'generate') ? $sebcode : '';
        }
        if (isset($cols['requiresafeexambrowser'])) {
            $s->requiresafeexambrowser = ($sebaction === 'generate') ? 1 : 0;
        }

        if ($sebaction === 'generate') {
            if (isset($cols['showsebdownloadlink'])) {
                $s->showsebdownloadlink = 1;
            }
            if (isset($cols['linkquitseb'])) {
                $s->linkquitseb = '';
            }
            if (isset($cols['userconfirmquit'])) {
                $s->userconfirmquit = 1;
            }
            if (isset($cols['templateid'])) {
                $s->templateid = 0;
            }
            if (isset($cols['sebconfigtemplate'])) {
                $s->sebconfigtemplate = 0;
            }
            if (isset($cols['configkey'])) {
                $s->configkey = '';
            }
            if (isset($cols['browserexamkey'])) {
                $s->browserexamkey = '';
            }
        }

        if ($existingseb) {
            $s->id = $existingseb->id;
            $DB->update_record('quizaccess_seb_quizsettings', $s);
            return;
        }

        if ($sebaction === 'generate' && (isset($s->cmid) || isset($s->quizid))) {
            $DB->insert_record('quizaccess_seb_quizsettings', $s);
        }
    }

    public static function program_row(array $row, array &$usedcodes): array {
        global $DB, $USER, $CFG;

        $tx = null;
        try {
            $course_shortname = trim((string)($row['course_shortname'] ?? ''));
            $quiz_name = trim((string)($row['quiz_name'] ?? ''));
            $selectedquizid = !empty($row['selected_quizid']) ? (int)$row['selected_quizid'] : 0;

            if ($course_shortname === '' || ($quiz_name === '' && $selectedquizid <= 0)) {
                return [
                    'status' => 'ERROR',
                    'message' => get_string('coursequizempty', 'local_exammanager'),
                    'access_code' => '',
                    'seb_exit_code' => ''
                ];
            }

            $course = $DB->get_record_sql(
                "SELECT *
                   FROM {course}
                  WHERE LOWER(TRIM(shortname)) = LOWER(TRIM(?))",
                [$course_shortname]
            );

            if (!$course) {
                return [
                    'status' => 'ERROR',
                    'message' => get_string('coursenotfoundwithname', 'local_exammanager', $course_shortname),
                    'access_code' => '',
                    'seb_exit_code' => ''
                ];
            }

            if ($selectedquizid > 0) {
                $quiz = $DB->get_record_sql(
                    "SELECT DISTINCT q.*
                       FROM {quiz} q
                       JOIN {modules} m ON m.name = 'quiz'
                       JOIN {course_modules} cm ON cm.instance = q.id
                            AND cm.module = m.id
                            AND cm.course = q.course
                            AND cm.deletioninprogress = 0
                      WHERE q.course = ?
                        AND q.id = ?",
                    [$course->id, $selectedquizid]
                );

                if (!$quiz) {
                    return [
                        'status' => 'ERROR',
                        'message' => get_string('selectedquiznotfoundforcourse', 'local_exammanager'),
                        'access_code' => '',
                        'seb_exit_code' => ''
                    ];
                }
            } else {
                $quizzes = $DB->get_records_sql(
                    "SELECT DISTINCT q.*
                       FROM {quiz} q
                       JOIN {modules} m ON m.name = 'quiz'
                       JOIN {course_modules} cm ON cm.instance = q.id
                            AND cm.module = m.id
                            AND cm.course = q.course
                            AND cm.deletioninprogress = 0
                      WHERE q.course = ?
                        AND LOWER(TRIM(q.name)) = LOWER(TRIM(?))",
                    [$course->id, $quiz_name]
                );

                if (!$quizzes) {
                    return [
                        'status' => 'ERROR',
                        'message' => get_string('quiznotfoundwithname', 'local_exammanager', $quiz_name),
                        'access_code' => '',
                        'seb_exit_code' => ''
                    ];
                }

                $quizzes = array_values($quizzes);
                usort($quizzes, function($a, $b) {
                    return (int)$b->id <=> (int)$a->id;
                });
                $quiz = $quizzes[0];
            }

            $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);

            $accessaction = util::normalize_access_action($row);
            $sebaction = util::normalize_seb_action($row);
            $forcelegacy = !empty($row['force_new_codes']) && (int)$row['force_new_codes'] === 1;
            $usingnewaccessaction = array_key_exists('access_code_action', $row) && trim((string)$row['access_code_action']) !== '';
            $usingnewsebaction = array_key_exists('seb_action', $row) && trim((string)$row['seb_action']) !== '';

            $existing = $DB->get_record(
                'local_exammanager_codes',
                ['quizid' => $quiz->id],
                '*',
                IGNORE_MISSING
            );

            [, $currentsebcode] = self::get_current_seb_state($DB, $quiz, $cm);
            $currentaccesscode = isset($quiz->password) ? (string)$quiz->password : '';

            $providedaccesscode = trim((string)($row['access_code'] ?? ''));
            $providedsebcode = trim((string)($row['seb_exit_code'] ?? ''));

            $access = $currentaccesscode;
            if ($accessaction === 'generate') {
                if ($providedaccesscode !== '') {
                    $access = $providedaccesscode;
                    if (!in_array($access, $usedcodes, true)) {
                        $usedcodes[] = $access;
                    }
                } else if (!$usingnewaccessaction && !$forcelegacy && $existing && !empty($existing->access_code)) {
                    $access = (string)$existing->access_code;
                } else {
                    $access = util::generate_code($usedcodes);
                }
            } else if ($accessaction === 'disable') {
                $access = '';
            }

            $seb = $currentsebcode;
            if ($sebaction === 'generate') {
                if ($providedsebcode !== '') {
                    $seb = $providedsebcode;
                    if (!in_array($seb, $usedcodes, true)) {
                        $usedcodes[] = $seb;
                    }
                } else if (!$usingnewsebaction && !$forcelegacy && $existing && !empty($existing->seb_exit_code)) {
                    $seb = (string)$existing->seb_exit_code;
                } else {
                    $seb = util::generate_code($usedcodes);
                }
            } else if ($sebaction === 'disable') {
                $seb = '';
            }

            $timeopen = util::parse_datetime((string)($row['open_time'] ?? ''));
            $timeclose = util::parse_datetime((string)($row['close_time'] ?? ''));
            $timelimit = ((int)($row['time_limit'] ?? 0)) * 60;

            $tx = $DB->start_delegated_transaction();

            $q = new \stdClass();
            $q->id = $quiz->id;
            $q->timeopen = $timeopen;
            $q->timeclose = $timeclose;
            $q->timelimit = $timelimit;

            if ($accessaction === 'generate' || $accessaction === 'disable') {
                $q->password = $access;
            }

            $DB->update_record('quiz', $q);

            // Keep the course calendar / timeline events of the quiz in line with the new dates.
            require_once($CFG->dirroot . '/mod/quiz/lib.php');
            if (function_exists('quiz_update_events')) {
                $updatedquiz = $DB->get_record('quiz', ['id' => $quiz->id], '*', MUST_EXIST);
                $updatedquiz->coursemodule = $cm->id;
                quiz_update_events($updatedquiz);
            }

            $r = new \stdClass();
            $r->quizid = $quiz->id;
            $r->courseid = $course->id;
            $r->quizname = $quiz->name;
            $r->course_shortname = $course->shortname;
            $r->access_code = $access;
            $r->seb_exit_code = $seb;
            $r->access_code_action = $accessaction;
            $r->seb_action = $sebaction;
            $r->teacher = trim((string)($row['teacher'] ?? ''));
            $r->room = trim((string)($row['room'] ?? ''));
            $r->sessionname = trim((string)($row['session'] ?? ''));
            $r->timeopen = $timeopen;
            $r->timeclose = $timeclose;
            $r->timelimit = $timelimit;
            $r->timemodified = time();
            $r->usermodified = (int)$USER->id;

            if ($existing) {
                $r->id = $existing->id;
                $DB->update_record('local_exammanager_codes', $r);
            } else {
                $r->timecreated = time();
                $DB->insert_record('local_exammanager_codes', $r);
            }

            // A SEB failure must not be hidden: the whole row is rolled back and reported as an error.
            self::apply_seb_action($DB, $quiz, $cm, $sebaction, $seb);

            try {
                self::reveal_quiz_section($course, $cm);
                self::reveal_quiz_activity($course, $cm);
            } catch (\Throwable $e) {
                debugging('Section/activity reveal error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }

            $tx->allow_commit();
            $tx = null;

            // After the commit, so that the course page is rebuilt from the saved data:
            // the quiz (dates, visibility) and its section are read again, not taken from the old cache.
            try {
                util::purge_course_cache((int)$course->id, [(int)$cm->id], [(int)$cm->section]);
            } catch (\Throwable $e) {
                debugging('ExamManager course cache purge error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }

            // Traceability: who programmed which exam, and how (codes are never logged).
            try {
                \local_exammanager\event\exam_programmed::create([
                    'objectid' => (int)$quiz->id,
                    'context' => \context_module::instance($cm->id),
                    'courseid' => (int)$course->id,
                    'other' => [
                        'timeopen' => (int)$timeopen,
                        'timeclose' => (int)$timeclose,
                        'timelimit' => (int)$timelimit,
                        'accessaction' => $accessaction,
                        'sebaction' => $sebaction,
                    ],
                ])->trigger();
            } catch (\Throwable $e) {
                // The change is already committed: a logging failure must not report it as failed.
                debugging('ExamManager event logging error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }

            $message = get_string('examprogrammedsuccess', 'local_exammanager');
            if ($accessaction === 'disable' && $sebaction === 'disable') {
                $message .= get_string('accesscoderemovedandsebdisabledsuffix', 'local_exammanager');
            } else if ($accessaction === 'disable') {
                $message .= get_string('accesscoderemovedsuffix', 'local_exammanager');
            } else if ($sebaction === 'disable') {
                $message .= get_string('sebdisabledsuffix', 'local_exammanager');
            }

            return [
                'status' => 'PROGRAMMÉ',
                'message' => $message,
                'access_code' => $access,
                'seb_exit_code' => $seb,
                'quiz_name' => $quiz->name,
                'selected_quizid' => (int)$quiz->id
            ];

        } catch (\Throwable $e) {
            if ($tx !== null && !$tx->is_disposed()) {
                try {
                    // Rolls back the quiz, codes and SEB changes of this row (rethrows $e).
                    $tx->rollback($e);
                } catch (\Throwable $ignored) {
                    // Expected: rollback() rethrows the original exception.
                }
            }
            debugging('ExamManager programming error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [
                'status' => 'ERROR',
                'message' => get_string('programmingerrormsg', 'local_exammanager'),
                'access_code' => '',
                'seb_exit_code' => ''
            ];
        }
    }
}
