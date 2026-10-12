<?php
session_start();
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uvjeti korištenja - Evidencija putnih naloga</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%231e3a8a'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'/></svg>">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="navbar">
    <h1>Evidencija putnih naloga</h1>
    <div class="navbar-user">
        <?php if (isset($_SESSION['korisnik_id'])): ?>
            <a href="dashboard.php" class="nav-btn">Nadzorna ploča</a>
            <?php if ($_SESSION['uloga_id'] == 1): ?>
                <a href="admin_korisnici.php" class="nav-btn">Upravljaj profilima</a>
            <?php endif; ?>
            <span class="user-badge"><?= htmlspecialchars($_SESSION['ime'] . ' ' . $_SESSION['prezime']) ?> (<?= htmlspecialchars($_SESSION['uloga_naziv']) ?>)</span>
            <a href="logout.php" class="nav-btn nav-btn-logout">Odjava</a>
        <?php else: ?>
            <a href="login.php" class="nav-btn">Prijava</a>
        <?php endif; ?>
    </div>
</div>

<div class="container">
    <div class="card">
        <h2>Uvjeti korištenja</h2>
        
        <p><strong>1. Prihvaćanje uvjeta</strong><br>
        Korištenjem aplikacije "Evidencija putnih naloga" korisnici pristaju na ove Uvjete korištenja. Sustav je namijenjen isključivo internom poslovanju organizacije i vođenju službenih evidencija.</p>

        <p><strong>2. Odgovornost za točnost podataka</strong><br>
        Korisnik je osobno odgovoran za točnost unesenih podataka o službenom putovanju, uključujući datume, satnice, prijeđenu kilometražu, iznose troškova te vjerodostojnost priloženih računa.</p>

        <p><strong>3. Korisnički računi i sigurnost</strong><br>
        Korisnici su dužni čuvati tajnost svojih pristupnih podataka (email i lozinka). Svaka aktivnost izvršena pod korisničkim računom smatra se radnjom registriranog korisnika.</p>

        <p><strong>4. Zlouporaba sustava</strong><br>
        Strogo je zabranjeno unošenje lažnih troškova, neovlašteni pristup tuđim profilima ili manipulacija sustavom odobravanja. Svaka utvrđena zlouporaba bit će zabilježena i prijavljena administratoru sustava.</p>

        <p><strong>5. Izmjene sustava</strong><br>
        Organizacija zadržava pravo izmjene ili dopune funkcionalnosti sustava u svrhu usklađivanja s izmjenama važećih poreznih i računovodstvenih propisa.</p>
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