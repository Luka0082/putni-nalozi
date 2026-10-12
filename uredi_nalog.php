<?php
session_start();
require 'db.php';

if (!isset($_SESSION['korisnik_id'])) {
    header("Location: login.php");
    exit;
}

$korisnik_id = $_SESSION['korisnik_id'];
$nalog_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$poruka = '';

$stmt = $pdo->prepare("SELECT * FROM putni_nalozi WHERE id = ? AND zaposlenik_id = ? AND status = 'Na čekanju'");
$stmt->execute([$nalog_id, $korisnik_id]);
$nalog = $stmt->fetch();

if (!$nalog) {
    die("Nalog ne postoji, nemate ovlasti za uređivanje ili je već obrađen.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $polaziste        = trim($_POST['polaziste']);
    $odrediste        = trim($_POST['odrediste']);
    $svrha            = trim($_POST['svrha']);
    $vrsta_prijevoza  = $_POST['vrsta_prijevoza'];
    $datum_polaska    = $_POST['datum_polaska'];
    $vrijeme_polaska  = $_POST['vrijeme_polaska'];
    $datum_povratka   = $_POST['datum_povratka'];
    $vrijeme_povratka = $_POST['vrijeme_povratka'];
    $pocetna_km       = !empty($_POST['pocetna_km']) ? (int)$_POST['pocetna_km'] : 0;
    $zavrsna_km       = !empty($_POST['zavrsna_km']) ? (int)$_POST['zavrsna_km'] : 0;
    $osiguran_dorucak = isset($_POST['osiguran_dorucak']) ? 1 : 0;
    $osiguran_rucak   = isset($_POST['osiguran_rucak']) ? 1 : 0;
    $osiguran_veceri  = isset($_POST['osiguran_veceri']) ? 1 : 0;
    $akontacija       = !empty($_POST['akontacija']) ? (float)$_POST['akontacija'] : 0.00;

    if (!empty($polaziste) && !empty($odrediste) && !empty($svrha) && !empty($datum_polaska) && !empty($datum_povratka)) {
        if (strtotime($datum_povratka) < strtotime($datum_polaska)) {
            $poruka = "Datum povratka ne može biti prije datuma polaska.";
        } else {
            $sql = "UPDATE putni_nalozi SET 
                    polaziste = ?,
                    odrediste = ?, 
                    svrha = ?, 
                    vrsta_prijevoza = ?, 
                    datum_polaska = ?, 
                    vrijeme_polaska = ?, 
                    datum_povratka = ?, 
                    vrijeme_povratka = ?, 
                    pocetna_km = ?, 
                    zavrsna_km = ?, 
                    osiguran_dorucak = ?, 
                    osiguran_rucak = ?, 
                    osiguran_veceri = ?, 
                    akontacija = ? 
                    WHERE id = ? AND zaposlenik_id = ? AND status = 'Na čekanju'";
            
            $update_stmt = $pdo->prepare($sql);
            $uspjeh = $update_stmt->execute([
                $polaziste, $odrediste, $svrha, $vrsta_prijevoza,
                $datum_polaska, $vrijeme_polaska, $datum_povratka, $vrijeme_povratka,
                $pocetna_km, $zavrsna_km, $osiguran_dorucak, $osiguran_rucak, $osiguran_veceri, $akontacija,
                $nalog_id, $korisnik_id
            ]);

            if ($uspjeh) {
                header("Location: dashboard.php");
                exit;
            } else {
                $poruka = "Greška pri ažuriranju naloga.";
            }
        }
    } else {
        $poruka = "Sva obvezna polja moraju biti popunjena.";
    }
}
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uredi putni nalog br. <?= $nalog['id'] ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%231e3a8a'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'/></svg>">
    <link rel="stylesheet" href="style.css">
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
    <div class="card">
        <h2>Uredi putni nalog br. <?= $nalog['id'] ?></h2>
        
        <?php if ($poruka): ?>
            <p style="color: #991b1b; background: #fef2f2; padding: 0.5rem; border: 1px solid #fecaca; border-radius: 4px;"><?= htmlspecialchars($poruka) ?></p>
        <?php endif; ?>

        <form method="POST" action="uredi_nalog.php?id=<?= $nalog['id'] ?>">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="polaziste">Polazište:*</label>
                    <input type="text" id="polaziste" name="polaziste" value="<?= htmlspecialchars($nalog['polaziste']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="odrediste">Odredište:*</label>
                    <input type="text" id="odrediste" name="odrediste" value="<?= htmlspecialchars($nalog['odrediste']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="vrsta_prijevoza">Vrsta prijevoza:*</label>
                    <select id="vrsta_prijevoza" name="vrsta_prijevoza" required>
                        <option value="Privatno vozilo" <?= $nalog['vrsta_prijevoza'] === 'Privatno vozilo' ? 'selected' : '' ?>>Privatno vozilo (0.50 €/km)</option>
                        <option value="Službeno vozilo" <?= $nalog['vrsta_prijevoza'] === 'Službeno vozilo' ? 'selected' : '' ?>>Službeno vozilo</option>
                        <option value="Javni prijevoz" <?= $nalog['vrsta_prijevoza'] === 'Javni prijevoz' ? 'selected' : '' ?>>Javni prijevoz</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="svrha">Svrha službenog putovanja:*</label>
                <textarea id="svrha" name="svrha" rows="3" required><?= htmlspecialchars($nalog['svrha']) ?></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="datum_polaska">Datum polaska:*</label>
                    <input type="date" id="datum_polaska" name="datum_polaska" value="<?= $nalog['datum_polaska'] ?>" required>
                </div>
                <div class="form-group">
                    <label for="vrijeme_polaska">Točno vrijeme polaska:*</label>
                    <input type="time" id="vrijeme_polaska" name="vrijeme_polaska" value="<?= htmlspecialchars($nalog['vrijeme_polaska']) ?>" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="datum_povratka">Datum povratka:*</label>
                    <input type="date" id="datum_povratka" name="datum_povratka" value="<?= $nalog['datum_povratka'] ?>" required>
                </div>
                <div class="form-group">
                    <label for="vrijeme_povratka">Točno vrijeme povratka:*</label>
                    <input type="time" id="vrijeme_povratka" name="vrijeme_povratka" value="<?= htmlspecialchars($nalog['vrijeme_povratka']) ?>" required>
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--color-border); margin: 1.5rem 0;">
            <h3 style="font-size: 1rem; margin-bottom: 1rem;">Obračun kilometraže (ako se koristi privatno vozilo)</h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="pocetna_km">Početno stanje brojača (km):</label>
                    <input type="number" id="pocetna_km" name="pocetna_km" value="<?= (int)$nalog['pocetna_km'] ?>">
                </div>
                <div class="form-group">
                    <label for="zavrsna_km">Završno stanje brojača (km):</label>
                    <input type="number" id="zavrsna_km" name="zavrsna_km" value="<?= (int)$nalog['zavrsna_km'] ?>">
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--color-border); margin: 1.5rem 0;">
            <h3 style="font-size: 1rem; margin-bottom: 1rem;">Osigurani besplatni obroci na putu</h3>

            <div style="display: flex; gap: 2rem; margin-bottom: 1.5rem;">
                <label><input type="checkbox" name="osiguran_dorucak" value="1" <?= $nalog['osiguran_dorucak'] ? 'checked' : '' ?>> Osiguran doručak (-20%)</label>
                <label><input type="checkbox" name="osiguran_rucak" value="1" <?= $nalog['osiguran_rucak'] ? 'checked' : '' ?>> Osiguran ručak (-30%)</label>
                <label><input type="checkbox" name="osiguran_veceri" value="1" <?= $nalog['osiguran_veceri'] ? 'checked' : '' ?>> Osigurana večera (-30%)</label>
            </div>

            <div class="form-group">
                <label for="akontacija">Isplaćena akontacija (predujam u €):</label>
                <input type="number" step="0.01" id="akontacija" name="akontacija" value="<?= htmlspecialchars($nalog['akontacija']) ?>">
            </div>

            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn">Spremi izmjene</button>
                <a href="dashboard.php" class="btn" style="background-color: #64748b; border-color: #64748b;">Odustani</a>
            </div>
        </form>
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