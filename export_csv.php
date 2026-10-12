<?php
session_start();
require_once 'db.php';
require_once 'obracun.php';

if (!isset($_SESSION['korisnik_id'])) {
    header("Location: login.php");
    exit;
}

$korisnik_id = $_SESSION['korisnik_id'];
$uloga_id = $_SESSION['uloga_id'];

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'svi';
$zaposlenik_filter = isset($_GET['zaposlenik_filter']) ? (int)$_GET['zaposlenik_filter'] : 0;
$datum_od = isset($_GET['datum_od']) ? $_GET['datum_od'] : '';
$datum_do = isset($_GET['datum_do']) ? $_GET['datum_do'] : '';

$sql = "SELECT pn.*, k.ime, k.prezime 
        FROM putni_nalozi pn 
        JOIN korisnici k ON pn.zaposlenik_id = k.id ";

$params = [];
$where = [];

if ($uloga_id == 3) {
    $where[] = "pn.zaposlenik_id = ?";
    $params[] = $korisnik_id;
} else {
    if ($zaposlenik_filter > 0) {
        $where[] = "pn.zaposlenik_id = ?";
        $params[] = $zaposlenik_filter;
    }
}

if ($filter === 'cekanje') {
    $where[] = "pn.status = 'Na čekanju'";
} elseif ($filter === 'odobreno') {
    $where[] = "pn.status = 'Odobreno'";
} elseif ($filter === 'odbijeno') {
    $where[] = "pn.status = 'Odbijeno'";
}

if (!empty($datum_od)) {
    $where[] = "pn.datum_polaska >= ?";
    $params[] = $datum_od;
}
if (!empty($datum_do)) {
    $where[] = "pn.datum_polaska <= ?";
    $params[] = $datum_do;
}

if (count($where) > 0) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY pn.datum_kreiranja DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$nalozi = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dohvat svih pojedinačnih troškova po nalozi
$stmt_troskovi = $pdo->query("SELECT * FROM troskovi_naloga");
$svi_troskovi = $stmt_troskovi->fetchAll(PDO::FETCH_ASSOC);

$troskovi_po_nalogu = [];
foreach ($svi_troskovi as $tr) {
    $troskovi_po_nalogu[$tr['nalog_id']][] = $tr;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=putni_nalozi_izvoz_' . date('Ymd_His') . '.csv');

$output = fopen('php://output', 'w');
fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM za Excel

// Zaglavlje tablice sa svim obračunskim stavkama
fputcsv($output, [
    'ID Naloga', 
    'Zaposlenik', 
    'Polazište', 
    'Odredište', 
    'Svrha putovanja', 
    'Datum polaska', 
    'Datum povratka', 
    'Status', 
    'Akontacija (€)', 
    'Ukupni trošak (€)', 
    'Saldo za isplatu/povrat (€)'
], ';');

foreach ($nalozi as $row) {
    $troskovi_naloga = isset($troskovi_po_nalogu[$row['id']]) ? $troskovi_po_nalogu[$row['id']] : [];
    $obracun = izracunajObracun($row, $troskovi_naloga);

    fputcsv($output, [
        $row['id'],
        $row['ime'] . ' ' . $row['prezime'],
        $row['polaziste'],
        $row['odrediste'],
        $row['svrha'],
        date('d.m.Y.', strtotime($row['datum_polaska'])),
        date('d.m.Y.', strtotime($row['datum_povratka'])),
        $row['status'],
        number_format($row['akontacija'], 2, ',', ''),
        number_format($obracun['ukupni_trosak'], 2, ',', ''),
        number_format($obracun['saldo'], 2, ',', '')
    ], ';');
}

fclose($output);
exit;
?>