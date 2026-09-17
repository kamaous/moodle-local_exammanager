<?php
namespace local_exammanager;

defined('MOODLE_INTERNAL') || die();

class xlsx_writer {

    /*
    ECHAPPE LES CARACTERES XML
    */
    private static function xml_escape(string $value): string {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /*
    NOM DES COLONNES EXCEL (A,B,C...)
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
    CREATION FICHIER XLSX
    */
    public static function write(array $headers, array $rows, string $filepath, string $sheetname = 'Examens'): void {

        if (!class_exists('ZipArchive')) {
            throw new \moodle_exception('Extension ZipArchive manquante.');
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
        CONTENT TYPES
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
        CREATION DU CONTENU EXCEL
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
        DONNEES
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
        CREATION ZIP
        */
        $zip = new \ZipArchive();

        if ($zip->open($filepath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
            throw new \moodle_exception('Impossible de créer le fichier XLSX');
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
        IMPORTANT : vérifier que le fichier existe
        */
        if (!file_exists($filepath)) {
            throw new \moodle_exception('Fichier Excel non généré.');
        }

        self::delete_dir($base);
    }

    /**
     * Suppression récursive du dossier temporaire.
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
