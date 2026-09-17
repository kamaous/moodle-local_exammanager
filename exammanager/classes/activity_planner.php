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
 * Business logic for the bulk course activity/section restriction planner.
 *
 * @package    local_exammanager
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_exammanager;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->dirroot . '/course/lib.php');

class activity_planner {

    public static function get_course_by_shortname(string $shortname): ?\stdClass {
        global $DB;

        $shortname = trim($shortname);
        if ($shortname === '') {
            return null;
        }

        return $DB->get_record_sql(
            "SELECT *
               FROM {course}
              WHERE LOWER(TRIM(shortname)) = LOWER(TRIM(?))",
            [$shortname],
            IGNORE_MISSING
        ) ?: null;
    }

    public static function get_course_activities(\stdClass $course): array {
        global $DB;

        $modinfo = get_fast_modinfo($course);
        $cmrecords = $DB->get_records('course_modules', ['course' => (int)$course->id], '', 'id, availability');
        $sectioninfos = $modinfo->get_section_info_all();
        $activities = [];

        foreach ($modinfo->get_cms() as $cm) {
            if (!empty($cm->deletioninprogress)) {
                continue;
            }

            $sectionnum = (int)$cm->sectionnum;
            $sectioninfo = $sectioninfos[$sectionnum] ?? null;

            $activities[] = [
                'id' => (int)$cm->id,
                'name' => format_string($cm->name),
                'modname' => (string)$cm->modname,
                'sectionnum' => $sectionnum,
                'sectionlabel' => self::get_section_label($course, $sectionnum, $sectioninfo),
                'visible' => !empty($cm->visible),
                'availability' => isset($cmrecords[$cm->id]) ? trim((string)$cmrecords[$cm->id]->availability) : '',
                'url' => !empty($cm->url) ? $cm->url->out(false) : '',
            ];
        }

        usort($activities, function(array $a, array $b): int {
            if ($a['sectionnum'] !== $b['sectionnum']) {
                return $a['sectionnum'] <=> $b['sectionnum'];
            }

            $namecompare = strcasecmp($a['name'], $b['name']);
            if ($namecompare !== 0) {
                return $namecompare;
            }

            return $a['id'] <=> $b['id'];
        });

        return $activities;
    }

    public static function get_course_sections(\stdClass $course): array {
        global $DB;

        $modinfo = get_fast_modinfo($course);
        $sectionrecords = $DB->get_records('course_sections', ['course' => (int)$course->id], '', 'id, section, availability, visible');
        $activitycounts = [];

        foreach ($modinfo->get_cms() as $cm) {
            if (!empty($cm->deletioninprogress)) {
                continue;
            }

            $sectionnum = (int)$cm->sectionnum;
            if (!isset($activitycounts[$sectionnum])) {
                $activitycounts[$sectionnum] = 0;
            }
            $activitycounts[$sectionnum]++;
        }

        $sections = [];
        foreach ($modinfo->get_section_info_all() as $sectionnum => $sectioninfo) {
            $sectionnum = (int)$sectionnum;
            $sectionrecord = $sectionrecords[$sectioninfo->id] ?? null;
            $activitycount = (int)($activitycounts[$sectionnum] ?? 0);

            if ($sectionnum !== 0 && $activitycount === 0) {
                continue;
            }

            $sections[] = [
                'id' => (int)$sectioninfo->id,
                'sectionnum' => $sectionnum,
                'label' => self::get_section_label($course, $sectionnum, $sectioninfo),
                'visible' => $sectionrecord ? !empty($sectionrecord->visible) : !empty($sectioninfo->visible),
                'availability' => $sectionrecord ? trim((string)$sectionrecord->availability) : '',
                'activitycount' => $activitycount,
            ];
        }

        usort($sections, function(array $a, array $b): int {
            return $a['sectionnum'] <=> $b['sectionnum'];
        });

        return $sections;
    }

    private static function get_section_label(\stdClass $course, int $sectionnum, $sectioninfo): string {
        if ($sectionnum === 0) {
            return get_string('generalsectionlabel', 'local_exammanager');
        }

        $base = ((string)($course->format ?? '') === 'tiles')
            ? get_string('tilelabel', 'local_exammanager') . ' ' . $sectionnum
            : get_string('section') . ' ' . $sectionnum;
        $sectionname = '';

        if (function_exists('get_section_name')) {
            try {
                $sectionname = trim((string)get_section_name($course, $sectioninfo ?: $sectionnum));
            } catch (\Throwable $e) {
                $sectionname = '';
            }
        }

        if ($sectionname === '' && $sectioninfo && !empty($sectioninfo->name)) {
            $sectionname = trim((string)$sectioninfo->name);
        }

        if ($sectionname === '' || $sectionname === (string)$sectionnum || strcasecmp($sectionname, $base) === 0) {
            return $base;
        }

        return $base . ' - ' . format_string($sectionname);
    }

    public static function get_grade_items(int $courseid): array {
        global $DB;

        $items = $DB->get_records_sql(
            "SELECT id, itemname, itemtype, itemmodule, iteminstance, grademax
               FROM {grade_items}
              WHERE courseid = ?
                AND gradetype <> 0
           ORDER BY sortorder ASC, id ASC",
            [$courseid]
        );

        $options = [];
        foreach ($items as $item) {
            if ((string)$item->itemtype === 'course') {
                $label = get_string('course') . ' - total';
            } else if (trim((string)$item->itemname) !== '') {
                $label = format_string($item->itemname);
            } else {
                $label = trim((string)$item->itemmodule) !== ''
                    ? ucfirst((string)$item->itemmodule) . ' #' . (int)$item->iteminstance
                    : 'Grade item #' . (int)$item->id;
            }

            $options[] = [
                'id' => (int)$item->id,
                'label' => $label,
                'grademax' => (float)$item->grademax,
            ];
        }

        return $options;
    }

    public static function get_profile_fields(): array {
        global $DB;

        $columns = $DB->get_columns('user');
        $standardfields = [
            'firstname',
            'lastname',
            'email',
            'city',
            'country',
            'institution',
            'department',
            'idnumber',
            'phone1',
            'phone2',
            'address',
            'lang',
            'timezone',
        ];

        $options = [];
        foreach ($standardfields as $field) {
            if (!isset($columns[$field])) {
                continue;
            }

            $options[] = [
                'value' => 'sf_' . $field,
                'label' => function_exists('get_user_field_name') ? get_user_field_name($field) : ucfirst($field),
            ];
        }

        $manager = $DB->get_manager();
        $table = new \xmldb_table('user_info_field');
        if ($manager->table_exists($table)) {
            $customfields = $DB->get_records(
                'user_info_field',
                null,
                'sortorder ASC, name ASC',
                'id, shortname, name'
            );

            foreach ($customfields as $field) {
                $shortname = trim((string)$field->shortname);
                if ($shortname === '') {
                    continue;
                }

                $options[] = [
                    'value' => 'cf_' . $shortname,
                    'label' => format_string($field->name) . ' (' . $shortname . ')',
                ];
            }
        }

        return $options;
    }

    public static function build_restriction_from_form(array $data): array {
        $kind = self::scalar($data, 'restrictionkind');
        if ($kind === 'remove') {
            return [(object)['type' => 'remove'], ''];
        }

        if (!in_array($kind, ['date', 'grade', 'profile', 'set'], true)) {
            return [null, get_string('invalidrestrictiontype', 'local_exammanager')];
        }

        if ($kind !== 'set') {
            return self::build_condition($kind, $data, '');
        }

        $children = [];
        foreach (['date', 'grade', 'profile'] as $childkind) {
            if (empty($data['set_' . $childkind])) {
                continue;
            }

            [$condition, $error] = self::build_condition($childkind, $data, 'set_');
            if ($error !== '') {
                return [null, $error];
            }
            $children[] = $condition;
        }

        if (empty($children)) {
            return [null, get_string('restrictionsetneedsatleastone', 'local_exammanager')];
        }

        $operator = self::scalar($data, 'set_operator') === '|' ? '|' : '&';
        return [self::make_nested_tree($children, $operator), ''];
    }

    public static function apply_restriction(\stdClass $course, array $cmids, \stdClass $restriction, bool $show = true): array {
        global $DB;

        $cmids = array_values(array_unique(array_filter(array_map('intval', $cmids))));
        if (empty($cmids)) {
            return ['updated' => 0, 'requested' => 0];
        }

        [$insql, $params] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'cmid');
        $params['courseid'] = (int)$course->id;

        $cms = $DB->get_records_sql(
            "SELECT cm.id, cm.course, cm.availability, m.name AS modname
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE cm.course = :courseid
                AND cm.id $insql",
            $params
        );

        if (!$cms) {
            return ['updated' => 0, 'requested' => count($cmids)];
        }

        $tx = $DB->start_delegated_transaction();
        $updated = 0;
        $remove = self::is_remove_restriction($restriction);

        foreach ($cms as $cm) {
            $availability = $remove ? null : self::append_to_availability((string)$cm->availability, $restriction, $show);
            $DB->set_field('course_modules', 'availability', $availability, ['id' => (int)$cm->id]);
            $updated++;
        }

        if (class_exists('\\core_course\\cache')) {
            \core_course\cache::purge_course_cache((int)$course->id);
        } else if (function_exists('rebuild_course_cache')) {
            rebuild_course_cache((int)$course->id, true);
        }

        $tx->allow_commit();

        return ['updated' => $updated, 'requested' => count($cmids)];
    }

    public static function apply_section_restriction(\stdClass $course, array $sectionids, \stdClass $restriction, bool $show = true): array {
        global $DB;

        $sectionids = array_values(array_unique(array_filter(array_map('intval', $sectionids))));
        if (empty($sectionids)) {
            return ['updated' => 0, 'requested' => 0];
        }

        [$insql, $params] = $DB->get_in_or_equal($sectionids, SQL_PARAMS_NAMED, 'sectionid');
        $params['courseid'] = (int)$course->id;

        $sections = $DB->get_records_sql(
            "SELECT id, course, section, availability
               FROM {course_sections}
              WHERE course = :courseid
                AND id $insql",
            $params
        );

        if (!$sections) {
            return ['updated' => 0, 'requested' => count($sectionids)];
        }

        $tx = $DB->start_delegated_transaction();
        $updated = 0;
        $remove = self::is_remove_restriction($restriction);

        foreach ($sections as $section) {
            $availability = $remove ? null : self::append_to_availability((string)$section->availability, $restriction, $show);
            $DB->set_field('course_sections', 'availability', $availability, ['id' => (int)$section->id]);
            $updated++;
        }

        if (class_exists('\\core_course\\cache')) {
            \core_course\cache::purge_course_cache((int)$course->id);
        } else if (function_exists('rebuild_course_cache')) {
            rebuild_course_cache((int)$course->id, true);
        }

        $tx->allow_commit();

        return ['updated' => $updated, 'requested' => count($sectionids)];
    }

    public static function preview_import_rows(array $rows, string $defaultshortname = ''): array {
        $preview = [];
        $rownum = 1;

        foreach ($rows as $row) {
            $rownum++;
            $preview[] = self::preview_import_row($row, $rownum, $defaultshortname);
        }

        return $preview;
    }

    public static function rebuild_import_preview(array $previewrows, array $postedrows, string $defaultshortname = ''): array {
        $rows = [];

        foreach ($previewrows as $idx => $previewrow) {
            $row = isset($previewrow['source_row']) && is_array($previewrow['source_row'])
                ? $previewrow['source_row']
                : self::legacy_preview_to_source_row($previewrow);

            if (isset($postedrows[$idx]) && is_array($postedrows[$idx])) {
                foreach (self::import_editable_fields() as $field) {
                    if (array_key_exists($field, $postedrows[$idx])) {
                        $row[$field] = trim((string)$postedrows[$idx][$field]);
                    }
                }
            }

            $rows[] = $row;
        }

        $preview = self::preview_import_rows($rows, $defaultshortname);
        foreach ($preview as $idx => &$previewrow) {
            $previousrow = $previewrows[$idx] ?? [];
            $previoussource = (isset($previousrow['source_row']) && is_array($previousrow['source_row']))
                ? $previousrow['source_row']
                : [];

            if (
                self::lower((string)($previousrow['status'] ?? '')) === 'applique' &&
                self::source_rows_equal($previoussource, $previewrow['source_row'] ?? [])
            ) {
                $previewrow['status'] = (string)$previousrow['status'];
                $previewrow['message'] = (string)($previousrow['message'] ?? get_string('restrictionalreadyapplied', 'local_exammanager'));
            }
        }
        unset($previewrow);

        return $preview;
    }

    public static function apply_import_preview_row(array $row): array {
        $status = (string)($row['status'] ?? '');
        if ($status !== 'READY') {
            return [
                'status' => 'ERROR',
                'message' => (string)($row['message'] ?? get_string('rownotready', 'local_exammanager')),
            ];
        }

        $course = self::get_course_by_shortname((string)($row['course_shortname'] ?? ''));
        if (!$course) {
            return [
                'status' => 'ERROR',
                'message' => get_string('shortnamenotfound', 'local_exammanager'),
            ];
        }

        $restriction = json_decode((string)($row['restriction_json'] ?? ''));
        if (!$restriction) {
            return [
                'status' => 'ERROR',
                'message' => get_string('invalidrestriction', 'local_exammanager'),
            ];
        }

        $show = !empty($row['showrestriction']);
        $targetid = (int)($row['target_id'] ?? 0);
        $remove = self::is_remove_restriction($restriction);

        if (($row['target_type'] ?? '') === 'sections') {
            $result = self::apply_section_restriction($course, [$targetid], $restriction, $show);
            return [
                'status' => ((int)$result['updated'] > 0) ? 'APPLIQUÉ' : 'ERROR',
                'message' => ((int)$result['updated'] > 0)
                    ? ($remove ? get_string('sectionrestrictionscleared', 'local_exammanager') : get_string('sectiontileupdated', 'local_exammanager'))
                    : get_string('nosectiontileupdated', 'local_exammanager'),
            ];
        }

        $result = self::apply_restriction($course, [$targetid], $restriction, $show);
        return [
            'status' => ((int)$result['updated'] > 0) ? 'APPLIQUÉ' : 'ERROR',
            'message' => ((int)$result['updated'] > 0)
                ? ($remove ? get_string('activityrestrictionscleared', 'local_exammanager') : get_string('activityupdated', 'local_exammanager'))
                : get_string('noactivityupdated', 'local_exammanager'),
        ];
    }

    private static function preview_import_row(array $row, int $rownum, string $defaultshortname): array {
        $source = self::canonical_import_source_row($row, $defaultshortname);
        $course_shortname = $source['course_shortname'];
        $targettype = self::normalize_target_type($source['target_type']);
        $sectionvalue = $source['section'];
        $activityvalue = $source['activity'];

        $base = [
            'rownum' => $rownum,
            'source_row' => $source,
            'course_shortname' => $course_shortname,
            'target_type' => $targettype,
            'target_id' => 0,
            'target_label' => '',
            'restriction_type' => self::normalize_restriction_type($source['restriction_type']),
            'restriction_label' => '',
            'showrestriction' => self::to_bool($source['show']),
            'restriction_json' => '',
            'status' => 'ERROR',
            'message' => '',
        ];

        if ($course_shortname === '') {
            $base['message'] = get_string('coursemissing', 'local_exammanager');
            return $base;
        }

        $course = self::get_course_by_shortname($course_shortname);
        if (!$course) {
            $base['message'] = get_string('shortnamenotfound', 'local_exammanager');
            return $base;
        }

        [$targetid, $targetlabel, $targeterror] = self::resolve_import_target($course, $targettype, $sectionvalue, $activityvalue);
        if ($targeterror !== '') {
            $base['message'] = $targeterror;
            return $base;
        }

        [$formdata, $restrictionlabel] = self::build_import_form_data($source, $course, $base['restriction_type']);
        [$restriction, $error] = self::build_restriction_from_form($formdata);
        if ($error !== '') {
            $base['message'] = $error;
            return $base;
        }

        $restrictionjson = json_encode($restriction);
        if ($restrictionjson === false) {
            $base['message'] = get_string('cannotpreparerestriction', 'local_exammanager');
            return $base;
        }

        $base['target_id'] = $targetid;
        $base['target_label'] = $targetlabel;
        $base['restriction_label'] = $restrictionlabel;
        $base['restriction_json'] = $restrictionjson;
        $base['status'] = 'READY';
        $base['message'] = get_string('previewok', 'local_exammanager');

        return $base;
    }

    private static function canonical_import_source_row(array $row, string $defaultshortname = ''): array {
        $source = [
            'course_shortname' => self::first_non_empty($row, ['course_shortname', 'shortname', 'cours'], $defaultshortname),
            'target_type' => self::first_non_empty($row, ['target_type', 'cible', 'type_cible'], 'sections'),
            'section' => self::first_non_empty($row, ['section', 'tuile', 'tile', 'section_or_tile'], ''),
            'activity' => self::first_non_empty($row, ['activity', 'activite', 'activité', 'activity_name'], ''),
            'restriction_type' => self::first_non_empty($row, ['restriction_type', 'type_restriction', 'restriction'], 'date'),
            'date_direction' => self::first_non_empty($row, ['date_direction', 'sens_date'], 'from'),
            'date_time' => self::first_non_empty($row, ['date_time', 'date', 'date_restriction'], ''),
            'grade_item' => self::first_non_empty($row, ['grade_item', 'note', 'grade_itemid'], ''),
            'grade_min' => self::first_non_empty($row, ['grade_min', 'note_min', 'min'], ''),
            'grade_max' => self::first_non_empty($row, ['grade_max', 'note_max', 'max'], ''),
            'profile_field' => self::first_non_empty($row, ['profile_field', 'champ_profil'], ''),
            'profile_operator' => self::first_non_empty($row, ['profile_operator', 'condition_profil'], 'isequalto'),
            'profile_value' => self::first_non_empty($row, ['profile_value', 'valeur_profil'], ''),
            'set_operator' => self::first_non_empty($row, ['set_operator', 'logique'], '&'),
            'set_date' => self::first_non_empty($row, ['set_date'], ''),
            'set_grade' => self::first_non_empty($row, ['set_grade'], ''),
            'set_profile' => self::first_non_empty($row, ['set_profile'], ''),
            'show' => self::first_non_empty($row, ['show', 'visible_when_restricted', 'afficher'], '1'),
        ];

        $source['target_type'] = self::normalize_target_type($source['target_type']);
        $source['restriction_type'] = self::normalize_restriction_type($source['restriction_type']);
        $source['date_direction'] = self::normalize_date_direction($source['date_direction']);
        $source['date_time'] = self::normalize_import_datetime_value($source['date_time']);
        $source['profile_field'] = self::normalize_profile_field($source['profile_field']);
        $source['profile_operator'] = self::normalize_profile_operator($source['profile_operator']);
        $source['set_operator'] = self::normalize_set_operator($source['set_operator']);

        foreach (['set_date', 'set_grade', 'set_profile'] as $field) {
            $source[$field] = self::to_bool($source[$field]) ? '1' : '';
        }

        $source['show'] = self::to_bool($source['show']) ? '1' : '0';

        return $source;
    }

    private static function legacy_preview_to_source_row(array $previewrow): array {
        return [
            'course_shortname' => (string)($previewrow['course_shortname'] ?? ''),
            'target_type' => ((string)($previewrow['target_type'] ?? '') === 'activities') ? 'activities' : 'sections',
            'section' => (string)($previewrow['target_label'] ?? ''),
            'activity' => '',
            'restriction_type' => (string)($previewrow['restriction_type'] ?? 'date'),
            'date_direction' => 'from',
            'date_time' => '',
            'grade_item' => '',
            'grade_min' => '',
            'grade_max' => '',
            'profile_field' => '',
            'profile_operator' => 'isequalto',
            'profile_value' => '',
            'set_operator' => '&',
            'set_date' => '',
            'set_grade' => '',
            'set_profile' => '',
            'show' => !empty($previewrow['showrestriction']) ? '1' : '0',
        ];
    }

    private static function import_editable_fields(): array {
        return [
            'course_shortname',
            'target_type',
            'section',
            'activity',
            'restriction_type',
            'date_direction',
            'date_time',
            'grade_item',
            'grade_min',
            'grade_max',
            'profile_field',
            'profile_operator',
            'profile_value',
            'set_operator',
            'set_date',
            'set_grade',
            'set_profile',
            'show',
        ];
    }

    private static function source_rows_equal(array $left, array $right): bool {
        foreach (self::import_editable_fields() as $field) {
            if ((string)($left[$field] ?? '') !== (string)($right[$field] ?? '')) {
                return false;
            }
        }

        return true;
    }

    private static function is_remove_restriction(\stdClass $restriction): bool {
        return (string)($restriction->type ?? '') === 'remove';
    }

    private static function resolve_import_target(\stdClass $course, string $targettype, string $sectionvalue, string $activityvalue): array {
        if ($targettype === 'sections') {
            if ($sectionvalue === '') {
                return [0, '', get_string('sectiontilemissing', 'local_exammanager')];
            }

            $section = self::find_section($course, $sectionvalue);
            if (!$section) {
                return [0, '', get_string('sectiontilenotfoundwithvalue', 'local_exammanager', $sectionvalue)];
            }

            return [(int)$section['id'], (string)$section['label'], ''];
        }

        if ($activityvalue === '') {
            return [0, '', get_string('activitymissing', 'local_exammanager')];
        }

        $activities = self::get_course_activities($course);
        $matches = [];

        foreach ($activities as $activity) {
            if (self::lower((string)$activity['name']) !== self::lower($activityvalue)) {
                continue;
            }

            if ($sectionvalue !== '' && !self::section_matches($activity, $sectionvalue)) {
                continue;
            }

            $matches[] = $activity;
        }

        if (empty($matches)) {
            return [0, '', get_string('activitynotfoundwithvalue', 'local_exammanager', $activityvalue)];
        }

        if (count($matches) > 1) {
            return [0, '', get_string('activityambiguouswithvalue', 'local_exammanager', $activityvalue)];
        }

        $activity = $matches[0];
        return [(int)$activity['id'], (string)$activity['sectionlabel'] . ' - ' . (string)$activity['name'], ''];
    }

    private static function build_import_form_data(array $row, \stdClass $course, string $restrictiontype): array {
        $restrictiontype = self::normalize_restriction_type($restrictiontype);
        $data = [
            'restrictionkind' => $restrictiontype,
            'date_direction' => self::normalize_date_direction(self::first_non_empty($row, ['date_direction', 'sens_date'], 'from')),
            'date_time' => self::first_non_empty($row, ['date_time', 'date', 'date_restriction'], ''),
            'grade_itemid' => (string)self::resolve_grade_item_id($course, self::first_non_empty($row, ['grade_item', 'note', 'grade_itemid'], '')),
            'grade_min' => self::first_non_empty($row, ['grade_min', 'note_min', 'min'], ''),
            'grade_max' => self::first_non_empty($row, ['grade_max', 'note_max', 'max'], ''),
            'profile_field' => self::normalize_profile_field(self::first_non_empty($row, ['profile_field', 'champ_profil'], '')),
            'profile_operator' => self::normalize_profile_operator(self::first_non_empty($row, ['profile_operator', 'condition_profil'], 'isequalto')),
            'profile_value' => self::first_non_empty($row, ['profile_value', 'valeur_profil'], ''),
            'set_operator' => self::normalize_set_operator(self::first_non_empty($row, ['set_operator', 'logique'], '&')),
        ];

        foreach (['date', 'grade', 'profile'] as $kind) {
            $data['set_' . $kind] = self::to_bool(self::first_non_empty($row, ['set_' . $kind], '')) ? '1' : '';
        }

        $data['set_date_direction'] = $data['date_direction'];
        $data['set_date_time'] = $data['date_time'];
        $data['set_grade_itemid'] = $data['grade_itemid'];
        $data['set_grade_min'] = $data['grade_min'];
        $data['set_grade_max'] = $data['grade_max'];
        $data['set_profile_field'] = $data['profile_field'];
        $data['set_profile_operator'] = $data['profile_operator'];
        $data['set_profile_value'] = $data['profile_value'];

        return [$data, self::restriction_label($data)];
    }

    private static function find_section(\stdClass $course, string $sectionvalue): ?array {
        $needle = self::lower($sectionvalue);

        foreach (self::get_course_sections($course) as $section) {
            $number = (string)$section['sectionnum'];
            $label = (string)$section['label'];
            $candidates = [
                $number,
                $label,
                'section ' . $number,
                'tuile ' . $number,
                'tile ' . $number,
            ];

            foreach ($candidates as $candidate) {
                if (self::lower($candidate) === $needle) {
                    return $section;
                }
            }
        }

        return null;
    }

    private static function section_matches(array $activity, string $sectionvalue): bool {
        $needle = self::lower($sectionvalue);
        $number = (string)($activity['sectionnum'] ?? '');
        $label = (string)($activity['sectionlabel'] ?? '');

        return in_array($needle, [
            self::lower($number),
            self::lower($label),
            self::lower('section ' . $number),
            self::lower('tuile ' . $number),
            self::lower('tile ' . $number),
        ], true);
    }

    private static function normalize_target_type(string $value): string {
        $value = self::lower($value);
        if (in_array($value, ['activity', 'activite', 'activité', 'activities', 'activites', 'activités'], true)) {
            return 'activities';
        }
        return 'sections';
    }

    private static function normalize_restriction_type(string $value): string {
        $value = self::lower($value);
        if (in_array($value, ['remove', 'clear', 'delete', 'supprimer', 'enlever', 'retirer', 'aucune'], true)) {
            return 'remove';
        }
        if (in_array($value, ['note', 'grade'], true)) {
            return 'grade';
        }
        if (in_array($value, ['profil', 'profile', 'utilisateur'], true)) {
            return 'profile';
        }
        if (in_array($value, ['set', 'jeu', 'jeu de restrictions', 'restrictions'], true)) {
            return 'set';
        }
        return 'date';
    }

    private static function normalize_date_direction(string $value): string {
        $value = self::lower($value);
        if (in_array($value, ['until', 'avant', 'jusqua', 'jusqu a', 'jusqu’à', 'jusqu\'a'], true)) {
            return 'until';
        }
        if (in_array($value, ['from', 'apres', 'après', 'partir', 'a partir', 'à partir'], true)) {
            return 'from';
        }
        return $value === 'until' ? 'until' : 'from';
    }

    private static function normalize_import_datetime_value(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        try {
            return util::to_datetime_local_value($value);
        } catch (\Throwable $e) {
            return $value;
        }
    }

    private static function resolve_grade_item_id(\stdClass $course, string $value): int {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        if (ctype_digit($value)) {
            return (int)$value;
        }

        $needle = self::lower($value);
        foreach (self::get_grade_items((int)$course->id) as $item) {
            if (self::lower((string)$item['label']) === $needle) {
                return (int)$item['id'];
            }
        }

        return 0;
    }

    private static function normalize_profile_field(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^(sf|cf)_/i', $value)) {
            return $value;
        }

        return 'sf_' . $value;
    }

    private static function normalize_profile_operator(string $value): string {
        $value = self::lower($value);
        $map = [
            '=' => 'isequalto',
            'egal' => 'isequalto',
            'égal' => 'isequalto',
            'contient' => 'contains',
            'ne contient pas' => 'doesnotcontain',
            'commence par' => 'startswith',
            'termine par' => 'endswith',
            'vide' => 'isempty',
            'non vide' => 'isnotempty',
        ];

        return $map[$value] ?? ($value !== '' ? $value : 'isequalto');
    }

    private static function normalize_set_operator(string $value): string {
        $value = self::lower($value);
        return in_array($value, ['|', 'ou', 'or'], true) ? '|' : '&';
    }

    private static function restriction_label(array $data): string {
        $kind = (string)($data['restrictionkind'] ?? '');
        if ($kind === 'remove') {
            return 'Enlever les restrictions';
        }
        if ($kind === 'date') {
            return 'Date ' . (string)($data['date_time'] ?? '');
        }
        if ($kind === 'grade') {
            return 'Note min ' . (string)($data['grade_min'] ?? '') . '%';
        }
        if ($kind === 'profile') {
            return 'Profil ' . (string)($data['profile_field'] ?? '');
        }
        return 'Jeu de restrictions';
    }

    private static function first_non_empty(array $row, array $keys, string $default = ''): string {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
                return trim((string)$row[$key]);
            }
        }

        return $default;
    }

    private static function to_bool(string $value): bool {
        $value = self::lower($value);
        return in_array($value, ['1', 'yes', 'oui', 'true', 'vrai', 'x'], true);
    }

    private static function lower(string $value): string {
        $value = trim($value);
        if (class_exists('\\core_text')) {
            $value = \core_text::strtolower($value);
        } else {
            $value = strtolower($value);
        }

        $value = strtr($value, [
            'à' => 'a',
            'á' => 'a',
            'â' => 'a',
            'ä' => 'a',
            'ç' => 'c',
            'è' => 'e',
            'é' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'î' => 'i',
            'ï' => 'i',
            'ô' => 'o',
            'ö' => 'o',
            'ù' => 'u',
            'û' => 'u',
            'ü' => 'u',
            '’' => "'",
            '–' => '-',
            '—' => '-',
        ]);

        $value = preg_replace('/\s+/', ' ', $value);
        return $value ?? '';
    }

    private static function build_condition(string $kind, array $data, string $prefix): array {
        if ($kind === 'date') {
            return self::build_date_condition($data, $prefix);
        }
        if ($kind === 'grade') {
            return self::build_grade_condition($data, $prefix);
        }
        if ($kind === 'profile') {
            return self::build_profile_condition($data, $prefix);
        }
        return [null, get_string('invalidrestrictiontype', 'local_exammanager')];
    }

    private static function build_date_condition(array $data, string $prefix): array {
        $directionkey = self::scalar($data, $prefix . 'date_direction');
        $datetime = self::scalar($data, $prefix . 'date_time');

        if ($datetime === '') {
            return [null, get_string('daterestrictionrequired', 'local_exammanager')];
        }

        try {
            $timestamp = util::parse_datetime($datetime);
        } catch (\Throwable $e) {
            return [null, get_string('invaliddateformat', 'local_exammanager')];
        }

        $direction = $directionkey === 'until' ? '<' : '>=';
        if (class_exists('\\availability_date\\condition')) {
            return [\availability_date\condition::get_json($direction, $timestamp), ''];
        }

        return [(object)['type' => 'date', 'd' => $direction, 't' => (int)$timestamp], ''];
    }

    private static function build_grade_condition(array $data, string $prefix): array {
        $gradeitemid = (int)self::scalar($data, $prefix . 'grade_itemid');
        $minraw = self::scalar($data, $prefix . 'grade_min');
        $maxraw = self::scalar($data, $prefix . 'grade_max');

        if ($gradeitemid <= 0) {
            return [null, get_string('gradereferencerequired', 'local_exammanager')];
        }

        if ($minraw === '' && $maxraw === '') {
            return [null, get_string('grademinmaxrequired', 'local_exammanager')];
        }

        $min = null;
        $max = null;
        if ($minraw !== '') {
            if (!is_numeric($minraw)) {
                return [null, get_string('grademinnotnumeric', 'local_exammanager')];
            }
            $min = (float)$minraw;
        }
        if ($maxraw !== '') {
            if (!is_numeric($maxraw)) {
                return [null, get_string('grademaxnotnumeric', 'local_exammanager')];
            }
            $max = (float)$maxraw;
        }

        if (($min !== null && ($min < 0 || $min > 100)) || ($max !== null && ($max < 0 || $max > 100))) {
            return [null, get_string('gradesmustbepercent', 'local_exammanager')];
        }
        if ($min !== null && $max !== null && $max <= $min) {
            return [null, get_string('grademaxmustexceedmin', 'local_exammanager')];
        }

        if (class_exists('\\availability_grade\\condition')) {
            return [\availability_grade\condition::get_json($gradeitemid, $min, $max), ''];
        }

        $condition = (object)['type' => 'grade', 'id' => $gradeitemid];
        if ($min !== null) {
            $condition->min = $min;
        }
        if ($max !== null) {
            $condition->max = $max;
        }
        return [$condition, ''];
    }

    private static function build_profile_condition(array $data, string $prefix): array {
        $field = self::scalar($data, $prefix . 'profile_field');
        $operator = self::scalar($data, $prefix . 'profile_operator');
        $value = self::scalar($data, $prefix . 'profile_value');
        $allowedoperators = ['isequalto', 'contains', 'doesnotcontain', 'startswith', 'endswith', 'isempty', 'isnotempty'];

        if (!preg_match('/^(sf|cf)_[A-Za-z0-9_-]+$/', $field)) {
            return [null, get_string('invalidprofilefield', 'local_exammanager')];
        }
        if (!in_array($operator, $allowedoperators, true)) {
            return [null, get_string('invalidprofileoperator', 'local_exammanager')];
        }

        $needsvalue = !in_array($operator, ['isempty', 'isnotempty'], true);
        if ($needsvalue && $value === '') {
            return [null, get_string('profilevaluerequired', 'local_exammanager')];
        }

        $iscustomfield = strpos($field, 'cf_') === 0;
        $fieldname = substr($field, 3);
        $conditionvalue = $needsvalue ? $value : null;

        if (class_exists('\\availability_profile\\condition')) {
            return [\availability_profile\condition::get_json($iscustomfield, $fieldname, $operator, $conditionvalue), ''];
        }

        $condition = (object)['type' => 'profile', 'op' => $operator];
        if ($iscustomfield) {
            $condition->cf = $fieldname;
        } else {
            $condition->sf = $fieldname;
        }
        if ($needsvalue) {
            $condition->v = $value;
        }

        return [$condition, ''];
    }

    private static function append_to_availability(string $current, \stdClass $restriction, bool $show): string {
        $current = trim($current);
        if ($current === '') {
            return self::encode_availability(self::make_root_tree([$restriction], '&', [$show]));
        }

        $existing = json_decode($current);
        if (!$existing || !isset($existing->op) || !isset($existing->c) || !is_array($existing->c)) {
            throw new \moodle_exception(get_string('invalidexistingavailabilitystructure', 'local_exammanager'));
        }

        if ((string)$existing->op === '&') {
            $previouscount = count($existing->c);
            $existing->c[] = $restriction;

            $showc = (isset($existing->showc) && is_array($existing->showc))
                ? array_values($existing->showc)
                : array_fill(0, $previouscount, true);

            if (count($showc) < $previouscount) {
                $showc = array_pad($showc, $previouscount, true);
            } else if (count($showc) > $previouscount) {
                $showc = array_slice($showc, 0, $previouscount);
            }

            $showc[] = $show;
            $existing->showc = $showc;
            unset($existing->show);

            return self::encode_availability($existing);
        }

        $existingshow = self::root_show_value($existing);
        $nestedexisting = clone $existing;
        unset($nestedexisting->show, $nestedexisting->showc);

        return self::encode_availability(self::make_root_tree([$nestedexisting, $restriction], '&', [$existingshow, $show]));
    }

    private static function root_show_value(\stdClass $root): bool {
        if (isset($root->show)) {
            return (bool)$root->show;
        }

        if (isset($root->showc) && is_array($root->showc)) {
            foreach ($root->showc as $childshow) {
                if (!$childshow) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function make_nested_tree(array $children, string $operator): \stdClass {
        return (object)['op' => $operator, 'c' => array_values($children)];
    }

    private static function make_root_tree(array $children, string $operator, $show): \stdClass {
        $root = self::make_nested_tree($children, $operator);
        if (in_array($operator, ['&', '!|'], true)) {
            if (is_array($show)) {
                $root->showc = array_values($show);
            } else {
                $root->showc = array_fill(0, count($children), (bool)$show);
            }
        } else {
            $root->show = is_array($show) ? (bool)reset($show) : (bool)$show;
        }
        return $root;
    }

    private static function encode_availability(\stdClass $availability): string {
        $json = json_encode($availability);
        if ($json === false) {
            throw new \moodle_exception(get_string('cannotgeneraterestriction', 'local_exammanager'));
        }
        return $json;
    }

    private static function scalar(array $data, string $key): string {
        if (!isset($data[$key]) || is_array($data[$key])) {
            return '';
        }
        return trim((string)$data[$key]);
    }
}
