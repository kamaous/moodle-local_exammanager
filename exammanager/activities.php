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
 * Bulk course activity and section availability restriction planner.
 *
 * @package    local_exammanager
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require('../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/exammanager:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/exammanager/activities.php'));
$PAGE->set_title(get_string('activitiesplanning', 'local_exammanager'));
$PAGE->set_heading(get_string('pluginname', 'local_exammanager'));

$PAGE->requires->js_call_amd('local_exammanager/activity_targets', 'init');
$PAGE->requires->js_call_amd('local_exammanager/activity_apply', 'init');

global $OUTPUT;
global $SESSION, $USER;

$shortname = trim(optional_param('shortname', '', PARAM_TEXT));
$action = optional_param('action', '', PARAM_ALPHA);
$sessionkey = 'local_exammanager_activity_import_' . $USER->id;
$course = null;
$applymessage = '';
$applymessagetype = 'notifysuccess';
$bulkrows = [];

if ($action === 'apply' && confirm_sesskey()) {
    $shortname = trim(optional_param('shortname', '', PARAM_TEXT));
    $course = \local_exammanager\activity_planner::get_course_by_shortname($shortname);

    if (!$course) {
        $applymessage = get_string('shortnamenotfound', 'local_exammanager');
        $applymessagetype = 'notifyproblem';
    } else {
        $targettype = optional_param('targettype', 'sections', PARAM_ALPHA);
        if (!in_array($targettype, ['sections', 'activities'], true)) {
            $targettype = 'sections';
        }

        $sectionids = optional_param_array('sectionids', [], PARAM_INT);
        $cmids = optional_param_array('cmids', [], PARAM_INT);
        [$restriction, $error] = \local_exammanager\activity_planner::build_restriction_from_form($_POST);
        $showrestriction = !empty($_POST['showrestriction']);
        $removerestriction = $restriction && (string)($restriction->type ?? '') === 'remove';

        if ($targettype === 'sections' && empty($sectionids)) {
            $applymessage = get_string('selectatleastonesection', 'local_exammanager');
            $applymessagetype = 'notifyproblem';
        } else if ($targettype === 'activities' && empty($cmids)) {
            $applymessage = get_string('selectatleastoneactivity', 'local_exammanager');
            $applymessagetype = 'notifyproblem';
        } else if ($error !== '') {
            $applymessage = $error;
            $applymessagetype = 'notifyproblem';
        } else {
            try {
                if ($targettype === 'sections') {
                    $result = \local_exammanager\activity_planner::apply_section_restriction($course, $sectionids, $restriction, $showrestriction);
                    $applymessage = $removerestriction
                        ? get_string('sectionscleaned', 'local_exammanager', (int)$result['updated'])
                        : get_string('sectionsupdated', 'local_exammanager', (int)$result['updated']);
                } else {
                    $result = \local_exammanager\activity_planner::apply_restriction($course, $cmids, $restriction, $showrestriction);
                    $applymessage = $removerestriction
                        ? get_string('activitiescleaned', 'local_exammanager', (int)$result['updated'])
                        : get_string('activitiesupdated', 'local_exammanager', (int)$result['updated']);
                }

                if ((int)$result['updated'] === 0) {
                    $applymessagetype = 'notifyproblem';
                    $applymessage = get_string('nocourseitemupdated', 'local_exammanager');
                }
            } catch (Throwable $e) {
                debugging('ExamManager activity planning error: ' . $e->getMessage(), DEBUG_DEVELOPER);
                $applymessage = get_string('restrictionapplyerror', 'local_exammanager');
                $applymessagetype = 'notifyproblem';
            }
        }
    }
}

if (!$course && $shortname !== '') {
    $course = \local_exammanager\activity_planner::get_course_by_shortname($shortname);
}

if ($action === 'bulkpreview' && confirm_sesskey()) {
    if (!isset($_FILES['activityfile']) || empty($_FILES['activityfile']['tmp_name'])) {
        $applymessage = get_string('nofile', 'local_exammanager');
        $applymessagetype = 'notifyproblem';
    } else {
        try {
            $tmp = $_FILES['activityfile']['tmp_name'];
            $name = $_FILES['activityfile']['name'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (!$ext) {
                throw new moodle_exception(get_string('cannotdetectfiletype', 'local_exammanager'));
            }

            $newfile = $tmp . '.' . $ext;
            if (!copy($tmp, $newfile)) {
                throw new moodle_exception(get_string('filecopyerror', 'local_exammanager'));
            }

            $importrows = \local_exammanager\reader::read_rows($newfile);
            $bulkrows = \local_exammanager\activity_planner::preview_import_rows($importrows, $shortname);
            $SESSION->$sessionkey = $bulkrows;
            $applymessage = get_string('bulkpreviewcompletedmsg', 'local_exammanager');
            $applymessagetype = 'notifysuccess';
        } catch (Throwable $e) {
            debugging('ExamManager activity import preview error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $applymessage = get_string('bulkpreviewerrormsg', 'local_exammanager');
            $applymessagetype = 'notifyproblem';
        }
    }
} else if ($action === 'bulkrefresh' && confirm_sesskey()) {
    if (empty($SESSION->$sessionkey) || !is_array($SESSION->$sessionkey)) {
        $applymessage = get_string('nopreviewtorefresh', 'local_exammanager');
        $applymessagetype = 'notifyproblem';
    } else {
        $postedrows = $_POST['rowedit'] ?? [];
        if (!is_array($postedrows)) {
            $postedrows = [];
        }

        $bulkrows = \local_exammanager\activity_planner::rebuild_import_preview($SESSION->$sessionkey, $postedrows, $shortname);
        $SESSION->$sessionkey = $bulkrows;
        $applymessage = get_string('bulkpreviewrefreshedmsg', 'local_exammanager');
        $applymessagetype = 'notifysuccess';
    }
} else if ($action === 'bulkapply' && confirm_sesskey()) {
    if (empty($SESSION->$sessionkey) || !is_array($SESSION->$sessionkey)) {
        $applymessage = get_string('nopreviewtoapply', 'local_exammanager');
        $applymessagetype = 'notifyproblem';
    } else {
        $postedrows = $_POST['rowedit'] ?? [];
        if (!is_array($postedrows)) {
            $postedrows = [];
        }

        $bulkrows = \local_exammanager\activity_planner::rebuild_import_preview($SESSION->$sessionkey, $postedrows, $shortname);
        $applied = 0;

        foreach ($bulkrows as &$bulkrow) {
            if (($bulkrow['status'] ?? '') !== 'READY') {
                continue;
            }

            $result = \local_exammanager\activity_planner::apply_import_preview_row($bulkrow);
            $bulkrow['status'] = $result['status'];
            $bulkrow['message'] = $result['message'];
            if ($result['status'] === 'APPLIQUÉ') {
                $applied++;
            }
        }
        unset($bulkrow);

        $SESSION->$sessionkey = $bulkrows;
        $applymessage = get_string('operationsappliedmsg', 'local_exammanager', $applied);
        $applymessagetype = $applied > 0 ? 'notifysuccess' : 'notifyproblem';
    }
} else if (!empty($SESSION->$sessionkey) && is_array($SESSION->$sessionkey)) {
    $bulkrows = $SESSION->$sessionkey;
}

$renderselect = function(string $name, array $options, string $selected = '', array $attrs = []): string {
    $attrs = array_merge(['class' => 'form-select custom-select'], $attrs);
    return html_writer::select($options, $name, $selected, false, $attrs);
};

$renderbulkinput = function(int $idx, string $field, array $source, array $attrs = []): string {
    $attrs = array_merge([
        'type' => 'text',
        'name' => 'rowedit[' . $idx . '][' . $field . ']',
        'value' => (string)($source[$field] ?? ''),
        'class' => 'form-control',
    ], $attrs);

    return html_writer::empty_tag('input', $attrs);
};

$renderbulkselect = function(int $idx, string $field, array $source, array $options, array $attrs = []): string {
    $attrs = array_merge(['class' => 'form-select custom-select'], $attrs);

    return html_writer::select(
        $options,
        'rowedit[' . $idx . '][' . $field . ']',
        (string)($source[$field] ?? ''),
        false,
        $attrs
    );
};

$renderbulkmanualselect = function(int $idx, string $field, array $source, array $options, array $attrs = [], string $selected = null): string {
    $selected = $selected === null ? (string)($source[$field] ?? '') : $selected;
    $attrs = array_merge([
        'name' => 'rowedit[' . $idx . '][' . $field . ']',
        'class' => 'form-select custom-select',
    ], $attrs);

    $hasselected = false;
    $optionhtml = '';
    foreach ($options as $option) {
        $value = (string)($option['value'] ?? '');
        $label = (string)($option['label'] ?? $value);
        $optionattrs = ['value' => $value];
        foreach ($option as $key => $optionvalue) {
            if (strpos((string)$key, 'data-') === 0) {
                $optionattrs[$key] = (string)$optionvalue;
            }
        }

        if ($selected !== '' && $value === $selected) {
            $optionattrs['selected'] = 'selected';
            $hasselected = true;
        }
        $optionhtml .= html_writer::tag('option', s($label), $optionattrs);
    }

    if ($selected !== '' && !$hasselected) {
        $optionhtml = html_writer::tag('option', s(get_string('importedvalue', 'local_exammanager', $selected)), [
            'value' => $selected,
            'selected' => 'selected',
            'data-imported' => '1',
        ]) . $optionhtml;
    }

    return html_writer::tag('select', $optionhtml, $attrs);
};

$bulkcoursecache = [];
$getbulktargets = function(string $shortname) use (&$bulkcoursecache): array {
    $shortname = trim($shortname);
    if ($shortname === '') {
        return ['sections' => [], 'activities' => []];
    }
    if (array_key_exists($shortname, $bulkcoursecache)) {
        return $bulkcoursecache[$shortname];
    }

    $course = \local_exammanager\activity_planner::get_course_by_shortname($shortname);
    if (!$course) {
        $bulkcoursecache[$shortname] = ['sections' => [], 'activities' => []];
        return $bulkcoursecache[$shortname];
    }

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
        ];
    }

    $bulkcoursecache[$shortname] = ['sections' => $sections, 'activities' => $activities];
    return $bulkcoursecache[$shortname];
};

$normalizetargetlabel = function(string $value): string {
    $value = trim($value);
    if (class_exists('\\core_text')) {
        $value = \core_text::strtolower($value);
    } else {
        $value = strtolower($value);
    }
    return $value;
};

$resolvebulksectionvalue = function(array $source, array $sections) use ($normalizetargetlabel): string {
    $selected = (string)($source['section'] ?? '');
    if ($selected === '') {
        return '';
    }

    $needle = $normalizetargetlabel($selected);
    foreach ($sections as $section) {
        if (
            (string)$section['value'] === $selected ||
            $normalizetargetlabel((string)$section['label']) === $needle
        ) {
            return (string)$section['value'];
        }
    }

    return $selected;
};

$renderbulksectionselect = function(int $idx, array $source) use ($getbulktargets, $renderbulkmanualselect, $resolvebulksectionvalue): string {
    $targets = $getbulktargets((string)($source['course_shortname'] ?? ''));
    $options = array_merge([['value' => '', 'label' => get_string('activitytargets_choosesection', 'local_exammanager')]], $targets['sections']);
    return $renderbulkmanualselect($idx, 'section', $source, $options, [
        'class' => 'form-select custom-select exammanager-bulk-section',
        'data-current' => (string)($source['section'] ?? ''),
    ], $resolvebulksectionvalue($source, $targets['sections']));
};

$renderbulkactivityselect = function(int $idx, array $source) use ($getbulktargets, $renderbulkmanualselect): string {
    $targets = $getbulktargets((string)($source['course_shortname'] ?? ''));
    $options = [['value' => '', 'label' => get_string('activitytargets_chooseactivity', 'local_exammanager')]];
    foreach ($targets['activities'] as $activity) {
        $options[] = $activity;
    }

    $attrs = [
        'class' => 'form-select custom-select exammanager-bulk-activity',
        'data-current' => (string)($source['activity'] ?? ''),
    ];
    if ((string)($source['target_type'] ?? '') !== 'activities') {
        $attrs['disabled'] = 'disabled';
    }

    return $renderbulkmanualselect($idx, 'activity', $source, $options, $attrs);
};

$renderbulklabel = function(string $label, string $content): string {
    return html_writer::tag('div', s($label), ['class' => 'local-exammanager-bulk-label']) . $content;
};

$renderbulkrestriction = function(int $idx, array $source) use ($renderbulkinput, $renderbulkselect, $renderbulklabel): string {
    $restrictiontype = (string)($source['restriction_type'] ?? 'date');
    $groupattrs = function(string $group) use ($restrictiontype, $source): array {
        $visible = $group === $restrictiontype;

        if ($restrictiontype === 'set' && in_array($group, ['date', 'grade', 'profile'], true)) {
            $visible = !empty($source['set_' . $group]);
        }

        $attrs = ['data-bulk-group' => $group];
        if (!$visible) {
            $attrs['style'] = 'display:none;';
        }

        return $attrs;
    };

    $html = html_writer::start_div('local-exammanager-bulk-restriction', ['data-bulk-restriction-row' => '1']);

    $html .= html_writer::start_div('local-exammanager-bulk-main');
    $html .= $renderbulklabel(get_string('restrictionlabel', 'local_exammanager'), $renderbulkselect($idx, 'restriction_type', $source, [
        'date' => get_string('restrictionkind_date', 'local_exammanager'),
        'grade' => get_string('restrictionkind_grade', 'local_exammanager'),
        'profile' => get_string('restrictionkind_profile', 'local_exammanager'),
        'set' => get_string('restrictionkind_set', 'local_exammanager'),
        'remove' => get_string('restrictionkind_remove', 'local_exammanager'),
    ], ['class' => 'form-select custom-select exammanager-bulk-restriction-kind']));
    $showattrs = ['data-bulk-show' => '1'];
    if ($restrictiontype === 'remove') {
        $showattrs['style'] = 'display:none;';
    }
    $html .= html_writer::start_div('', $showattrs);
    $html .= $renderbulklabel(get_string('displaylabel', 'local_exammanager'), $renderbulkselect($idx, 'show', $source, [
        '1' => get_string('yeslabel', 'local_exammanager'),
        '0' => get_string('nolabel', 'local_exammanager'),
    ]));
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('local-exammanager-bulk-group', $groupattrs('date'));
    $html .= html_writer::tag('div', get_string('restrictionkind_date', 'local_exammanager'), ['class' => 'local-exammanager-bulk-group-title']);
    $html .= html_writer::start_div('local-exammanager-bulk-pair');
    $html .= $renderbulklabel(get_string('directionlabel', 'local_exammanager'), $renderbulkselect($idx, 'date_direction', $source, [
        'from' => get_string('direction_from', 'local_exammanager'),
        'until' => get_string('direction_until', 'local_exammanager'),
    ]));
    $html .= $renderbulklabel(get_string('datetimelabel', 'local_exammanager'), $renderbulkinput($idx, 'date_time', $source, ['type' => 'datetime-local']));
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('local-exammanager-bulk-group', $groupattrs('grade'));
    $html .= html_writer::tag('div', get_string('restrictionkind_grade', 'local_exammanager'), ['class' => 'local-exammanager-bulk-group-title']);
    $html .= $renderbulklabel(get_string('gradereflabel', 'local_exammanager'), $renderbulkinput($idx, 'grade_item', $source, ['placeholder' => get_string('gradeitemplaceholder', 'local_exammanager')]));
    $html .= html_writer::start_div('local-exammanager-bulk-pair');
    $html .= $renderbulklabel(get_string('minimumlabel', 'local_exammanager'), $renderbulkinput($idx, 'grade_min', $source, ['placeholder' => get_string('minpercentplaceholder', 'local_exammanager')]));
    $html .= $renderbulklabel(get_string('maximumlabel', 'local_exammanager'), $renderbulkinput($idx, 'grade_max', $source, ['placeholder' => get_string('maxpercentplaceholder', 'local_exammanager')]));
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('local-exammanager-bulk-group', $groupattrs('profile'));
    $html .= html_writer::tag('div', get_string('restrictionkind_profile', 'local_exammanager'), ['class' => 'local-exammanager-bulk-group-title']);
    $html .= $renderbulklabel(get_string('fieldlabel', 'local_exammanager'), $renderbulkinput($idx, 'profile_field', $source, ['placeholder' => get_string('profilefieldplaceholder', 'local_exammanager')]));
    $html .= html_writer::start_div('local-exammanager-bulk-pair');
    $html .= $renderbulklabel(get_string('conditionlabel', 'local_exammanager'), $renderbulkselect($idx, 'profile_operator', $source, [
        'isequalto' => get_string('profileop_isequalto', 'local_exammanager'),
        'contains' => get_string('profileop_contains', 'local_exammanager'),
        'doesnotcontain' => get_string('profileop_doesnotcontain', 'local_exammanager'),
        'startswith' => get_string('profileop_startswith', 'local_exammanager'),
        'endswith' => get_string('profileop_endswith', 'local_exammanager'),
        'isempty' => get_string('profileop_isempty', 'local_exammanager'),
        'isnotempty' => get_string('profileop_isnotempty', 'local_exammanager'),
    ]));
    $html .= $renderbulklabel(get_string('valuelabel', 'local_exammanager'), $renderbulkinput($idx, 'profile_value', $source, ['placeholder' => get_string('valuelabel', 'local_exammanager')]));
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('local-exammanager-bulk-group', $groupattrs('set'));
    $html .= html_writer::tag('div', get_string('restrictionkind_set', 'local_exammanager'), ['class' => 'local-exammanager-bulk-group-title']);
    $html .= html_writer::start_div('local-exammanager-bulk-pair');
    $html .= $renderbulklabel(get_string('logiclabel', 'local_exammanager'), $renderbulkselect($idx, 'set_operator', $source, [
        '&' => get_string('andlabel', 'local_exammanager'),
        '|' => get_string('orlabel', 'local_exammanager'),
    ]));
    $html .= $renderbulklabel(get_string('restrictionkind_date', 'local_exammanager'), $renderbulkselect($idx, 'set_date', $source, [
        '' => get_string('nolabel', 'local_exammanager'),
        '1' => get_string('yeslabel', 'local_exammanager'),
    ], ['class' => 'form-select custom-select exammanager-bulk-set-toggle', 'data-bulk-set-toggle' => 'date']));
    $html .= $renderbulklabel(get_string('restrictionkind_grade', 'local_exammanager'), $renderbulkselect($idx, 'set_grade', $source, [
        '' => get_string('nolabel', 'local_exammanager'),
        '1' => get_string('yeslabel', 'local_exammanager'),
    ], ['class' => 'form-select custom-select exammanager-bulk-set-toggle', 'data-bulk-set-toggle' => 'grade']));
    $html .= $renderbulklabel(get_string('restrictionkind_profile', 'local_exammanager'), $renderbulkselect($idx, 'set_profile', $source, [
        '' => get_string('nolabel', 'local_exammanager'),
        '1' => get_string('yeslabel', 'local_exammanager'),
    ], ['class' => 'form-select custom-select exammanager-bulk-set-toggle', 'data-bulk-set-toggle' => 'profile']));
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('local-exammanager-bulk-group local-exammanager-bulk-remove', $groupattrs('remove'));
    $html .= html_writer::tag('strong', get_string('removealltargetrestrictions', 'local_exammanager'));
    $html .= html_writer::end_div();

    $html .= html_writer::end_div();
    return $html;
};

$renderdatefields = function(string $prefix = '') use ($renderselect): string {
    $directionoptions = [
        'from' => get_string('direction_from_long', 'local_exammanager'),
        'until' => get_string('direction_until_long', 'local_exammanager'),
    ];

    $html = html_writer::start_div('local-exammanager-formrow');
    $html .= html_writer::start_div();
    $html .= html_writer::tag('label', get_string('daterestrictionlabel', 'local_exammanager'));
    $html .= $renderselect($prefix . 'date_direction', $directionoptions, 'from');
    $html .= html_writer::end_div();
    $html .= html_writer::start_div();
    $html .= html_writer::tag('label', get_string('datetimelabel', 'local_exammanager'));
    $html .= html_writer::empty_tag('input', [
        'type' => 'datetime-local',
        'name' => $prefix . 'date_time',
        'class' => 'form-control',
    ]);
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    return $html;
};

$rendergradefields = function(string $prefix, array $gradeoptions) use ($renderselect): string {
    $html = html_writer::start_div('local-exammanager-formrow');
    $html .= html_writer::start_div();
    $html .= html_writer::tag('label', get_string('restrictionkind_grade', 'local_exammanager'));
    $html .= $renderselect($prefix . 'grade_itemid', $gradeoptions, '');
    $html .= html_writer::end_div();
    $html .= html_writer::start_div();
    $html .= html_writer::tag('label', get_string('minimumpercent_long', 'local_exammanager'));
    $html .= html_writer::empty_tag('input', [
        'type' => 'number',
        'name' => $prefix . 'grade_min',
        'class' => 'form-control',
        'min' => '0',
        'max' => '100',
        'step' => '0.01',
        'placeholder' => get_string('gradeexampleplaceholder', 'local_exammanager'),
    ]);
    $html .= html_writer::end_div();
    $html .= html_writer::start_div();
    $html .= html_writer::tag('label', get_string('maximumpercent_long', 'local_exammanager'));
    $html .= html_writer::empty_tag('input', [
        'type' => 'number',
        'name' => $prefix . 'grade_max',
        'class' => 'form-control',
        'min' => '0',
        'max' => '100',
        'step' => '0.01',
        'placeholder' => get_string('optionalplaceholder', 'local_exammanager'),
    ]);
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    return $html;
};

$renderprofilefields = function(string $prefix, array $profileoptions) use ($renderselect): string {
    $operatoroptions = [
        'isequalto' => get_string('profileop_isequalto', 'local_exammanager'),
        'contains' => get_string('profileop_contains', 'local_exammanager'),
        'doesnotcontain' => get_string('profileop_doesnotcontain', 'local_exammanager'),
        'startswith' => get_string('profileop_startswith', 'local_exammanager'),
        'endswith' => get_string('profileop_endswith', 'local_exammanager'),
        'isempty' => get_string('profileop_isempty', 'local_exammanager'),
        'isnotempty' => get_string('profileop_isnotempty', 'local_exammanager'),
    ];

    $html = html_writer::start_div('local-exammanager-formrow');
    $html .= html_writer::start_div();
    $html .= html_writer::tag('label', get_string('fieldlabel', 'local_exammanager'));
    $html .= $renderselect($prefix . 'profile_field', $profileoptions, '');
    $html .= html_writer::end_div();
    $html .= html_writer::start_div();
    $html .= html_writer::tag('label', get_string('conditionlabel', 'local_exammanager'));
    $html .= $renderselect($prefix . 'profile_operator', $operatoroptions, 'isequalto', [
        'class' => 'form-select custom-select exammanager-profile-operator',
    ]);
    $html .= html_writer::end_div();
    $html .= html_writer::start_div();
    $html .= html_writer::tag('label', get_string('valuelabel', 'local_exammanager'));
    $html .= html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => $prefix . 'profile_value',
        'class' => 'form-control exammanager-profile-value',
    ]);
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    return $html;
};

echo $OUTPUT->header();
echo html_writer::start_div('local-exammanager-app');
echo \local_exammanager\output\navbar::render('activities');

echo $OUTPUT->render_from_template('local_exammanager/hero', [
    'title' => get_string('activitiesplanning', 'local_exammanager'),
    'subtitle' => get_string('activities_hero_subtitle', 'local_exammanager'),
]);

if ($applymessage !== '') {
    echo $OUTPUT->notification($applymessage, $applymessagetype);
}

echo '<div class="local-exammanager-panel">';
echo '<form method="get" class="local-exammanager-inline-form">';
echo html_writer::tag('label', get_string('courseshortnamelabel', 'local_exammanager'), ['for' => 'exammanager-shortname']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'exammanager-shortname',
    'name' => 'shortname',
    'value' => $shortname,
    'class' => 'form-control',
    'required' => 'required',
]);
echo '<button class="btn btn-primary" type="submit">' . get_string('previewactivities', 'local_exammanager') . '</button>';
echo '</form>';
echo '</div>';

echo '<div class="local-exammanager-panel">';
echo '<h3 class="local-exammanager-sectiontitle">' . get_string('bulkrestrictions_title', 'local_exammanager') . '</h3>';
echo '<div class="local-exammanager-activity-toolbar">';
echo '<a href="' . new moodle_url('/local/exammanager/download_activity_template.php', ['sesskey' => sesskey()]) . '" class="btn btn-secondary">' . get_string('downloadexceltemplate', 'local_exammanager') . '</a>';
echo '</div>';
echo '<form method="post" enctype="multipart/form-data" class="local-exammanager-bulk-upload">';
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'bulkpreview']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'shortname', 'value' => $shortname]);
echo '<div class="local-exammanager-dropzone">';
echo '<div><strong>' . get_string('dropzonelabel', 'local_exammanager') . '</strong></div>';
echo '<input type="file" name="activityfile" accept=".csv,.xlsx,.xls" required>';
echo '</div>';
echo '<br>';
echo '<button class="btn btn-primary" type="submit">' . get_string('previewfile', 'local_exammanager') . '</button>';
echo '</form>';
echo '</div>';

if (!empty($bulkrows)) {
    $readycount = 0;
    foreach ($bulkrows as $bulkrow) {
        if (($bulkrow['status'] ?? '') === 'READY') {
            $readycount++;
        }
    }

    echo '<div class="local-exammanager-panel">';
    echo '<h3 class="local-exammanager-sectiontitle">' . get_string('filepreview_title', 'local_exammanager') . '</h3>';
    echo '<div class="local-exammanager-activity-summary">';
    echo '<span>' . get_string('linescount', 'local_exammanager', count($bulkrows)) . '</span>';
    echo '<span>' . get_string('readycountlabel', 'local_exammanager', $readycount) . '</span>';
    echo '</div>';

    echo '<form method="post">';
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'shortname', 'value' => $shortname]);
    echo '<div class="local-exammanager-preview-wrap">';
    echo '<table class="generaltable local-exammanager-bulk-table">';
    echo '<thead><tr>';
    $bulktableheadings = [
        get_string('bulkcol_line', 'local_exammanager'),
        get_string('course', 'local_exammanager'),
        get_string('bulkcol_target', 'local_exammanager'),
        get_string('bulkcol_sectiontile', 'local_exammanager'),
        get_string('activitylabel', 'local_exammanager'),
        get_string('bulkcol_restriction', 'local_exammanager'),
        get_string('bulkcol_resolved', 'local_exammanager'),
        get_string('status', 'local_exammanager'),
        get_string('message', 'local_exammanager'),
    ];
    foreach ($bulktableheadings as $heading) {
        echo html_writer::tag('th', s($heading));
    }
    echo '</tr></thead><tbody>';

    foreach ($bulkrows as $idx => $bulkrow) {
        $source = isset($bulkrow['source_row']) && is_array($bulkrow['source_row']) ? $bulkrow['source_row'] : [];
        $status = (string)($bulkrow['status'] ?? '');
        $statusclass = in_array($status, ['READY', 'APPLIQUÉ'], true) ? 'ok' : 'err';

        echo '<tr class="exammanager-bulk-target-row">';
        echo html_writer::tag('td', s((string)($bulkrow['rownum'] ?? '')));
        echo html_writer::tag('td', $renderbulkinput($idx, 'course_shortname', $source, [
            'class' => 'form-control exammanager-bulk-course',
            'style' => 'min-width:130px;',
        ]));
        echo html_writer::tag('td', $renderbulkselect($idx, 'target_type', $source, [
            'sections' => get_string('targettype_sections', 'local_exammanager'),
            'activities' => get_string('targettype_activities', 'local_exammanager'),
        ], ['class' => 'form-select custom-select exammanager-bulk-target-type']));
        echo html_writer::tag('td', $renderbulksectionselect($idx, $source));
        echo html_writer::tag('td', $renderbulkactivityselect($idx, $source));
        echo html_writer::tag('td', $renderbulkrestriction($idx, $source), ['class' => 'local-exammanager-bulk-restriction-cell']);
        echo html_writer::tag('td', s((string)($bulkrow['target_label'] ?? '')) . '<br>' . s((string)($bulkrow['restriction_label'] ?? '')));
        echo html_writer::tag('td', html_writer::span($status, 'local-exammanager-badge ' . $statusclass));
        echo html_writer::tag('td', s((string)($bulkrow['message'] ?? '')));
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';

    echo '<div style="margin-top:12px; display:flex; gap:12px; flex-wrap:wrap;">';
    echo '<button class="btn btn-outline-secondary" type="submit" name="action" value="bulkrefresh">' . get_string('refreshpreview', 'local_exammanager') . '</button>';
    echo '<button class="btn btn-success" type="submit" name="action" value="bulkapply" ' . ($readycount > 0 ? '' : 'disabled') . '>' . get_string('applyallreadyrows', 'local_exammanager') . '</button>';
    echo '</div>';
    echo '</form>';


    echo '</div>';
}

if ($shortname !== '' && !$course) {
    echo $OUTPUT->notification(get_string('shortnamenotfound', 'local_exammanager'), 'notifyproblem');
}

if ($course) {
    $sections = \local_exammanager\activity_planner::get_course_sections($course);
    $activities = \local_exammanager\activity_planner::get_course_activities($course);
    $gradeitems = \local_exammanager\activity_planner::get_grade_items((int)$course->id);
    $profilefields = \local_exammanager\activity_planner::get_profile_fields();

    $gradeoptions = ['' => get_string('choosegradeitem', 'local_exammanager')];
    foreach ($gradeitems as $item) {
        $gradeoptions[(string)$item['id']] = $item['label'];
    }

    $profileoptions = ['' => get_string('choosefield', 'local_exammanager')];
    foreach ($profilefields as $field) {
        $profileoptions[$field['value']] = $field['label'];
    }

    echo '<form method="post" id="exammanager-activities-form">';
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'apply']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'shortname', 'value' => $course->shortname]);

    echo '<div class="local-exammanager-panel">';
    echo '<h3 class="local-exammanager-sectiontitle">' . get_string('results', 'local_exammanager') . '</h3>';
    echo '<div class="local-exammanager-activity-summary">';
    echo '<strong>' . format_string($course->fullname) . '</strong>';
    echo '<span>' . s($course->shortname) . '</span>';
    echo '<span>' . get_string('sectionscount', 'local_exammanager', count($sections)) . '</span>';
    echo '<span>' . get_string('activitiescount', 'local_exammanager', count($activities)) . '</span>';
    echo '</div>';

    echo '<div class="local-exammanager-target-switch">';
    echo '<div>';
    echo html_writer::tag('label', get_string('elementtorestrict', 'local_exammanager'));
    echo $renderselect('targettype', [
        'sections' => get_string('targettype_sections', 'local_exammanager'),
        'activities' => get_string('targettype_activities', 'local_exammanager'),
    ], 'sections', ['id' => 'exammanager-target-type']);
    echo '</div>';
    echo '<div class="local-exammanager-activity-toolbar">';
    echo '<button type="button" class="btn btn-outline-secondary" id="exammanager-select-all">' . get_string('selectalllabel', 'local_exammanager') . '</button>';
    echo '<button type="button" class="btn btn-outline-secondary" id="exammanager-select-none">' . get_string('selectnonelabel', 'local_exammanager') . '</button>';
    echo '</div>';
    echo '</div>';

    echo '<div data-target-panel="sections">';
    if (empty($sections)) {
        echo $OUTPUT->notification(get_string('nosectionfoundincourse', 'local_exammanager'), 'notifyproblem');
    } else {
        echo '<div class="local-exammanager-preview-wrap">';
        echo '<table class="generaltable local-exammanager-activity-table">';
        echo '<thead><tr>';
        $sectiontableheadings = [
            '',
            get_string('sectioncol_tile', 'local_exammanager'),
            get_string('targettype_activities', 'local_exammanager'),
            get_string('visibilitylabel', 'local_exammanager'),
            get_string('restrictionslabel', 'local_exammanager'),
        ];
        foreach ($sectiontableheadings as $heading) {
            echo html_writer::tag('th', s($heading));
        }
        echo '</tr></thead><tbody>';

        foreach ($sections as $section) {
            $visiblebadge = $section['visible']
                ? html_writer::span(get_string('visiblelabel', 'local_exammanager'), 'local-exammanager-badge ok')
                : html_writer::span(get_string('hiddenlabel', 'local_exammanager'), 'local-exammanager-badge warn');
            $restrictionbadge = $section['availability'] !== ''
                ? html_writer::span(get_string('restrictionspresent', 'local_exammanager'), 'local-exammanager-badge info')
                : html_writer::span(get_string('restrictionsnone', 'local_exammanager'), 'local-exammanager-badge neutral');

            echo '<tr class="exammanager-section-target-row">';
            echo html_writer::tag('td', html_writer::empty_tag('input', [
                'type' => 'checkbox',
                'name' => 'sectionids[]',
                'value' => (int)$section['id'],
                'class' => 'exammanager-section-target',
            ]));
            echo html_writer::tag('td', s($section['label']));
            echo html_writer::tag('td', s((string)$section['activitycount']));
            echo html_writer::tag('td', $visiblebadge);
            echo html_writer::tag('td', $restrictionbadge);
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }
    echo '</div>';

    echo '<div data-target-panel="activities">';
    if (empty($activities)) {
        echo $OUTPUT->notification(get_string('noactivityfoundincourse', 'local_exammanager'), 'notifyproblem');
    } else {

        echo '<div class="local-exammanager-preview-wrap">';
        echo '<table class="generaltable local-exammanager-activity-table">';
        echo '<thead><tr>';
        $activitytableheadings = [
            '',
            get_string('sectioncol_tile', 'local_exammanager'),
            get_string('activitylabel', 'local_exammanager'),
            get_string('moduletypelabel', 'local_exammanager'),
            get_string('visibilitylabel', 'local_exammanager'),
            get_string('restrictionslabel', 'local_exammanager'),
        ];
        foreach ($activitytableheadings as $heading) {
            echo html_writer::tag('th', s($heading));
        }
        echo '</tr></thead><tbody>';

        $currentsection = null;
        foreach ($activities as $activity) {
            $sectionkey = 'section-' . (int)$activity['sectionnum'];
            $sectionlabel = (string)$activity['sectionlabel'];

            if ($currentsection !== $sectionkey) {
                $currentsection = $sectionkey;
                echo '<tr class="local-exammanager-section-row">';
                echo html_writer::tag('td', '');
                echo html_writer::tag('td', s($sectionlabel), ['colspan' => 5]);
                echo '</tr>';
            }

            $visiblebadge = $activity['visible']
                ? html_writer::span(get_string('visiblelabel', 'local_exammanager'), 'local-exammanager-badge ok')
                : html_writer::span(get_string('hiddenlabel', 'local_exammanager'), 'local-exammanager-badge warn');
            $restrictionbadge = $activity['availability'] !== ''
                ? html_writer::span(get_string('restrictionspresent', 'local_exammanager'), 'local-exammanager-badge info')
                : html_writer::span(get_string('restrictionsnone', 'local_exammanager'), 'local-exammanager-badge neutral');
            $activityname = $activity['url'] !== ''
                ? html_writer::link(new moodle_url($activity['url']), $activity['name'])
                : s($activity['name']);

            echo '<tr class="exammanager-activity-row" data-section-key="' . s($sectionkey) . '">';
            echo html_writer::tag('td', html_writer::empty_tag('input', [
                'type' => 'checkbox',
                'name' => 'cmids[]',
                'value' => (int)$activity['id'],
                'class' => 'exammanager-activity-target',
            ]));
            echo html_writer::tag('td', s($sectionlabel));
            echo html_writer::tag('td', $activityname);
            echo html_writer::tag('td', s($activity['modname']));
            echo html_writer::tag('td', $visiblebadge);
            echo html_writer::tag('td', $restrictionbadge);
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';

    echo '<div class="local-exammanager-panel">';
    echo '<h3 class="local-exammanager-sectiontitle">' . get_string('restrictiontoapply_title', 'local_exammanager') . '</h3>';
    echo '<div class="local-exammanager-restriction-simple">';
    echo '<div>';
    echo html_writer::tag('label', get_string('restrictiontype_label', 'local_exammanager'));
    echo $renderselect('restrictionkind', [
        'date' => get_string('restrictionkind_date', 'local_exammanager'),
        'grade' => get_string('restrictionkind_grade', 'local_exammanager'),
        'profile' => get_string('restrictionkind_profile', 'local_exammanager'),
        'set' => get_string('restrictionkind_set', 'local_exammanager'),
        'remove' => get_string('restrictionkind_remove', 'local_exammanager'),
    ], 'date', ['id' => 'exammanager-restriction-kind']);
    echo '</div>';
    echo '<label class="local-exammanager-checkline local-exammanager-showline">';
    echo html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'showrestriction', 'value' => '1', 'checked' => 'checked']);
    echo '<span>' . get_string('showgreyedoutlabel', 'local_exammanager') . '</span>';
    echo '</label>';
    echo '</div>';

    echo '<div class="local-exammanager-restriction-fields" data-restriction-fields="date">';
    echo $renderdatefields('');
    echo '</div>';

    echo '<div class="local-exammanager-restriction-fields" data-restriction-fields="grade">';
    if (count($gradeoptions) <= 1) {
        echo $OUTPUT->notification(get_string('nogradeavailableforcourse', 'local_exammanager'), 'notifyproblem');
    }
    echo $rendergradefields('', $gradeoptions);
    echo '</div>';

    echo '<div class="local-exammanager-restriction-fields" data-restriction-fields="profile">';
    echo $renderprofilefields('', $profileoptions);
    echo '</div>';

    echo '<div class="local-exammanager-restriction-fields" data-restriction-fields="set">';
    echo '<div class="local-exammanager-formrow">';
    echo '<div>';
    echo html_writer::tag('label', get_string('setlogiclabel', 'local_exammanager'));
    echo $renderselect('set_operator', [
        '&' => get_string('alllogic_and', 'local_exammanager'),
        '|' => get_string('anylogic_or', 'local_exammanager'),
    ], '&');
    echo '</div>';
    echo '</div>';

    foreach ([
        'date' => [get_string('restrictionkind_date', 'local_exammanager'), $renderdatefields('set_')],
        'grade' => [get_string('restrictionkind_grade', 'local_exammanager'), $rendergradefields('set_', $gradeoptions)],
        'profile' => [get_string('restrictionkind_profile', 'local_exammanager'), $renderprofilefields('set_', $profileoptions)],
    ] as $setkey => $setdata) {
        echo '<label class="local-exammanager-checkline exammanager-set-toggle">';
        echo html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'set_' . $setkey, 'value' => '1']);
        echo '<span>' . s($setdata[0]) . '</span>';
        echo '</label>';
        echo '<div class="local-exammanager-set-fields" data-set-fields="' . s($setkey) . '">';
        echo $setdata[1];
        echo '</div>';
    }
    echo '</div>';

    echo '<div style="margin-top:12px; display:flex; gap:12px; flex-wrap:wrap;">';
    echo '<button class="btn btn-success" type="submit" id="exammanager-apply-activities-btn">' . get_string('activityapply_applysections', 'local_exammanager') . '</button>';
    echo '</div>';
    echo '</div>';

    echo '</form>';

}

echo html_writer::end_div();
echo $OUTPUT->footer();
