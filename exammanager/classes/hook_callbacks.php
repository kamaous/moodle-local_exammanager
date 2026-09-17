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
 * Core hook callbacks (primary navigation extension).
 *
 * @package    local_exammanager
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_exammanager;

defined('MOODLE_INTERNAL') || die();

class hook_callbacks {

    /**
     * Adds a quick access link to the plugin in the primary navigation bar,
     * only for authorised users (managers and administrators).
     */
    public static function primary_extend(\core\hook\navigation\primary_extend $hook): void {
        if (!isloggedin() || isguestuser()) {
            return;
        }

        if (!has_capability('local/exammanager:manage', \context_system::instance())) {
            return;
        }

        $hook->get_primaryview()->add(
            get_string('primarynavlabel', 'local_exammanager'),
            new \moodle_url('/local/exammanager/dashboard.php'),
            \navigation_node::TYPE_CUSTOM,
            null,
            'localexammanager'
        );
    }
}
