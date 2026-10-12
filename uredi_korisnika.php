<?php
session_start();
require 'db.php';

if (!isset($_SESSION['korisnik_id']) || $_SESSION['uloga_id'] != 1) {
    header("Location: dashboard.php");
    exit;
}

$edit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$poruka = '';
$greska = '';

$stmt = $pdo->prepare("SELECT * FROM korisnici WHERE id = ?");
$stmt->execute([$edit_id]);
$korisnik_edit = $stmt->fetch();

if (!$korisnik_edit) {
    die("Profil nije pronađen.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['spremi_korisnika'])) {
    $ime = trim($_POST['ime']);
    $prezime = trim($_POST['prezime']);
    $email = trim($_POST['email']);
    $uloga_id = (int)$_POST['uloga_id'];
    $nadredeni_id = !empty($_POST['nadredeni_id']) ? (int)$_POST['nadredeni_id'] : null;
    $nova_lozinka = trim($_POST['nova_lozinka']);

    if (!empty($ime) && !empty($prezime) && !empty($email) && $uloga_id > 0) {
        try {
            if (!empty($nova_lozinka)) {
                $hash = password_hash($nova_lozinka, PASSWORD_DEFAULT);
                $stmt_u = $pdo->prepare("UPDATE korisnici SET ime = ?, prezime = ?, email = ?, uloga_id = ?, nadredeni_id = ?, lozinka = ? WHERE id = ?");
                $stmt_u->execute([$ime, $prezime, $email, $uloga_id, $nadredeni_id, $hash, $edit_id]);
            } else {
                $stmt_u = $pdo->prepare("UPDATE korisnici SET ime = ?, prezime = ?, email = ?, uloga_id = ?, nadredeni_id = ? WHERE id = ?");
                $stmt_u->execute([$ime, $prezime, $email, $uloga_id, $nadredeni_id, $edit_id]);
            }

            if ($edit_id == $_SESSION['korisnik_id']) {
                $_SESSION['ime'] = $ime;
                $_SESSION['prezime'] = $prezime;
            }

            header("Location: admin_korisnici.php");
            exit;
        } catch (\PDOException $e) {
            $greska = "Greška pri ažuriranju: " . $e->getMessage();
        }
    } else {
        $greska = "Popunite sva obvezna polja.";
    }
}

$uloge = $pdo->query("SELECT * FROM uloge")->fetchAll();

// Izmijenjen upit: isključujemo korisnika kojeg upravo uređujemo I sve korisnike koji su 'Zaposlenik'
$sql_nadredeni = "
    SELECT k.id, k.ime, k.prezime 
    FROM korisnici k 
    JOIN uloge u ON k.uloga_id = u.id 
    WHERE k.id != ? AND u.naziv != 'Zaposlenik' 
    ORDER BY k.prezime, k.ime
";
$stmt_svi = $pdo->prepare($sql_nadredeni);
$stmt_svi->execute([$edit_id]);
$svi_korisnici = $stmt_svi->fetchAll();
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uredi profil - Admin panel</title>
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

    <?php if ($greska): ?>
        <div class="card" style="border-left: 4px solid #991b1b; background: #fef2f2;">
            <p style="margin:0; color: #991b1b;"><?= htmlspecialchars($greska) ?></p>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Uredi korisnički profil (ID: <?= $korisnik_edit['id'] ?>)</h2>
        <form method="POST" action="uredi_korisnika.php?id=<?= $edit_id ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="ime">Ime:*</label>
                    <input type="text" id="ime" name="ime" value="<?= htmlspecialchars($korisnik_edit['ime']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="prezime">Prezime:*</label>
                    <input type="text" id="prezime" name="prezime" value="<?= htmlspecialchars($korisnik_edit['prezime']) ?>" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="email">Email adresa:*</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($korisnik_edit['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label for="nova_lozinka">Nova lozinka (ostavite prazno ako ne mijenjate):</label>
                    <input type="password" id="nova_lozinka" name="nova_lozinka">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="uloga_id">Uloga u sustavu:*</label>
                    <select id="uloga_id" name="uloga_id" required>
                        <?php foreach ($uloge as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $korisnik_edit['uloga_id'] == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['naziv']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="nadredeni_id">Nadređena osoba:</label>
                    <select id="nadredeni_id" name="nadredeni_id">
                        <option value="">-- Nema nadređenog --</option>
                        <?php foreach ($svi_korisnici as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= $korisnik_edit['nadredeni_id'] == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['ime'] . ' ' . $k['prezime']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: flex; gap: 1rem;">
                <button type="submit" name="spremi_korisnika" class="btn">Spremi izmjene</button>
                <a href="admin_korisnici.php" class="btn" style="background-color: #64748b; border-color: #64748b;">Odustani</a>
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