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
 * Library callbacks.
 *
 * @package    local_exammanager
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

function local_exammanager_extend_navigation(global_navigation $navigation) {
    if (!isloggedin()) {
        return;
    }

    $context = context_system::instance();
    if (!has_capability('local/exammanager:manage', $context)) {
        return;
    }

    $node = navigation_node::create(
        get_string('pluginname', 'local_exammanager'),
        new moodle_url('/local/exammanager/dashboard.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_exammanager',
        new pix_icon('i/report', '')
    );

    $navigation->add_node($node);
}
