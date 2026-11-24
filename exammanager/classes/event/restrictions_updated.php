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
 * Event: access restrictions of course sections or activities were changed by the exam manager.
 *
 * @package    local_exammanager
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_exammanager\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Logged in the course context each time restrictions are added or removed.
 *
 * @property-read array $other {
 *      - string targettype (sections|activities)
 *      - string targetids comma-separated ids of the updated sections / course modules
 *      - string restrictiontype (date|grade|profile|set|remove)
 * }
 */
class restrictions_updated extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_restrictionsupdated', 'local_exammanager');
    }

    /**
     * Event description (not translated, as for core events).
     *
     * @return string
     */
    public function get_description() {
        $other = $this->other;
        return "The user with id '{$this->userid}' updated the '" . s($other['restrictiontype'] ?? '')
            . "' access restrictions of the " . s($other['targettype'] ?? '') . " with ids '"
            . s($other['targetids'] ?? '') . "' in the course with id '{$this->courseid}'.";
    }

    /**
     * URL of the course.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/course/view.php', ['id' => $this->courseid]);
    }

    /**
     * Mapping of the "other" fields when restoring logs.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }

    /**
     * Data validation.
     */
    protected function validate_data() {
        parent::validate_data();

        foreach (['targettype', 'targetids', 'restrictiontype'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception('The \'' . $key . '\' value must be set in other.');
            }
        }
    }
}
