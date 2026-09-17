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
        $applymessage = 'Shortname du cours introuvable.';
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
            $applymessage = 'Sélectionnez au moins une section ou une tuile.';
            $applymessagetype = 'notifyproblem';
        } else if ($targettype === 'activities' && empty($cmids)) {
            $applymessage = 'Sélectionnez au moins une activité.';
            $applymessagetype = 'notifyproblem';
        } else if ($error !== '') {
            $applymessage = $error;
            $applymessagetype = 'notifyproblem';
        } else {
            try {
                if ($targettype === 'sections') {
                    $result = \local_exammanager\activity_planner::apply_section_restriction($course, $sectionids, $restriction, $showrestriction);
                    $applymessage = $removerestriction
                        ? (int)$result['updated'] . ' section(s) / tuile(s) nettoyée(s).'
                        : (int)$result['updated'] . ' section(s) / tuile(s) mise(s) à jour.';
                } else {
                    $result = \local_exammanager\activity_planner::apply_restriction($course, $cmids, $restriction, $showrestriction);
                    $applymessage = $removerestriction
                        ? (int)$result['updated'] . ' activité(s) nettoyée(s).'
                        : (int)$result['updated'] . ' activité(s) mise(s) à jour.';
                }

                if ((int)$result['updated'] === 0) {
                    $applymessagetype = 'notifyproblem';
                    $applymessage = 'Aucun élément du cours n’a été mis à jour.';
                }
            } catch (Throwable $e) {
                debugging('ExamManager activity planning error: ' . $e->getMessage(), DEBUG_DEVELOPER);
                $applymessage = 'Erreur pendant l’application des restrictions. Les détails techniques ont été journalisés côté serveur.';
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
        $applymessage = 'Aucun fichier uploadé.';
        $applymessagetype = 'notifyproblem';
    } else {
        try {
            $tmp = $_FILES['activityfile']['tmp_name'];
            $name = $_FILES['activityfile']['name'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (!$ext) {
                throw new moodle_exception('Impossible de détecter le type du fichier.');
            }

            $newfile = $tmp . '.' . $ext;
            if (!copy($tmp, $newfile)) {
                throw new moodle_exception('Erreur lors de la copie du fichier.');
            }

            $importrows = \local_exammanager\reader::read_rows($newfile);
            $bulkrows = \local_exammanager\activity_planner::preview_import_rows($importrows, $shortname);
            $SESSION->$sessionkey = $bulkrows;
            $applymessage = 'Prévisualisation du fichier terminée.';
            $applymessagetype = 'notifysuccess';
        } catch (Throwable $e) {
            debugging('ExamManager activity import preview error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $applymessage = 'Erreur pendant la prévisualisation du fichier. Vérifiez le modèle importé.';
            $applymessagetype = 'notifyproblem';
        }
    }
} else if ($action === 'bulkrefresh' && confirm_sesskey()) {
    if (empty($SESSION->$sessionkey) || !is_array($SESSION->$sessionkey)) {
        $applymessage = 'Aucune prévisualisation à actualiser.';
        $applymessagetype = 'notifyproblem';
    } else {
        $postedrows = $_POST['rowedit'] ?? [];
        if (!is_array($postedrows)) {
            $postedrows = [];
        }

        $bulkrows = \local_exammanager\activity_planner::rebuild_import_preview($SESSION->$sessionkey, $postedrows, $shortname);
        $SESSION->$sessionkey = $bulkrows;
        $applymessage = 'Prévisualisation du fichier actualisée.';
        $applymessagetype = 'notifysuccess';
    }
} else if ($action === 'bulkapply' && confirm_sesskey()) {
    if (empty($SESSION->$sessionkey) || !is_array($SESSION->$sessionkey)) {
        $applymessage = 'Aucune prévisualisation à appliquer.';
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
        $applymessage = $applied . ' opération(s) appliquée(s).';
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
        $optionhtml = html_writer::tag('option', s('Valeur importée : ' . $selected), [
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
    $options = array_merge([['value' => '', 'label' => 'Choisir une section / tuile']], $targets['sections']);
    return $renderbulkmanualselect($idx, 'section', $source, $options, [
        'class' => 'form-select custom-select exammanager-bulk-section',
        'data-current' => (string)($source['section'] ?? ''),
    ], $resolvebulksectionvalue($source, $targets['sections']));
};

$renderbulkactivityselect = function(int $idx, array $source) use ($getbulktargets, $renderbulkmanualselect): string {
    $targets = $getbulktargets((string)($source['course_shortname'] ?? ''));
    $options = [['value' => '', 'label' => 'Choisir une activité']];
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
    $html .= $renderbulklabel('Restriction', $renderbulkselect($idx, 'restriction_type', $source, [
        'date' => 'Date',
        'grade' => 'Note',
        'profile' => 'Profil utilisateur',
        'set' => 'Jeu de restrictions',
        'remove' => 'Enlever les restrictions',
    ], ['class' => 'form-select custom-select exammanager-bulk-restriction-kind']));
    $showattrs = ['data-bulk-show' => '1'];
    if ($restrictiontype === 'remove') {
        $showattrs['style'] = 'display:none;';
    }
    $html .= html_writer::start_div('', $showattrs);
    $html .= $renderbulklabel('Affichage', $renderbulkselect($idx, 'show', $source, ['1' => 'Oui', '0' => 'Non']));
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('local-exammanager-bulk-group', $groupattrs('date'));
    $html .= html_writer::tag('div', 'Date', ['class' => 'local-exammanager-bulk-group-title']);
    $html .= html_writer::start_div('local-exammanager-bulk-pair');
    $html .= $renderbulklabel('Sens', $renderbulkselect($idx, 'date_direction', $source, [
        'from' => 'À partir',
        'until' => 'Jusqu’à',
    ]));
    $html .= $renderbulklabel('Date et heure', $renderbulkinput($idx, 'date_time', $source, ['type' => 'datetime-local']));
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('local-exammanager-bulk-group', $groupattrs('grade'));
    $html .= html_writer::tag('div', 'Note', ['class' => 'local-exammanager-bulk-group-title']);
    $html .= $renderbulklabel('Note de référence', $renderbulkinput($idx, 'grade_item', $source, ['placeholder' => 'Nom ou id de la note']));
    $html .= html_writer::start_div('local-exammanager-bulk-pair');
    $html .= $renderbulklabel('Minimum', $renderbulkinput($idx, 'grade_min', $source, ['placeholder' => 'Min %']));
    $html .= $renderbulklabel('Maximum', $renderbulkinput($idx, 'grade_max', $source, ['placeholder' => 'Max %']));
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('local-exammanager-bulk-group', $groupattrs('profile'));
    $html .= html_writer::tag('div', 'Profil utilisateur', ['class' => 'local-exammanager-bulk-group-title']);
    $html .= $renderbulklabel('Champ', $renderbulkinput($idx, 'profile_field', $source, ['placeholder' => 'Ex. sf_department']));
    $html .= html_writer::start_div('local-exammanager-bulk-pair');
    $html .= $renderbulklabel('Condition', $renderbulkselect($idx, 'profile_operator', $source, [
        'isequalto' => 'est égal à',
        'contains' => 'contient',
        'doesnotcontain' => 'ne contient pas',
        'startswith' => 'commence par',
        'endswith' => 'se termine par',
        'isempty' => 'est vide',
        'isnotempty' => 'n’est pas vide',
    ]));
    $html .= $renderbulklabel('Valeur', $renderbulkinput($idx, 'profile_value', $source, ['placeholder' => 'Valeur']));
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('local-exammanager-bulk-group', $groupattrs('set'));
    $html .= html_writer::tag('div', 'Jeu de restrictions', ['class' => 'local-exammanager-bulk-group-title']);
    $html .= html_writer::start_div('local-exammanager-bulk-pair');
    $html .= $renderbulklabel('Logique', $renderbulkselect($idx, 'set_operator', $source, ['&' => 'ET', '|' => 'OU']));
    $html .= $renderbulklabel('Date', $renderbulkselect($idx, 'set_date', $source, ['' => 'Non', '1' => 'Oui'], ['class' => 'form-select custom-select exammanager-bulk-set-toggle', 'data-bulk-set-toggle' => 'date']));
    $html .= $renderbulklabel('Note', $renderbulkselect($idx, 'set_grade', $source, ['' => 'Non', '1' => 'Oui'], ['class' => 'form-select custom-select exammanager-bulk-set-toggle', 'data-bulk-set-toggle' => 'grade']));
    $html .= $renderbulklabel('Profil', $renderbulkselect($idx, 'set_profile', $source, ['' => 'Non', '1' => 'Oui'], ['class' => 'form-select custom-select exammanager-bulk-set-toggle', 'data-bulk-set-toggle' => 'profile']));
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();

    $html .= html_writer::start_div('local-exammanager-bulk-group local-exammanager-bulk-remove', $groupattrs('remove'));
    $html .= html_writer::tag('strong', 'Enlever toutes les restrictions de cette cible.');
    $html .= html_writer::end_div();

    $html .= html_writer::end_div();
    return $html;
};

$renderdatefields = function(string $prefix = '') use ($renderselect): string {
    $directionoptions = [
        'from' => 'À partir d’une date',
        'until' => 'Jusqu’à une date',
    ];

    $html = html_writer::start_div('local-exammanager-formrow');
    $html .= html_writer::start_div();
    $html .= html_writer::tag('label', 'Restriction de date');
    $html .= $renderselect($prefix . 'date_direction', $directionoptions, 'from');
    $html .= html_writer::end_div();
    $html .= html_writer::start_div();
    $html .= html_writer::tag('label', 'Date et heure');
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
    $html .= html_writer::tag('label', 'Note');
    $html .= $renderselect($prefix . 'grade_itemid', $gradeoptions, '');
    $html .= html_writer::end_div();
    $html .= html_writer::start_div();
    $html .= html_writer::tag('label', 'Minimum (%)');
    $html .= html_writer::empty_tag('input', [
        'type' => 'number',
        'name' => $prefix . 'grade_min',
        'class' => 'form-control',
        'min' => '0',
        'max' => '100',
        'step' => '0.01',
        'placeholder' => 'Ex. 50',
    ]);
    $html .= html_writer::end_div();
    $html .= html_writer::start_div();
    $html .= html_writer::tag('label', 'Maximum (%)');
    $html .= html_writer::empty_tag('input', [
        'type' => 'number',
        'name' => $prefix . 'grade_max',
        'class' => 'form-control',
        'min' => '0',
        'max' => '100',
        'step' => '0.01',
        'placeholder' => 'Optionnel',
    ]);
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    return $html;
};

$renderprofilefields = function(string $prefix, array $profileoptions) use ($renderselect): string {
    $operatoroptions = [
        'isequalto' => 'est égal à',
        'contains' => 'contient',
        'doesnotcontain' => 'ne contient pas',
        'startswith' => 'commence par',
        'endswith' => 'se termine par',
        'isempty' => 'est vide',
        'isnotempty' => 'n’est pas vide',
    ];

    $html = html_writer::start_div('local-exammanager-formrow');
    $html .= html_writer::start_div();
    $html .= html_writer::tag('label', 'Champ');
    $html .= $renderselect($prefix . 'profile_field', $profileoptions, '');
    $html .= html_writer::end_div();
    $html .= html_writer::start_div();
    $html .= html_writer::tag('label', 'Condition');
    $html .= $renderselect($prefix . 'profile_operator', $operatoroptions, 'isequalto', [
        'class' => 'form-select custom-select exammanager-profile-operator',
    ]);
    $html .= html_writer::end_div();
    $html .= html_writer::start_div();
    $html .= html_writer::tag('label', 'Valeur');
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

echo '<div class="local-exammanager-hero">';
echo '<h2>Planifier des activités</h2>';
echo '<div class="local-exammanager-muted">Restrictions d’accès par shortname de cours</div>';
echo '</div>';

if ($applymessage !== '') {
    echo $OUTPUT->notification($applymessage, $applymessagetype);
}

echo '<div class="local-exammanager-panel">';
echo '<form method="get" class="local-exammanager-inline-form">';
echo html_writer::tag('label', 'Shortname du cours', ['for' => 'exammanager-shortname']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'exammanager-shortname',
    'name' => 'shortname',
    'value' => $shortname,
    'class' => 'form-control',
    'required' => 'required',
]);
echo '<button class="btn btn-primary" type="submit">Prévisualiser les activités</button>';
echo '</form>';
echo '</div>';

echo '<div class="local-exammanager-panel">';
echo '<h3 class="local-exammanager-sectiontitle">Restrictions en masse</h3>';
echo '<div class="local-exammanager-activity-toolbar">';
echo '<a href="' . new moodle_url('/local/exammanager/download_activity_template.php', ['sesskey' => sesskey()]) . '" class="btn btn-secondary">Télécharger modèle Excel</a>';
echo '</div>';
echo '<form method="post" enctype="multipart/form-data" class="local-exammanager-bulk-upload">';
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'bulkpreview']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'shortname', 'value' => $shortname]);
echo '<div class="local-exammanager-dropzone">';
echo '<div><strong>Glissez-déposez ou cliquez</strong></div>';
echo '<input type="file" name="activityfile" accept=".csv,.xlsx,.xls" required>';
echo '</div>';
echo '<br>';
echo '<button class="btn btn-primary" type="submit">Prévisualiser le fichier</button>';
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
    echo '<h3 class="local-exammanager-sectiontitle">Prévisualisation du fichier</h3>';
    echo '<div class="local-exammanager-activity-summary">';
    echo '<span>' . count($bulkrows) . ' ligne(s)</span>';
    echo '<span>' . $readycount . ' prête(s)</span>';
    echo '</div>';

    echo '<form method="post">';
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'shortname', 'value' => $shortname]);
    echo '<div class="local-exammanager-preview-wrap">';
    echo '<table class="generaltable local-exammanager-bulk-table">';
    echo '<thead><tr>';
    foreach (['Ligne', 'Cours', 'Cible', 'Section / tuile', 'Activité', 'Restriction', 'Résolu', 'Statut', 'Message'] as $heading) {
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
            'sections' => 'Sections / tuiles',
            'activities' => 'Activités',
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
    echo '<button class="btn btn-outline-secondary" type="submit" name="action" value="bulkrefresh">Actualiser la prévisualisation</button>';
    echo '<button class="btn btn-success" type="submit" name="action" value="bulkapply" ' . ($readycount > 0 ? '' : 'disabled') . '>Appliquer toutes les lignes prêtes</button>';
    echo '</div>';
    echo '</form>';


    echo '</div>';
}

if ($shortname !== '' && !$course) {
    echo $OUTPUT->notification('Shortname du cours introuvable.', 'notifyproblem');
}

if ($course) {
    $sections = \local_exammanager\activity_planner::get_course_sections($course);
    $activities = \local_exammanager\activity_planner::get_course_activities($course);
    $gradeitems = \local_exammanager\activity_planner::get_grade_items((int)$course->id);
    $profilefields = \local_exammanager\activity_planner::get_profile_fields();

    $gradeoptions = ['' => 'Choisir une note'];
    foreach ($gradeitems as $item) {
        $gradeoptions[(string)$item['id']] = $item['label'];
    }

    $profileoptions = ['' => 'Choisir un champ'];
    foreach ($profilefields as $field) {
        $profileoptions[$field['value']] = $field['label'];
    }

    echo '<form method="post" id="exammanager-activities-form">';
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'apply']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'shortname', 'value' => $course->shortname]);

    echo '<div class="local-exammanager-panel">';
    echo '<h3 class="local-exammanager-sectiontitle">Résultats</h3>';
    echo '<div class="local-exammanager-activity-summary">';
    echo '<strong>' . format_string($course->fullname) . '</strong>';
    echo '<span>' . s($course->shortname) . '</span>';
    echo '<span>' . count($sections) . ' section(s) / tuile(s)</span>';
    echo '<span>' . count($activities) . ' activité(s)</span>';
    echo '</div>';

    echo '<div class="local-exammanager-target-switch">';
    echo '<div>';
    echo html_writer::tag('label', 'Élément à restreindre');
    echo $renderselect('targettype', [
        'sections' => 'Sections / tuiles',
        'activities' => 'Activités',
    ], 'sections', ['id' => 'exammanager-target-type']);
    echo '</div>';
    echo '<div class="local-exammanager-activity-toolbar">';
    echo '<button type="button" class="btn btn-outline-secondary" id="exammanager-select-all">Tout sélectionner</button>';
    echo '<button type="button" class="btn btn-outline-secondary" id="exammanager-select-none">Tout désélectionner</button>';
    echo '</div>';
    echo '</div>';

    echo '<div data-target-panel="sections">';
    if (empty($sections)) {
        echo $OUTPUT->notification('Aucune section ou tuile trouvée dans ce cours.', 'notifyproblem');
    } else {
        echo '<div class="local-exammanager-preview-wrap">';
        echo '<table class="generaltable local-exammanager-activity-table">';
        echo '<thead><tr>';
        foreach (['', 'Tuile / section', 'Activités', 'Visibilité', 'Restrictions'] as $heading) {
            echo html_writer::tag('th', s($heading));
        }
        echo '</tr></thead><tbody>';

        foreach ($sections as $section) {
            $visiblebadge = $section['visible']
                ? html_writer::span('Visible', 'local-exammanager-badge ok')
                : html_writer::span('Masquée', 'local-exammanager-badge warn');
            $restrictionbadge = $section['availability'] !== ''
                ? html_writer::span('Déjà présentes', 'local-exammanager-badge info')
                : html_writer::span('Aucune', 'local-exammanager-badge neutral');

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
        echo $OUTPUT->notification('Aucune activité trouvée dans ce cours.', 'notifyproblem');
    } else {

        echo '<div class="local-exammanager-preview-wrap">';
        echo '<table class="generaltable local-exammanager-activity-table">';
        echo '<thead><tr>';
        foreach (['', 'Tuile / section', 'Activité', 'Type', 'Visibilité', 'Restrictions'] as $heading) {
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
                ? html_writer::span('Visible', 'local-exammanager-badge ok')
                : html_writer::span('Masquée', 'local-exammanager-badge warn');
            $restrictionbadge = $activity['availability'] !== ''
                ? html_writer::span('Déjà présentes', 'local-exammanager-badge info')
                : html_writer::span('Aucune', 'local-exammanager-badge neutral');
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
    echo '<h3 class="local-exammanager-sectiontitle">Restriction à appliquer</h3>';
    echo '<div class="local-exammanager-restriction-simple">';
    echo '<div>';
    echo html_writer::tag('label', 'Type de restriction');
    echo $renderselect('restrictionkind', [
        'date' => 'Date',
        'grade' => 'Note',
        'profile' => 'Profil utilisateur',
        'set' => 'Jeu de restrictions',
        'remove' => 'Enlever les restrictions',
    ], 'date', ['id' => 'exammanager-restriction-kind']);
    echo '</div>';
    echo '<label class="local-exammanager-checkline local-exammanager-showline">';
    echo html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'showrestriction', 'value' => '1', 'checked' => 'checked']);
    echo '<span>Afficher l’élément grisé quand la restriction n’est pas remplie</span>';
    echo '</label>';
    echo '</div>';

    echo '<div class="local-exammanager-restriction-fields" data-restriction-fields="date">';
    echo $renderdatefields('');
    echo '</div>';

    echo '<div class="local-exammanager-restriction-fields" data-restriction-fields="grade">';
    if (count($gradeoptions) <= 1) {
        echo $OUTPUT->notification('Aucune note disponible pour ce cours.', 'notifyproblem');
    }
    echo $rendergradefields('', $gradeoptions);
    echo '</div>';

    echo '<div class="local-exammanager-restriction-fields" data-restriction-fields="profile">';
    echo $renderprofilefields('', $profileoptions);
    echo '</div>';

    echo '<div class="local-exammanager-restriction-fields" data-restriction-fields="set">';
    echo '<div class="local-exammanager-formrow">';
    echo '<div>';
    echo html_writer::tag('label', 'Logique du jeu');
    echo $renderselect('set_operator', ['&' => 'Toutes les restrictions (ET)', '|' => 'Au moins une restriction (OU)'], '&');
    echo '</div>';
    echo '</div>';

    foreach ([
        'date' => ['Date', $renderdatefields('set_')],
        'grade' => ['Note', $rendergradefields('set_', $gradeoptions)],
        'profile' => ['Profil utilisateur', $renderprofilefields('set_', $profileoptions)],
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
    echo '<button class="btn btn-success" type="submit" id="exammanager-apply-activities-btn">Appliquer aux sections / tuiles sélectionnées</button>';
    echo '</div>';
    echo '</div>';

    echo '</form>';

}

echo html_writer::end_div();
echo $OUTPUT->footer();
