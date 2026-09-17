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
 * Date parsing, code generation and shared row-normalisation helpers.
 *
 * @package    local_exammanager
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_exammanager;

use core_date;

defined('MOODLE_INTERNAL') || die();

class util {

    /**
     * Génère un code unique (5 chiffres)
     */
    public static function generate_code(array &$used): string {

        do {
            $code = (string)random_int(10000, 99999);
        } while (in_array($code, $used, true));

        $used[] = $code;

        return $code;
    }

    /**
     * Parse date format : YYYY-MM-DD HH:MM ou YYYY-MM-DDTHH:MM.
     */
    public static function parse_datetime(string $value): int {

        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $tz = core_date::get_server_timezone_object();

        if (is_numeric($value) && (float)$value > 20000 && (float)$value < 70000) {
            $seconds = (int)round(((float)$value) * 86400);
            $dt = new \DateTime('1899-12-30 00:00:00', $tz);
            $dt->modify('+' . $seconds . ' seconds');
            return $dt->getTimestamp();
        }

        $formats = [
            'Y-m-d\TH:i',
            'Y-m-d H:i',
            'Y-m-d\TH:i:s',
            'Y-m-d H:i:s',
            'd/m/Y H:i',
            'd/m/Y H:i:s',
            'd-m-Y H:i',
            'd-m-Y H:i:s',
        ];

        foreach ($formats as $format) {
            $dt = \DateTime::createFromFormat($format, $value, $tz);
            if ($dt instanceof \DateTime) {
                return $dt->getTimestamp();
            }
        }

        throw new \moodle_exception('Format date invalide : ' . $value);
    }

    /**
     * Convertit une date texte ou timestamp en valeur compatible datetime-local.
     */
    public static function to_datetime_local_value($value): string {
        if ($value === null || $value === '') {
            return '';
        }

        $tz = core_date::get_server_timezone_object();

        if (is_numeric($value)) {
            $dt = new \DateTime('@' . (int)$value);
            $dt->setTimezone($tz);
            return $dt->format('Y-m-d\TH:i');
        }

        try {
            $timestamp = self::parse_datetime((string)$value);
            $dt = new \DateTime('@' . $timestamp);
            $dt->setTimezone($tz);
            return $dt->format('Y-m-d\TH:i');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Validation d'une ligne du planning
     */
    public static function validate_row(array $row): array {

        if (empty($row['course_shortname'])) {
            return [false, 'Course_shortname manquant'];
        }

        if (empty($row['quiz_name']) && empty($row['selected_quizid'])) {
            return [false, 'Quiz non sélectionné'];
        }

        if (empty($row['open_time']) || empty($row['close_time'])) {
            return [false, 'Dates manquantes'];
        }

        try {
            $timeopen = self::parse_datetime((string)$row['open_time']);
            $timeclose = self::parse_datetime((string)$row['close_time']);
        } catch (\Throwable $e) {
            return [false, 'Format de date invalide'];
        }

        if ($timeclose <= $timeopen) {
            return [false, 'La date de fermeture doit être postérieure à la date d\'ouverture.'];
        }

        if (!isset($row['time_limit']) || $row['time_limit'] === '') {
            return [false, 'Durée manquante'];
        }

        if ((int)$row['time_limit'] <= 0) {
            return [false, 'La durée doit être supérieure à 0 minute.'];
        }

        $accessaction = self::normalize_access_action($row);
        if (!in_array($accessaction, ['keep', 'generate', 'disable'], true)) {
            return [false, 'access_code_action invalide'];
        }

        $sebaction = self::normalize_seb_action($row);
        if (!in_array($sebaction, ['keep', 'generate', 'disable'], true)) {
            return [false, 'seb_action invalide'];
        }

        return [true, 'OK'];
    }



    /**
     * Clé de mutualisation des codes par cours + date/heure d'ouverture.
     */
    public static function build_code_sharing_key(array $row): string {
        $course = strtolower(trim((string)($row['course_shortname'] ?? '')));
        $open = trim((string)($row['open_time'] ?? ''));

        if ($course === '' || $open === '') {
            return '';
        }

        try {
            $timestamp = self::parse_datetime($open);
        } catch (\Throwable $e) {
            return '';
        }

        $tz = core_date::get_server_timezone_object();
        $dt = new \DateTime('@' . $timestamp);
        $dt->setTimezone($tz);

        return $course . '|' . $dt->format('Y-m-d H:i');
    }

    /**
     * Affecte des codes générés partagés pour un même course_shortname et une même date/heure.
     * Les lignes déjà programmées peuvent servir de référence si $preserveprogrammed = true.
     * Si $allowsharedcodes = false, chaque ligne reçoit ses propres codes uniques.
     */
    public static function assign_shared_generated_codes(array $rows, bool $preserveprogrammed = false, bool $allowsharedcodes = true): array {
        $used = [];
        $accessmap = [];
        $sebmap = [];

        $registerused = function(string $code) use (&$used): void {
            if ($code !== '' && !in_array($code, $used, true)) {
                $used[] = $code;
            }
        };

        if ($preserveprogrammed) {
            foreach ($rows as $row) {
                $status = (string)($row['status'] ?? '');
                if (!in_array($status, ['PROGRAMMÉ', 'PROGRAMMED'], true)) {
                    continue;
                }

                $key = self::build_code_sharing_key($row);
                if ($key === '') {
                    continue;
                }

                $accessaction = self::normalize_access_action($row);
                $sebaction = self::normalize_seb_action($row);
                $accesscode = trim((string)($row['access_code'] ?? ''));
                $sebcode = trim((string)($row['seb_exit_code'] ?? ''));

                if ($accessaction === 'generate' && $accesscode !== '' && !isset($accessmap[$key])) {
                    $accessmap[$key] = $accesscode;
                    $registerused($accesscode);
                }

                if ($sebaction === 'generate' && $sebcode !== '' && !isset($sebmap[$key])) {
                    $sebmap[$key] = $sebcode;
                    $registerused($sebcode);
                }
            }
        }

        foreach ($rows as &$row) {
            $status = (string)($row['status'] ?? '');
            if ($preserveprogrammed && in_array($status, ['PROGRAMMÉ', 'PROGRAMMED'], true)) {
                continue;
            }

            if ($status === 'ERROR') {
                $row['access_code'] = '';
                $row['seb_exit_code'] = '';
                continue;
            }

            $row['access_code_action'] = self::normalize_access_action($row);
            $row['seb_action'] = self::normalize_seb_action($row);
            $key = $allowsharedcodes ? self::build_code_sharing_key($row) : '';

            if (($row['access_code_action'] ?? 'generate') === 'generate') {
                if ($key !== '' && isset($accessmap[$key])) {
                    $row['access_code'] = $accessmap[$key];
                } else {
                    $row['access_code'] = self::generate_code($used);
                    if ($key !== '') {
                        $accessmap[$key] = $row['access_code'];
                    }
                }
            } else {
                $row['access_code'] = '';
            }

            if (($row['seb_action'] ?? 'generate') === 'generate') {
                if ($key !== '' && isset($sebmap[$key])) {
                    $row['seb_exit_code'] = $sebmap[$key];
                } else {
                    $row['seb_exit_code'] = self::generate_code($used);
                    if ($key !== '') {
                        $sebmap[$key] = $row['seb_exit_code'];
                    }
                }
            } else {
                $row['seb_exit_code'] = '';
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Format date pour affichage
     */
    public static function format_date(int $timestamp): string {
        return userdate($timestamp, '%Y-%m-%d %H:%M');
    }

    /**
     * Normalise une action textuelle.
     */
    public static function normalize_action_value($value): string {
        $value = strtolower(trim((string)$value));
        $value = preg_replace('/[^a-z]/', '', $value);
        return $value ?? '';
    }

    /**
     * Action code d'accès.
     * Priorité au nouveau champ, avec repli sur l'ancien comportement.
     */
    public static function normalize_access_action(array $row): string {
        if (array_key_exists('access_code_action', $row) && trim((string)$row['access_code_action']) !== '') {
            $action = self::normalize_action_value($row['access_code_action']);
            if (in_array($action, ['keep', 'generate', 'disable'], true)) {
                return $action;
            }
        }

        if (isset($row['generate_access_code'])) {
            return ((int)$row['generate_access_code'] === 1) ? 'generate' : 'keep';
        }

        return 'generate';
    }

    /**
     * Action Safe Exam Browser.
     * Priorité au nouveau champ, avec repli sur l'ancien comportement.
     */
    public static function normalize_seb_action(array $row): string {
        if (array_key_exists('seb_action', $row) && trim((string)$row['seb_action']) !== '') {
            $action = self::normalize_action_value($row['seb_action']);
            if (in_array($action, ['keep', 'generate', 'disable'], true)) {
                return $action;
            }
        }

        if (isset($row['generate_seb_exit_code'])) {
            return ((int)$row['generate_seb_exit_code'] === 1) ? 'generate' : 'keep';
        }

        return 'generate';
    }
}
