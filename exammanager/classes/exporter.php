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
 * CSV, Excel, log and PDF export helpers for the planning results.
 *
 * @package    local_exammanager
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_exammanager;

defined('MOODLE_INTERNAL') || die();

class exporter {

    /**
     * Neutralises CSV/Excel formulas and normalises scalar values.
     */
    private static function sanitize_cell($value): string {
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } else if ($value === null) {
            $value = '';
        } else if (is_scalar($value)) {
            $value = (string)$value;
        } else {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        $value = str_replace(["
", "
"], "
", $value);

        if ($value !== '' && preg_match('/^[=+\-@]/', $value)) {
            $value = "'" . $value;
        }

        return $value;
    }

    private static function export_cell(string $column, $value): string {
        $value = self::sanitize_cell($value);

        if (in_array($column, ['open_time', 'close_time'], true)) {
            return str_replace('T', ' ', $value);
        }

        return $value;
    }

    /*
    EXACT COLUMN ORDER EXPECTED BY THE PLANNING TEMPLATE
    */
    private static function headers(): array {
        return [
            'course_shortname',
            'quiz_name',
            'open_time',
            'close_time',
            'time_limit',
            'access_code',
            'seb_exit_code',
            'access_code_action',
            'seb_action',
            'generate_access_code',
            'generate_seb_exit_code',
            'force_new_codes',
            'status',
            'message',
            'teacher',
            'room',
            'session'
        ];
    }

    /*
    CSV EXPORT (with UTF-8 BOM for Excel)
    */
    public static function export_csv(array $rows, string $filepath): void {

        $f = fopen($filepath, 'w');

        // UTF-8 BOM so accented characters display correctly in Excel.
        fprintf($f, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($f, self::headers(), ';');

        $previouscourse = null;
        $emptyline = array_fill(0, count(self::headers()), '');

        foreach ($rows as $row) {

            $currentcourse = trim((string)($row['course_shortname'] ?? ''));

            if ($previouscourse !== null && $currentcourse !== $previouscourse) {
                fputcsv($f, $emptyline, ';');
            }

            $line = [];

            foreach (self::headers() as $col) {
                $line[] = self::export_cell($col, $row[$col] ?? '');
            }

            fputcsv($f, $line, ';');

            $previouscourse = $currentcourse;
        }

        fclose($f);
    }

    /*
    EXCEL EXPORT (.xlsx)
    */
    public static function export_excel(array $rows, string $filepath): void {

        $headers = self::headers();

        $matrix = [];

        $previouscourse = null;
        $emptyline = array_fill(0, count($headers), '');

        foreach ($rows as $row) {

            $currentcourse = trim((string)($row['course_shortname'] ?? ''));

            if ($previouscourse !== null && $currentcourse !== $previouscourse) {
                $matrix[] = $emptyline;
            }

            $line = [];

            foreach ($headers as $col) {
                $line[] = self::export_cell($col, $row[$col] ?? '');
            }

            $matrix[] = $line;

            $previouscourse = $currentcourse;
                }

        \local_exammanager\xlsx_writer::write($headers, $matrix, $filepath);
    }

    /*
    LOG EXPORT
    */
    public static function export_log(array $rows, string $filepath): void {

        $lines = [];

        foreach ($rows as $row) {

            $lines[] =
                '[' . self::sanitize_cell($row['status'] ?? '') . '] ' .
                self::sanitize_cell($row['course_shortname'] ?? '') . ' | ' .
                self::sanitize_cell($row['quiz_name'] ?? '') . ' | ' .
                self::export_cell('open_time', $row['open_time'] ?? '') . ' -> ' .
                self::export_cell('close_time', $row['close_time'] ?? '') . ' | ' .
                self::sanitize_cell($row['message'] ?? '');
        }

        file_put_contents($filepath, implode(PHP_EOL, $lines));
    }

    /*
    HTML CONSTRUCTION FOR PDF
    */
    private static function build_grouped_html(array $rows, string $groupkey, callable $labelfn, string $title): string {

        $groups = [];

        foreach ($rows as $row) {

            if (!in_array(($row['status'] ?? ''), ['PROGRAMMED', 'PROGRAMMÉ'], true)) {
                continue;
            }

            $key = $labelfn((string)($row[$groupkey] ?? ''));

            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }

            $groups[$key][] = $row;
        }

        $html = '<h2>'.s($title).'</h2>';

        foreach ($groups as $group => $items) {

            $html .= '<h3>'.s($group).'</h3>';

            $html .= '<table border="1" cellpadding="4">
                <tr>
                    <th>' . s(get_string('quiz', 'local_exammanager')) . '</th>
                    <th>' . s(get_string('course', 'local_exammanager')) . '</th>
                    <th>' . s(get_string('sessionlabel', 'local_exammanager')) . '</th>
                    <th>' . s(get_string('open', 'local_exammanager')) . '</th>
                    <th>' . s(get_string('close', 'local_exammanager')) . '</th>
                    <th>' . s(get_string('duration', 'local_exammanager')) . '</th>
                    <th>' . s(get_string('accesskeylabel', 'local_exammanager')) . '</th>
                    <th>' . s(get_string('sebexitcode', 'local_exammanager')) . '</th>
                </tr>';

            foreach ($items as $item) {

                $html .= '<tr>
                    <td>'.s($item['quiz_name'] ?? '').'</td>
                    <td>'.s($item['course_shortname'] ?? '').'</td>
                    <td>'.s($item['session'] ?? '').'</td>
                    <td>'.s(self::export_cell('open_time', $item['open_time'] ?? '')).'</td>
                    <td>'.s(self::export_cell('close_time', $item['close_time'] ?? '')).'</td>
                    <td>'.s($item['time_limit'] ?? '').'</td>
                    <td>'.s($item['access_code'] ?? '').'</td>
                    <td>'.s($item['seb_exit_code'] ?? '').'</td>
                </tr>';
            }

            $html .= '</table><br>';
        }

        return $html;
    }

    /*
    PDF GENERATION
    */
    private static function export_pdf_from_html(string $html, string $filepath): void {

        global $CFG;

        require_once($CFG->dirroot.'/lib/tcpdf/tcpdf.php');

        $pdf = new \TCPDF();

        $pdf->SetCreator('Moodle');
        $pdf->SetMargins(15,15,15);

        $pdf->AddPage();

        $pdf->SetFont('helvetica','',10);

        $pdf->writeHTML($html);

        $pdf->Output($filepath,'F');
    }

    /*
    PDF BY ROOM
    */
    public static function export_pdf_by_room(array $rows, string $filepath): void {

        $html = self::build_grouped_html(
            $rows,
            'room',
            ['\\local_exammanager\\util','room_label'],
            get_string('codesbyroom','local_exammanager')
        );

        self::export_pdf_from_html($html,$filepath);
    }

    /*
    PDF BY SUPERVISOR
    */
    public static function export_pdf_by_teacher(array $rows, string $filepath): void {

        $html = self::build_grouped_html(
            $rows,
            'teacher',
            ['\\local_exammanager\\util','teacher_label'],
            get_string('codesbyteacher','local_exammanager')
        );

        self::export_pdf_from_html($html,$filepath);
    }
}
