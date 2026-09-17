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
 * Privacy Subsystem implementation for local_exammanager.
 *
 * @package    local_exammanager
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_exammanager\privacy;

use core_privacy\local\metadata\null_provider;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for local_exammanager.
 *
 * This plugin does not store, export or process any personal data about
 * an identifiable Moodle user. It only reads existing course and quiz
 * metadata (course id, quiz id) to bulk-schedule quiz opening/closing
 * dates, time limits and access codes. The free-text `teacher` and
 * `room` fields recorded in the local_exammanager_codes table are manual
 * labels typed by the manager for reporting purposes; they are never
 * linked to a Moodle user account and are not populated from user
 * profile data.
 */
class provider implements null_provider {

    /**
     * Get the language string identifier with the plugin's language
     * file to explain why this plugin stores no personal data.
     *
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
