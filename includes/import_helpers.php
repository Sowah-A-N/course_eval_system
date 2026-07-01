<?php
/**
 * Shared helpers for bulk imports (CSV and Excel .xlsx).
 *
 * Both formats are read into the same shape: an array of rows, where each row
 * is a 0-indexed array of trimmed cell strings and row 0 is the header.
 *
 * .xlsx is parsed with PHP's built-in ZipArchive + SimpleXML (an .xlsx file is
 * a ZIP of XML parts), so no external library is required.
 */

/** Default password given to imported user accounts (students, lecturers). They must change it on first login. */
if (!defined('DEFAULT_IMPORT_PASSWORD')) {
    define('DEFAULT_IMPORT_PASSWORD', 'password246');
}

/** Read a CSV file into an array of rows. */
function import_parse_csv(string $path): array {
    $rows = [];
    $handle = fopen($path, 'r');
    if ($handle === false) {
        throw new RuntimeException('Could not open the CSV file.');
    }
    while (($r = fgetcsv($handle)) !== false) {
        $rows[] = array_map(function ($v) { return trim((string) $v); }, $r);
    }
    fclose($handle);
    return $rows;
}

/** Convert an Excel column reference ("A", "B", "AA") to a 0-based index. */
function import_xlsx_col_index(string $ref): int {
    if (!preg_match('/^([A-Za-z]+)/', $ref, $m)) return -1;
    $letters = strtoupper($m[1]);
    $n = 0;
    for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
        $n = $n * 26 + (ord($letters[$i]) - 64);
    }
    return $n - 1;
}

/** Extract text from a sharedStrings <si> element (handles rich-text runs). */
function import_xlsx_si_text(SimpleXMLElement $si): string {
    if (isset($si->t)) return (string) $si->t;
    $text = '';
    if (isset($si->r)) {
        foreach ($si->r as $r) $text .= (string) $r->t;
    }
    return $text;
}

/** Read an .xlsx file into an array of rows using only ZipArchive + SimpleXML. */
function import_parse_xlsx(string $path): array {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Excel import is not available on this server. Please save your file as CSV and upload that instead.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Could not open the Excel file — it may be corrupted.');
    }

    // Shared strings table (text cells reference entries here).
    $shared = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $sx = simplexml_load_string($ssXml);
        if ($sx !== false) {
            foreach ($sx->si as $si) $shared[] = import_xlsx_si_text($si);
        }
    }

    // Worksheet XML (first sheet).
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $sheetXml = $zip->getFromName($name);
                break;
            }
        }
    }
    $zip->close();

    if ($sheetXml === false) {
        throw new RuntimeException('Could not find a worksheet inside the Excel file.');
    }
    $sheet = simplexml_load_string($sheetXml);
    if ($sheet === false) {
        throw new RuntimeException('Could not read the Excel worksheet.');
    }

    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $cells   = [];
        $max_col = -1;
        $auto    = 0; // fallback position when a cell omits its "r" reference
        foreach ($row->c as $c) {
            $ref = (string) $c['r'];
            $col = $ref !== '' ? import_xlsx_col_index($ref) : $auto;
            if ($col < 0) $col = $auto;
            $auto = $col + 1;

            $type = (string) $c['t'];
            if ($type === 's') {
                $val = $shared[(int) $c->v] ?? '';
            } elseif ($type === 'inlineStr') {
                $val = (string) $c->is->t;
            } else {
                $val = (string) $c->v; // number, boolean, or literal string
            }
            $cells[$col] = $val;
            if ($col > $max_col) $max_col = $col;
        }
        $dense = [];
        for ($i = 0; $i <= $max_col; $i++) {
            $dense[$i] = isset($cells[$i]) ? trim((string) $cells[$i]) : '';
        }
        $rows[] = $dense;
    }
    return $rows;
}

/**
 * Validate an uploaded file and read it into rows (row 0 = header).
 *
 * @param string $field  the $_FILES key of the upload input
 * @return array         array of rows
 * @throws RuntimeException  if the file is missing, the wrong type, or empty
 */
function import_read_upload(string $field): array {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Please select a valid CSV or Excel file to upload.');
    }
    $tmp = $_FILES[$field]['tmp_name'];
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));

    if ($ext === 'csv') {
        $rows = import_parse_csv($tmp);
    } elseif ($ext === 'xlsx') {
        $rows = import_parse_xlsx($tmp);
    } elseif ($ext === 'xls') {
        throw new RuntimeException('The old Excel .xls format is not supported. Please save the file as .xlsx or CSV and try again.');
    } else {
        throw new RuntimeException('File must be a CSV (.csv) or Excel (.xlsx) file.');
    }

    if (empty($rows)) {
        throw new RuntimeException('The file appears to be empty.');
    }
    return $rows;
}

/** Build a lowercased header-name → column-index map from the header row. */
function import_header_map(array $header_row): array {
    return array_flip(array_map('strtolower', array_map('trim', $header_row)));
}

/** Read a cell from a data row by header name (returns '' if absent). */
function import_cell(array $row, array $header_map, string $name): string {
    return (isset($header_map[$name]) && isset($row[$header_map[$name]]))
        ? trim((string) $row[$header_map[$name]])
        : '';
}
