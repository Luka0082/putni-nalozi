<?php
session_start();
require 'db.php';

if (isset($_SESSION['korisnik_id'])) {
    header("Location: dashboard.php");
    exit;
}

$greska = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $lozinka = trim($_POST['lozinka']);

    if (!empty($email) && !empty($lozinka)) {
        $stmt = $pdo->prepare("
            SELECT k.id, k.ime, k.prezime, k.email, k.lozinka, k.uloga_id, u.naziv AS uloga_naziv 
            FROM korisnici k 
            JOIN uloge u ON k.uloga_id = u.id 
            WHERE k.email = ?
        ");
        $stmt->execute([$email]);
        $korisnik = $stmt->fetch();

        if ($korisnik && password_verify($lozinka, $korisnik['lozinka'])) {
            $_SESSION['korisnik_id'] = $korisnik['id'];
            $_SESSION['ime'] = $korisnik['ime'];
            $_SESSION['prezime'] = $korisnik['prezime'];
            $_SESSION['email'] = $korisnik['email'];
            $_SESSION['uloga_id'] = $korisnik['uloga_id'];
            $_SESSION['uloga_naziv'] = $korisnik['uloga_naziv'];
            
            header("Location: dashboard.php");
            exit;
        } else {
            $greska = "Pogrešan email ili lozinka!";
        }
    } else {
        $greska = "Molimo unesite email i lozinku.";
    }
}
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prijava - Evidencija putnih naloga</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%231e3a8a'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'/></svg>">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: var(--color-bg);
            margin: 0;
        }
        .login-card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
        }
        .login-card h2 {
            margin-top: 0;
            margin-bottom: 1.5rem;
            text-align: center;
            color: #0d1e2d;
            border-bottom: 2px solid #0d1e2d;
            padding-bottom: 0.5rem;
            text-transform: uppercase;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>

<div class="login-card">
    <h2>Evidencija putnih naloga</h2>
    
    <?php if ($greska): ?>
        <div style="background-color: #f8d7da; border: 1px solid #f5c2c7; color: #842029; padding: 0.75rem; margin-bottom: 1rem; font-weight: bold; font-size: 0.85rem;">
            <?= htmlspecialchars($greska) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div class="form-group">
            <label for="email">Email adresa</label>
            <input type="email" id="email" name="email" required autofocus>
        </div>
        <div class="form-group">
            <label for="lozinka">Lozinka</label>
            <input type="password" id="lozinka" name="lozinka" required>
        </div>
        <button type="submit" class="btn" style="width: 100%; margin-top: 0.5rem;">Prijava u sustav</button>
    </form>
</div>

</body>
</html>