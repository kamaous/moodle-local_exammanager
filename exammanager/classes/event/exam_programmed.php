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
 * Event: an exam (quiz) was programmed by the exam manager.
 *
 * @package    local_exammanager
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_exammanager\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Logged each time the dates, duration, access code or SEB settings of a quiz are set
 * through the exam manager. The codes themselves are never written to the logs.
 *
 * @property-read array $other {
 *      - int timeopen
 *      - int timeclose
 *      - int timelimit (seconds)
 *      - string accessaction (keep|generate|disable)
 *      - string sebaction (keep|generate|disable)
 * }
 */
class exam_programmed extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'quiz';
    }

    /**
     * Event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_examprogrammed', 'local_exammanager');
    }

    /**
     * Event description (not translated, as for core events).
     *
     * @return string
     */
    public function get_description() {
        $other = $this->other;
        return "The user with id '{$this->userid}' programmed the quiz with id '{$this->objectid}' "
            . "(course module id '{$this->contextinstanceid}'): opens " . (int)($other['timeopen'] ?? 0)
            . ", closes " . (int)($other['timeclose'] ?? 0)
            . ", time limit " . (int)($other['timelimit'] ?? 0) . " s"
            . ", access code action '" . s($other['accessaction'] ?? '') . "'"
            . ", SEB action '" . s($other['sebaction'] ?? '') . "'.";
    }

    /**
     * URL of the programmed quiz.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/quiz/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Mapping used when restoring logs.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'quiz', 'restore' => 'quiz'];
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

        if (!isset($this->objectid)) {
            throw new \coding_exception('The \'objectid\' must be set.');
        }
        foreach (['timeopen', 'timeclose', 'timelimit', 'accessaction', 'sebaction'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception('The \'' . $key . '\' value must be set in other.');
            }
        }
    }
}
