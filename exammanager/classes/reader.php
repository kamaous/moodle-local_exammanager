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
 * CSV / XLSX planning file reader.
 *
 * @package    local_exammanager
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_exammanager;

defined('MOODLE_INTERNAL') || die();

class reader {

    public static function read_rows(string $filepath): array {

        global $CFG;

        $rows = [];
        $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

        /* ======================
           CSV
        ====================== */
        if ($ext === 'csv') {

            $fh = fopen($filepath, 'r');

            if (!$fh) {
                throw new \moodle_exception('Impossible de lire le fichier CSV');
            }

            $header = fgetcsv($fh, 0, ';');

            if ($header === false || empty($header)) {
                throw new \moodle_exception('CSV vide ou en-têtes invalides');
            }

            $header = array_map(function($value) {
                $value = trim((string)$value);
                $value = preg_replace('/^\xEF\xBB\xBF/u', '', $value);
                return $value;
            }, $header);

            if (count($header) !== count(array_unique($header))) {
                throw new \moodle_exception('CSV invalide : colonnes dupliquées');
            }

            while (($data = fgetcsv($fh, 0, ';')) !== false) {

                if (count(array_filter($data)) === 0) {
                    continue;
                }

                if (count($data) !== count($header)) {
                    throw new \moodle_exception('CSV invalide : nombre de colonnes incohérent');
                }

                $combined = array_combine($header, $data);
                if ($combined === false) {
                    throw new \moodle_exception("CSV invalide : impossible d'associer les colonnes");
                }

                $rows[] = array_map(function($value) {
                    return trim((string)$value);
                }, $combined);
            }

            fclose($fh);
            return $rows;
        }

        /* ======================
           XLSX / XLS
        ====================== */
        if ($ext === 'xlsx' || $ext === 'xls') {

            // 🔥 Charger PhpSpreadsheet si dispo
            if (file_exists($CFG->dirroot . '/lib/phpspreadsheet/vendor/autoload.php')) {
                require_once($CFG->dirroot . '/lib/phpspreadsheet/vendor/autoload.php');
            } elseif (file_exists($CFG->dirroot . '/vendor/autoload.php')) {
                require_once($CFG->dirroot . '/vendor/autoload.php');
            }

            // Vérification
            if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                throw new \moodle_exception(
                    'Lecture Excel impossible : PhpSpreadsheet non installé. Utilisez CSV ou installez la librairie.'
                );
            }

            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filepath);
                $sheet = $spreadsheet->getActiveSheet();
                $data = $sheet->toArray(null, true, true, false);

                if (empty($data)) {
                    throw new \moodle_exception('Fichier Excel vide');
                }

                $header = array_map(function($value) {
                    return trim((string)$value);
                }, $data[0]);

                if (empty($header) || count($header) !== count(array_unique($header))) {
                    throw new \moodle_exception('Fichier Excel invalide : en-têtes manquants ou dupliqués');
                }

                for ($i = 1; $i < count($data); $i++) {

                    $row = $data[$i];

                    if (count(array_filter($row)) === 0) {
                        continue;
                    }

                    $assoc = [];

                    foreach ($header as $index => $colname) {
                        $assoc[$colname] = trim((string)($row[$index] ?? ''));
                    }

                    $rows[] = $assoc;
                }

                return $rows;

            } catch (\Throwable $e) {
                debugging('ExamManager Excel read error: ' . $e->getMessage(), DEBUG_DEVELOPER);
                throw new moodle_exception('Erreur de lecture Excel. Vérifiez que le fichier est valide et conforme au modèle attendu.');
            }
        }

        /* ======================
           AUTRE FORMAT
        ====================== */
        throw new \moodle_exception('Format non supporté : ' . $ext);
    }
}