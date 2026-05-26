<?php
/**
 * =============================================================================
 *  Számlaellenőrzés — automatizált feldolgozás  (PhpSpreadsheet)
 * =============================================================================
 *
 *  STEP 1 — Számlaszámok feldolgozása  (KÉSZ)
 *    1. A "Szamlaszamok" munkalap A oszlopának deduplikálása egy új
 *       "Szamlaszamok_nonduplicate" munkalapra, az első előfordulás
 *       sorrendjében (az egyetlen üres számlaszám is megmarad — pont mint
 *       az Excel "Ismétlődések eltávolítása" funkciója).
 *    2. B/C/D oszlop feltöltése VLOOKUP képletekkel (Név, Azonosító, Dátum).
 *    3. E/F/G segédoszlopok: DATE, CONCATENATE, TIMEVALUE.
 *    4. H (COUNTIF) és I (SUMIFS) összesítő oszlopok hozzáadása.
 *    5. "Vizsgalati_artetelek" munkalap létrehozása: az E oszlop (vizsgálat-
 *       típusok) deduplikálása COUNTIF + VLOOKUP képletekkel.
 *
 *  STEP 2 — Vizsgálatok szétválasztása  (a kombinált nyers export
 *           megérkezése után kerül be ide — lásd a fájl alján).
 *
 * -----------------------------------------------------------------------------
 *  TELEPÍTÉS  (egyszeri):
 *    1. Telepíts PHP-t (8.1 vagy újabb ajánlott)  ->  https://windows.php.net
 *    2. Telepítsd a Composert                     ->  https://getcomposer.org
 *    3. Ennek a fájlnak a mappájában futtasd:   composer install
 *
 *  FUTTATÁS  (havonta):
 *    - Frissítsd a lenti BEÁLLÍTÁSOK blokkot az aktuális havi fájlnevekkel.
 *    - Parancssorból:   php szamlaellenorzes.php
 * =============================================================================
 */

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/* ============================ BEÁLLÍTÁSOK ================================== *
 *  Havonta csak ezt a blokkot kell átírni.                                    */
$CONFIG = [
    // A nyers számlaszám-export. Tartalmaznia kell a 'raw_sheet' munkalapot.
    'input'  => __DIR__ . '/25_09_szamlaszamok.xlsx',

    // A feldolgozott munkafüzet mentési helye (a nyers fájl érintetlen marad).
    'output' => __DIR__ . '/25_09_szamlaszamok_nyers.xlsx',

    // Munkalapnevek
    'raw_sheet'       => 'Szamlaszamok',               // nyers adatok (1. tábla)
    'dedup_sheet'     => 'Szamlaszamok_nonduplicate',  // generált 2. tábla
    'artetelek_sheet' => 'Vizsgalati_artetelek',       // generált 3. tábla
];
/* ============================================================================ */

/* ===================== BEÁLLÍTÁSOK — STEP 2 (Vizsgálatok) ================== */
$CFG_VIZSGALATOK = [
    // A kombinált nyers vizsgálat-export (egyetlen munkalap: RTG + Fogleü + egyéb).
    'vizsgalatok_input' => __DIR__ . '/25_09_vizsgalatok.xlsx',
    'vizsgalatok_sheet' => 'Vizsgálatok',

    // A számlaszám-fájl — ebből épül a Számla-ellenőrzés (Paciens/Azonosito halmaz).
    'szamla_lookup_file'  => __DIR__ . '/25_09_szamlaszamok_nyers.xlsx',
    'szamla_lookup_sheet' => 'Szamlaszamok',

    // A Menedzser-tábla és az aktuális havi munkalap (nevek a B oszlopban, 3. sortól).
    'menedzser_file'  => __DIR__ . '/Menedzser táblázat 2025 12 DECEMBER.xlsx',
    'menedzser_sheet' => 'Augusztus',

    // A riport mentési helye.
    'report_output' => __DIR__ . '/ellenorzes_riport.xlsx',

    // Szétválasztási kulcsszavak a 'Szakrendelés' oszlopra
    // (kisbetűs, ékezetes, részlet-egyezés — bővíthető, ha új érték jelenik meg).
    'rtg_keywords'    => ['radiológia', 'rtg', 'röntgen'],
    'fogleu_keywords' => ['foglalkozás', 'fogleü', 'összefoglaló'],
];
/* ============================================================================ */

processSzamlaszamok($CONFIG);
processVizsgalatok($CFG_VIZSGALATOK);


/* ===========================================================================
 *  STEP 1
 * =========================================================================== */

/**
 * A teljes számlaszám-feldolgozás vezérlője.
 */
function processSzamlaszamok(array $cfg): void
{
    if (!is_file($cfg['input'])) {
        fwrite(STDERR, "HIBA: a bemeneti fájl nem található:\n  {$cfg['input']}\n");
        exit(1);
    }

    echo "Betöltés: {$cfg['input']}\n";
    $reader      = IOFactory::createReader('Xlsx');
    $spreadsheet = $reader->load($cfg['input']);

    $raw = $spreadsheet->getSheetByName($cfg['raw_sheet']);
    if ($raw === null) {
        fwrite(STDERR, "HIBA: nincs '{$cfg['raw_sheet']}' nevű munkalap a fájlban.\n");
        exit(1);
    }

    // A nyers adatok utolsó sora — a VLOOKUP tartományok ettől függnek.
    $N = $raw->getHighestRow();
    echo "  '{$cfg['raw_sheet']}' munkalap — utolsó adatsor: {$N}\n";

    // Az Artetel és NettoAr oszlopot FEJLÉC szerint keressük meg — így nem
    // számít, hogy a nyers exportban éppen melyik betűnél vannak (pl. ha az
    // export E-be teszi a NettoAr-t és F-be az Artetelt, az is jó lesz).
    $artetelCol = findHeaderColumn($raw, 'Szamlazas/Artetel');
    $nettoCol   = findHeaderColumn($raw, 'Szamlazas/NettoAr');
    echo "  Oszlopok megtalálva: Artetel={$artetelCol}  |  NettoAr={$nettoCol}\n";

    $uniqueInvoices = dedupColumn($raw, 'A', $N);
    $uniqueExams    = dedupColumn($raw, $artetelCol, $N);
    echo "  Egyedi számlaszám: " . count($uniqueInvoices)
        . "  |  egyedi vizsgálattípus: " . count($uniqueExams) . "\n";

    // A korábbi generált munkalapok eltávolítása — így a szkript többször is
    // lefuttatható ugyanarra a fájlra (idempotens).
    foreach ([$cfg['dedup_sheet'], $cfg['artetelek_sheet']] as $name) {
        if ($spreadsheet->sheetNameExists($name)) {
            $spreadsheet->removeSheetByIndex(
                $spreadsheet->getIndex($spreadsheet->getSheetByName($name))
            );
        }
    }

    buildDedupSheet($spreadsheet, $cfg, $uniqueInvoices, $N, $nettoCol);
    buildArtetelekSheet($spreadsheet, $cfg, $uniqueExams, $artetelCol, $nettoCol);

    $spreadsheet->setActiveSheetIndex(0);

    echo "Mentés: {$cfg['output']}\n";
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    // A képleteket nem számoljuk elő — az Excel a megnyitáskor frissíti őket.
    $writer->setPreCalculateFormulas(false);
    $writer->save($cfg['output']);

    echo "STEP 1 KÉSZ.\n";
}


/**
 * Egy oszlop értékeinek deduplikálása az ELSŐ ELŐFORDULÁS sorrendjében.
 *
 * Az üres cellát is önálló értéknek tekinti, amely legfeljebb egyszer kerül be
 * — ez pontosan az Excel "Ismétlődések eltávolítása" viselkedése, és a meglévő
 * sablonnal is megegyezik.
 *
 * @return array a megőrzött, eredeti értékek listája (az üres cella = null)
 */
function dedupColumn(Worksheet $sheet, string $col, int $lastRow): array
{
    $seen   = [];
    $result = [];
    for ($r = 2; $r <= $lastRow; $r++) {
        $value = $sheet->getCell($col . $r)->getValue();
        $key   = ($value === null || $value === '') ? "\0EMPTY\0" : (string) $value;
        if (!array_key_exists($key, $seen)) {
            $seen[$key] = true;
            $result[]   = $value;
        }
    }
    return $result;
}


/**
 * 2. tábla — "Szamlaszamok_nonduplicate".
 *
 * Oszlopok:
 *   A  számlaszám (deduplikált érték)
 *   B  Paciens/Nev               = VLOOKUP(...,2,FALSE)
 *   C  Paciens/Azonosito         = VLOOKUP(...,3,FALSE)
 *   D  Szamlazas/SzamlazasDatuma = VLOOKUP(...,4,FALSE)
 *   E  (segéd) = DATE(YEAR(D),MONTH(D),DAY(D))
 *   F  (segéd) = CONCATENATE(C,E)
 *   G  (segéd) = TIMEVALUE(D)
 *   H  Szamlazas/Ossztetel    = COUNTIF(Szamlaszamok!A:A, A)
 *   I  Szamlazas/OsszNettoAr  = SUMIFS(Szamlaszamok!F:F, Szamlaszamok!A:A, A)
 *
 * A B/C/D VLOOKUP a sablonban használt "csúszó" tartományt használja
 * (=VLOOKUP($A2,Szamlaszamok!$A1:F1043,...) -> a következő sorban $A2:F1044
 * stb.). Pontos egyezésnél ez ugyanazt az eredményt adja, viszont a képletek
 * karakterre megegyeznek azzal, amit kézi lehúzással kapnál.
 */
function buildDedupSheet(Spreadsheet $spreadsheet, array $cfg,
                         array $invoices, int $N, string $nettoCol): void
{
    $raw       = qsheet($cfg['raw_sheet']);
    $dedupName = $cfg['dedup_sheet'];

    $ws = $spreadsheet->createSheet();
    $ws->setTitle($dedupName);

    // Fejléc — E1/F1/G1 szándékosan üres marad (a sablonnal megegyezően).
    $ws->setCellValue('A1', 'Szamlazas/Szamlaszam');
    $ws->setCellValue('B1', 'Paciens/Nev');
    $ws->setCellValue('C1', 'Paciens/Azonosito');
    $ws->setCellValue('D1', 'Szamlazas/SzamlazasDatuma');
    $ws->setCellValue('H1', 'Szamlazas/Ossztetel');
    $ws->setCellValue('I1', 'Szamlazas/OsszNettoAr');

    $row = 2;
    foreach ($invoices as $invoice) {
        // A oszlop — a számlaszám. Az egyetlen üres érték üresen marad.
        if ($invoice !== null && $invoice !== '') {
            $ws->setCellValueExplicit('A' . $row, $invoice, DataType::TYPE_STRING);
        }

        // B/C/D — VLOOKUP a nyers munkalapra, csúszó tartománnyal.
        $rangeStart = $row - 1;
        $rangeEnd   = $N + $row - 2;
        $lookup     = "{$raw}!\$A{$rangeStart}:F{$rangeEnd}";
        $ws->setCellValue('B' . $row, "=VLOOKUP(\$A{$row},{$lookup},2,FALSE)");
        $ws->setCellValue('C' . $row, "=VLOOKUP(\$A{$row},{$lookup},3,FALSE)");
        $ws->setCellValue('D' . $row, "=VLOOKUP(\$A{$row},{$lookup},4,FALSE)");

        // E/F/G — segédoszlopok (dátum, azonosító+dátum kulcs, időpont).
        $ws->setCellValue('E' . $row, "=DATE(YEAR(D{$row}),MONTH(D{$row}),DAY(D{$row}))");
        $ws->setCellValue('F' . $row, "=CONCATENATE(C{$row},E{$row})");
        $ws->setCellValue('G' . $row, "=TIMEVALUE(D{$row})");

        // H/I — összesítők a teljes nyers oszlopok felett.
        $ws->setCellValue('H' . $row, "=COUNTIF({$raw}!A:A,A{$row})");
        $ws->setCellValue('I' . $row,
            "=SUMIFS({$raw}!{$nettoCol}:{$nettoCol},{$raw}!A:A,"
            . qsheet($dedupName) . "!A{$row})");

        $row++;
    }

    echo "  '{$dedupName}' munkalap kész — " . count($invoices) . " sor.\n";
}


/**
 * 3. tábla — "Vizsgalati_artetelek".
 *
 * Oszlopok:
 *   A  Szamlazas/Vizsgalattipus (deduplikált vizsgálattípus)
 *   B  Szamlazas/TetelDarab = COUNTIF(Szamlaszamok!E:E, A)
 *   C  Szamlazas/Tetelar    = VLOOKUP(A, Szamlaszamok!E:F, 2, FALSE)
 */
function buildArtetelekSheet(Spreadsheet $spreadsheet, array $cfg,
                             array $exams, string $artetelCol, string $nettoCol): void
{
    $raw = qsheet($cfg['raw_sheet']);

    $ws = $spreadsheet->createSheet();
    $ws->setTitle($cfg['artetelek_sheet']);

    $ws->setCellValue('A1', 'Szamlazas/Vizsgalattipus');
    $ws->setCellValue('B1', 'Szamlazas/TetelDarab');
    $ws->setCellValue('C1', 'Szamlazas/Tetelar');

    $row = 2;
    foreach ($exams as $exam) {
        if ($exam !== null && $exam !== '') {
            $ws->setCellValueExplicit('A' . $row, $exam, DataType::TYPE_STRING);
        }
        $ws->setCellValue('B' . $row,
            "=COUNTIF({$raw}!{$artetelCol}:{$artetelCol},A{$row})");
        // INDEX/MATCH-et használunk VLOOKUP helyett, mert így akkor is működik,
        // ha a NettoAr oszlop az Artetel-től BALRA helyezkedik el.
        $ws->setCellValue('C' . $row,
            "=INDEX({$raw}!{$nettoCol}:{$nettoCol},"
            . "MATCH(A{$row},{$raw}!{$artetelCol}:{$artetelCol},0))");
        $row++;
    }

    echo "  '{$cfg['artetelek_sheet']}' munkalap kész — " . count($exams) . " sor.\n";
}


/**
 * Munkalapnév biztonságos hivatkozása képletben: ha szóközt vagy speciális
 * karaktert tartalmaz, idézőjelbe teszi (a belső aposztrófokat duplázza).
 */
function qsheet(string $name): string
{
    return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)
        ? $name
        : "'" . str_replace("'", "''", $name) . "'";
}


/**
 * Megkeresi a megadott fejlécnevet a munkalap első sorában, és visszaadja a
 * hozzá tartozó oszlop betűjelét (pl. 'E'). Így a képletek nem ragadnak rá
 * fix oszlop-pozíciókra. Hibát dob, ha a fejléc nincs meg.
 */
function findHeaderColumn(Worksheet $sheet, string $headerName): string
{
    $lastColIdx = Coordinate::columnIndexFromString($sheet->getHighestColumn());
    for ($c = 1; $c <= $lastColIdx; $c++) {
        $h = $sheet->getCell(Coordinate::stringFromColumnIndex($c) . '1')->getValue();
        if ((string) $h === $headerName) {
            return Coordinate::stringFromColumnIndex($c);
        }
    }
    fwrite(STDERR,
        "HIBA: nincs '{$headerName}' fejlécű oszlop a nyers munkalapon.\n");
    exit(1);
}


/* ===========================================================================
 *  STEP 2 — Vizsgálatok szétválasztása és ellenőrzése
 * =========================================================================== */

/**
 * A kombinált vizsgálat-export feldolgozása:
 *   1. a sorok szétválasztása a 'Szakrendelés' oszlop alapján: RTG / Fogleü /
 *      egyéb ("a többi");
 *   2. az "egyéb" halmazon: Telephely-kategória (Cég / Magánszemély / Üres),
 *      Számla-ellenőrzés (a páciens azonosítója szerepel-e a számlaszám-fájlban)
 *      és Menedzser-ellenőrzés (a páciens neve szerepel-e a Menedzser-táblában);
 *   3. riport-munkafüzet (ellenorzes_riport.xlsx) előállítása több munkalappal.
 */
function processVizsgalatok(array $cfg): void
{
    echo "\n--- STEP 2: Vizsgálatok feldolgozása ---\n";

    foreach (['vizsgalatok_input', 'szamla_lookup_file', 'menedzser_file'] as $k) {
        if (!is_file($cfg[$k])) {
            fwrite(STDERR, "HIBA: nem található a fájl ({$k}):\n  {$cfg[$k]}\n");
            exit(1);
        }
    }

    // --- A kombinált export beolvasása ---
    echo "Betöltés: {$cfg['vizsgalatok_input']}\n";
    $reader = IOFactory::createReader('Xlsx');
    $reader->setReadDataOnly(true);
    $book  = $reader->load($cfg['vizsgalatok_input']);
    $sheet = $book->getSheetByName($cfg['vizsgalatok_sheet']) ?? $book->getActiveSheet();

    $lastColIdx = Coordinate::columnIndexFromString($sheet->getHighestColumn());
    $lastRow    = $sheet->getHighestRow();

    // Fejléc beolvasása + oszlopnév -> index térkép.
    $header = [];
    for ($c = 1; $c <= $lastColIdx; $c++) {
        $header[$c] = $sheet->getCell(Coordinate::stringFromColumnIndex($c) . '1')->getValue();
    }
    $colOf = array_flip(array_map('strval', $header));
    foreach (['Szakrendelés', 'Egyedi/Telephely', 'Paciens/Nev', 'Paciens/Azonosito'] as $need) {
        if (!isset($colOf[$need])) {
            fwrite(STDERR, "HIBA: hiányzó oszlop a vizsgálat-exportban: '{$need}'\n");
            exit(1);
        }
    }
    $iSzak = $colOf['Szakrendelés'];
    $iTel  = $colOf['Egyedi/Telephely'];
    $iNev  = $colOf['Paciens/Nev'];
    $iAz   = $colOf['Paciens/Azonosito'];

    // Adatsorok beolvasása (az üres sorokat kihagyjuk).
    $rows = [];
    for ($r = 2; $r <= $lastRow; $r++) {
        $row = [];
        $blank = true;
        for ($c = 1; $c <= $lastColIdx; $c++) {
            $v = $sheet->getCell(Coordinate::stringFromColumnIndex($c) . $r)->getValue();
            if ($v !== null && $v !== '') $blank = false;
            $row[$c] = $v;
        }
        if (!$blank) $rows[] = $row;
    }
    echo "  Beolvasott adatsor: " . count($rows) . "\n";

    // --- Keresőhalmazok a Számla- és Menedzser-ellenőrzéshez ---
    $invoiceIds   = buildLookupSet($cfg['szamla_lookup_file'],
                                   $cfg['szamla_lookup_sheet'], 'Paciens/Azonosito');
    $managerNames = buildManagerNames($cfg['menedzser_file'], $cfg['menedzser_sheet']);
    echo "  Számlázott azonosítók: " . count($invoiceIds)
        . "  |  Menedzserszűrés-nevek: " . count($managerNames) . "\n";

    // --- Szétválasztás + ellenőrzés ---
    $rtg = []; $fogleu = []; $restEnriched = []; $maganUres = []; $hianyzo = [];
    $missSzamla = $missMenedzser = $missBoth = 0;

    foreach ($rows as $row) {
        $cat  = categorize((string) ($row[$iSzak] ?? ''),
                           $cfg['rtg_keywords'], $cfg['fogleu_keywords']);
        $base = array_values($row);            // 1-alapú -> 0-alapú tömb

        if ($cat === 'RTG')    { $rtg[]    = $base; continue; }
        if ($cat === 'Fogleü') { $fogleu[] = $base; continue; }

        // "egyéb" sor — Telephely-kategória.
        $tel = trim((string) ($row[$iTel] ?? ''));
        if ($tel === '')                                $telKat = 'Üres';
        elseif (mb_strtolower($tel) === 'magánszemély') $telKat = 'Magánszemély';
        else                                            $telKat = 'Cég';

        // Számla- és Menedzser-ellenőrzés.
        $azKey  = normKey($row[$iAz]  ?? null);
        $nevKey = normKey($row[$iNev] ?? null);
        $szMiss = ($azKey  === '') || !isset($invoiceIds[$azKey]);
        $mzMiss = ($nevKey === '') || !isset($managerNames[$nevKey]);

        if ($szMiss) $missSzamla++;
        if ($mzMiss) $missMenedzser++;
        if ($szMiss && $mzMiss) $missBoth++;

        $enriched = array_merge($base, [
            $telKat,
            $szMiss ? 'HIÁNYZIK' : 'OK',
            $mzMiss ? 'HIÁNYZIK' : 'OK',
        ]);
        $restEnriched[] = $enriched;
        if ($telKat !== 'Cég')  $maganUres[] = $enriched;   // Magánszemély vagy Üres
        if ($szMiss || $mzMiss) $hianyzo[]   = $enriched;
    }

    echo "  RTG: " . count($rtg) . "  |  Fogleü: " . count($fogleu)
        . "  |  egyéb: " . count($restEnriched) . "\n";

    // --- Riport összeállítása ---
    $inHeader   = array_values(array_map('strval', $header));
    $restHeader = array_merge($inHeader, ['Telephely kategória', 'Számla', 'Menedzser']);

    $report = new Spreadsheet();
    $report->getProperties()->setTitle('Számlaellenőrzés riport');

    // 1) Összesítés munkalap
    $maganUresMiss = 0;
    foreach ($maganUres as $e) {
        $n = count($e);
        if ($e[$n - 2] === 'HIÁNYZIK' || $e[$n - 1] === 'HIÁNYZIK') $maganUresMiss++;
    }
    $summary = $report->getActiveSheet();
    $summary->setTitle('Összesítés');
    $summary->fromArray([
        ['Mutató', 'Érték'],
        ['Összes vizsgálati sor', count($rows)],
        ['RTG sorok', count($rtg)],
        ['Fogleü sorok', count($fogleu)],
        ['Egyéb (a többi) sor', count($restEnriched)],
        ['', ''],
        ['Egyéb — Magánszemély', countTel($restEnriched, 'Magánszemély')],
        ['Egyéb — Üres telephely', countTel($restEnriched, 'Üres')],
        ['Egyéb — Céges telephely', countTel($restEnriched, 'Cég')],
        ['', ''],
        ['Egyéb — hiányzó Számla', $missSzamla],
        ['Egyéb — hiányzó Menedzser', $missMenedzser],
        ['Egyéb — hiányzó Számla VAGY Menedzser', count($hianyzo)],
        ['Egyéb — hiányzó Számla ÉS Menedzser', $missBoth],
        ['', ''],
        ['Magánszemély/Üres sor összesen', count($maganUres)],
        ['Magánszemély/Üres ÉS hiányzó tétel', $maganUresMiss],
    ], null, 'A1');
    $summary->getStyle('A1:B1')->getFont()->setBold(true);
    $summary->getColumnDimension('A')->setWidth(44);
    $summary->getColumnDimension('B')->setWidth(14);

    // 2-6) Adat-munkalapok
    addReportSheet($report, 'RTG',                      $inHeader,   $rtg);
    addReportSheet($report, 'Fogleü',                   $inHeader,   $fogleu);
    addReportSheet($report, 'Egyéb',                    $restHeader, $restEnriched);
    addReportSheet($report, 'Magánszemély_üres',        $restHeader, $maganUres);
    addReportSheet($report, 'Hiányzó_Számla_Menedzser', $restHeader, $hianyzo);

    $report->setActiveSheetIndex(0);

    echo "Mentés: {$cfg['report_output']}\n";
    $writer = IOFactory::createWriter($report, 'Xlsx');
    $writer->save($cfg['report_output']);
    echo "STEP 2 KÉSZ.\n";
}


/**
 * Egy munkalap hozzáadása a riporthoz: fejléc + adatsorok, félkövér fejléccel,
 * rögzített fejlécsorral és automatikus szűrővel.
 */
function addReportSheet(Spreadsheet $book, string $title,
                        array $header, array $dataRows): void
{
    $ws = $book->createSheet();
    $ws->setTitle($title);
    $ws->fromArray([$header], null, 'A1');
    if (!empty($dataRows)) {
        $ws->fromArray($dataRows, null, 'A2');
    }
    $lastCol = Coordinate::stringFromColumnIndex(max(1, count($header)));
    $ws->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
    $ws->freezePane('A2');
    $ws->setAutoFilter("A1:{$lastCol}" . (count($dataRows) + 1));
    for ($c = 1; $c <= count($header); $c++) {
        $ws->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
    }
    echo "  '{$title}' munkalap: " . count($dataRows) . " sor.\n";
}


/**
 * Egy 'Szakrendelés'-érték besorolása kulcsszavak alapján.
 * Először az RTG-, majd a Fogleü-kulcsszavakat vizsgálja; egyébként 'rest'.
 */
function categorize(string $szakrendeles, array $rtgKw, array $fogleuKw): string
{
    $s = mb_strtolower(trim($szakrendeles));
    foreach ($rtgKw as $kw) {
        if ($kw !== '' && mb_strpos($s, mb_strtolower($kw)) !== false) return 'RTG';
    }
    foreach ($fogleuKw as $kw) {
        if ($kw !== '' && mb_strpos($s, mb_strtolower($kw)) !== false) return 'Fogleü';
    }
    return 'rest';
}


/**
 * Kulcs normalizálása az egyezés-vizsgálathoz: levágott szóköz + kisbetű.
 */
function normKey($value): string
{
    return mb_strtolower(trim((string) $value));
}


/**
 * Egy oszlop értékkészlete fejlécnév alapján (a Számla-ellenőrzéshez:
 * mely páciens-azonosítókhoz tartozik számla).
 *
 * @return array normalizált kulcs => true
 */
function buildLookupSet(string $file, string $sheetName, string $headerName): array
{
    $reader = IOFactory::createReader('Xlsx');
    $reader->setReadDataOnly(true);
    $book  = $reader->load($file);
    $sheet = $book->getSheetByName($sheetName) ?? $book->getActiveSheet();

    $lastColIdx = Coordinate::columnIndexFromString($sheet->getHighestColumn());
    $col = null;
    for ($c = 1; $c <= $lastColIdx; $c++) {
        $h = $sheet->getCell(Coordinate::stringFromColumnIndex($c) . '1')->getValue();
        if ((string) $h === $headerName) { $col = $c; break; }
    }
    if ($col === null) {
        fwrite(STDERR, "HIBA: '{$headerName}' oszlop nincs meg itt:\n  {$file}\n");
        exit(1);
    }

    $set    = [];
    $letter = Coordinate::stringFromColumnIndex($col);
    $last   = $sheet->getHighestRow();
    for ($r = 2; $r <= $last; $r++) {
        $k = normKey($sheet->getCell($letter . $r)->getValue());
        if ($k !== '') $set[$k] = true;
    }
    return $set;
}


/**
 * A Menedzser-tábla adott havi munkalapjának nevei (B oszlop, a 3. sortól).
 *
 * @return array normalizált név => true
 */
function buildManagerNames(string $file, string $sheetName): array
{
    $reader = IOFactory::createReader('Xlsx');
    $reader->setReadDataOnly(true);
    $book  = $reader->load($file);
    $sheet = $book->getSheetByName($sheetName);
    if ($sheet === null) {
        fwrite(STDERR, "HIBA: nincs '{$sheetName}' munkalap a Menedzser-táblában.\n");
        exit(1);
    }
    $set  = [];
    $last = $sheet->getHighestRow();
    for ($r = 3; $r <= $last; $r++) {
        $k = normKey($sheet->getCell('B' . $r)->getValue());
        if ($k !== '') $set[$k] = true;
    }
    return $set;
}


/**
 * Adott Telephely-kategóriájú sorok darabszáma az enriched (12 oszlopos) tömbben.
 * A Telephely-kategória a sor utolsó előtti előtti eleme (count - 3).
 */
function countTel(array $rows, string $kat): int
{
    $n = 0;
    foreach ($rows as $row) {
        if (($row[count($row) - 3] ?? null) === $kat) $n++;
    }
    return $n;
}