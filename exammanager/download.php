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
 * Serves the generated planning export files (CSV / Excel / log).
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

$type = required_param('type', PARAM_ALPHA);

$base = make_temp_directory('local_exammanager/' . $USER->id);

foreach (glob($base . '/*') as $tmpfile) {
    if (is_file($tmpfile) && filemtime($tmpfile) < (time() - 3600)) {
        @unlink($tmpfile);
    }
}

$files = [
    'csv' => [
        'file' => $base . '/examens.csv',
        'name' => 'Examens_' . date('Y-m-d_H-i') . '.csv',
        'mime' => 'text/csv'
    ],
    'excel' => [
        'file' => $base . '/examens.xlsx',
        'name' => 'Examens_' . date('Y-m-d_H-i') . '.xlsx',
        'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ],
    'log' => [
        'file' => $base . '/execution.txt',
        'name' => 'Execution_' . date('Y-m-d_H-i') . '.txt',
        'mime' => 'text/plain'
    ],
];

if (!isset($files[$type])) {
    throw new moodle_exception('Type de téléchargement invalide');
}

$info = $files[$type];

if (!file_exists($info['file'])) {
    throw new moodle_exception('Fichier introuvable');
}

$realbase = realpath($base);
$realfile = realpath($info['file']);
if ($realbase === false || $realfile === false || strpos($realfile, $realbase) !== 0) {
    throw new moodle_exception('invalidaccess');
}

header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-Type: ' . $info['mime']);
header('Content-Disposition: attachment; filename="' . $info['name'] . '"');
header('Content-Length: ' . filesize($info['file']));

readfile($info['file']);
exit;