<?php
session_start();
require 'db.php';

if (!isset($_SESSION['korisnik_id'])) {
    header("Location: login.php");
    exit;
}

$poruka = '';

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
    $zaposlenik_id    = $_SESSION['korisnik_id'];

    if (!empty($polaziste) && !empty($odrediste) && !empty($svrha) && !empty($datum_polaska) && !empty($datum_povratka)) {
        if (strtotime($datum_povratka) < strtotime($datum_polaska)) {
            $poruka = "Datum povratka ne može biti prije datuma polaska.";
        } else {
            $sql = "INSERT INTO putni_nalozi 
                    (zaposlenik_id, polaziste, odrediste, svrha, vrsta_prijevoza, datum_polaska, vrijeme_polaska, datum_povratka, vrijeme_povratka, pocetna_km, zavrsna_km, osiguran_dorucak, osiguran_rucak, osiguran_veceri, akontacija) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $uspjeh = $stmt->execute([
                $zaposlenik_id, $polaziste, $odrediste, $svrha, $vrsta_prijevoza,
                $datum_polaska, $vrijeme_polaska, $datum_povratka, $vrijeme_povratka,
                $pocetna_km, $zavrsna_km, $osiguran_dorucak, $osiguran_rucak, $osiguran_veceri, $akontacija
            ]);

            if ($uspjeh) {
                header("Location: dashboard.php");
                exit;
            } else {
                $poruka = "Greška prilikom spremanja naloga u bazu.";
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
    <title>Novi putni nalog - Evidencija</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="navbar">
    <h1>Evidencija putnih naloga</h1>
    <div class="navbar-user">
        <a href="dashboard.php" class="nav-btn">Nadzorna ploča</a>
        <a href="logout.php" class="nav-btn nav-btn-logout">Odjava</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <h2>Novi službeni putni nalog</h2>
        
        <?php if ($poruka): ?>
            <p style="color: #991b1b; background: #fef2f2; padding: 0.5rem; border: 1px solid #fecaca; border-radius: 4px;"><?= htmlspecialchars($poruka) ?></p>
        <?php endif; ?>

        <form method="POST" action="kreiraj_nalog.php">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="polaziste">Polazište:*</label>
                    <input type="text" id="polaziste" name="polaziste" required placeholder="npr. Slavonski Brod">
                </div>
                <div class="form-group">
                    <label for="odrediste">Odredište:*</label>
                    <input type="text" id="odrediste" name="odrediste" required placeholder="npr. Zagreb">
                </div>
                <div class="form-group">
                    <label for="vrsta_prijevoza">Vrsta prijevoza:*</label>
                    <select id="vrsta_prijevoza" name="vrsta_prijevoza" required>
                        <option value="Privatno vozilo">Privatno vozilo (0.50 €/km)</option>
                        <option value="Službeno vozilo">Službeno vozilo</option>
                        <option value="Javni prijevoz">Javni prijevoz</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="svrha">Svrha službenog putovanja:*</label>
                <textarea id="svrha" name="svrha" rows="2" required></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="datum_polaska">Datum polaska:*</label>
                    <input type="date" id="datum_polaska" name="datum_polaska" required>
                </div>
                <div class="form-group">
                    <label for="vrijeme_polaska">Točno vrijeme polaska:*</label>
                    <input type="time" id="vrijeme_polaska" name="vrijeme_polaska" value="08:00" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="datum_povratka">Datum povratka:*</label>
                    <input type="date" id="datum_povratka" name="datum_povratka" required>
                </div>
                <div class="form-group">
                    <label for="vrijeme_povratka">Točno vrijeme povratka:*</label>
                    <input type="time" id="vrijeme_povratka" name="vrijeme_povratka" value="17:00" required>
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--color-border); margin: 1.5rem 0;">
            <h3 style="font-size: 1rem; margin-bottom: 1rem;">Obračun kilometraže (ako se koristi privatno vozilo)</h3>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="pocetna_km">Početno stanje brojača (km):</label>
                    <input type="number" id="pocetna_km" name="pocetna_km" value="0">
                </div>
                <div class="form-group">
                    <label for="zavrsna_km">Završno stanje brojača (km):</label>
                    <input type="number" id="zavrsna_km" name="zavrsna_km" value="0">
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--color-border); margin: 1.5rem 0;">
            <h3 style="font-size: 1rem; margin-bottom: 1rem;">Osigurani besplatni obroci na putu</h3>

            <div style="display: flex; gap: 2rem; margin-bottom: 1.5rem;">
                <label><input type="checkbox" name="osiguran_dorucak" value="1"> Osiguran doručak (-20%)</label>
                <label><input type="checkbox" name="osiguran_rucak" value="1"> Osiguran ručak (-30%)</label>
                <label><input type="checkbox" name="osiguran_veceri" value="1"> Osigurana večera (-30%)</label>
            </div>

            <div class="form-group">
                <label for="akontacija">Isplaćena akontacija (predujam u €):</label>
                <input type="number" step="0.01" id="akontacija" name="akontacija" value="0.00">
            </div>

            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn">Spremi i pošalji nalog</button>
                <a href="dashboard.php" class="btn" style="background-color: #64748b; border-color: #64748b;">Odustani</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>