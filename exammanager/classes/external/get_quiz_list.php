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
 * External function returning the active quizzes of a course.
 *
 * @package    local_exammanager
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_exammanager\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * Returns the active (non-deleted) quizzes belonging to a course, for the
 * planning quiz selector.
 */
class get_quiz_list extends \external_api {

    /**
     * Describes the parameters for execute().
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'Course id'),
        ]);
    }

    /**
     * Return the active quizzes of a course.
     *
     * @param int $courseid Course id.
     * @return array List of quizzes as ['id' => int, 'name' => string].
     */
    public static function execute(int $courseid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $courseid = $params['courseid'];

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/exammanager:manage', $context);

        $course = get_course($courseid);
        $coursecontext = \context_course::instance((int)$course->id);
        require_capability('moodle/course:view', $coursecontext);

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

        $result = [];
        foreach ($quizzes as $quiz) {
            $result[] = [
                'id' => (int)$quiz->id,
                'name' => format_string($quiz->name),
            ];
        }

        return $result;
    }

    /**
     * Describes the return value of execute().
     *
     * @return \external_multiple_structure
     */
    public static function execute_returns(): \external_multiple_structure {
        return new \external_multiple_structure(
            new \external_single_structure([
                'id' => new \external_value(PARAM_INT, 'Quiz id'),
                'name' => new \external_value(PARAM_RAW, 'Quiz name'),
            ])
        );
    }
}
