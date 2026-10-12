<?php
session_start();
require 'db.php';

if (!isset($_SESSION['korisnik_id']) || $_SESSION['uloga_id'] != 1) {
    header("Location: dashboard.php");
    exit;
}

$poruka = '';
$greska = '';

// Brisanje profila korisnika
if (isset($_GET['obrisi_id'])) {
    $del_id = (int)$_GET['obrisi_id'];
    if ($del_id === $_SESSION['korisnik_id']) {
        $greska = "Ne možete obrisati vlastiti profil!";
    } else {
        try {
            $stmt_del = $pdo->prepare("DELETE FROM korisnici WHERE id = ?");
            $stmt_del->execute([$del_id]);
            $poruka = "Korisnički profil je uspješno obrisan.";
        } catch (\PDOException $e) {
            $greska = "Nije moguće obrisati korisnika jer ima povezanih naloga ili evidencija.";
        }
    }
}

// Dodavanje novog korisnika
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dodaj_korisnika'])) {
    $ime = trim($_POST['ime']);
    $prezime = trim($_POST['prezime']);
    $email = trim($_POST['email']);
    $lozinka = trim($_POST['lozinka']);
    $uloga_id = (int)$_POST['uloga_id'];
    $nadredeni_id = !empty($_POST['nadredeni_id']) ? (int)$_POST['nadredeni_id'] : null;

    if (!empty($ime) && !empty($prezime) && !empty($email) && !empty($lozinka) && $uloga_id > 0) {
        $hash_lozinka = password_hash($lozinka, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("INSERT INTO korisnici (ime, prezime, email, lozinka, uloga_id, nadredeni_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$ime, $prezime, $email, $hash_lozinka, $uloga_id, $nadredeni_id]);
            $poruka = "Korisnički profil je uspješno dodan.";
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                $greska = "Korisnik s tom email adresom već postoji.";
            } else {
                $greska = "Greška pri upisu u bazu: " . $e->getMessage();
            }
        }
    } else {
        $greska = "Molimo popunite sva obvezna polja.";
    }
}

$uloge = $pdo->query("SELECT * FROM uloge")->fetchAll();

// Izbor u padajućem meniju za nadređene - isključeni zaposlenici
$sql_nadredeni = "
    SELECT k.id, k.ime, k.prezime 
    FROM korisnici k 
    JOIN uloge u ON k.uloga_id = u.id 
    WHERE u.naziv != 'Zaposlenik' 
    ORDER BY k.prezime, k.ime
";
$potencijalni_nadredeni = $pdo->query($sql_nadredeni)->fetchAll();

// Glavna tablica za pregled svih korisnika (Uključuje i zaposlenike)
$sql_lista = "
    SELECT k1.id, k1.ime, k1.prezime, k1.email, u.naziv AS uloga, 
           CONCAT(k2.ime, ' ', k2.prezime) AS nadredeni
    FROM korisnici k1
    JOIN uloge u ON k1.uloga_id = u.id
    LEFT JOIN korisnici k2 ON k1.nadredeni_id = k2.id
    ORDER BY k1.id DESC
";
$lista_korisnika = $pdo->query($sql_lista)->fetchAll();
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upravljaj profilima - Admin panel</title>
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

    <div class="card">
        <h2>Dodavanje novog korisničkog profila</h2>
        <form method="POST" action="admin_korisnici.php">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="ime">Ime:*</label>
                    <input type="text" id="ime" name="ime" required>
                </div>
                <div class="form-group">
                    <label for="prezime">Prezime:*</label>
                    <input type="text" id="prezime" name="prezime" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="email">Email adresa:*</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="lozinka">Lozinka:*</label>
                    <input type="password" id="lozinka" name="lozinka" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="uloga_id">Uloga u sustavu:*</label>
                    <select id="uloga_id" name="uloga_id" required>
                        <option value="">-- Odaberite ulogu --</option>
                        <?php foreach ($uloge as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['naziv']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="nadredeni_id">Nadređena osoba (Odobrava naloge):</label>
                    <select id="nadredeni_id" name="nadredeni_id">
                        <option value="">-- Nema nadređenog --</option>
                        <?php foreach ($potencijalni_nadredeni as $k): ?>
                            <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['ime'] . ' ' . $k['prezime']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="submit" name="dodaj_korisnika" class="btn">Spremi profil</button>
        </form>
    </div>

    <div class="card">
        <h2>Popis profila u organizaciji</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Ime i prezime</th>
                    <th>Email</th>
                    <th>Uloga</th>
                    <th>Nadređena osoba</th>
                    <th style="width: 1%;">Akcije</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lista_korisnika as $korisnik): ?>
                    <tr>
                        <td><?= $korisnik['id'] ?></td>
                        <td><?= htmlspecialchars($korisnik['ime'] . ' ' . $korisnik['prezime']) ?></td>
                        <td><?= htmlspecialchars($korisnik['email']) ?></td>
                        <td><strong><?= htmlspecialchars($korisnik['uloga']) ?></strong></td>
                        <td><?= htmlspecialchars($korisnik['nadredeni'] ?? '-') ?></td>
                        <td>
                            <div class="action-flex">
                                <a href="uredi_korisnika.php?id=<?= $korisnik['id'] ?>" class="btn" style="background:#0055a5; border-color:#004080;">Uredi</a>
                                <?php if ($korisnik['id'] != $_SESSION['korisnik_id']): ?>
                                    <!-- Zamijenjen standardni alert s modalom -->
                                    <button type="button" class="btn btn-danger" onclick="otvoriModalZaBrisanjeKorisnika('admin_korisnici.php?obrisi_id=<?= $korisnik['id'] ?>')">Obriši</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<footer class="footer">
    <div class="footer-links">
        <a href="privatnost.php">Politika privatnosti</a> | 
        <a href="uvjeti.php">Uvjeti korištenja</a>
    </div>
    <div>Evidencija putnih naloga &copy; 2026</div>
</footer>

<!-- Modalni prozor za brisanje profila -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-box">
        <h3 style="margin-top: 0; color: var(--color-danger); border-bottom: 2px solid var(--color-danger); padding-bottom: 0.5rem; text-transform: uppercase;">Potvrda brisanja profila</h3>
        <p style="font-weight: bold; font-size: 1rem;">Jeste li sigurni da želite obrisati ovaj korisnički profil?</p>
        <p style="font-size: 0.85rem; color: #444444;">Ako profil ima kreirane putne naloge, brisanje neće biti moguće zbog očuvanja baze podataka.</p>
        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
            <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Obriši profil</a>
            <button type="button" class="btn" style="background-color: #666666; border-color: #444444;" onclick="zatvoriModal()">Odustani</button>
        </div>
    </div>
</div>

<script>
    function otvoriModalZaBrisanjeKorisnika(url) {
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