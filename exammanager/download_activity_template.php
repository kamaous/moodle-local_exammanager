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
 * Serves the bulk activity planning Excel template.
 *
 * @package    local_exammanager
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require('../../config.php');
require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/exammanager:manage', $context);

$headers = [
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

$example = [
    [
        'INFO101',
        'sections',
        '3',
        '',
        'date',
        'from',
        '2026-08-31 08:00',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '1',
    ],
    [
        'INFO101',
        'activities',
        '3',
        'Quiz séquence 1',
        'grade',
        '',
        '',
        'Quiz diagnostic',
        '50',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '1',
    ],
    [
        'INFO101',
        'sections',
        'Fonctions et formules',
        '',
        'set',
        'from',
        '2026-09-15 08:00',
        'Quiz diagnostic',
        '50',
        '',
        'sf_department',
        'contains',
        'Excel',
        '&',
        '1',
        '1',
        '1',
        '1',
    ],
    [
        'INFO101',
        'activities',
        '3',
        'Quiz séquence 1',
        'remove',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '1',
    ],
];

$base = make_temp_directory('local_exammanager/template');
foreach (glob($base . '/*') as $tmpfile) {
    if (is_file($tmpfile) && filemtime($tmpfile) < (time() - 3600)) {
        @unlink($tmpfile);
    }
}

$file = $base . '/modele_restrictions_activites.xlsx';
\local_exammanager\xlsx_writer::write($headers, $example, $file, 'Restrictions');

header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="modele_restrictions_activites.xlsx"');
header('Content-Length: ' . filesize($file));

readfile($file);
@unlink($file);
exit;
