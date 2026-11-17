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
 * Privacy API implementation.
 *
 * The plugin stores, for each programmed exam, the user who last programmed it (usermodified)
 * and free-text supervisor / room / session labels. Exam records are operational data of the
 * institution: on a deletion request, the link to the user is removed, the exam record is kept.
 *
 * @package    local_exammanager
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_exammanager\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for local_exammanager.
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    /**
     * Describes the personal data stored by the plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_exammanager_codes', [
            'usermodified' => 'privacy:metadata:codes:usermodified',
            'teacher' => 'privacy:metadata:codes:teacher',
            'room' => 'privacy:metadata:codes:room',
            'sessionname' => 'privacy:metadata:codes:sessionname',
            'quizname' => 'privacy:metadata:codes:quizname',
            'course_shortname' => 'privacy:metadata:codes:course_shortname',
            'timeopen' => 'privacy:metadata:codes:timeopen',
            'timeclose' => 'privacy:metadata:codes:timeclose',
            'timemodified' => 'privacy:metadata:codes:timemodified',
        ], 'privacy:metadata:codes');

        return $collection;
    }

    /**
     * Contexts holding data of the user (the plugin works at system level).
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                 WHERE ctx.contextlevel = :contextlevel
                   AND EXISTS (SELECT 1 FROM {local_exammanager_codes} c WHERE c.usermodified = :userid)";
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_SYSTEM, 'userid' => $userid]);

        return $contextlist;
    }

    /**
     * Users who have data in the given context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        $userlist->add_from_sql('usermodified',
            "SELECT DISTINCT usermodified FROM {local_exammanager_codes} WHERE usermodified > 0", []);
    }

    /**
     * Exports the exams programmed by the user (access codes are not exported).
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }

            $records = $DB->get_records('local_exammanager_codes', ['usermodified' => $userid], 'timemodified ASC');
            if (!$records) {
                continue;
            }

            $exams = [];
            foreach ($records as $record) {
                $exams[] = (object)[
                    'quizname' => format_string($record->quizname),
                    'course_shortname' => $record->course_shortname,
                    'teacher' => $record->teacher,
                    'room' => $record->room,
                    'sessionname' => $record->sessionname,
                    'timeopen' => transform::datetime($record->timeopen),
                    'timeclose' => transform::datetime($record->timeclose),
                    'timemodified' => transform::datetime($record->timemodified),
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_exammanager')],
                (object)['programmedexams' => $exams]
            );
        }
    }

    /**
     * Removes the link between all users and the exam records of the context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context instanceof \context_system) {
            $DB->set_field_select('local_exammanager_codes', 'usermodified', 0, 'usermodified > 0');
        }
    }

    /**
     * Removes the link between the user and the exam records.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_system) {
                $DB->set_field('local_exammanager_codes', 'usermodified', 0,
                    ['usermodified' => (int)$contextlist->get_user()->id]);
            }
        }
    }

    /**
     * Removes the link between the given users and the exam records.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        if (!$userlist->get_context() instanceof \context_system) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->set_field_select('local_exammanager_codes', 'usermodified', 0, "usermodified $insql", $params);
    }
}
