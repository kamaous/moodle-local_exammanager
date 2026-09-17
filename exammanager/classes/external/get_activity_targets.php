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
 * External function returning the sections and activities of a course.
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
 * Returns the sections and activities of a course identified by its
 * shortname, for the bulk activity restriction planner.
 */
class get_activity_targets extends \external_api {

    /**
     * Describes the parameters for execute().
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'shortname' => new \external_value(PARAM_TEXT, 'Course shortname'),
        ]);
    }

    /**
     * Return the sections and activities of a course.
     *
     * @param string $shortname Course shortname.
     * @return array Response with success flag, sections, activities and message.
     */
    public static function execute(string $shortname): array {
        $params = self::validate_parameters(self::execute_parameters(), ['shortname' => $shortname]);
        $shortname = trim($params['shortname']);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/exammanager:manage', $context);

        if (\core_text::strlen($shortname) > 100) {
            throw new \invalid_parameter_exception('Course shortname is too long');
        }

        $response = [
            'success' => false,
            'sections' => [],
            'activities' => [],
            'message' => get_string('shortnamenotfound', 'local_exammanager'),
        ];

        $course = \local_exammanager\activity_planner::get_course_by_shortname($shortname);
        if (!$course) {
            return $response;
        }

        $coursecontext = \context_course::instance((int)$course->id);
        require_capability('moodle/course:view', $coursecontext);

        $sections = [];
        foreach (\local_exammanager\activity_planner::get_course_sections($course) as $section) {
            $sections[] = [
                'value' => (string)$section['sectionnum'],
                'label' => (string)$section['label'],
                'sectionnum' => (string)$section['sectionnum'],
            ];
        }

        $activities = [];
        foreach (\local_exammanager\activity_planner::get_course_activities($course) as $activity) {
            $activities[] = [
                'value' => (string)$activity['name'],
                'label' => (string)$activity['sectionlabel'] . ' - ' . (string)$activity['name'],
                'sectionnum' => (string)$activity['sectionnum'],
                'sectionlabel' => (string)$activity['sectionlabel'],
                'name' => (string)$activity['name'],
                'modname' => (string)$activity['modname'],
            ];
        }

        return [
            'success' => true,
            'sections' => $sections,
            'activities' => $activities,
            'message' => '',
        ];
    }

    /**
     * Describes the return value of execute().
     *
     * @return \external_single_structure
     */
    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'success' => new \external_value(PARAM_BOOL, 'Whether the course was found'),
            'sections' => new \external_multiple_structure(
                new \external_single_structure([
                    'value' => new \external_value(PARAM_RAW, 'Section number'),
                    'label' => new \external_value(PARAM_RAW, 'Section label'),
                    'sectionnum' => new \external_value(PARAM_RAW, 'Section number'),
                ])
            ),
            'activities' => new \external_multiple_structure(
                new \external_single_structure([
                    'value' => new \external_value(PARAM_RAW, 'Activity name'),
                    'label' => new \external_value(PARAM_RAW, 'Section - activity label'),
                    'sectionnum' => new \external_value(PARAM_RAW, 'Section number'),
                    'sectionlabel' => new \external_value(PARAM_RAW, 'Section label'),
                    'name' => new \external_value(PARAM_RAW, 'Activity name'),
                    'modname' => new \external_value(PARAM_RAW, 'Activity module name'),
                ])
            ),
            'message' => new \external_value(PARAM_RAW, 'Error message when the course was not found'),
        ]);
    }
}
