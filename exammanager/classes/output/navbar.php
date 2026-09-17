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
 * Plugin secondary navigation bar renderer.
 *
 * @package    local_exammanager
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_exammanager\output;

defined('MOODLE_INTERNAL') || die();

class navbar {
    public static function render(string $active = 'dashboard'): string {
        $items = [
            'dashboard' => [get_string('dashboard', 'local_exammanager'), new \moodle_url('/local/exammanager/dashboard.php')],
            'programming' => [get_string('planning', 'local_exammanager'), new \moodle_url('/local/exammanager/index.php')],
            'activities' => [get_string('activitiesplanning', 'local_exammanager'), new \moodle_url('/local/exammanager/activities.php')],
            'calendar' => [get_string('calendar', 'local_exammanager'), new \moodle_url('/local/exammanager/calendar.php')],
            'history' => [get_string('history', 'local_exammanager'), new \moodle_url('/local/exammanager/history.php')],
            'sessions' => [get_string('sessions', 'local_exammanager'), new \moodle_url('/local/exammanager/sessions.php')],
            'reports' => [get_string('reports', 'local_exammanager'), new \moodle_url('/local/exammanager/reports.php')],
        ];

        $html = '<div class="local-exammanager-nav">';
        foreach ($items as $key => $item) {
            $class = $key === $active ? 'active' : '';
            $html .= \html_writer::link($item[1], $item[0], ['class' => $class]);
        }
        $html .= '</div>';
        return $html;
    }
}
