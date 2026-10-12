<?php
session_start();
require 'db.php';
require 'obracun.php';

if (!isset($_SESSION['korisnik_id']) || $_SESSION['uloga_id'] == 3) {
    header("Location: dashboard.php");
    exit;
}

$korisnik_id = $_SESSION['korisnik_id'];

// Dohvat opcija filtriranja
$zaposlenik_filter = isset($_GET['zaposlenik_filter']) ? (int)$_GET['zaposlenik_filter'] : 0;

if (!isset($_GET['datum_od']) && !isset($_GET['datum_do'])) {
    $datum_od = date('Y-m-01');
    $datum_do = date('Y-m-t');
} else {
    $datum_od = $_GET['datum_od'];
    $datum_do = $_GET['datum_do'];
}

// Popis svih zaposlenika za padajući izbornik
$svi_zaposlenici = $pdo->query("SELECT id, ime, prezime FROM korisnici ORDER BY prezime, ime")->fetchAll();

// Gradnja SQL upita za odobrene naloge
$sql = "SELECT pn.*, k.ime, k.prezime 
        FROM putni_nalozi pn 
        JOIN korisnici k ON pn.zaposlenik_id = k.id 
        WHERE pn.status = 'Odobreno'";
$params = [];

if ($zaposlenik_filter > 0) {
    $sql .= " AND pn.zaposlenik_id = ?";
    $params[] = $zaposlenik_filter;
}
if (!empty($datum_od)) {
    $sql .= " AND pn.datum_polaska >= ?";
    $params[] = $datum_od;
}
if (!empty($datum_do)) {
    $sql .= " AND pn.datum_polaska <= ?";
    $params[] = $datum_do;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$odobreni_nalozi = $stmt->fetchAll();

// Dohvat pojedinačnih troškova po nalogu
$stmt_troskovi = $pdo->query("SELECT * FROM troskovi_naloga");
$all_troskovi = $stmt_troskovi->fetchAll();

$troskovi_by_nalog = [];
foreach ($all_troskovi as $tr) {
    $troskovi_by_nalog[$tr['nalog_id']][] = $tr;
}

// Inicijalizacija zbrojeva
$total_naloga = count($odobreni_nalozi);
$total_km = 0;
$total_trosak_firme = 0;

$moji_ukupno_km = 0;
$moji_ukupni_trosak = 0;

$po_zaposleniku = [];

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
    $total_km += $km;
    $po_zaposleniku[$emp_id]['ukupno_km'] += $km;
    $po_zaposleniku[$emp_id]['broj_naloga'] += 1;
    
    $n_troskovi = isset($troskovi_by_nalog[$n['id']]) ? $troskovi_by_nalog[$n['id']] : [];
    $obracun = izracunajObracun($n, $n_troskovi);
    
    $total_trosak_firme += $obracun['ukupni_trosak'];
    $po_zaposleniku[$emp_id]['ukupni_trosak'] += $obracun['ukupni_trosak'];

    if ($emp_id == $korisnik_id) {
        $moji_ukupno_km += $km;
        $moji_ukupni_trosak += $obracun['ukupni_trosak'];
    }
}

usort($po_zaposleniku, function($a, $b) {
    return $b['ukupni_trosak'] <=> $a['ukupni_trosak'];
});

// Naslov razdoblja
$prikaz_razdoblja = "CJELOKUPNO RAZDOBLJE (SVI PODACI)";
if (!empty($datum_od) && !empty($datum_do)) {
    $prikaz_razdoblja = "RAZDOBLJE: " . date('d.m.Y.', strtotime($datum_od)) . " - " . date('d.m.Y.', strtotime($datum_do));
} elseif (!empty($datum_od)) {
    $prikaz_razdoblja = "RAZDOBLJE: OD " . date('d.m.Y.', strtotime($datum_od));
} elseif (!empty($datum_do)) {
    $prikaz_razdoblja = "RAZDOBLJE: DO " . date('d.m.Y.', strtotime($datum_do));
}
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Izvještaji - Evidencija putnih naloga</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%231e3a8a'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'/></svg>">
    <link rel="stylesheet" href="style.css">
    <style>
        .stats-grid {
            display: grid;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .grid-2 { grid-template-columns: repeat(2, 1fr); }
        .grid-3 { grid-template-columns: repeat(3, 1fr); }
        
        .stat-box {
            background: #ffffff;
            border: 1px solid var(--color-border);
            padding: 1.5rem;
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--color-primary);
            margin-bottom: 0.5rem;
        }
        
        .filter-container {
            background-color: #ffffff;
            border: 1px solid #000000;
            border-top: 4px solid #0d1e2d;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .filter-label {
            display: block;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #444444;
            margin-bottom: 0.4rem;
        }
        .modern-input, .modern-date {
            padding: 0.6rem 0.8rem;
            border: 2px solid #a0a0a0;
            background-color: #fcfcfc;
            font-size: 0.95rem;
            font-weight: bold;
            color: #111111;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
        }
        .modern-input { min-width: 170px; }
        .modern-date { appearance: none; -webkit-appearance: none; width: 170px; }
        .modern-input:focus, .modern-input:hover,
        .modern-date:focus, .modern-date:hover {
            outline: none;
            border-color: #0055a5;
            background-color: #ffffff;
        }
        .filter-info-bar {
            background-color: #eef7f0;
            border: 1px solid #badbcc;
            padding: 0.75rem;
            margin-bottom: 1rem;
            font-weight: bold;
            color: #0f5132;
            text-align: center;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>

<div class="navbar">
    <h1>Evidencija putnih naloga</h1>
    <div class="navbar-user">
        <a href="dashboard.php" class="nav-btn">Nadzorna ploča</a>
        <?php if ($_SESSION['uloga_id'] == 1 || $_SESSION['uloga_id'] == 2): ?>
            <a href="izvjestaji.php" class="nav-btn">Izvještaji</a>
        <?php endif; ?>
        <?php if ($_SESSION['uloga_id'] == 1): ?>
            <a href="admin_korisnici.php" class="nav-btn">Upravljaj profilima</a>
        <?php endif; ?>
        <span class="user-badge"><?= htmlspecialchars($_SESSION['ime'] . ' ' . $_SESSION['prezime']) ?> (<?= htmlspecialchars($_SESSION['uloga_naziv']) ?>)</span>
        <a href="logout.php" class="nav-btn nav-btn-logout">Odjava</a>
    </div>
</div>

<div class="container">

    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1rem;">
        <div>
            <h2 style="margin: 0; padding: 0; text-transform: uppercase; font-size: 1.4rem; color: #0d1e2d;">Financijski izvještaji</h2>
            <p style="font-size: 0.85rem; color: #444; margin: 0.25rem 0 0 0;">Analitika stvarnih troškova temeljem isključivo ODOBRENIH putnih naloga.</p>
        </div>
    </div>

    <div class="filter-info-bar">
        <?= $prikaz_razdoblja ?>
    </div>

    <!-- FILTER BLOK -->
    <div class="filter-container">
        <form method="GET" action="izvjestaji.php" style="display: flex; gap: 1.5rem; align-items: flex-end; flex-wrap: wrap;">
            <div>
                <label class="filter-label">Zaposlenik:</label>
                <select name="zaposlenik_filter" class="modern-input">
                    <option value="0">Svi zaposlenici</option>
                    <?php foreach ($svi_zaposlenici as $z): ?>
                        <option value="<?= $z['id'] ?>" <?= $zaposlenik_filter == $z['id'] ? 'selected' : '' ?>><?= htmlspecialchars($z['ime'] . ' ' . $z['prezime']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="filter-label">Od datuma:</label>
                <input type="date" name="datum_od" value="<?= htmlspecialchars($datum_od) ?>" class="modern-date">
            </div>
            <div>
                <label class="filter-label">Do datuma:</label>
                <input type="date" name="datum_do" value="<?= htmlspecialchars($datum_do) ?>" class="modern-date">
            </div>
            
            <div style="margin-left: auto; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <button type="submit" class="btn" style="height: 38px; padding: 0 1.2rem;">Generiraj</button>
                <a href="izvjestaji.php?zaposlenik_filter=0&datum_od=&datum_do=" class="btn" style="height: 38px; background:#64748b; border-color:#475569; padding: 0 1.2rem; line-height: 36px;">Svi podaci</a>
                <a href="export_izvjestaj_csv.php?tip=filtrirano&zaposlenik_filter=<?= $zaposlenik_filter ?>&datum_od=<?= urlencode($datum_od) ?>&datum_do=<?= urlencode($datum_do) ?>" class="btn btn-success" style="height: 38px; padding: 0 1.2rem; line-height: 36px;">Izvezi troškove tvrtke / filtrirano (CSV)</a>
            </div>
        </form>
    </div>

    <!-- MOJA OSOBNA POTROŠNJA S PREMJEŠTENIM GUMBOM -->
    <div class="card" style="border: 2px solid #0055a5;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid #e0e0e0; padding-bottom: 0.5rem;">
            <h2 style="margin: 0; color: #0055a5; font-size: 1rem; text-transform: uppercase; border: none; padding: 0;">Moja osobna potrošnja u odabranom razdoblju</h2>
            <a href="export_izvjestaj_csv.php?tip=moji&datum_od=<?= urlencode($datum_od) ?>&datum_do=<?= urlencode($datum_do) ?>" class="btn btn-success" style="height: 34px; padding: 0 1rem; line-height: 32px;">Izvezi moje troškove (CSV)</a>
        </div>
        
        <div class="stats-grid grid-2" style="margin-bottom: 0;">
            <div class="stat-box" style="padding: 1rem; border: none; border-right: 1px solid #e0e0e0;">
                <div class="stat-number" style="font-size: 1.6rem; color: #1e3a8a;"><?= number_format($moji_ukupno_km, 0, ',', '.') ?> km</div>
                <div style="font-size: 0.85rem; color: #444; font-weight: bold; text-transform: uppercase;">Moji prijeđeni kilometri</div>
            </div>
            <div class="stat-box" style="padding: 1rem; border: none;">
                <div class="stat-number" style="font-size: 1.6rem; color: #1e3a8a;"><?= number_format($moji_ukupni_trosak, 2, ',', '.') ?> €</div>
                <div style="font-size: 0.85rem; color: #444; font-weight: bold; text-transform: uppercase;">Moj generirani trošak</div>
            </div>
        </div>
    </div>

    <!-- GLAVNI BROJEVI -->
    <h3 style="text-transform: uppercase; font-size: 1.1rem; border-bottom: 2px solid #000; padding-bottom: 0.3rem; margin-top: 2rem;">
        <?= $zaposlenik_filter > 0 ? "Prikaz potrošnje za odabranog zaposlenika" : "Zbirna potrošnja cijele tvrtke" ?>
    </h3>
    <div class="stats-grid grid-3">
        <div class="stat-box" style="border-top: 4px solid #0d1e2d;">
            <div class="stat-number"><?= $total_naloga ?></div>
            <div style="font-size: 0.95rem; color: #444444; font-weight: bold; text-transform: uppercase;">Odobrena putovanja</div>
        </div>
        <div class="stat-box" style="border-top: 4px solid #8a6200;">
            <div class="stat-number" style="color: #8a6200;"><?= number_format($total_km, 0, ',', '.') ?> km</div>
            <div style="font-size: 0.95rem; color: #444444; font-weight: bold; text-transform: uppercase;">Prijeđeni kilometri</div>
        </div>
        <div class="stat-box" style="border-top: 4px solid #1e6b2d;">
            <div class="stat-number" style="color: #1e6b2d;"><?= number_format($total_trosak_firme, 2, ',', '.') ?> €</div>
            <div style="font-size: 0.95rem; color: #444444; font-weight: bold; text-transform: uppercase;">Ukupni trošak</div>
        </div>
    </div>

    <!-- TABLICA PO ZAPOSLENICIMA -->
    <div class="card">
        <h2>Detaljna potrošnja po zaposlenicima</h2>
        <?php if (count($po_zaposleniku) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Zaposlenik</th>
                        <th>Broj odobrenih naloga</th>
                        <th>Prijeđeni kilometri</th>
                        <th>Generirani trošak (Dnevnice + Računi)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($po_zaposleniku as $z): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($z['ime'] . ' ' . $z['prezime']) ?></strong></td>
                            <td><?= $z['broj_naloga'] ?> naloga</td>
                            <td><?= number_format($z['ukupno_km'], 0, ',', '.') ?> km</td>
                            <td><strong><?= number_format($z['ukupni_trosak'], 2, ',', '.') ?> €</strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="border: 1px solid #b0b0b0; padding: 1rem; background-color: #f9f9f9;">U odabranom razdoblju nema odobrenih putnih naloga za odabrane kriterije.</p>
        <?php endif; ?>
    </div>

</div>

<footer class="footer">
    <div class="footer-links">
        <a href="privatnost.php">Politika privatnosti</a> | 
        <a href="uvjeti.php">Uvjeti korištenja</a>
    </div>
    <div>Evidencija putnih naloga &copy; 2026</div>
</footer>

</body>
</html>