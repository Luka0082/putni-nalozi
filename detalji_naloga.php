<?php
session_start();
require 'db.php';
require 'obracun.php';

if (!isset($_SESSION['korisnik_id'])) {
    header("Location: login.php");
    exit;
}

$nalog_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$korisnik_id = $_SESSION['korisnik_id'];
$uloga_id = $_SESSION['uloga_id'];

$stmt = $pdo->prepare("
    SELECT pn.*, k.ime AS zaposlenik_ime, k.prezime AS zaposlenik_prezime, k.nadredeni_id
    FROM putni_nalozi pn
    JOIN korisnici k ON pn.zaposlenik_id = k.id
    WHERE pn.id = ?
");
$stmt->execute([$nalog_id]);
$nalog = $stmt->fetch();

if (!$nalog) {
    die("Putni nalog nije pronađen.");
}

if ($nalog['zaposlenik_id'] != $korisnik_id && $nalog['nadredeni_id'] != $korisnik_id && $uloga_id != 1) {
    die("Nemate ovlasti za pregled ovog naloga.");
}

$poruka = '';
$greska = '';

// Brisanje pojedinačnog troška
if (isset($_GET['obrisi_trosak'])) {
    if ($nalog['status'] !== 'Na čekanju') {
        $greska = "Nalog je obrađen. Brisanje troškova nije dopušteno.";
    } else {
        $trosak_id = (int)$_GET['obrisi_trosak'];
        
        $stmt_f = $pdo->prepare("SELECT putanja_racuna FROM troskovi_naloga WHERE id = ? AND nalog_id = ?");
        $stmt_f->execute([$trosak_id, $nalog_id]);
        $f = $stmt_f->fetch();
        if ($f && !empty($f['putanja_racuna']) && file_exists($f['putanja_racuna'])) {
            unlink($f['putanja_racuna']);
        }

        $stmt_del = $pdo->prepare("DELETE FROM troskovi_naloga WHERE id = ? AND nalog_id = ?");
        $stmt_del->execute([$trosak_id, $nalog_id]);
        header("Location: detalji_naloga.php?id=" . $nalog_id);
        exit;
    }
}

// Unos novog troška s datotekom
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_trosak'])) {
    if ($nalog['status'] !== 'Na čekanju') {
        $greska = "Nalog je obrađen. Dodavanje novih troškova nije dopušteno.";
    } else {
        $vrsta_troska = trim($_POST['vrsta_troska']);
        $iznos = (float)$_POST['iznos'];
        $putanja_racuna = null;

        if (!empty($vrsta_troska) && $iznos > 0) {
            
            if (isset($_FILES['racun_file']) && $_FILES['racun_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['racun_file']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['racun_file']['error'] === UPLOAD_ERR_FORM_SIZE) {
                    $greska = "Slika je prevelika. Ograničenje servera blokira prijenos. Odaberite manju sliku.";
                } elseif ($_FILES['racun_file']['error'] !== UPLOAD_ERR_OK) {
                    $greska = "Greška pri prijenosu datoteke. Kod greške: " . $_FILES['racun_file']['error'];
                } else {
                    $fileTmpPath = $_FILES['racun_file']['tmp_name'];
                    $fileName = $_FILES['racun_file']['name'];
                    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    
                    $dozvoljene = ['jpg', 'jpeg', 'png', 'pdf'];
                    if (in_array($fileExtension, $dozvoljene)) {
                        if (!is_dir('./uploads')) {
                            mkdir('./uploads', 0777, true);
                            chmod('./uploads', 0777);
                        }

                        $novoIme = 'racun_' . $nalog_id . '_' . time() . '_' . rand(10, 99) . '.' . $fileExtension;
                        $destPutanja = './uploads/' . $novoIme;

                        if (move_uploaded_file($fileTmpPath, $destPutanja)) {
                            $putanja_racuna = $destPutanja;
                        } else {
                            $greska = "Datoteka je prenesena, ali server ju ne može spremiti. Provjerite ovlasti (Read/Write) za mapu 'uploads'.";
                        }
                    } else {
                        $greska = "Dozvoljeni formati računa su isključivo: JPG, PNG i PDF.";
                    }
                }
            }

            if (empty($greska)) {
                $stmt_trosak = $pdo->prepare("INSERT INTO troskovi_naloga (nalog_id, vrsta_troska, iznos, putanja_racuna) VALUES (?, ?, ?, ?)");
                $stmt_trosak->execute([$nalog_id, $vrsta_troska, $iznos, $putanja_racuna]);
                $poruka = "Trošak je uspješno dodan.";
            }
        } else {
            $greska = "Naziv troška i važeći iznos su obvezni.";
        }
    }
}

// Spremanje putnog izvješća
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['spremi_izvjesce'])) {
    if ($nalog['status'] !== 'Na čekanju') {
        $greska = "Nalog je obrađen. Izmjena izvješća nije dopuštena.";
    } else {
        $putno_izvjesce = trim($_POST['putno_izvjesce']);
        $stmt_izv = $pdo->prepare("UPDATE putni_nalozi SET putno_izvjesce = ? WHERE id = ?");
        $stmt_izv->execute([$putno_izvjesce, $nalog_id]);
        $nalog['putno_izvjesce'] = $putno_izvjesce;
        $poruka = "Putno izvješće je uspješno spremljeno.";
    }
}

$stmt_troskovi = $pdo->prepare("SELECT * FROM troskovi_naloga WHERE nalog_id = ?");
$stmt_troskovi->execute([$nalog_id]);
$troskovi = $stmt_troskovi->fetchAll();

$obracun = izracunajObracun($nalog, $troskovi);
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalji i Obračun Naloga br. <?= $nalog['id'] ?></title>
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

    <?php if ($poruka): ?>
        <div class="card" style="border-left: 4px solid #1e6b2d; background: #eef7f0;">
            <p style="margin:0; color: #1e6b2d; font-weight:bold;"><?= htmlspecialchars($poruka) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($greska): ?>
        <div class="card" style="border-left: 4px solid #a81919; background: #fdf5f5;">
            <p style="margin:0; color: #a81919; font-weight:bold;"><?= htmlspecialchars($greska) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($nalog['status'] !== 'Na čekanju'): ?>
        <div class="card" style="border-left: 4px solid #0055a5; background: #f0f7fd;">
            <p style="margin:0; color: #0055a5; font-weight:bold;">Ovaj putni nalog je zaključen (Status: <?= htmlspecialchars($nalog['status']) ?>). Dodavanje troškova i promjene nisu dopuštene.</p>
        </div>
    <?php endif; ?>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h2>Službeni putni nalog br. <?= $nalog['id'] ?></h2>
            <div style="display: flex; gap: 0.5rem;">
                <a href="print_nalog.php?id=<?= $nalog['id'] ?>" class="btn" style="background:#444444; border-color:#222222;">Ispis / PDF</a>
                <a href="dashboard.php" class="btn" style="background:#666666; border-color:#444444;">Natrag</a>
            </div>
        </div>

        <p><strong>Zaposlenik:</strong> <?= htmlspecialchars($nalog['zaposlenik_ime'] . ' ' . $nalog['zaposlenik_prezime']) ?></p>
        <p><strong>Relacija:</strong> <?= htmlspecialchars($nalog['polaziste']) ?> - <?= htmlspecialchars($nalog['odrediste']) ?></p>
        <p><strong>Svrha puta:</strong> <?= htmlspecialchars($nalog['svrha']) ?></p>
        <p><strong>Vrijeme puta:</strong> <?= date('d.m.Y. H:i', strtotime($nalog['datum_polaska'] . ' ' . $nalog['vrijeme_polaska'])) ?> do <?= date('d.m.Y. H:i', strtotime($nalog['datum_povratka'] . ' ' . $nalog['vrijeme_povratka'])) ?> (Ukupno: <?= $obracun['ukupno_sati'] ?> h)</p>
    </div>

    <div class="card">
        <h2>Obračun putnog naloga (Porezna uprava)</h2>
        <table>
            <thead>
                <tr>
                    <th>Stavka obračuna</th>
                    <th>Količina / Osnovica</th>
                    <th>Iznos (€)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Pune dnevnice (30.00 €)</td>
                    <td><?= $obracun['pune_dnevnice_broj'] ?> kom</td>
                    <td><?= number_format($obracun['pune_dnevnice_broj'] * 30, 2) ?> €</td>
                </tr>
                <tr>
                    <td>Poludnevnice (15.00 €)</td>
                    <td><?= $obracun['pola_dnevnice_broj'] ?> kom</td>
                    <td><?= number_format($obracun['pola_dnevnice_broj'] * 15, 2) ?> €</td>
                </tr>
                <tr>
                    <td>Neto dnevnice (nakon umanjenja za obroke)</td>
                    <td>-</td>
                    <td><strong><?= number_format($obracun['neto_dnevnice'], 2) ?> €</strong></td>
                </tr>
                <tr>
                    <td>Kilometraža (Privatno vozilo - 0.50 €/km)</td>
                    <td><?= $obracun['prijedeno_km'] ?> km</td>
                    <td><?= number_format($obracun['iznos_km'], 2) ?> €</td>
                </tr>
                <tr>
                    <td>Ostali troškovi (Računi)</td>
                    <td><?= count($troskovi) ?> računa</td>
                    <td><?= number_format($obracun['iznos_ostali_troskovi'], 2) ?> €</td>
                </tr>
                <tr style="background-color: #f8fafc; font-weight: bold;">
                    <td>UKUPNI TROŠAK PUTOVANJA:</td>
                    <td>-</td>
                    <td><?= number_format($obracun['ukupni_trosak'], 2) ?> €</td>
                </tr>
                <tr>
                    <td>Isplaćena akontacija (Predujam):</td>
                    <td>-</td>
                    <td>- <?= number_format($obracun['akontacija'], 2) ?> €</td>
                </tr>
                <tr style="background-color: #eef7f0; font-size: 1.05rem;">
                    <td><strong>SALDO ZA ISPLATU / POVRAT:</strong></td>
                    <td>-</td>
                    <td><strong style="color: <?= $obracun['saldo'] >= 0 ? '#1e6b2d' : '#a81919' ?>;"><?= number_format($obracun['saldo'], 2) ?> €</strong></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Pojedinačni troškovi i računi (Cestarina, Smještaj, Parking...)</h2>
        
        <?php if (count($troskovi) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Vrsta troška</th>
                        <th>Iznos (€)</th>
                        <th>Priloženi račun</th>
                        <?php if ($nalog['status'] === 'Na čekanju'): ?>
                            <th style="width: 1%;">Akcija</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($troskovi as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['vrsta_troska']) ?></td>
                            <td><?= number_format($t['iznos'], 2) ?> €</td>
                            <td>
                                <?php if (!empty($t['putanja_racuna'])): ?>
                                    <?php 
                                        $ext = strtolower(pathinfo($t['putanja_racuna'], PATHINFO_EXTENSION)); 
                                        if (in_array($ext, ['jpg', 'jpeg', 'png'])): 
                                    ?>
                                        <img src="<?= htmlspecialchars($t['putanja_racuna']) ?>" alt="Pregled računa" style="max-height: 80px; display: block; margin-bottom: 0.5rem; border: 1px solid #b0b0b0;">
                                    <?php endif; ?>
                                    <a href="<?= htmlspecialchars($t['putanja_racuna']) ?>" target="_blank" class="btn" style="padding: 0 0.5rem; height: 28px; font-size: 0.8rem; background-color: #0d1e2d; border-color: #0d1e2d;">Otvori datoteku</a>
                                <?php else: ?>
                                    Nema računa
                                <?php endif; ?>
                            </td>
                            <?php if ($nalog['status'] === 'Na čekanju'): ?>
                                <td>
                                    <button type="button" class="btn btn-danger" style="padding: 0 0.5rem; height: 28px; font-size: 0.8rem;" onclick="otvoriModalZaBrisanjeTroska('detalji_naloga.php?id=<?= $nalog_id ?>&obrisi_trosak=<?= $t['id'] ?>')">Ukloni</button>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Nema unesenih dodatnih troškova.</p>
        <?php endif; ?>

        <?php if ($nalog['status'] === 'Na čekanju'): ?>
            <hr style="border:0; border-top:2px solid #0d1e2d; margin: 1.5rem 0;">
            <h3 style="margin-top:0; font-size:1.1rem; color:#0d1e2d; text-transform:uppercase;">Dodaj novi trošak i priloži račun</h3>
            
            <form method="POST" action="detalji_naloga.php?id=<?= $nalog_id ?>" enctype="multipart/form-data">
                <div style="display: grid; grid-template-columns: 2fr 1fr 2fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="vrsta_troska">Opis troška:*</label>
                        <input type="text" id="vrsta_troska" name="vrsta_troska" required placeholder="npr. Cestarina Lučko-Slavonski Brod">
                    </div>
                    <div class="form-group">
                        <label for="iznos">Iznos (€):*</label>
                        <input type="number" step="0.01" id="iznos" name="iznos" required placeholder="18.50">
                    </div>
                    <div class="form-group">
                        <label for="racun_file">Slika računa ili PDF:</label>
                        <input type="file" id="racun_file" name="racun_file" accept=".jpg,.jpeg,.png,.pdf" style="background-color: transparent; border: none; padding: 0;">
                    </div>
                </div>
                <button type="submit" name="dodaj_trosak" class="btn">Spremi trošak i račun</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Obvezno Putno Izvješće po povratku</h2>
        <form method="POST" action="detalji_naloga.php?id=<?= $nalog_id ?>">
            <div class="form-group">
                <label for="putno_izvjesce">Detaljan opis obavljenog posla i rezultata putovanja:*</label>
                <textarea id="putno_izvjesce" name="putno_izvjesce" rows="5" required placeholder="Napišite s kim su održani sastanci, što je dogovoreno i koji su rezultati službenog puta..." <?= $nalog['status'] !== 'Na čekanju' ? 'readonly style="background-color: #e9e9e9; border: 1px solid #b0b0b0;"' : '' ?>><?= htmlspecialchars($nalog['putno_izvjesce'] ?? '') ?></textarea>
            </div>
            <?php if ($nalog['status'] === 'Na čekanju'): ?>
                <button type="submit" name="spremi_izvjesce" class="btn">Spremi putno izvješće</button>
            <?php endif; ?>
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

<div id="deleteModal" class="modal-overlay">
    <div class="modal-box">
        <h3 style="margin-top: 0; color: var(--color-danger); border-bottom: 2px solid var(--color-danger); padding-bottom: 0.5rem; text-transform: uppercase;">Potvrda brisanja</h3>
        <p style="font-weight: bold; font-size: 1rem;">Jeste li sigurni da želite obrisati ovaj trošak i pripadajući račun?</p>
        <p style="font-size: 0.85rem; color: #444444;">Ova radnja se ne može poništiti.</p>
        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
            <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Obriši trošak</a>
            <button type="button" class="btn" style="background-color: #666666; border-color: #444444;" onclick="zatvoriModal()">Odustani</button>
        </div>
    </div>
</div>

<script>
    function otvoriModalZaBrisanjeTroska(url) {
        document.getElementById('confirmDeleteBtn').href = url;
        document.getElementById('deleteModal').style.display = 'flex';
    }

    function zatvoriModal() {
        document.getElementById('deleteModal').style.display = 'none';
        document.getElementById('confirmDeleteBtn').href = '#';
    }
</script>

</body>
</html>