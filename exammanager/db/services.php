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
 * External services (AJAX-callable) declaration for local_exammanager.
 *
 * @package    local_exammanager
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_exammanager_get_quiz_list' => [
        'classname' => \local_exammanager\external\get_quiz_list::class,
        'methodname' => 'execute',
        'description' => 'Return the active quizzes of a course for the planning quiz selector.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/exammanager:manage',
    ],
    'local_exammanager_get_activity_targets' => [
        'classname' => \local_exammanager\external\get_activity_targets::class,
        'methodname' => 'execute',
        'description' => 'Return the sections and activities of a course, identified by shortname, for the bulk activity restriction planner.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/exammanager:manage',
    ],
    'local_exammanager_search_courses' => [
        'classname' => \local_exammanager\external\search_courses::class,
        'methodname' => 'execute',
        'description' => 'Search courses by shortname or fullname for the planning course picker.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/exammanager:manage',
    ],
];
