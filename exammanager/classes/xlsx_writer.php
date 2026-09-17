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
 * Minimal dependency-free XLSX writer.
 *
 * @package    local_exammanager
 * @copyright  2026 KAMA <ousmane.kama@unchk.edu.sn>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_exammanager;

defined('MOODLE_INTERNAL') || die();

class xlsx_writer {

    /*
    ESCAPE XML CHARACTERS
    */
    private static function xml_escape(string $value): string {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /*
    EXCEL COLUMN NAMES (A, B, C...)
    */
    private static function col_name(int $index): string {

        $name = '';

        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $name = chr(65 + $mod) . $name;
            $index = intval(($index - $mod) / 26);
        }

        return $name;
    }

    /*
    CREATE THE XLSX FILE
    */
    public static function write(array $headers, array $rows, string $filepath, string $sheetname = 'Examens'): void {

        if (!class_exists('ZipArchive')) {
            throw new \moodle_exception(get_string('zipextensionmissing', 'local_exammanager'));
        }

        $tmpdir = make_request_directory();

        $base = $tmpdir . '/xlsx_' . uniqid();

        mkdir($base);
        mkdir($base . '/_rels');
        mkdir($base . '/docProps');
        mkdir($base . '/xl');
        mkdir($base . '/xl/_rels');
        mkdir($base . '/xl/worksheets');

        /*
        CONTENT TYPES DESCRIPTOR
        */
        file_put_contents($base . '/[Content_Types].xml',
'<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml"
ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml"
ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>');

        /*
        RELS
        */
        file_put_contents($base . '/_rels/.rels',
'<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1"
Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"
Target="xl/workbook.xml"/>
</Relationships>');

        /*
        WORKBOOK
        */
        file_put_contents($base . '/xl/workbook.xml',
'<?xml version="1.0" encoding="UTF-8"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets>
<sheet name="' . self::xml_escape($sheetname) . '" sheetId="1" r:id="rId1"/>
</sheets>
</workbook>');

        /*
        WORKBOOK REL
        */
        file_put_contents($base . '/xl/_rels/workbook.xml.rels',
'<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1"
Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"
Target="worksheets/sheet1.xml"/>
</Relationships>');

        /*
        BUILD THE EXCEL WORKSHEET CONTENT
        */
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        $xml .= '<sheetData>';

        /*
        HEADER
        */
        $xml .= '<row r="1">';

        foreach ($headers as $i => $header) {

            $cell = self::col_name($i + 1) . "1";

            $xml .= '<c r="'.$cell.'" t="inlineStr">
                        <is><t>'.self::xml_escape($header).'</t></is>
                     </c>';
        }

        $xml .= '</row>';

        /*
        DATA ROWS
        */
        $rowindex = 2;

        foreach ($rows as $data) {

            $xml .= '<row r="'.$rowindex.'">';

            foreach ($data as $i => $value) {

                $cell = self::col_name($i + 1) . $rowindex;

                $xml .= '<c r="'.$cell.'" t="inlineStr">
                            <is><t>'.self::xml_escape((string)$value).'</t></is>
                         </c>';
            }

            $xml .= '</row>';

            $rowindex++;
        }

        $xml .= '</sheetData>';
        $xml .= '</worksheet>';

        file_put_contents($base . '/xl/worksheets/sheet1.xml', $xml);

        /*
        CREATE THE ZIP ARCHIVE
        */
        $zip = new \ZipArchive();

        if ($zip->open($filepath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
            throw new \moodle_exception(get_string('xlsxcreateerror', 'local_exammanager'));
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {

            if (!$file->isDir()) {

                $entrypath = $file->getRealPath();
                $relativepath = substr($entrypath, strlen($base) + 1);

                $zip->addFile($entrypath, $relativepath);
            }
        }

        $zip->close();

        /*
        IMPORTANT: check that the file was actually generated.
        */
        if (!file_exists($filepath)) {
            throw new \moodle_exception(get_string('xlsxnotgenerated', 'local_exammanager'));
        }

        self::delete_dir($base);
    }

    /**
     * Recursively deletes the temporary directory.
     */
    private static function delete_dir(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getRealPath());
            } else {
                @unlink($item->getRealPath());
            }
        }

        @rmdir($dir);
    }
}
