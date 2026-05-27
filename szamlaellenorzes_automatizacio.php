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
    'input'  => __DIR__ . '/25_08_szamlaszamok.xlsx',

    // A feldolgozott munkafüzet mentési helye (a nyers fájl érintetlen marad).
    'output' => __DIR__ . '/25_08_szamlaszamok_szurt.xlsx',

    // Munkalapnevek
    'raw_sheet'       => 'Sheet1',               // nyers adatok (1. tábla)
    'dedup_sheet'     => 'Szamlaszamok_nonduplicate',  // generált 2. tábla
    'artetelek_sheet' => 'Vizsgalati_artetelek',       // generált 3. tábla
];
/* ============================================================================ */

/* ===================== BEÁLLÍTÁSOK — STEP 2 (Vizsgálatok) ================== */
$CFG_VIZSGALATOK = [
    // A kombinált nyers vizsgálat-export (egyetlen munkalap: RTG + Fogleü + egyéb).
    'vizsgalatok_input' => __DIR__ . '/25_08_vizsgalatok.xlsx',
    'vizsgalatok_sheet' => 'Vizsgálatok',

    // A számlaszám-fájl — ebből épül a Számla-ellenőrzés (Paciens/Azonosito halmaz).
    'szamla_lookup_file'  => __DIR__ . '/25_08_szamlaszamok.xlsx',
    'szamla_lookup_sheet' => 'Sheet1',

    // A Menedzser-tábla és az aktuális havi munkalap (nevek a B oszlopban, 3. sortól).
    'menedzser_file'  => __DIR__ . '/Menedzser táblázat 2025 12 DECEMBER.xlsx',
    'menedzser_sheet' => 'Augusztus',

    // A riport mentési helye.
    'report_output' => __DIR__ . '/25_08_vizsgalati_riport.xlsx',

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
    $azonCol    = findHeaderColumn($raw, 'Paciens/Azonosito');
    $nameCol    = findHeaderColumn($raw, 'Paciens/Nev');
    $dateCol    = findHeaderColumn($raw, 'Szamlazas/SzamlazasDatuma');
    echo "  Oszlopok megtalálva: Artetel={$artetelCol}  |  NettoAr={$nettoCol}\n";

    // Üres Paciens/Azonosito-k pótlása: minden ID nélküli soron 'nev|YYYY-MM-DD'
    // formátumú pótkulcsot írunk a cellába. Ugyanezt a sémát használja a
    // STEP 2 a vizsgálatok-fájlon, így a két oldal össze tudja párosítani az
    // azonosító nélküli pácienseket is.
    $endLetter = $raw->getHighestColumn();
    $rawAll    = $raw->rangeToArray("A2:{$endLetter}{$N}", null, false, false, false);
    $iAz = Coordinate::columnIndexFromString($azonCol) - 1;
    $iNm = Coordinate::columnIndexFromString($nameCol) - 1;
    $iDt = Coordinate::columnIndexFromString($dateCol) - 1;
    $filled = 0;
    foreach ($rawAll as $idx => $r) {
        if (normKey($r[$iAz] ?? null) !== '') continue;
        $synth = makePatientKey($r[$iNm] ?? null, $r[$iDt] ?? null);
        if ($synth === '') continue;
        $raw->setCellValueExplicit(
            $azonCol . ($idx + 2), $synth, DataType::TYPE_STRING
        );
        $filled++;
    }
    if ($filled > 0) echo "  Pótolt Azonosító (Sheet1): {$filled}\n";

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
    // Bulk olvasás — egy hívás sok ezer getCell helyett.
    $rows = $sheet->rangeToArray("{$col}2:{$col}{$lastRow}", null, false, false, false);
    $seen   = [];
    $result = [];
    foreach ($rows as $r) {
        $value = $r[0] ?? null;
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
 *   1. besorolás a 'Szakrendelés' oszlop alapján: RTG / Fogleü / egyéb;
 *   2. az "egyéb" halmazból csak a Magánszemély vagy üres telephelyű sorokat
 *      tartjuk meg, kiegészítve Számla- és Menedzser-státusszal;
 *   3. riport (ellenorzes_riport.xlsx) 3 munkalappal: RTG, Fogleü, Magánszemély_üres.
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

    // Fejlécek -> 1-alapú forrás-oszlop indexek.
    $colOf = [];
    for ($c = 1; $c <= $lastColIdx; $c++) {
        $h = $sheet->getCell(Coordinate::stringFromColumnIndex($c) . '1')->getValue();
        if ($h !== null) $colOf[(string) $h] = $c;
    }
    foreach (['Szakrendelés', 'Egyedi/Telephely', 'Paciens/Nev', 'Paciens/Azonosito'] as $need) {
        if (!isset($colOf[$need])) {
            fwrite(STDERR, "HIBA: hiányzó oszlop a vizsgálat-exportban: '{$need}'\n");
            exit(1);
        }
    }

    // A riport 9 lényeges oszlopa (a PII és Fogleü-specifikus mezők kimaradnak).
    $reportHeaders = [
        'Vizsgalat/UtolsoModositasDatuma',
        'Paciens/Nev',
        'Szakrendelés',
        'Felhasználó',
        'Paciens/Azonosito',
        'Paciens/SzuletesiDatum',
        'Egyedi/Telephely',
        'Egyedi/Munkakör',
        'Vizsgalat/FelvetelDatuma',
    ];
    // 0-alapú forrás-indexek a $reportHeaders sorrendjében (null = nincs az exportban).
    $srcIdx = [];
    foreach ($reportHeaders as $h) {
        $srcIdx[] = isset($colOf[$h]) ? $colOf[$h] - 1 : null;
    }
    // Fix pozíciók a projektált sorban — a $reportHeaders sorrendje rögzített.
    [$pNev, $pSzak, $pAz, $pTel, $pFelv] = [1, 2, 4, 6, 8];

    // --- Keresőhalmazok ---
    // Az ID nélküli számla-soroknak is keletkezik pótkulcs (nev|YYYY-MM-DD),
    // és a vizsgálat-sorokra ugyanezt a pótkulcsot fogjuk használni — így a
    // két fájl össze tudja párosítani az azonosító nélküli pácienseket is.
    $invoiceIds   = buildLookupSetWithFallback(
        $cfg['szamla_lookup_file'], $cfg['szamla_lookup_sheet'],
        'Paciens/Azonosito', 'Paciens/Nev', 'Szamlazas/SzamlazasDatuma'
    );
    $managerNames = buildManagerNames($cfg['menedzser_file'], $cfg['menedzser_sheet']);
    echo "  Számlázott azonosítók: " . count($invoiceIds)
        . "  |  Menedzserszűrés-nevek: " . count($managerNames) . "\n";

    // --- Bulk beolvasás (rangeToArray jóval gyorsabb, mint a cellánkénti getCell) ---
    $endLetter = Coordinate::stringFromColumnIndex($lastColIdx);
    $allRows   = $sheet->rangeToArray("A2:{$endLetter}{$lastRow}", null, false, false, false);

    // Kisbetűs kulcsszavak az ismétlődő mb_strtolower elkerüléséhez.
    $rtgKw = array_map('mb_strtolower', $cfg['rtg_keywords']);
    $fogKw = array_map('mb_strtolower', $cfg['fogleu_keywords']);

    // --- Besorolás + szűrés egy menetben ---
    $rtg = []; $fogleu = []; $maganUres = [];

    foreach ($allRows as $src) {
        // 9-oszlopos riport-sor projekció.
        $row = [];
        $blank = true;
        foreach ($srcIdx as $i) {
            $v = ($i === null) ? null : ($src[$i] ?? null);
            if ($v !== null && $v !== '') $blank = false;
            $row[] = $v;
        }
        if ($blank) continue;

        // Üres Azonosító -> generált pótkulcs (név + FelvetelDatuma).
        // Ugyanezt a sémát használja a buildLookupSet a számla-fájlon, így a
        // két oldal össze tud párosulni azonosító nélkül is.
        if (normKey($row[$pAz]) === '') {
            $synth = makePatientKey($row[$pNev], $row[$pFelv]);
            if ($synth !== '') $row[$pAz] = $synth;
        }

        // Besorolás a Szakrendelés kulcsszavak alapján.
        $s = mb_strtolower(trim((string) $row[$pSzak]));
        $cat = 'rest';
        foreach ($rtgKw as $kw) {
            if ($kw !== '' && mb_strpos($s, $kw) !== false) { $cat = 'RTG'; break; }
        }
        if ($cat === 'rest') {
            foreach ($fogKw as $kw) {
                if ($kw !== '' && mb_strpos($s, $kw) !== false) { $cat = 'Fogleü'; break; }
            }
        }

        if ($cat === 'RTG')    { $rtg[]    = $row; continue; }
        if ($cat === 'Fogleü') { $fogleu[] = $row; continue; }

        // "egyéb" — csak akkor kell, ha Magánszemély vagy üres a telephely.
        $tel = trim((string) $row[$pTel]);
        if ($tel !== '' && mb_strtolower($tel) !== 'magánszemély') continue;

        // Számla/Menedzser státusz hozzáfűzése.
        $azKey  = mb_strtolower(trim((string) $row[$pAz]));
        $nevKey = mb_strtolower(trim((string) $row[$pNev]));
        $row[] = ($azKey  === '' || !isset($invoiceIds[$azKey]))    ? 'HIÁNYZIK' : 'OK';
        $row[] = ($nevKey === '' || !isset($managerNames[$nevKey])) ? 'HIÁNYZIK' : 'OK';
        $maganUres[] = $row;
    }

    echo "  RTG: " . count($rtg) . "  |  Fogleü: " . count($fogleu)
        . "  |  Magánszemély/Üres: " . count($maganUres) . "\n";

    // --- Riport: 3 munkalap (RTG, Fogleü, Magánszemély_üres) ---
    $maganHeader = array_merge($reportHeaders, ['Számla', 'Menedzser']);
    $report = new Spreadsheet();
    $first  = true;
    foreach ([
        ['RTG',               $reportHeaders, $rtg],
        ['Fogleü',            $reportHeaders, $fogleu],
        ['Magánszemély_üres', $maganHeader,   $maganUres],
    ] as [$title, $hdr, $data]) {
        $ws = $first ? $report->getActiveSheet() : $report->createSheet();
        $first = false;
        $ws->setTitle($title);
        $ws->fromArray([$hdr], null, 'A1');
        if ($data) $ws->fromArray($data, null, 'A2');
        $lastCol = Coordinate::stringFromColumnIndex(count($hdr));
        $ws->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $ws->freezePane('A2');
        $ws->setAutoFilter("A1:{$lastCol}" . (count($data) + 1));
    }
    $report->setActiveSheetIndex(0);

    echo "Mentés: {$cfg['report_output']}\n";
    IOFactory::createWriter($report, 'Xlsx')->save($cfg['report_output']);
    echo "STEP 2 KÉSZ.\n";
}


/**
 * Kulcs normalizálása az egyezés-vizsgálathoz: levágott szóköz + kisbetű.
 */
function normKey($value): string
{
    return mb_strtolower(trim((string) $value));
}


/**
 * Pótkulcs az üres Azonosító-soroknak: 'normalizált_nev|YYYY-MM-DD'.
 * Ha mind a név, mind a dátum hiányzik, üres stringet ad — ekkor a sor tényleg
 * nem azonosítható.
 */
function makePatientKey($name, $date): string
{
    $n = mb_strtolower(trim((string) $name));
    $d = dateToYmd($date);
    if ($n === '' && $d === '') return '';
    return $n . '|' . $d;
}


/**
 * Dátum normalizálása 'YYYY-MM-DD' alakra, többféle bemenetből (DateTime,
 * Excel-sorszám, '2025.08.27 11:04:00' szöveg stb.).
 */
function dateToYmd($value): string
{
    if ($value === null || $value === '') return '';
    if ($value instanceof \DateTimeInterface) {
        return $value->format('Y-m-d');
    }
    if (is_numeric($value)) {
        try {
            $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value);
            return $dt->format('Y-m-d');
        } catch (\Throwable $e) { /* fallthrough */ }
    }
    $s = (string) $value;
    if (preg_match('/^(\d{4})[\.\-\/](\d{1,2})[\.\-\/](\d{1,2})/', $s, $m)) {
        return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
    }
    $ts = strtotime($s);
    return $ts !== false ? date('Y-m-d', $ts) : $s;
}


/**
 * Egy oszlop értékkészlete fejlécnév alapján (a Számla-ellenőrzéshez:
 * mely páciens-azonosítókhoz tartozik számla).
 *
 * @return array normalizált kulcs => true
 */

/**
 * Mint a buildLookupSet, de a soron belül több oszlopot olvas: ha az ID üres,
 * a (név, dátum) párból 'nev|YYYY-MM-DD' alakú pótkulcsot generál a halmazba.
 * Így az azonosító nélküli páciensek is összeköthetők a vizsgálatok-fájl
 * ugyanúgy generált pótkulcsával.
 *
 * @return array normalizált kulcs => true
 */
function buildLookupSetWithFallback(string $file, string $sheetName,
                                    string $idHeader, string $nameHeader,
                                    string $dateHeader): array
{
    $reader = IOFactory::createReader('Xlsx');
    $reader->setReadDataOnly(true);
    $book  = $reader->load($file);
    $sheet = $book->getSheetByName($sheetName) ?? $book->getActiveSheet();

    $lastColIdx = Coordinate::columnIndexFromString($sheet->getHighestColumn());
    $colOf = [];
    for ($c = 1; $c <= $lastColIdx; $c++) {
        $h = $sheet->getCell(Coordinate::stringFromColumnIndex($c) . '1')->getValue();
        if ($h !== null) $colOf[(string) $h] = $c;
    }
    foreach ([$idHeader, $nameHeader, $dateHeader] as $h) {
        if (!isset($colOf[$h])) {
            fwrite(STDERR, "HIBA: '{$h}' oszlop nincs meg itt:\n  {$file}\n");
            exit(1);
        }
    }
    $iId   = $colOf[$idHeader]   - 1;
    $iName = $colOf[$nameHeader] - 1;
    $iDate = $colOf[$dateHeader] - 1;

    $endLetter = Coordinate::stringFromColumnIndex($lastColIdx);
    $last      = $sheet->getHighestRow();
    $rows      = $sheet->rangeToArray("A2:{$endLetter}{$last}", null, false, false, false);

    $set = [];
    foreach ($rows as $r) {
        $k = normKey($r[$iId] ?? null);
        if ($k === '') $k = makePatientKey($r[$iName] ?? null, $r[$iDate] ?? null);
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
    $last = $sheet->getHighestRow();
    $rows = $sheet->rangeToArray("B3:B{$last}", null, false, false, false);
    $set = [];
    foreach ($rows as $r) {
        $k = normKey($r[0] ?? null);
        if ($k !== '') $set[$k] = true;
    }
    return $set;
}
