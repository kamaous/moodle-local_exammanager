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
 * External function to search courses by shortname or fullname.
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
 * Searches courses by shortname or fullname for the planning course picker.
 */
class search_courses extends \external_api {

    /**
     * Describes the parameters for execute().
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'query' => new \external_value(PARAM_TEXT, 'Search text'),
            'limit' => new \external_value(PARAM_INT, 'Maximum number of results', VALUE_DEFAULT, 20),
            'offset' => new \external_value(PARAM_INT, 'Results offset', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Search courses by shortname or fullname.
     *
     * @param string $query Search text.
     * @param int $limit Maximum number of results (capped at 50).
     * @param int $offset Results offset.
     * @return array Response with results and a hasmore flag.
     */
    public static function execute(string $query, int $limit = 20, int $offset = 0): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'query' => $query,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/exammanager:manage', $context);

        $query = trim($params['query']);
        if (\core_text::strlen($query) > 100) {
            throw new \invalid_parameter_exception('Search text is too long');
        }

        $limit = max(1, min(50, $params['limit']));
        $offset = max(0, $params['offset']);

        $sqlparams = ['q1' => '%' . $query . '%', 'q2' => '%' . $query . '%'];
        $sql = "SELECT id, shortname, fullname
                  FROM {course}
                 WHERE id > 1
                   AND (" . $DB->sql_like('shortname', ':q1', false) . " OR " . $DB->sql_like('fullname', ':q2', false) . ")
              ORDER BY shortname ASC";
        $courses = $DB->get_records_sql($sql, $sqlparams, $offset, $limit + 1);

        $results = [];
        $count = 0;
        foreach ($courses as $course) {
            if ($count >= $limit) {
                break;
            }

            $shortname = format_string($course->shortname);
            $fullname = format_string($course->fullname);
            $results[] = [
                'id' => (int)$course->id,
                'shortname' => $shortname,
                'fullname' => $fullname,
                'label' => $shortname . ' — ' . $fullname,
            ];
            $count++;
        }

        return [
            'results' => $results,
            'hasmore' => count($courses) > $limit,
        ];
    }

    /**
     * Describes the return value of execute().
     *
     * @return \external_single_structure
     */
    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'results' => new \external_multiple_structure(
                new \external_single_structure([
                    'id' => new \external_value(PARAM_INT, 'Course id'),
                    'shortname' => new \external_value(PARAM_RAW, 'Course shortname'),
                    'fullname' => new \external_value(PARAM_RAW, 'Course fullname'),
                    'label' => new \external_value(PARAM_RAW, 'Display label'),
                ])
            ),
            'hasmore' => new \external_value(PARAM_BOOL, 'Whether more results are available'),
        ]);
    }
}
