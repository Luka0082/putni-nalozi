<?php
session_start();
require_once 'db.php';
require_once 'obracun.php';

if (!isset($_SESSION['korisnik_id']) || $_SESSION['uloga_id'] == 3) {
    header("Location: dashboard.php");
    exit;
}

$korisnik_id = $_SESSION['korisnik_id'];
$tip = isset($_GET['tip']) ? $_GET['tip'] : 'filtrirano';
$zaposlenik_filter = isset($_GET['zaposlenik_filter']) ? (int)$_GET['zaposlenik_filter'] : 0;

if (!isset($_GET['datum_od']) && !isset($_GET['datum_do'])) {
    $datum_od = date('Y-m-01');
    $datum_do = date('Y-m-t');
} else {
    $datum_od = $_GET['datum_od'];
    $datum_do = $_GET['datum_do'];
}

$prikaz_razdoblja = "Cjelokupno razdoblje (Svi podaci)";
if (!empty($datum_od) && !empty($datum_do)) {
    $prikaz_razdoblja = date('d.m.Y.', strtotime($datum_od)) . " - " . date('d.m.Y.', strtotime($datum_do));
} elseif (!empty($datum_od)) {
    $prikaz_razdoblja = "Od " . date('d.m.Y.', strtotime($datum_od));
} elseif (!empty($datum_do)) {
    $prikaz_razdoblja = "Do " . date('d.m.Y.', strtotime($datum_do));
}

// Dohvat pojedinačnih troškova
$stmt_troskovi = $pdo->query("SELECT * FROM troskovi_naloga");
$all_troskovi = $stmt_troskovi->fetchAll();

$troskovi_by_nalog = [];
foreach ($all_troskovi as $tr) {
    $troskovi_by_nalog[$tr['nalog_id']][] = $tr;
}

header('Content-Type: text/csv; charset=utf-8');

// ==========================================
// OPCIJA 1: IZVOZ SAMO MOJIH OSOBNIH TROŠKOVA
// ==========================================
if ($tip === 'moji') {
    header('Content-Disposition: attachment; filename=moj_izvjestaj_troskova_' . date('Ymd_His') . '.csv');

    $sql = "SELECT pn.*, k.ime, k.prezime 
            FROM putni_nalozi pn 
            JOIN korisnici k ON pn.zaposlenik_id = k.id 
            WHERE pn.status = 'Odobreno' 
            AND pn.zaposlenik_id = ?";
    $params = [$korisnik_id];

    if (!empty($datum_od)) { $sql .= " AND pn.datum_polaska >= ?"; $params[] = $datum_od; }
    if (!empty($datum_do)) { $sql .= " AND pn.datum_polaska <= ?"; $params[] = $datum_do; }

    $sql .= " ORDER BY pn.datum_polaska DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $moji_nalozi = $stmt->fetchAll();

    $output = fopen('php://output', 'w');
    fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($output, ['IZVJESTAJ O MOJOJ POTROSNJI'], ';');
    fputcsv($output, ['Korisnik:', $_SESSION['ime'] . ' ' . $_SESSION['prezime']], ';');
    fputcsv($output, ['Razdoblje:', $prikaz_razdoblja], ';');
    fputcsv($output, [], ';');

    fputcsv($output, ['ID Naloga', 'Relacija', 'Svrha putovanja', 'Datum polaska', 'Datum povratka', 'Neto dnevnice (EUR)', 'Kilometraža (EUR)', 'Ostali troškovi (EUR)', 'Ukupni trošak (EUR)'], ';');

    $zbroj_trosak = 0;
    foreach ($moji_nalozi as $n) {
        $n_troskovi = isset($troskovi_by_nalog[$n['id']]) ? $troskovi_by_nalog[$n['id']] : [];
        $obracun = izracunajObracun($n, $n_troskovi);
        $relacija = !empty($n['polaziste']) ? $n['polaziste'] . ' - ' . $n['odrediste'] : $n['odrediste'];
        $zbroj_trosak += $obracun['ukupni_trosak'];

        fputcsv($output, [
            $n['id'],
            $relacija,
            $n['svrha'],
            date('d.m.Y.', strtotime($n['datum_polaska'])),
            date('d.m.Y.', strtotime($n['datum_povratka'])),
            number_format($obracun['neto_dnevnice'], 2, ',', ''),
            number_format($obracun['iznos_km'], 2, ',', ''),
            number_format($obracun['iznos_ostali_troskovi'], 2, ',', ''),
            number_format($obracun['ukupni_trosak'], 2, ',', '')
        ], ';');
    }

    fputcsv($output, [], ';');
    fputcsv($output, ['UKUPNO MOJI TROSKOVI', '', '', '', '', '', '', '', number_format($zbroj_trosak, 2, ',', '') . ' EUR'], ';');

    fclose($output);
    exit;
}

// ==========================================
// OPCIJA 2: IZVOZ FILTRIRANIH / SVIH TROŠKOVA (TVRTKA / ODABRANI ZAPOSLENIK)
// ==========================================
header('Content-Disposition: attachment; filename=izvjestaj_troskova_' . date('Ymd_His') . '.csv');

$sql = "SELECT pn.*, k.ime, k.prezime 
        FROM putni_nalozi pn 
        JOIN korisnici k ON pn.zaposlenik_id = k.id 
        WHERE pn.status = 'Odobreno'";
$params = [];

if ($zaposlenik_filter > 0) {
    $sql .= " AND pn.zaposlenik_id = ?";
    $params[] = $zaposlenik_filter;
}
if (!empty($datum_od)) { $sql .= " AND pn.datum_polaska >= ?"; $params[] = $datum_od; }
if (!empty($datum_do)) { $sql .= " AND pn.datum_polaska <= ?"; $params[] = $datum_do; }

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$odobreni_nalozi = $stmt->fetchAll();

$po_zaposleniku = [];
$total_trosak_firme = 0;
$total_km_firme = 0;

foreach ($odobreni_nalozi as $n) {
    $emp_id = $n['zaposlenik_id'];

    if (!isset($po_zaposleniku[$emp_id])) {
        $po_zaposleniku[$emp_id] = [
            'ime' => $n['ime'],
            'prezime' => $n['prezime'],
            'broj_naloga' => 0,
            'ukupno_km' => 0,
            'ukupni_trosak' => 0
        ];
    }

    $km = max(0, $n['zavrsna_km'] - $n['pocetna_km']);
    $po_zaposleniku[$emp_id]['ukupno_km'] += $km;
    $po_zaposleniku[$emp_id]['broj_naloga'] += 1;
    $total_km_firme += $km;

    $n_troskovi = isset($troskovi_by_nalog[$n['id']]) ? $troskovi_by_nalog[$n['id']] : [];
    $obracun = izracunajObracun($n, $n_troskovi);

    $po_zaposleniku[$emp_id]['ukupni_trosak'] += $obracun['ukupni_trosak'];
    $total_trosak_firme += $obracun['ukupni_trosak'];
}

usort($po_zaposleniku, function($a, $b) {
    return $b['ukupni_trosak'] <=> $a['ukupni_trosak'];
});

$output = fopen('php://output', 'w');
fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($output, ['IZVJESTAJ O TROSKOVIMA ORGANIZACIJE'], ';');
fputcsv($output, ['Razdoblje:', $prikaz_razdoblja], ';');
fputcsv($output, [], ';');

fputcsv($output, ['Zaposlenik', 'Broj odobrenih naloga', 'Prijeđeni kilometri (km)', 'Ukupni stvarni trošak (EUR)'], ';');

foreach ($po_zaposleniku as $z) {
    fputcsv($output, [
        $z['ime'] . ' ' . $z['prezime'],
        $z['broj_naloga'],
        $z['ukupno_km'],
        number_format($z['ukupni_trosak'], 2, ',', '')
    ], ';');
}

fputcsv($output, [], ';');
fputcsv($output, ['SVEUKUPNO', count($odobreni_nalozi) . ' naloga', $total_km_firme . ' km', number_format($total_trosak_firme, 2, ',', '') . ' EUR'], ';');

fclose($output);
exit;
?>